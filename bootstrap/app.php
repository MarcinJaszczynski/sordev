<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Za reverse proxy (nginx, Cloudflare) — bez tego sesja/CSRF może padać z 419.
        $middleware->trustProxies(at: '*');

        // Enable Sanctum stateful middleware for first-party SPA API auth.
        $middleware->statefulApi();

        // Web middleware additions
        $middleware->web(append: [
            \App\Http\Middleware\EnsureApplicationInstalled::class,
            \App\Http\Middleware\ServeCompressedAssets::class,
            \App\Http\Middleware\ResolveRegionSlug::class,
        ]);

        $middleware->alias([
            'office' => \App\Http\Middleware\EnsureOfficeStaff::class,
            'api.pilot' => \App\Http\Middleware\EnsurePilotApiAudience::class,
            'api.client' => \App\Http\Middleware\EnsureClientApiAudience::class,
            'api.ability' => \App\Http\Middleware\EnsureApiTokenAbility::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('payments:send-reminders')
            ->dailyAt(config('payments.reminder_schedule_at', '09:00'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/payment-reminders-schedule.log'));

        if (! config('backup.schedule_enabled', true)) {
            return;
        }

        $schedule->command('app:backup', [
            '--components' => config('backup.scheduled_components', 'db,storage'),
        ])
            ->cron(config('backup.schedule_cron', '0 2 * * *'))
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-schedule.log'));

        $schedule->command('app:backup-prune')
            ->cron(config('backup.schedule_cron', '0 2 * * *'))
            ->withoutOverlapping();

        $schedule->command('app:notify-margin-discrepancies')
            ->dailyAt('07:00')
            ->withoutOverlapping();

        $schedule->command('tfg:notify-monthly-reminder')
            ->dailyAt(config('tfg.scheduler.reminder_hour', '06:00'))
            ->when(fn () => (int) now()->format('j') <= 14)
            ->withoutOverlapping();

        $schedule->job(new \App\Jobs\Tfg\BuildMonthlyTfgFeedJob)
            ->monthlyOn((int) config('tfg.scheduler.monthly_submit_day', 10), '06:30')
            ->withoutOverlapping();

        $schedule->command('tfg:notify-correction-deadlines')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        $schedule->command('inquiries:escalate-portal')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/portal-inquiry-escalation.log'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('livewire/*')) {
                return response()->json([
                    'message' => 'Sesja wygasła. Odśwież stronę i zaloguj się ponownie.',
                ], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except('_token'))
                ->with('error', 'Sesja wygasła. Odśwież stronę i zaloguj się ponownie.');
        });
    })->create();
