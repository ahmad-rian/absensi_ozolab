<?php

use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Support\Facades\View;

function renderCard(SchoolCardLayout $layout, Student $student): string
{
    $config = $layout->normalizedConfig();

    return View::make('cards.student-card', [
        'student' => $student->load('classroom'),
        'school' => $student->school,
        'layout' => $layout,
        'config' => $config,
        'orientation' => $config['orientation'],
        'qrSvg' => app(QrTokenGenerator::class)->renderSvg($student),
        'logoUrl' => null,
        'photoUrl' => null,
        'frameUrl' => null,
        'exportMm' => 15.748,
    ])->render();
}

test('card template renders enabled elements for landscape', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);
    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'L',
        'type' => 'osis',
        'layout_config' => ['orientation' => 'landscape', 'elements' => SchoolCardLayout::defaultElements()],
    ]);

    $html = renderCard($layout, $student);

    expect($html)->toContain(strtoupper($student->full_name));
    expect($html)->toContain('NAMA');
    expect($html)->toContain('85.6 * var(--mm)'); // landscape width
});

test('card template auto-fits long field values (wrap + shrink script)', function () {
    $school = School::factory()->create();
    $longAddress = 'Jalan Raya Sidabowa RT 02 / RW 07 No 7 Kecamatan Patikraja Kabupaten Banyumas Jawa Tengah 53171';
    $student = Student::factory()->create(['school_id' => $school->id, 'address' => $longAddress]);

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'L',
        'type' => 'osis',
        'layout_config' => ['orientation' => 'landscape', 'elements' => SchoolCardLayout::defaultElements()],
    ]);

    $html = renderCard($layout, $student);

    // Long value present in full (not clipped), wraps, bounded, and the auto-fit script is injected.
    expect($html)->toContain($longAddress);
    expect($html)->toContain('overflow-wrap: anywhere');
    expect($html)->toContain('max-width: calc(');
    expect($html)->toContain('document.fonts.ready');
});

test('card template swaps dimensions for portrait and hides disabled elements', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $elements = SchoolCardLayout::defaultElements();
    $elements['qr']['enabled'] = false;

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'P',
        'type' => 'osis',
        'layout_config' => ['orientation' => 'portrait', 'elements' => $elements],
    ]);

    $html = renderCard($layout, $student);

    // portrait: width 54mm, height 85.6mm
    expect($html)->toContain('width: calc(54 * var(--mm))');
    expect($html)->toContain('height: calc(85.6 * var(--mm))');
    expect($html)->not->toContain('class="el el-qr"');
});
