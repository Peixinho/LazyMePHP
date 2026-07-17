<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Http;

use Core\BladeFactory;
use Core\DocsServer;
use Core\ErrorHandler;
use Core\Helpers\ActivityLogger;
use Core\LazyMePHP;
use Core\Security\CsrfMiddleware;
use Pecee\Http\Request as PeceeRequest;
use Pecee\SimpleRouter\SimpleRouter;

/**
 * Web front controller. Call from public/index.php, after App/bootstrap.php:
 *
 *   require_once __DIR__ . '/../App/bootstrap.php';
 *   \Core\Http\Kernel::handle();
 *
 * Bootstrap already registers a PHP error/exception handler (Core\Helpers\ErrorUtil)
 * for every context, CLI included. This Kernel intentionally installs a second,
 * web-specific error_handler on top of it — set_error_handler() only keeps the
 * latest registration, so ErrorUtil::ErrorHandler() never actually runs for PHP
 * errors/warnings on web requests once this fires; Core\ErrorHandler takes over
 * instead, rendering an HTML/JSON error page instead of ErrorUtil's plain log
 * (note this also means ErrorUtil's error-email-alert feature does not fire here).
 */
class Kernel
{
    public static function handle(): void
    {
        self::installErrorHandler();

        $blade = BladeFactory::getBlade();

        if (self::serveDocsIfRequested()) {
            return;
        }

        self::loadRoutes($blade);

        SimpleRouter::error(function (PeceeRequest $request, \Exception $exception): void {
            ErrorHandler::handleWebException($exception, $request->getUrl()->getPath());
            exit;
        });

        try {
            ob_start();
            SimpleRouter::start();
            $pageContent = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            ErrorHandler::handleWebException($e, $_SERVER['REQUEST_URI'] ?? '');
            exit;
        }

        // A route that already set a JSON content-type (GraphQL, OpenAPI, ...) is an
        // API response, not a page — wrapping it in the HTML layout would make it
        // invalid JSON. Endpoints that call exit() after echoing (e.g. /auth/*)
        // already skip this naturally; this covers ones that return normally instead.
        if (self::isJsonResponse()) {
            echo $pageContent ?? '';
        } else {
            echo $blade->run('_Layouts.app', [
                'pageContent' => $pageContent ?? '',
            ]);
        }

        self::afterRequest();
    }

    /** True when the route handler already sent a JSON content-type header. */
    private static function isJsonResponse(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'application/json') !== false) {
                return true;
            }
        }
        return false;
    }

    private static function installErrorHandler(): void
    {
        set_error_handler(function ($severity, $message, $file, $line): bool {
            if (!(error_reporting() & $severity)) {
                return false; // Don't execute PHP's internal error handler
            }
            $exception = new \ErrorException($message, 0, $severity, $file, $line);
            ErrorHandler::handleWebException($exception);
            exit;
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $exception = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                ErrorHandler::handleWebException($exception);
            }
        });

        // Safety net for routes/middleware that call exit() directly (AuthMiddleware's
        // login redirect and role checks, AuthEndpoint's JSON responses, ...) — those
        // skip afterRequest() entirely, which would otherwise silently drop the access-log
        // row for exactly the requests most worth auditing (unauthenticated/forbidden
        // access attempts). Shutdown functions run regardless of how the script ends.
        // logActivity() is idempotent, so this is harmless alongside the normal
        // afterRequest() call for requests that complete without exit().
        register_shutdown_function(function (): void {
            if (class_exists(LazyMePHP::class)) {
                ActivityLogger::logActivity();
            }
        });
    }

    /** Serves the pre-built docs site at /docs, bypassing the router and layout entirely. Returns true if handled. */
    private static function serveDocsIfRequested(): bool
    {
        $basePath    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $requestUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $requestPath = $basePath !== '' ? substr($requestUri, strlen($basePath)) : $requestUri;

        if (!str_starts_with($requestPath, '/docs')) {
            return false;
        }

        DocsServer::serve(substr($requestPath, strlen('/docs')), __DIR__ . '/../../../docs/build');
        return true;
    }

    /** Loads every top-level App/Routes/*.php file within a base-path + middleware group. */
    private static function loadRoutes(\eftec\bladeone\BladeOne $blade): void
    {
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        $middleware = [
            // Runs first so an OPTIONS preflight for /graphql or /auth/* gets
            // answered (or rejected) before CSRF/security-header logic even sees it.
            CorsMiddleware::class,
            SecurityHeadersMiddleware::class,
            CsrfMiddleware::class,
        ];
        // App-specific, not a generic framework default: gates the web UI behind
        // login (see App\Middleware\AuthMiddleware). Guarded by class_exists so
        // this file stays a no-op drop-in for apps that don't define it.
        if (class_exists(\App\Middleware\AuthMiddleware::class)) {
            $middleware[] = \App\Middleware\AuthMiddleware::class;
        }

        SimpleRouter::group([
            'prefix'     => $basePath,
            'middleware' => $middleware,
        ], function () use ($blade): void {
            foreach (glob(__DIR__ . '/../../Routes/*.php') ?: [] as $routeFile) {
                require_once $routeFile;
            }
        });
    }

    private static function afterRequest(): void
    {
        if (!class_exists(LazyMePHP::class)) {
            return;
        }
        ActivityLogger::logActivity();
        if (LazyMePHP::DB_CONNECTION()) {
            LazyMePHP::DB_CONNECTION()->Close();
        }
    }
}
