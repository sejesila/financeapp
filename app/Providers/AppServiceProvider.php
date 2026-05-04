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
        $chromePath = glob('/root/.cache/puppeteer/chrome/linux-*/chrome-linux64/chrome')[0]
            ?? glob('/home/farmpedia-finance/.cache/puppeteer/chrome/linux-*/chrome-linux64/chrome')[0]
            ?? glob('/usr/local/share/puppeteer/chrome/linux-*/chrome-linux64/chrome')[0]
            ?? null;

        if (!$chromePath) {
            throw new \RuntimeException('Chrome not found');
        }

        Pdf::default()->withBrowsershot(function (Browsershot $browsershot) use ($chromePath) {
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
