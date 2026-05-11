<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('NEXUM')
            ->brandLogo(asset('images/eggon_logo_43542.png'))
            ->brandLogoHeight('3.25rem')
            ->login()
            ->colors([
                'primary' => [
                    50 => 'f5f8fb',
                    100 => 'edf5fb',
                    200 => 'd7e4ef',
                    300 => 'bfd3e4',
                    400 => '8eb8d7',
                    500 => '6fa6cf',
                    600 => '4f8bb9',
                    700 => '2f678f',
                    800 => '285675',
                    900 => '18324a',
                    950 => '0f1720',
                ],
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): Htmlable => new HtmlString('<link rel="stylesheet" href="'.asset('css/nexum-filament.css').'">'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): Htmlable => new HtmlString(<<<'HTML'
                    <script>
                        document.addEventListener('alpine:initialized', () => {
                            const closeMobileSidebar = () => {
                                if (window.matchMedia('(max-width: 1023px)').matches && window.Alpine?.store('sidebar')) {
                                    window.Alpine.store('sidebar').close()
                                }
                            }

                            closeMobileSidebar()
                            document.addEventListener('livewire:navigated', closeMobileSidebar)
                        })
                    </script>
                HTML),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
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
            ]);
    }
}
