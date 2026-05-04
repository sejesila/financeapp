<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

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
        User::observe(UserObserver::class);
        Pdf::default()->withBrowsershot(function (Browsershot $browsershot) {
            $chromePath = glob('/usr/local/share/puppeteer/chrome/linux-*/chrome-linux64/chrome')[0]
                ?? throw new \RuntimeException('Chrome not found');

            $browsershot
                ->setChromePath($chromePath)
                ->noSandbox()
                ->disableGpu()
                ->addChromiumArguments([
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-software-rasterizer',
                ]);
        });
    }
}
