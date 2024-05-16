<?php

namespace App\Providers;

use App\Repository\iConsumerRepository;
use App\Repository\iMasterRepository;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * | ------------ Provider for the Binding of Interface and Concrete Class of the Repository --------------------------- |
     * | Created On- 
     * | Created By- 
     */
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(iConsumerRepository::class, ConsumerRepository::class);
        $this->app->bind(iMasterRepository::class, MasterRepository::class);
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
