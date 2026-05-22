<?php

namespace App\Providers;

use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Listeners\DispatchAttendanceWhatsAppNotification;
use App\Listeners\LogAttendanceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();
        $this->configureEvents();
    }

    protected function configureEvents(): void
    {
        Event::listen(StudentCheckedIn::class, DispatchAttendanceWhatsAppNotification::class);
        Event::listen(StudentCheckedIn::class, LogAttendanceActivity::class);
        Event::listen(StudentCheckedOut::class, LogAttendanceActivity::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
