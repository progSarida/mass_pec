<?php

namespace App\Jobs;

use App\Enums\PecInteractionType;
use App\Models\PecInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadEmailsWithReceiptsJob implements ShouldQueue
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
            DownloadEmailsJob::dispatch($this->userId, $this->accountId);

            // 2. Poi scarica le ricevute per tutte le registry in uscita inviate
            $this->downloadAllPendingReceipts();

            // INSERISCO QUI LA CREAZIONE DEL RECORD DI pec_interactions ('download', today())
            PecInteraction::create([
                'pec_interaction_type' => PecInteractionType::DOWNLOAD,
                'registry_id' => null,
                'interaction_date' => now(),
                'user_id' => $this->userId,
            ]);

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

    private function downloadAllPendingReceipts(): void
    {
        $user = \App\Models\User::find($this->userId);

        // Trova tutte le registry inviate che hanno destinatari senza ricevuta completa
        $registries = \App\Models\Registry::where('registry_origin_type', 'send_email')
            ->whereNotNull('send_date')
            ->whereNotNull('account_id')
            ->whereHas('registryReceivers', function ($query) {
                $query->whereIn('pec_status', [
                    \App\Enums\PecStatus::WAITING,
                    \App\Enums\PecStatus::ACCEPTED,
                ]);
            })
            ->get();

        $totalRegistries = $registries->count();

        if ($totalRegistries === 0) {
            Log::info("Nessuna registry con ricevute pendenti");

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

        Log::info("Trovate {$totalRegistries} registry con ricevute pendenti");

        $delay = 0;
        $jobsDispatched = 0;
        $jobsFailed = 0;

        foreach ($registries as $registry) {
            try {
                // Dispatch job per ogni registry con un piccolo delay per non sovraccaricare
                DownloadReceiptsJob::dispatch($registry->id, $this->userId)
                    ->delay(now()->addSeconds($delay));

                $delay += $this->receiptsDelaySeconds;
                $jobsDispatched++;

                Log::info("Schedulato download ricevute per protocollo {$registry->protocol_number} (delay: {$delay}s)");

            } catch (\Throwable $e) {
                $jobsFailed++;

                Log::error("Errore scheduling download ricevute", [
                    'registry_id' => $registry->id,
                    'protocol_number' => $registry->protocol_number,
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
                    ->body("Impossibile schedulare il download delle ricevute per {$jobsFailed} protocolli.")
                    ->danger()
                    ->sendToDatabase($user);
            } elseif ($jobsFailed > 0) {
                \Filament\Notifications\Notification::make()
                    ->title('Scheduling ricevute parziale')
                    ->body("Schedulati {$jobsDispatched} download di ricevute su {$totalRegistries}. {$jobsFailed} non schedulati.")
                    ->warning()
                    ->sendToDatabase($user);
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Download ricevute avviato')
                    ->body("Avviato il download delle ricevute per {$jobsDispatched} protocolli in background.")
                    ->info()
                    ->sendToDatabase($user);
            }
        }
    }
}
