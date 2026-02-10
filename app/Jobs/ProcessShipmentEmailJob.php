<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentEmailService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShipmentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout del job padre. Deve essere sufficiente per creare tutti i job figli.
     */
    public $timeout = 120;

    public function __construct(
        public int $shipmentId,
        public int $userId
    ) {}

    public function handle(ShipmentEmailService $service): void
    {
        // 1. Carichiamo la spedizione con i suoi destinatari
        $shipment = Shipment::with('receivers')->find($this->shipmentId);

        if (!$shipment) {
            Log::error("Impossibile avviare: Spedizione #{$this->shipmentId} non trovata.");
            return;
        }

        // 2. Estraiamo i destinatari (usando il service che legge la tabella receivers)
        $recipients = $service->extractRecipients($shipment);
        $totalRecipients = count($recipients);

        if ($totalRecipients === 0) {
            Log::warning("Nessun destinatario trovato per la spedizione #{$this->shipmentId}");
            return;
        }

        /**
         * 3. RESET CONTATORI (Come faceva la versione precedente)
         * Prepariamo la tabella Shipment impostando chi invia e resettando i numeri.
         */
        $shipment->update([
            'send_date' => now()->format('Y-m-d H:i:s'),
            'send_user_id' => $this->userId,
            'no_mails_to_send' => $totalRecipients,
            'no_mails_sended' => 0,
            // Non impostiamo ancora send_date (lo faremo a Batch concluso)
        ]);

        // 4. Trasformiamo ogni destinatario in un Job "figlio"
        $jobs = collect($recipients)->map(function ($receiverData) {
            return new SendShipmentEmailJob(
                shipmentId: $this->shipmentId,
                receiverId: $receiverData['id']
            );
        })->toArray();

        // Variabili per le callback del Batch
        $shipmentId = $this->shipmentId;
        $userId = $this->userId;

        \Illuminate\Support\Facades\Cache::forget("shipment_log_count_{$this->shipmentId}");

        Log::info('Avvio spedizione_______________________________________________________________');

        // 5. Avviamo il Batch
        Bus::batch($jobs)
            ->name("Invio Massivo Spedizione #{$shipmentId}")
            ->then(function (Batch $batch) use ($shipmentId) {
                // Eseguito solo se TUTTI i job hanno avuto successo
                $s = Shipment::find($shipmentId);
                if ($s) {
                    $s->update(['send_date' => now()]);
                }
            })
            ->finally(function (Batch $batch) use ($shipmentId, $userId) {
                // Notifica finale su Filament per l'utente
                $user = User::find($userId);
                if ($user) {
                    $hasFailures = $batch->failedJobs > 0;

                    Log::info($hasFailures ? 'Spedizione terminata con alcuni errori' : 'Spedizione completata');

                    \Filament\Notifications\Notification::make()
                        ->title($hasFailures ? 'Spedizione terminata con alcuni errori' : 'Spedizione completata')
                        ->body("Spedizione #{$shipmentId}: {$batch->processedJobs()} inviate di {$batch->totalJobs}.")
                        ->icon($hasFailures ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                        ->color($hasFailures ? 'warning' : 'success')
                        ->sendToDatabase($user);
                }
            })
            ->allowFailures() // Permette al batch di continuare anche se una mail fallisce
            ->dispatch();

        Log::info("Batch avviato correttamente per Spedizione #{$shipmentId} con {$totalRecipients} destinatari.");
    }
}
