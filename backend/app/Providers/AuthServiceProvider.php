<?php

namespace App\Providers;

use App\Auth\Guards\SanctumGuard;
use App\Auth\Providers\DoctrineUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        Auth::extend('sanctum', function ($app, $name, array $config) {
            return new SanctumGuard(
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['request']
            );
        });

        Auth::provider('doctrine', function ($app, array $config) {
            return new DoctrineUserProvider(
                $app['em'],
                $config['entity']
            );
        });
    }
}
