<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('public card form submit queues generation and returns processing', function () {
    Queue::fake();

    $form = CardForm::create([
        'name' => 'Haji Test',
        'token' => Str::random(40),
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'portrait',
        'layout_config' => ['elements' => []],
        'is_active' => true,
    ]);

    $this->post("/f/{$form->token}", ['data' => ['nama' => 'Budi']])
        ->assertOk();

    $submission = CardFormSubmission::where('card_form_id', $form->id)->firstOrFail();
    expect($submission->status)->toBe('processing');
    Queue::assertPushed(GenerateDynamicCardJob::class);
});

test('card form status endpoint reports submission state', function () {
    $form = CardForm::create([
        'name' => 'Haji Test',
        'token' => Str::random(40),
        'fields' => [],
        'orientation' => 'portrait',
        'layout_config' => ['elements' => []],
        'is_active' => true,
    ]);

    $submission = CardFormSubmission::create([
        'card_form_id' => $form->id,
        'data' => [],
        'status' => 'completed',
        'drive_url' => 'https://drive.example/abc',
    ]);

    $this->getJson("/f/{$form->token}/status/{$submission->id}")
        ->assertOk()
        ->assertJson(['status' => 'completed', 'card_url' => 'https://drive.example/abc']);
});

test('the render job dispatches to the cards queue', function () {
    Queue::fake();
    GenerateDynamicCardJob::dispatch('01k000000000000000000000');
    Queue::assertPushedOn(config('cards.queue'), GenerateDynamicCardJob::class);
});
