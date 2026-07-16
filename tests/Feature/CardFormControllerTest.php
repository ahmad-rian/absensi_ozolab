<?php

use App\Http\Controllers\Admin\CardFormController;
use App\Http\Controllers\Public\CardFormController as PublicCardFormController;
use App\Models\CardForm;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Modul C routes are wired by the lead dev separately (routes/web.php is off-limits
 * to this feature). We register equivalent routes here so the controllers are
 * exercised end-to-end under test.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
        Route::get('card-forms', [CardFormController::class, 'index'])->name('admin.card-forms');
        Route::get('card-forms/create', [CardFormController::class, 'create'])->name('admin.card-forms.create');
        Route::post('card-forms', [CardFormController::class, 'store'])->name('admin.card-forms.store');
        Route::get('card-forms/{cardForm}/edit', [CardFormController::class, 'edit'])->name('admin.card-forms.edit');
        Route::put('card-forms/{cardForm}', [CardFormController::class, 'update'])->name('admin.card-forms.update');
        Route::delete('card-forms/{cardForm}', [CardFormController::class, 'destroy'])->name('admin.card-forms.destroy');
    });

    Route::middleware('web')->group(function () {
        Route::get('f/{token}', [PublicCardFormController::class, 'show'])->name('public.card-forms.show');
        Route::post('f/{token}', [PublicCardFormController::class, 'submit'])->name('public.card-forms.submit');
    });

    // Rebuild the name lookup + point the URL generator at the current route
    // collection so route()/to_route() resolve the routes registered above.
    $routes = app('router')->getRoutes();
    $routes->refreshNameLookups();
    app('url')->setRoutes($routes);
});

function makeAdmin(): User
{
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    return $user;
}

test('card forms index loads', function () {
    $this->actingAs(makeAdmin())
        ->get(route('admin.card-forms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/card-forms/index')->has('forms'));
});

test('card form can be created with a generated token', function () {
    $user = makeAdmin();

    $payload = [
        'name' => 'Kartu Peserta',
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true],
            ['key' => 'kelas', 'label' => 'Kelas', 'type' => 'select', 'required' => false, 'options' => ['A', 'B']],
        ],
        'orientation' => 'portrait',
        'frame_id' => null,
        'layout_config' => [
            'orientation' => 'portrait',
            'frame_id' => null,
            'elements' => [
                'nama' => ['type' => 'field', 'label' => 'NAMA', 'source' => 'nama', 'x' => 3, 'y' => 6, 'width' => 48, 'labelWidth' => 14, 'fontSize' => 2.0, 'enabled' => true],
            ],
        ],
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->post(route('admin.card-forms.store'), $payload)
        ->assertRedirect(route('admin.card-forms'));

    $form = CardForm::where('name', 'Kartu Peserta')->firstOrFail();

    expect($form->token)->toHaveLength(40);
    expect($form->created_by)->toBe($user->id);
    expect($form->orientation)->toBe('portrait');
    expect($form->fields)->toHaveCount(2);
});

test('card form store validates field types', function () {
    $this->actingAs(makeAdmin())
        ->post(route('admin.card-forms.store'), [
            'name' => 'Bad',
            'fields' => [['key' => 'x', 'label' => 'X', 'type' => 'email', 'required' => false]],
            'orientation' => 'landscape',
            'layout_config' => ['elements' => []],
        ])
        ->assertSessionHasErrors('fields.0.type');
});

test('card form can be updated', function () {
    $user = makeAdmin();
    $form = CardForm::create([
        'created_by' => $user->id,
        'token' => str()->random(40),
        'name' => 'Old',
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'landscape',
        'layout_config' => ['orientation' => 'landscape', 'frame_id' => null, 'elements' => []],
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('admin.card-forms.update', $form), [
            'name' => 'New Name',
            'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => false]],
            'orientation' => 'portrait',
            'frame_id' => null,
            'layout_config' => ['orientation' => 'portrait', 'frame_id' => null, 'elements' => []],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.card-forms'));

    expect($form->fresh()->name)->toBe('New Name');
    expect($form->fresh()->orientation)->toBe('portrait');
});

test('card form can be deleted', function () {
    $user = makeAdmin();
    $form = CardForm::create([
        'created_by' => $user->id,
        'token' => str()->random(40),
        'name' => 'Del',
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'landscape',
        'layout_config' => ['orientation' => 'landscape', 'frame_id' => null, 'elements' => []],
    ]);

    $this->actingAs($user)
        ->delete(route('admin.card-forms.destroy', $form))
        ->assertRedirect(route('admin.card-forms'));

    $this->assertDatabaseMissing('card_forms', ['id' => $form->id]);
});

test('public form page renders active form fields', function () {
    $form = CardForm::create([
        'created_by' => null,
        'token' => str()->random(40),
        'name' => 'Public Form',
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'landscape',
        'layout_config' => ['orientation' => 'landscape', 'frame_id' => null, 'elements' => []],
        'is_active' => true,
    ]);

    $this->get(route('public.card-forms.show', $form->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/card-form')
            ->where('form.name', 'Public Form')
            ->has('form.fields', 1));
});

test('inactive public form returns 404', function () {
    $form = CardForm::create([
        'created_by' => null,
        'token' => str()->random(40),
        'name' => 'Off',
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'landscape',
        'layout_config' => ['orientation' => 'landscape', 'frame_id' => null, 'elements' => []],
        'is_active' => false,
    ]);

    $this->get(route('public.card-forms.show', $form->token))->assertNotFound();
});

test('public submit validates required fields', function () {
    $form = CardForm::create([
        'created_by' => null,
        'token' => str()->random(40),
        'name' => 'Req',
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'landscape',
        'layout_config' => ['orientation' => 'landscape', 'frame_id' => null, 'elements' => []],
        'is_active' => true,
    ]);

    $this->post(route('public.card-forms.submit', $form->token), ['data' => ['nama' => '']])
        ->assertSessionHasErrors('data.nama');
});
