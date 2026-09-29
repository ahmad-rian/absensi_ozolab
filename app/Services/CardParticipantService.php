<?php

namespace App\Services;

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CardParticipantService
{
    public function __construct(private PhotoCropService $cropper) {}

    public function save(Request $request, CardForm $form, ?CardFormSubmission $participant = null): CardFormSubmission
    {
        $createdPaths = [];
        $obsoletePaths = [];
        try {
            $result = DB::transaction(function () use ($request, $form, $participant, &$createdPaths, &$obsoletePaths) {
                $record = $participant ? CardFormSubmission::query()->lockForUpdate()->findOrFail($participant->id) : new CardFormSubmission(['card_form_id' => $form->id]);
                abort_if($record->status === 'processing', 409, 'Kartu sedang diproses. Tunggu hingga selesai.');
                $validated = $this->validate($request, $form, $record);
                $data = [];
                foreach ($form->inputFields() as $field) {
                    $key = $field['key'];
                    if ($field['type'] !== 'photo') {
                        $data[$key] = $validated['data'][$key] ?? null;

                        continue;
                    }
                    $file = $request->file("data.{$key}");
                    if (! $file && ! $request->filled('manual_crop')) {
                        continue;
                    }
                    $original = $record->original_photo_path ?: $record->photo_path;
                    if ($file) {
                        $original = $file->store("card-forms/{$form->id}/originals", 'public');
                        throw_unless($original, \RuntimeException::class, 'Foto gagal disimpan.');
                        $createdPaths[] = $original;
                    }
                    abort_unless($original && Storage::disk('public')->exists($original), 422, 'Foto asli tidak tersedia. Unggah foto kembali.');
                    $photo = "card-forms/{$form->id}/photos/".Str::ulid().'.png';
                    $createdPaths[] = $photo;
                    $this->cropper->cropAndStore(Storage::disk('public')->path($original), $photo, 9, $validated['manual_crop'] ?? null);
                    $obsoletePaths = array_filter([$record->photo_path, $record->original_photo_path], fn ($path) => $path && $path !== $original);
                    $record->fill(['original_photo_path' => $original, 'photo_path' => $photo, 'manual_crop' => $validated['manual_crop'] ?? null]);
                }
                $record->fill(['data' => $data, 'status' => 'draft', 'generation_error' => null])->save();

                return $record;
            });
        } catch (\Throwable $exception) {
            $this->deletePhotos($createdPaths);
            throw $exception;
        }
        $this->deletePhotos($obsoletePaths);

        return $result;
    }

    /** @param array<int, string> $paths */
    public function deletePhotos(array $paths): void
    {
        Storage::disk('public')->delete(array_merge($paths, array_map(StudentPhotoStorage::thumbPath(...), $paths)));
    }

    public function generate(CardFormSubmission $participant): void
    {
        $token = (string) Str::uuid();
        try {
            DB::transaction(function () use ($participant, $token) {
                $record = CardFormSubmission::query()->with('cardForm')->lockForUpdate()->findOrFail($participant->id);
                abort_if($record->status === 'processing', 409, 'Kartu sedang diproses.');
                $this->validate(new Request(['data' => $record->data]), $record->cardForm, $record);
                $record->update(['status' => 'processing', 'generation_token' => $token, 'generation_error' => null]);
                GenerateDynamicCardJob::dispatch($record->id, $token)->afterCommit();
            });
        } catch (\Throwable $exception) {
            CardFormSubmission::whereKey($participant->id)->where('generation_token', $token)->where('status', 'processing')
                ->update(['status' => 'failed', 'generation_error' => 'Permintaan belum masuk antrean. Coba generate kembali.']);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function validate(Request $request, CardForm $form, CardFormSubmission $record): array
    {
        $rules = ['data' => ['nullable', 'array'], 'manual_crop' => ['nullable', 'array:sx,sy,sw,sh']];
        foreach (['sx', 'sy', 'sw', 'sh'] as $coordinate) {
            $rules["manual_crop.{$coordinate}"] = ['required_with:manual_crop', 'numeric', 'between:0,1'];
            if (in_array($coordinate, ['sw', 'sh'])) {
                $rules["manual_crop.{$coordinate}"][] = 'gt:0';
            }
        }
        foreach ($form->inputFields() as $field) {
            $required = ! empty($field['required']);
            if ($field['type'] === 'photo') {
                $hasPhoto = $record->photo_path && Storage::disk('public')->exists($record->photo_path);
                $rules['data.'.$field['key']] = [$required && ! $hasPhoto ? 'required' : 'nullable', 'image', 'max:8192'];

                continue;
            }
            $rules['data.'.$field['key']] = array_merge([$required ? 'required' : 'nullable'], match ($field['type']) {
                'number' => ['numeric'],
                'date' => ['date'],
                'select' => ['string', Rule::in($field['options'] ?? [])],
                default => ['string', 'max:1000'],
            });
        }
        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request) {
            $crop = $request->input('manual_crop');
            if (is_array($crop) && count(array_filter($crop, 'is_numeric')) === 4) {
                if (($crop['sx'] ?? 0) + ($crop['sw'] ?? 0) > 1.000001 || ($crop['sy'] ?? 0) + ($crop['sh'] ?? 0) > 1.000001) {
                    $validator->errors()->add('manual_crop', 'Area crop harus berada di dalam foto.');
                }
            }
        });

        return $validator->validate();
    }
}
