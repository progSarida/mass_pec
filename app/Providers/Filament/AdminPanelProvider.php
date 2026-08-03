<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckDbSession;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->navigationGroups([
                NavigationGroup::make('Parametri'),
                NavigationGroup::make('Tabelle'),
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                CheckDbSession::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.topbar.ticket-button')->render()
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Operatore')
                    ->url('/user')
                    ->icon('fas-user'),
                MenuItem::make()
                    ->label('Pannello Utente')
                    ->url(config('services.sso.user_dashboard'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->openUrlInNewTab(),
                MenuItem::make()
                    ->label('Controlla IP server')
                    ->url(fn () => route('filament.admin.ip-check'))
                    ->icon('heroicon-o-globe-alt')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),
                'logout'=>MenuItem::make()
                    ->label('Vai al Portale')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle'),
                // ...
            ])
            ->routes(function () {
                \Illuminate\Support\Facades\Route::get('/my-ip4', function () {
                    abort_unless(auth()->user()->hasRole('super_admin'), 404);

                    $responseV4 = \Illuminate\Support\Facades\Http::withOptions([
                        'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                        'timeout' => 3,
                    ])->get('http://ip-api.com/json');

                    $dataV4 = $responseV4->successful() ? $responseV4->json() : null;
                    $ip = $dataV4['query'] ?? null;

                    $title = $ip ? "IP pubblico: {$ip}" : 'IP non disponibile';
                    $body = $dataV4 ? "Città: {$dataV4['city']}, ISP: {$dataV4['isp']}" : ($dataV4['error'] ?? null);
                    $status = $ip ? 'success' : 'danger';

                    // Toast immediato (richiede il redirect per essere mostrato)
                    \Filament\Notifications\Notification::make()
                        ->title($title)
                        ->body($body)
                        ->status($status)
                        ->send();

                    // Copia persistente nello storico notifiche
                    \Filament\Notifications\Notification::make()
                        ->title($title)
                        ->body($body)
                        ->status($status)
                        ->sendToDatabase(auth()->user());

                    return redirect()->back();
                })->name('ip-check');
            });
    }
}
