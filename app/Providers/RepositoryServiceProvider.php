<?php

namespace App\Providers;

use App\Repository\ConsumerRepository;
use App\Repository\iConsumerRepository;
use App\Repository\MasterRepository;
use App\Repository\iMasterRepository;
use App\Repository\ReportRepository;
use App\Repository\iReportRepository;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->bind(iConsumerRepository::class, ConsumerRepository::class);
        $this->app->bind(iMasterRepository::class, MasterRepository::class);
        $this->app->bind(iReportRepository::class, ReportRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
