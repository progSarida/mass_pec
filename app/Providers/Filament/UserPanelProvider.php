<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckDbSession;
use Filament\Http\Middleware\Authenticate;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('user')
            ->login()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
                'print' => Color::rgb('rgb(255, 0, 0)'),
                'export' => Color::rgb('rgb(0, 153, 0)'),
            ])
            // ->topNavigation()
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->navigationGroups([
                NavigationGroup::make('Protocolo'),
                NavigationGroup::make('Pec Massiva'),
                NavigationGroup::make('Email'),
                NavigationGroup::make('Tabelle'),
            ])
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->pages([
                // Pages\Dashboard::class,
                \App\Filament\User\Pages\CustomDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\\Filament\\User\\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.topbar.ticket-button')->render()
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Amministratore')
                // ->visible(fn (): bool => Auth::user()->is_admin)
                    ->visible(fn (): bool => Auth::user()->hasRole('super_admin'))
                    ->url('/admin')
                    ->icon('fas-user-lock'),
                    MenuItem::make()
                    ->label('Pannello Utente')
                    ->url(config('services.sso.user_dashboard'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->openUrlInNewTab(),
                'logout' => MenuItem::make()
                    ->label('Vai al Portale')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle'),
            ]);
    }
}
