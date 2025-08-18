<?php

namespace App\Providers;

use App\Repositories\UserRepository;
use App\Repositories\TransactionRepository;
use App\Services\UserService;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Services\ExternalServices\AuthorizationService;
use App\Services\ExternalServices\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, function ($app) {
            return new UserRepository($app->make(EntityManagerInterface::class));
        });

        $this->app->singleton(TransactionRepository::class, function ($app) {
            return new TransactionRepository($app->make(EntityManagerInterface::class));
        });

        $this->app->singleton(AuthorizationService::class, function ($app) {
            return new AuthorizationService();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService($app->make(UserRepository::class));
        });

        $this->app->singleton(TransferService::class, function ($app) {
            return new TransferService(
                $app->make(EntityManagerInterface::class),
                $app->make(AuthorizationService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService(
                $app->make(TransactionRepository::class),
                $app->make(UserRepository::class),
                $app->make(EntityManagerInterface::class),
                $app->make(AuthorizationService::class),
                $app->make(NotificationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
