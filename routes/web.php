<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router = app()->router;
$router->get('/', function () use ($router) {
    return response()->json(['status' => 'success', 'message' => 'Welcome to '.env('MAIL_FROM_NAME'), 'framework' => $router->app->version()]);
});

/*------------------------------------------ Api Version 1 Routes -------------------------------------------*/
$router->group(['prefix' => 'api/v1', 'middleware' => 'cors', 'namespace' => 'V1'], function () use ($router) {
    /*------------------------------------------ Guest Routes -------------------------------------------*/
    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->post('login', ['uses' => 'Auth\AuthsController@login']);
        $router->post('register', ['uses' => 'Auth\AuthsController@register']);
        $router->get('activate/{code}', ['uses' => 'Auth\AuthsController@activate']);
        $router->post('email/reset/password/link', ['uses' => 'Auth\AuthsController@email']);
        $router->post('reset/password', ['uses' => 'Auth\AuthsController@reset']);
        $router->get('logout', ['uses' => 'Auth\AuthsController@logout']);
    });

    /*------------------------------------------ JWT Refresh Routes -------------------------------------------*/
    $router->group(['prefix' => 'auth', 'middleware' => 'jwt.refresh'], function () use ($router) {
        $router->get('refresh', ['uses' => 'Auth\AuthsController@refresh']);
    });
    
    /*------------------------------------------ JWT Auth Routes -------------------------------------------*/
    $router->group(['middleware' => 'jwt.auth'], function() use ($router) {
        // Auth Routes
        $router->group(['prefix' => 'auth'], function () use ($router) {
            $router->get('user', ['uses' => 'Auth\AuthsController@profile']);
            $router->put('user/update', ['uses' => 'Auth\AuthsController@update']);
            $router->put('user/password/update', ['uses' => 'Auth\AuthsController@password']);
            $router->get('logout', ['uses' => 'Auth\AuthsController@logout']);
        });

        // User Routes
        $router->group(['prefix' => 'users'], function () use ($router) {
            $router->get('/',  ['uses' => 'UsersController@index']);  
            $router->post('store', ['uses' => 'UsersController@store']);
            $router->get('show/{id}', ['uses' => 'UsersController@show']);
            $router->put('update/{id}', ['uses' => 'UsersController@update']);
            $router->put('role/update/{id}', ['uses' => 'UsersController@role']);
            $router->put('deactivate/{id}', ['uses' => 'UsersController@deactivate']);
            $router->put('reactivate/{id}', ['uses' => 'UsersController@reactivate']);
            $router->delete('delete/{id}', ['uses' => 'UsersController@delete']);
        });

        // User Routes
        $router->group(['prefix' => 'roles'], function () use ($router) {
            $router->get('/',  ['uses' => 'RolesController@index']);
        });
    });
});