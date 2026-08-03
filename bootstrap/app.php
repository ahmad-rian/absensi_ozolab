<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentSchool;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'super-admin' => EnsureSuperAdmin::class,
            'feature' => EnsureFeatureEnabled::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetCurrentSchool::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $page = match ($status) {
                    403 => 'errors/403',
                    404 => 'errors/404',
                    500, 503 => 'errors/500',
                    default => null,
                };

                if ($page && request()->header('X-Inertia')) {
                    return inertia($page, ['status' => $status])
                        ->toResponse(request())
                        ->setStatusCode($status);
                }

                if ($page) {
                    try {
                        return inertia($page, ['status' => $status])
                            ->toResponse(request())
                            ->setStatusCode($status);
                    } catch (Throwable) {
                        return $response;
                    }
                }
            }

            return $response;
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Purge stale photo-preview temp files hourly.
        $schedule->command('temp:clean')->hourly();

        // Close attendance rows that checked in but never checked out. Runs
        // hourly because each school sets its own check_out_end.
        $schedule->command('attendance:auto-checkout')->hourly()->withoutOverlapping();

        // Peringatan alpa sholat. Hourly, bukan dailyAt(), karena jendela
        // sholat diatur per sekolah — satu jam tetap pasti salah untuk
        // sebagian tenant. Command sendiri yang memutuskan apakah jendela hari
        // ini sudah benar-benar tutup.
        $schedule->command('prayer:notify-absence')->hourly()->withoutOverlapping();

        // PDF lembar pas foto hanya alat cetak — dibuang setelah seminggu supaya
        // tidak menumpuk di disk.
        $schedule->command('photo-sheets:prune')->dailyAt('02:30');
    })->create();
