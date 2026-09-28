<?php

use App\Models\CardDataset;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\DynamicCardGenerator;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Inertia\Testing\AssertableInertia as Assert;

function qrCardPayload(string $dataset): array
{
    return [
        'name' => 'Kartu Haji', 'card_dataset_id' => $dataset,
        'orientation' => 'portrait', 'is_active' => true,
        'layout_config' => ['orientation' => 'portrait', 'elements' => [
            '__qr' => ['type' => 'qr', 'source' => 'porsi', 'x' => 30, 'y' => 5, 'w' => 18, 'h' => 18, 'enabled' => true],
        ]],
    ];
}

test('haji qr layout survives saving and reopening the editor', function () {
    $user = createSuperAdminUser();
    $dataset = CardDataset::create(['created_by' => $user->id, 'name' => 'Haji', 'fields' => [
        ['key' => 'porsi', 'label' => 'Nomor Porsi', 'type' => 'text'],
    ]]);
    $payload = qrCardPayload($dataset->id);

    $this->actingAs($user)->post(route('kartu-bebas.layouts.store'), $payload)->assertRedirect(route('kartu-bebas.layouts'));
    $form = CardForm::where('name', 'Kartu Haji')->firstOrFail();
    expect($form->layout_config)->toBe($payload['layout_config']);
    $this->get(route('kartu-bebas.layouts.edit', $form))->assertInertia(fn (Assert $page) => $page
        ->component('kartu-bebas/layouts/editor')
        ->where('form.layout_config.elements.__qr.source', 'porsi')
        ->where('form.layout_config.elements.__qr.w', 18));

    $payload['layout_config']['elements']['__qr']['x'] = 12;
    $this->put(route('kartu-bebas.layouts.update', $form), $payload)->assertRedirect();
    expect($form->fresh()->layout_config['elements']['__qr']['x'])->toBe(12);
});

test('qr source must belong to a non photo dataset field', function (string $source) {
    $user = createSuperAdminUser();
    $dataset = CardDataset::create(['created_by' => $user->id, 'name' => 'Haji', 'fields' => [
        ['key' => 'porsi', 'label' => 'Nomor Porsi', 'type' => 'text'],
        ['key' => 'foto', 'label' => 'Foto', 'type' => 'photo'],
    ]]);
    $payload = qrCardPayload($dataset->id);
    $payload['layout_config']['elements']['__qr']['source'] = $source;

    $this->actingAs($user)->post(route('kartu-bebas.layouts.store'), $payload)
        ->assertSessionHasErrors('layout_config.elements.__qr.source');
    expect(CardForm::count())->toBe(0);
})->with(['foto', 'missing', '']);

test('generated qr encodes the selected participant value and retains its placement', function () {
    $form = new CardForm(['layout_config' => qrCardPayload('unused')['layout_config']]);
    $submission = new CardFormSubmission(['data' => ['porsi' => '0012345678', 'nama' => 'Peserta']]);

    $html = app(DynamicCardGenerator::class)->renderHtml($form, $submission);
    $writer = new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd));

    expect($html)->toContain($writer->writeString('0012345678'))
        ->toContain('class="el el-qr"')
        ->toContain('left: calc(30 * var(--mm))')
        ->toContain('width: calc(18 * var(--mm))');

    $submission->data = ['porsi' => '0098765432'];
    expect(app(DynamicCardGenerator::class)->renderHtml($form, $submission))
        ->toContain($writer->writeString('0098765432'))
        ->not->toContain($writer->writeString('0012345678'));
});

test('disabled or empty qr does not appear on the generated card', function (bool $enabled, mixed $value) {
    $config = qrCardPayload('unused')['layout_config'];
    $config['elements']['__qr']['enabled'] = $enabled;
    $form = new CardForm(['layout_config' => $config]);
    $submission = new CardFormSubmission(['data' => ['porsi' => $value]]);

    expect(app(DynamicCardGenerator::class)->renderHtml($form, $submission))->not->toContain('class="el el-qr"');
})->with([[false, '0012345678'], [true, ''], [true, null], [true, []]]);
