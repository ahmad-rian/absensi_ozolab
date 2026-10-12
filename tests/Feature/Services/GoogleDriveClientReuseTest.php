<?php

use App\Models\SchoolDriveConfig;
use App\Services\GoogleDriveService;
use Google\Client as GoogleClient;

function setelKlienOauth(?GoogleClient $client): void
{
    (new ReflectionProperty(GoogleDriveService::class, 'oauthClient'))->setValue(null, $client);
}

function klienDari(GoogleDriveService $service): GoogleClient
{
    return (new ReflectionProperty(GoogleDriveService::class, 'client'))->getValue($service);
}

afterEach(fn () => setelKlienOauth(null));

/**
 * Tiap GoogleClient membawa koneksi curl sendiri yang tidak dilepas selama
 * proses hidup. Satu klien per foto membuat unggahan massal mati
 * "Too many open files" setelah ±360 foto di prod.
 */
test('klien OAuth global dipakai ulang, tidak dibangun per layanan', function () {
    config([
        'services.google.oauth_client_id' => 'id',
        'services.google.oauth_client_secret' => 'rahasia',
        'services.google.oauth_refresh_token' => 'refresh',
    ]);

    $bersama = new GoogleClient;
    setelKlienOauth($bersama);

    $sekolahA = GoogleDriveService::forSchool(new SchoolDriveConfig(['is_active' => true]));
    $sekolahB = GoogleDriveService::forSchool(new SchoolDriveConfig(['is_active' => true]));

    expect(klienDari($sekolahA))->toBe($bersama)
        ->and(klienDari($sekolahB))->toBe($bersama);
});
