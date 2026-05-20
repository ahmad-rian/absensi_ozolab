<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentSchool;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
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
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
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
    })->create();
