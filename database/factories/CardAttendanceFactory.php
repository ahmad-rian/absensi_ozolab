<?php

namespace Database\Factories;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Support\SchoolTime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CardAttendance> */
class CardAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_form_submission_id' => function (): string {
                $form = CardForm::create(['name' => 'Peserta Haji', 'token' => Str::random(40), 'fields' => [], 'layout_config' => ['elements' => []], 'orientation' => 'portrait', 'is_active' => true]);

                return CardFormSubmission::create(['card_form_id' => $form->id, 'data' => ['nama' => fake()->name()], 'status' => 'draft'])->id;
            },
            'attendance_date' => SchoolTime::todayString(),
            'status' => 'hadir',
            'check_in' => '08:00:00',
        ];
    }
}
