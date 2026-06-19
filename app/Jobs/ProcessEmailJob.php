<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\EmailService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $emailId,
        public int $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        $email = Email::find($this->emailId);

        if (!$email) {
            Log::error("Email non trovata", ['id' => $this->emailId]);
            return;
        }

        if ($email->send_date) {
            Log::warning("Email già inviata", [
                'id' => $this->emailId,
                'subject' => $email->subject,
            ]);
            return;
        }

        // Estrai i destinatari
        $recipients = $emailService->extractRecipients($email);

        if ($recipients->isEmpty()) {
            Log::warning("Nessun destinatario trovato per Email", [
                'id' => $this->emailId,
                'subject' => $email->subject,
            ]);
            return;
        }

        // Crea un batch di job per ogni destinatario
        $jobs = $recipients->map(fn ($recipient) => new EmailSendJob(
            userId: $this->userId,
            emailId: $this->emailId,
            recipientEmail: $recipient->email,
            recipientName: $recipient->name,
        ))->toArray();

        // IMPORTANTE: salva le variabili in variabili locali per evitare problemi di serializzazione
        $emailId = $this->emailId;
        $userId = $this->userId;
        $subject = $email->subject;

        // Esegui il batch con callbacks
        Bus::batch($jobs)
            ->name("Send Email #{$emailId} ({$subject})")
            ->then(function (Batch $batch) use ($emailId, $userId, $subject) {
                if ($batch->cancelled()) return;

                // Tutti i job sono completati con successo
                $email = Email::find($emailId);

                if ($email && !$email->send_date) {
                    $email->update([
                        'send_date' => now()->format('Y-m-d H:i:s'),
                        'send_user_id' => $userId,
                    ]);

                    Log::info("Email marcata come inviata", [
                        'email_id' => $emailId,
                        'subject' => $subject,
                    ]);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($emailId, $userId) {
                Log::error("Batch Email fallito", [
                    'email_id' => $emailId,
                    'error' => $e->getMessage(),
                ]);

                $user = \App\Models\User::find($userId);

                $emailSubject = Email::find($emailId)?->subject ?? 'N/A';

                if ($user) {
                    \Filament\Notifications\Notification::make()
                        ->title('Errore critico invio email')
                        // ->body("Il processo di invio per l'email ID {$emailId} si è interrotto bruscamente.")
                        ->body("Il processo di invio per l'email {$emailSubject} si è interrotto bruscamente.")
                        ->danger()
                        ->persistent()
                        ->sendToDatabase($user);
                }
            })
            ->finally(function (Batch $batch) use ($emailId, $userId, $subject) {
                $user = \App\Models\User::find($userId);
                if (!$user) return;

                $totalJobs = $batch->totalJobs;
                $failedJobs = $batch->failedJobs;
                $successJobs = $totalJobs - $failedJobs;

                Log::info("Batch Email concluso", [
                    'email_id' => $emailId,
                    'subject' => $subject,
                    'total' => $totalJobs,
                    'success' => $successJobs,
                    'failed' => $failedJobs,
                ]);

                Email::where('id', $emailId)->update([
                    'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $emailId,
                    'uid' => '#email_send' . $emailId,
                ]);

                if ($failedJobs > 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Invio completato con errori')
                        ->body("Email '{$subject}': {$failedJobs} email su {$totalJobs} non sono state inviate. Controlla i log.")
                        ->warning()
                        ->sendToDatabase($user);
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Invio completato')
                        ->body("Tutte le email per '{$subject}' sono state inviate con successo.")
                        ->success()
                        ->sendToDatabase($user);
                }
            })
            ->allowFailures()
            ->dispatch();

        Log::info("Batch Email avviato", [
            'email_id' => $emailId,
            'recipients_count' => count($jobs),
        ]);
    }
}
