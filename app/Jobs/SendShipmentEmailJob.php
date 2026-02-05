<?php

namespace App\Jobs;

use App\Mail\ShipmentMailable;
use App\Models\Sender;
use App\Models\Shipment;
use App\Models\Receiver;
use App\Services\ShipmentEmailService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SendShipmentEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Numero di tentativi in caso di fallimento
     */
    public $tries = 3;

    /**
     * Secondi di attesa tra un tentativo e l'altro
     */
    public $backoff = 60;

    public function __construct(
        public int $shipmentId,
        public int $receiverId
    ) {}

    /**
     * Logica principale del Job
     */
    public function handle(ShipmentEmailService $service): void
    {
        if ($this->batch()?->cancelled()) return;

        $shipment = Shipment::find($this->shipmentId);
        $receiver = Receiver::find($this->receiverId);

        if (!$shipment || !$receiver || $receiver->send_date !== null) return;

        /**
         * 1. CONTROLLO ANTI-DUPLICATO
         * Se il destinatario ha già una data di invio, saltiamo.
         * Evita di inviare due volte la stessa PEC se il Job viene riavviato.
         */
        if ($receiver->send_date !== null) return;

        $sender = Sender::find($shipment->sender_id) ?? Sender::find(1);

        $mailerName = 'dynamic_smtp';

        // 2. Configurazione SMTP dinamica
        $this->configureSmtp($sender, $mailerName);

        // 3. Preparazione oggetto con REF (come nella vecchia versione)
        $customSubject = $shipment->mail_object . " [" . $receiver->ref . "]";

        // 4. Preparazione allegati
        $attachments = $service->prepareAttachments($shipment);

        // 5. INVIO EMAIL
        Mail::mailer($mailerName)->to($receiver->address)->send(
            new ShipmentMailable($shipment, $attachments, $customSubject)
        );

        /**
         * 6. AGGIORNAMENTO STATO E CONTATORI
         * Registriamo l'invio avvenuto con successo
         */

        // Aggiorna il destinatario con la data attuale
        $receiver->update([
            'send_date' => now()->format('Y-m-d H:i:s')
        ]);

        // Aggiorna i contatori della testata Shipment in modo atomico
        $shipment->increment('no_mails_sended');

        if ($shipment->no_mails_to_send > 0) {
            $shipment->decrement('no_mails_to_send');
        }
    }

    /**
     * Configura il mittente SMTP leggendo i dati dal DB
     */
    protected function configureSmtp(Sender $sender, string $mailerName): void
    {
        $password = $sender->out_password;

        try {
            $decrypted = Crypt::decryptString($sender->out_password);
            $password = str_starts_with($decrypted, 's:') ? unserialize($decrypted) : $decrypted;
        } catch (\Exception $e) {
            Log::error("Errore decriptazione password per Sender ID: {$sender->id}");
        }

        // Settiamo lo slot specifico senza toccare i default globali
        Config::set("mail.mailers.{$mailerName}", [
            'transport' => 'smtp',
            'host' => $sender->out_mail_server,
            'port' => $sender->out_mail_port,
            'encryption' => $sender->connection_safety_type?->value ?? 'ssl',
            'username' => $sender->out_username,
            'password' => $password,
            'timeout' => null,
            'auth_mode' => null,
        ]);

    }

    /**
     * Determina dopo quanti secondi riprovare il job se il limite è superato.
     */
    public function retryAfter(): int
    {
        return 60; // Aspetta un minuto prima di riprovare
    }

    /**
     * Richiamo del limiter per scongiurare il blocco del server SMTP per troppi invii successivi.
     */
    public function middleware(): array
    {
        return [new RateLimited('shipment-emails')];
    }
}
