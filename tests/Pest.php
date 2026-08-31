<?php

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Role + permission per modul, sama persis dengan produksi.
        $this->seed(RolePermissionSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createAdminUser(array $attributes = []): User
{
    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        ...$attributes,
    ]);
    $user->assignRole('ADMIN');

    return $user;
}

function createSuperAdminUser(array $attributes = []): User
{
    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        ...$attributes,
    ]);
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

/**
 * Baris-baris XLSX dari sebuah respons unduhan.
 *
 * Ekspor memakai `BinaryFileResponse`, jadi `streamedContent()` tidak berlaku —
 * berkasnya ada di disk dan baru dihapus saat respons benar-benar dikirim, yang
 * tidak pernah terjadi di dalam test.
 *
 * @return array<int, array<int, string>>
 */
function xlsxRows(TestResponse $response): array
{
    $reader = new XlsxReader;
    $reader->open($response->baseResponse->getFile()->getPathname());

    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(
                static fn ($value): string => $value instanceof DateTimeInterface
                    ? $value->format('Y-m-d')
                    : (string) $value,
                $row->toArray(),
            );
        }

        break;
    }

    $reader->close();

    return $rows;
}

/**
 * Isi mentah `xl/worksheets/sheet1.xml` di dalam berkas XLSX.
 *
 * Dipakai untuk membuktikan tidak ada elemen `<f>` — satu-satunya cara pasti
 * membedakan sel teks dari sel RUMUS. Pembaca OpenSpout mengembalikan nilai,
 * bukan jenis selnya, jadi membaca nilai saja tidak bisa menangkap injeksi.
 */
function xlsxSheetXml(TestResponse $response): string
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    return $xml;
}
