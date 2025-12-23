<?php

namespace App\Providers;

use App\Responses\SsoLogoutResponse;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Responses\Auth\LogoutResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, SsoLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FileUpload::configureUsing(function (FileUpload $component): void {

                $diskName = $component->getDiskName() ?? Config::get('filesystems.default');
                $diskConfig = Config::get("filesystems.disks.{$diskName}");

                if (
                    $diskConfig &&
                    ($diskConfig['driver'] ?? '') === 's3' &&
                    empty($diskConfig['url'])
                ) {
                    $component->visibility('private');
                }
            });
    }
}
