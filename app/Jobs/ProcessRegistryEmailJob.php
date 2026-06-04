<?php

namespace App\Jobs;

use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Services\RegistryEmailService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessRegistryEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $registryId,
        public int $userId,
        public bool $autoDownloadReceipts = true, // Flag per scaricare ricevute automaticamente
        public int $receiptsDelayMinutes = 30,    // Delay prima di scaricare le ricevute
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RegistryEmailService $registryEmailService): void
    {
        $registry = Registry::find($this->registryId);

        if (!$registry) {
            Log::error("Registry non trovato", ['id' => $this->registryId]);
            return;
        }

        if ($registry->send_date) {
            Log::warning("Email Registry già inviata", [
                'id' => $this->registryId,
                'protocol_number' => $registry->protocol_number,
            ]);
            return;
        }

        // Estrai i destinatari
        $recipients = $registryEmailService->extractRecipients($registry);

        if (empty($recipients)) {
            Log::warning("Nessun destinatario trovato per Registry", [
                'id' => $this->registryId,
                'protocol_number' => $registry->protocol_number,
            ]);
            return;
        }

        // Crea un batch di job per ogni destinatario
        $jobs = $recipients->map(fn (RegistryReceiver $recipient) => new SendRegistryEmailJob(
            registryId: $this->registryId,
            recipientEmail: $recipient->address,
            registryReceiverId: $recipient->id,
        ))->toArray();

        // IMPORTANTE: salva le variabili in variabili locali per evitare problemi di serializzazione
        $registryId = $this->registryId;
        $userId = $this->userId;
        $protocolNumber = $registry->protocol_number;
        $autoDownloadReceipts = $this->autoDownloadReceipts;
        $receiptsDelayMinutes = $this->receiptsDelayMinutes;

        // Esegui il batch con callbacks
        Bus::batch($jobs)
            ->name("Send Registry Email #{$registryId} ({$protocolNumber})")
            ->then(function (Batch $batch) use ($registryId, $userId, $protocolNumber, $autoDownloadReceipts, $receiptsDelayMinutes) {
                if ($batch->cancelled()) return;

                // Tutti i job sono completati con successo
                $registry = Registry::find($registryId);

                if ($registry && !$registry->send_date) {
                    $registry->update([
                        'send_date' => now()->format('Y-m-d H:i:s'),
                        'send_user_id' => $userId,
                    ]);

                    Log::info("Registry email marcata come inviata", [
                        'registry_id' => $registryId,
                        'protocol_number' => $protocolNumber,
                    ]);

                    // Schedule download ricevute se richiesto
                    if ($autoDownloadReceipts) {
                        DownloadReceiptsJob::dispatch($registryId, $userId)
                            ->delay(now()->addMinutes($receiptsDelayMinutes));

                        Log::info("Schedulato download ricevute tra {$receiptsDelayMinutes} minuti", [
                            'registry_id' => $registryId,
                            'protocol_number' => $protocolNumber,
                        ]);
                    }
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($registryId, $userId) {
                Log::error("Batch Registry email fallito", [
                    'registry_id' => $registryId,
                    'error' => $e->getMessage(),
                ]);
                $user = \App\Models\User::find($userId);

                $protocolNumber = Registry::find($registryId)?->protocol_number ?? 'N/A';

                if ($user) {
                    \Filament\Notifications\Notification::make()
                        ->title('Errore critico invio email')
                        // ->body("Il processo di invio per il protocollo ID {$registryId} si è interrotto bruscamente.")
                        ->body("Il processo di invio per il protocollo {$protocolNumber} si è interrotto bruscamente.")
                        ->danger()
                        ->persistent()
                        ->sendToDatabase($user);
                }
            })
            ->finally(function (Batch $batch) use ($registryId, $userId, $protocolNumber) {
                $user = \App\Models\User::find($userId);
                if (!$user) return;

                $totalJobs = $batch->totalJobs;
                $failedJobs = $batch->failedJobs;
                $successJobs = $totalJobs - $failedJobs;

                Log::info("Batch Registry email concluso", [
                    'registry_id' => $registryId,
                    'protocol_number' => $protocolNumber,
                    'total' => $totalJobs,
                    'success' => $successJobs,
                    'failed' => $failedJobs,
                ]);

                if ($failedJobs > 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Invio completato con errori')
                        ->body("Protocollo {$protocolNumber}: {$failedJobs} email su {$totalJobs} non sono state inviate. Controlla i log.")
                        ->warning()
                        ->sendToDatabase($user);
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Invio completato')
                        ->body("Tutte le email del protocollo {$protocolNumber} sono state inviate con successo.")
                        ->success()
                        ->sendToDatabase($user);
                }
            })
            ->allowFailures()
            ->dispatch();

        Log::info("Batch Registry email avviato", [
            'registry_id' => $registryId,
            'recipients_count' => count($jobs),
        ]);
    }
}
