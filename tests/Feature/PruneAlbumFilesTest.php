<?php

use Illuminate\Support\Facades\Storage;

test('albums:prune menghapus berkas album lama dan menyisakan yang baru', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');
    $disk->put('albums/s1/album-lama-page-1.png', 'lama');
    $disk->put('albums/s1/album-lama.zip', 'lama');
    $disk->put('albums/s1/album-baru.zip', 'baru');
    $disk->put('cards/s1/kartu.jpg', 'kartu');
    touch($disk->path('albums/s1/album-lama-page-1.png'), now()->subDays(3)->getTimestamp());
    touch($disk->path('albums/s1/album-lama.zip'), now()->subDays(3)->getTimestamp());
    touch($disk->path('cards/s1/kartu.jpg'), now()->subDays(30)->getTimestamp());

    $this->artisan('albums:prune')->assertSuccessful();

    expect($disk->exists('albums/s1/album-lama.zip'))->toBeFalse()
        ->and($disk->exists('albums/s1/album-lama-page-1.png'))->toBeFalse()
        ->and($disk->exists('albums/s1/album-baru.zip'))->toBeTrue()
        ->and($disk->exists('cards/s1/kartu.jpg'))->toBeTrue();
});
