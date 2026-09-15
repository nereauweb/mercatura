<?php

namespace App\Exceptions;

use App\Http\Controllers\FrontendContentController;
use App\Support\CaughtExceptionLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Always persist exceptions to application logs, even when they
     * belong to Laravel's internal dontReport list.
     */
    public function report(Throwable $e): void
    {
        try {
            Log::error('Unhandled exception', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } catch (Throwable $loggingError) {
            // Never break exception flow because logging failed.
        }

        parent::report($e);
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return parent::render($request, $exception);
        }

        // In debug mode always show Laravel's detailed error page.
        if (config('app.debug')) {
            return parent::render($request, $exception);
        }

        if ($this->isHttpException($exception)) {
            $statusCode = $exception->getStatusCode();

            switch ($statusCode) {
                // not found
                case 404:
                    if ($redirect = $this->legacyRedirect($request)) {
                        return $redirect;
                    }
                    try {
                        $controller = App::make(FrontendContentController::class);

                        return $controller->notFound($request);
                    } catch (\Exception $e) {
                        CaughtExceptionLogger::error('Exception Handler: FrontendContentController::notFound failed', $e, [
                            'status_code' => 404,
                        ]);

                        return response()->view('frontend.pages.not_found', [], 404);
                    }
                    break;

                    // forbidden
                case 403:
                    try {
                        $controller = App::make(FrontendContentController::class);

                        return $controller->error($request, 403);
                    } catch (\Exception $e) {
                        CaughtExceptionLogger::error('Exception Handler: FrontendContentController::error failed (403)', $e, [
                            'status_code' => 403,
                        ]);

                        return response()->view('frontend.pages.error', ['statusCode' => 403], 403);
                    }
                    break;

                    // internal error and other server errors
                case 500:
                case 503:
                default:
                    try {
                        $controller = App::make(FrontendContentController::class);

                        return $controller->error($request, $statusCode);
                    } catch (\Exception $e) {
                        CaughtExceptionLogger::error('Exception Handler: FrontendContentController::error failed', $e, [
                            'status_code' => $statusCode,
                        ]);

                        return response()->view('frontend.pages.error', ['statusCode' => $statusCode], $statusCode);
                    }
                    break;
            }
        } else {
            // Handle non-HTTP exceptions (like general PHP errors, etc.)
            // These should be shown as 500 errors when APP_DEBUG is false
            if (! config('app.debug')) {
                try {
                    $controller = App::make(FrontendContentController::class);

                    return $controller->error($request, 500);
                } catch (\Exception $e) {
                    CaughtExceptionLogger::error('Exception Handler: FrontendContentController::error failed (non-HTTP exception path)', $e, [
                        'status_code' => 500,
                    ]);

                    return response()->view('frontend.pages.error', ['statusCode' => 500], 500);
                }
            }

            // In development (APP_DEBUG=true), show the default Laravel error page with details
            return parent::render($request, $exception);
        }
    }

    /**
     * Render an HTTP exception into an HTTP response.
     */
    protected function renderHttpException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e)
    {
        $statusCode = $e->getStatusCode();
        $request = request();

        // Force use of our custom error pages
        switch ($statusCode) {
            case 404:
                if ($redirect = $this->legacyRedirect($request)) {
                    return $redirect;
                }
                try {
                    $controller = App::make(FrontendContentController::class);

                    return $controller->notFound($request);
                } catch (\Exception $ex) {
                    CaughtExceptionLogger::error('Exception Handler: renderHttpException notFound failed', $ex, [
                        'status_code' => 404,
                    ]);

                    return response()->view('frontend.pages.not_found', [], 404);
                }

            case 403:
                try {
                    $controller = App::make(FrontendContentController::class);

                    return $controller->error($request, 403);
                } catch (\Exception $ex) {
                    CaughtExceptionLogger::error('Exception Handler: renderHttpException error page failed (403)', $ex, [
                        'status_code' => 403,
                    ]);

                    return response()->view('frontend.pages.error', ['statusCode' => 403], 403);
                }

            case 500:
            case 503:
            default:
                try {
                    $controller = App::make(FrontendContentController::class);

                    return $controller->error($request, $statusCode);
                } catch (\Exception $ex) {
                    CaughtExceptionLogger::error('Exception Handler: renderHttpException error page failed', $ex, [
                        'status_code' => $statusCode,
                    ]);

                    return response()->view('frontend.pages.error', ['statusCode' => $statusCode], $statusCode);
                }
        }
    }

    /**
     * Prepare a response for the given exception.
     */
    protected function prepareResponse($request, Throwable $e)
    {
        // Convert non-HTTP exceptions to HTTP 500
        if (! $this->isHttpException($e)) {
            if (! config('app.debug')) {
                // In production, show our custom error page
                try {
                    $controller = App::make(FrontendContentController::class);

                    return $controller->error($request, 500);
                } catch (\Exception $ex) {
                    CaughtExceptionLogger::error('Exception Handler: prepareResponse error page failed', $ex, [
                        'status_code' => 500,
                    ]);

                    return response()->view('frontend.pages.error', ['statusCode' => 500], 500);
                }
            }
            $e = new HttpException(500, $e->getMessage(), $e);
        }

        return $this->toIlluminateResponse(
            $this->renderHttpException($e), $e
        )->prepare($request);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    /**
     * docs/ARCHITECTURE.md §12: a path the router does not know may be an
     * old URL with a stored redirect (301, or 410 when the content is gone).
     */
    protected function legacyRedirect($request): ?\Symfony\Component\HttpFoundation\Response
    {
        if (! $request instanceof \Illuminate\Http\Request || ! $request->isMethod('GET')) {
            return null;
        }
        try {
            $redirect = \App\Models\LegacyRedirect::for($request->getPathInfo());
        } catch (\Throwable $e) {
            return null;
        }
        if ($redirect === null) {
            return null;
        }
        $redirect->registerHit();
        if ($redirect->status_code === 410 || $redirect->to_path === null) {
            return response()->view('frontend.pages.not_found', [], 410);
        }

        return redirect($redirect->to_path, $redirect->status_code === 302 ? 302 : 301);
    }

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
