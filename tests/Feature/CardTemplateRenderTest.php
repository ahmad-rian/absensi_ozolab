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

test('photo and qr are printed at the size stored in the layout', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $elements = SchoolCardLayout::defaultElements();
    $elements['photo'] = array_merge($elements['photo'], ['w' => 24.5, 'h' => 30.0]);
    $elements['qr'] = array_merge($elements['qr'], ['w' => 18.0, 'h' => 11.0]);

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Ukuran Bebas',
        'type' => 'osis',
        'layout_config' => ['elements' => $elements],
    ]);

    $html = renderCard($layout, $student);

    expect($html)->toContain('width: calc(24.5 * var(--mm)); height: calc(30 * var(--mm));')
        ->and($html)->toContain('width: calc(18 * var(--mm)); height: calc(11 * var(--mm));');
});

test('a qr saved before the resize feature keeps its own size', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    // Konfigurasi lama: QR hanya punya `size`, belum punya w/h. Tanpa penurunan
    // di normalizedConfig() ia akan terlempar balik ke 15mm bawaan.
    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Warisan',
        'type' => 'osis',
        'layout_config' => ['elements' => ['qr' => ['type' => 'qr', 'x' => 22.0, 'y' => 32.0, 'size' => 21.0, 'enabled' => true]]],
    ]);

    $qr = $layout->normalizedConfig()['elements']['qr'];

    expect($qr['w'])->toBe(21.0)
        ->and($qr['h'])->toBe(21.0);

    expect(renderCard($layout, $student))->toContain('width: calc(21 * var(--mm)); height: calc(21 * var(--mm));');
});
