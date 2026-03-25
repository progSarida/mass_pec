<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadInMailsWithReceiptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public int $userId,
        public ?int $accountId = null,
        public int $receiptsDelaySeconds = 5, // Delay tra un registry e l'altro per non sovraccaricare
    ) {}

    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            Log::error("User non trovato", ['id' => $this->userId]);
            return;
        }

        try {
            // 1. Prima scarica tutte le email normali
            Log::info("Avvio download email per user {$this->userId}");
            DownloadInMailsJob::dispatch($this->userId);

            // 2. Poi scarica le ricevute delle spedizioni
            $this->downloadAllPendingShipmentReceipts();

        } catch (\Throwable $e) {
            Log::error("Errore download email con ricevute: " . $e->getMessage(), [
                'user_id' => $this->userId,
                'trace' => $e->getTraceAsString()
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Errore download email e ricevute')
                ->body($e->getMessage())
                ->danger()
                ->sendToDatabase($user);
        }
    }

    private function downloadAllPendingShipmentReceipts(): void
    {
        $user = \App\Models\User::find($this->userId);

        // Trova tutte le registry inviate che hanno destinatari senza esito definitivo
        $shipments = \App\Models\Shipment::with('receivers')
            ->whereNotNull('send_date') // Spedizione partita a livello globale
            ->whereNull('extraction_zip_file')
            ->whereHas('receivers', function ($query) {
                $query->whereNotNull('send_date') // Il singolo destinatario è stato processato
                    ->where(function ($parenthesis) { // Racchiudiamo i due casi tra parentesi
                        $parenthesis->where(function ($q) {
                            // CASO A: Aspettiamo ancora la prima risposta (Accettazione)
                            $q->whereNull('send_receipt')
                            ->whereNull('anomaly_receipt');
                        })
                        ->orWhere(function ($q) {
                            // CASO B: Accettata, ma aspettiamo l'esito finale (Consegna o Errore)
                            $q->whereNotNull('send_receipt')
                            ->whereNull('delivery_receipt')
                            ->whereNull('anomaly_receipt');
                        });

                    });
            })
            ->get();

        $totalShipments = $shipments->count();

        if ($totalShipments === 0) {
            Log::info("Nessuna spedizione con ricevute da scaricare");

            // Notifica che non ci sono ricevute da scaricare
            if ($user) {
                \Filament\Notifications\Notification::make()
                    ->title('Download ricevute')
                    ->body("Nessuna ricevuta pendente da scaricare.")
                    ->info()
                    ->sendToDatabase($user);
            }

            return;
        }

        Log::info("Trovate {$totalShipments} registry con ricevute pendenti");

        $delay = 0;
        $jobsDispatched = 0;
        $jobsFailed = 0;

        foreach ($shipments as $shipment) {
            try {
                // Dispatch job per ogni registry con un piccolo delay per non sovraccaricare
                DownloadShipmentReceiptsJob::dispatch($shipment->id, $this->userId)
                    ->delay(now()->addSeconds($delay));

                $delay += $this->receiptsDelaySeconds;
                $jobsDispatched++;

                Log::info("Schedulato download ricevute per spedizione {$shipment->id} (delay: {$delay}s)");

            } catch (\Throwable $e) {
                $jobsFailed++;

                Log::error("Errore scheduling download ricevute", [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage()
                ]);

                // Continua con la prossima registry
                continue;
            }
        }

        // Notifica riepilogo scheduling
        if ($user) {
            if ($jobsFailed > 0 && $jobsDispatched === 0) {
                \Filament\Notifications\Notification::make()
                    ->title('Errore scheduling ricevute')
                    ->body("Impossibile schedulare il download delle ricevute per {$jobsFailed} spedizioni.")
                    ->danger()
                    ->sendToDatabase($user);
            } elseif ($jobsFailed > 0) {
                \Filament\Notifications\Notification::make()
                    ->title('Scheduling ricevute parziale')
                    ->body("Schedulati {$jobsDispatched} download di ricevute su {$totalShipments}. {$jobsFailed} non schedulati.")
                    ->warning()
                    ->sendToDatabase($user);
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Download ricevute avviato')
                    ->body("Avviato il download delle ricevute per {$jobsDispatched} spedizioni in background.")
                    ->info()
                    ->sendToDatabase($user);
            }
        }
    }
}
