<?php

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.
        $this->app['auth']->viaRequest('api', function ($request) {
            return app('auth')->setRequest($request)->user();
        });

        // Register Policies for the permissions available
        $this->registerPolicies();
    }

    /**
     * Checks for the user's access permissions
     *
     * @return void
     */
    public function registerPolicies()
    {
        Gate::define('admin-access', function (User $user) {
            return $user->hasAccess(['admin-access']);
        });
        
        Gate::define('subscriber-access', function (User $user) {
            return $user->hasAccess(['subscriber-access']);
        });
    }
}
