<?php

namespace DhippoVendor\ErrorIssueCreator;

use DhippoVendor\ErrorIssueCreator\Jobs\CreateGitHubIssueJob;
use Illuminate\Contracts\Debug\ExceptionHandler as HandlerContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ErrorIssueServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/error-issue-creator.php'
            => config_path('error-issue-creator.php'),
        ], 'config');

        // Extend Laravel’s exception handler
        $this->app->extend(HandlerContract::class, function ($handler) {
            // Use renderable() instead of reportable()
            $handler->renderable(function (Throwable $e, $request) {
                // Only in production
                if (! app()->environment('production')) {
                    return;
                }

                // Determine HTTP status (500 if not an HttpException)
                $status = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;

                // Load statuses to monitor
                $monitor = config('error-issue-creator.monitor_statuses', [500]);

                // Skip if this status isn’t in our list
                if (! in_array($status, $monitor, true)) {
                    return;
                }

                // Build unique key
                $key = sha1($e->getMessage() . $e->getFile() . $e->getLine());

                // Prepare payload
                $data = [
                    'key'     => $key,
                    'status'  => $status,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ];

                // Fetch any existing issue info
                $cached = Cache::get($key);

                Log::info("[ErrorIssueCreator] Captured HTTP {$status}, dispatching job", [
                    'key'    => $key,
                    'status' => $status,
                    'cached' => $cached,
                ]);

                CreateGitHubIssueJob::dispatch($data + ['cached' => $cached]);
            });

            return $handler;
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/error-issue-creator.php',
            'error-issue-creator'
        );
    }
}