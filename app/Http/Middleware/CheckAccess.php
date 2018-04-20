<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class CheckAccess
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $role, $guard = null)
    {
        // Pre-Middleware Action
        if ($this->auth->guard($guard)->guest()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Please login to continue'], 401);
        }

        if(!$this->auth->guard($guard)->user()->can($role.'-access'))
        {
            return response()->json(['status' => 'error', 'message' => 'Forbidden. You do not have permission to perform this action'], 403);
        }

        // Post-Middleware Action
        return $next($request);
    }
}
