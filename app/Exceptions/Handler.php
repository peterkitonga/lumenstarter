<?php

namespace App\Exceptions;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
     * @param  \Illuminate\Http\Request $request
     * @param Exception $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // Handles the exceptions
        if ($exception instanceof MethodNotAllowedHttpException) {
            $httpStatusCode = Response::HTTP_METHOD_NOT_ALLOWED;
            $message = Response::$statusTexts[$httpStatusCode];
        } elseif ($exception instanceof NotFoundHttpException) {
            $httpStatusCode = Response::HTTP_NOT_FOUND;
            $message = Response::$statusTexts[$httpStatusCode];
        } elseif ($exception instanceof AuthorizationException) {
            $httpStatusCode = Response::HTTP_UNAUTHORIZED;
            $message = Response::$statusTexts[$httpStatusCode];
        } elseif ($exception instanceof \Dotenv\Exception\ValidationException && $exception->getResponse()) {
            $httpStatusCode = Response::HTTP_BAD_REQUEST;
            $message = Response::$statusTexts[$httpStatusCode];
        } elseif ($exception instanceof UnauthorizedHttpException) {
            $httpStatusCode = Response::HTTP_UNAUTHORIZED;

            if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                $message = 'Authorization token is Invalid';
            } else if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                $message = 'Authorization token is Expired';
            } else if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenBlacklistedException) {
                $message = 'Authorization token is Blacklisted';
            } else {
                $message = 'Authorization token not found';
            }
        } else {
            $httpStatusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = Response::$statusTexts[$httpStatusCode];
        }

        $exceptionLog = [
            'path' => $request->path(),
            'method' => $request->method(),
            'request' => json_encode($request->toArray()),
            'message' => empty($exception->getMessage()) ? $message : $exception->getMessage(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTraceAsString()
        ];

        \Illuminate\Support\Facades\Log::error(json_encode($exceptionLog, true));

        return response()->json([
            'status'  => 'error',
            'message' => $message
        ], $httpStatusCode);
    }
}
