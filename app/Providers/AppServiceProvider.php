<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Request;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Request::server('HTTP_X_FORWARDED_PROTO') == 'https' || Request::server('HTTPS') == 'on') {
            URL::forceScheme('https');
        }
        // Для локального доступа по IP
        if (request()->getHost() === '192.168.1.2') {
            \URL::forceRootUrl('http://192.168.1.2:8080');
            \URL::forceScheme('http');
        }

        $host = request()->getHost();
        $port = request()->getPort();
        $scheme = request()->getScheme();

        // Для localhost и WSL
        if (in_array($host, ['localhost', '127.0.0.1']) ||
            str_contains($host, '.local') ||
            preg_match('/^172\./', $host)) {

            $url = $port && $port != 80 && $port != 443
                ? "{$scheme}://{$host}:{$port}"
                : "{$scheme}://{$host}";

            URL::forceRootUrl($url);
            URL::forceScheme($scheme);
        }

    }
}
