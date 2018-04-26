<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $e
     * @return void
     * @throws Exception
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        // Handles the JWT exceptions
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
            switch (get_class($e->getPrevious())) {
                case \Tymon\JWTAuth\Exceptions\TokenExpiredException::class:
                    return response()->json(['status' => 'error', 'message' => 'Token has expired'], $e->getStatusCode());
                case \Tymon\JWTAuth\Exceptions\TokenInvalidException::class:
                    return response()->json(['status' => 'error', 'message' => 'Token is invalid'], $e->getStatusCode());
                case \Tymon\JWTAuth\Exceptions\TokenBlacklistedException::class:
                    return response()->json(['status' => 'error', 'message' => 'Token is blacklisted'], $e->getStatusCode());
                default:
                    return response()->json(['status' => 'error', 'message' => 'Token not found'], 401);
                    break;
            }
        }

        return parent::render($request, $e);
    }
}
