<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;
use Illuminate\Http\UploadedFile;

function programImpor($admin): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
}

function csvPeserta(string $isi): UploadedFile
{
    return UploadedFile::fake()->createWithContent('peserta.csv', $isi);
}

test('pratinjau menampilkan baris valid dan gagal beserta alasannya', function () {
    $admin = createPenyelenggaraUser();
    $program = programImpor($admin);
    Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan 3']);

    $csv = "Nama,No Porsi,Jenis Kelamin,Telepon Keluarga,Grup\n"
        ."Siti,1100,Perempuan,0812,Rombongan 3\n"
        .",1101,Laki-laki,0813,Rombongan 3\n"
        ."Ahmad,1102,Pria,0814,Rombongan 3\n"
        ."Budi,1103,Laki-laki,0815,Rombongan 9\n"
        ."Siti,1100,Perempuan,0812,Rombongan 3\n";

    $this->actingAs($admin)
        ->post(route('penyelenggara.impor.pratinjau', $program), ['file' => csvPeserta($csv)])
        ->assertInertia(fn ($page) => $page
            ->where('pratinjau.valid', 1)
            ->where('pratinjau.gagal', 4)
            ->where('pratinjau.errors', fn ($errors) => collect($errors)->pluck('alasan')->implode('|') !== ''
                && collect($errors)->contains(fn ($e) => str_contains($e['alasan'], 'Nama wajib'))
                && collect($errors)->contains(fn ($e) => str_contains($e['alasan'], 'Jenis Kelamin'))
                && collect($errors)->contains(fn ($e) => str_contains($e['alasan'], 'Rombongan 9'))
                && collect($errors)->contains(fn ($e) => str_contains($e['alasan'], 'kembar'))));
});

test('konfirmasi menyimpan baris valid ke grup yang cocok persis', function () {
    $admin = createPenyelenggaraUser();
    $program = programImpor($admin);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan 3']);

    $csv = "Nama,Telepon Keluarga,Grup\nSiti,0812,Rombongan 3\nAhmad,0813,\n";

    $this->actingAs($admin)->post(route('penyelenggara.impor.pratinjau', $program), ['file' => csvPeserta($csv)]);
    $kunci = session('impor_peserta_kunci');

    $this->actingAs($admin)->post(route('penyelenggara.impor.simpan', [$program, $kunci]))->assertRedirect();

    $peserta = CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->get();

    expect($peserta)->toHaveCount(2)
        ->and($grup->peserta()->count())->toBe(1);
});

test('impor ditolak bila melampaui sisa kuota', function () {
    $admin = createPenyelenggaraUser();
    $admin->penyelenggara->update(['participant_quota' => 1]);
    $program = programImpor($admin);

    $this->actingAs($admin)->post(route('penyelenggara.impor.pratinjau', $program), ['file' => csvPeserta("Nama\nA\nB\n")]);
    $kunci = session('impor_peserta_kunci');

    $this->actingAs($admin)->post(route('penyelenggara.impor.simpan', [$program, $kunci]))->assertSessionHasErrors('kuota');

    expect(CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->count())->toBe(0);
});
