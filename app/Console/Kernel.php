<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**

     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\DatabaseBackUp',
        // \App\Console\Commands\GenerateMonthlyDemand::class,

        //\App\Console\Commands\GenerateNextMonthDemand::class,


    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule database backup daily
        $schedule->command('database:backup')->daily();

        // Run demand generation on the 1st of every month at 12:01 AM
        // $schedule->command('demand:generate-next-month')->monthlyOn(1, '00:00');
       // $schedule->command('demand:generate-next-month')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
