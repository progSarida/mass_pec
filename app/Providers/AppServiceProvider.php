<?php

namespace App\Providers;

use App\Responses\SsoLogoutResponse;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Responses\Auth\LogoutResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
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

        /**
         * Mappatura dei nomi abbreviati (alias) per i modelli polimorfici.
         * * FUNZIONAMENTO:
         * 1. Sostituisce il nome completo della classe (es. App\Models\InMail)
         * con un alias (es. 'in_mail') nel database e negli URL.
         * * UTILIZZO:
         * - Sicurezza: evita di esporre l'intera struttura delle classi negli URL.
         * - Flessibilità: se rinomini un modello in futuro, basta aggiornare la
         * mappa qui senza rompere i link o i dati esistenti.
         * - Controller: permette al download dei ZIP di identificare il modello
         * tramite $record->getMorphClass().
         */
        Relation::morphMap([
            'download_email' => \App\Models\DownloadEmail::class,
            'in_mail'  => \App\Models\InMail::class,
            'registry'   => \App\Models\Registry::class,
            'send_email'   => \App\Models\SendEmail::class,
        ]);


        /**
         * Gestione del flusso per ovviare al blocco per invio sospetto del server SMTP
         */
        RateLimiter::for('shipment-emails', function (object $job) {
            // return Limit::perMinute(25)->by('shipment-' . $job->shipmentId);
            // return Limit::perMinutes(5, 30)->by('shipment-' . $job->shipmentId);
            // return [
            //     Limit::perMinute(10)->by('shipment-' . $job->shipmentId),           // Non più di 10 email al minuto (sicurezza)
            //     Limit::perHour(500)->by('shipment-' . $job->shipmentId),            // Non più di 500 email all'ora (limite SMTP)
            // ];
            return Limit::perMinute(10)->by('shipment-' . $job->shipmentId);
        });
    }
}
