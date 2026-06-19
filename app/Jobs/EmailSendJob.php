<?php

namespace App\Jobs;

use App\Mail\EmailMailable;
use App\Models\Email;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class EmailSendJob implements ShouldQueue
{
        use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public int $emailId,
        public string $recipientEmail,
        public ?string $recipientName = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        if ($this->batch()?->cancelled()) return;

        $email = Email::find($this->emailId);

        if (!$email) return;

        $user = User::find($this->userId);
        $account = '';

        try {
            $emailService->setAccount($email->account_id);
            $account = $emailService->getAccount();

            $mailerName = "dynamic_smtp_" . $this->emailId; // Nome univoco per evitare collisioni

            // Carichiamo la config che ora include già lo 'stream'
            $config = $account->out_mail_server == 'smtp-pc.aruba.it' ? $account->getSmtpMailerConfigSarida() : $account->getSmtpMailerConfig();
            Config::set("mail.mailers.{$mailerName}", $config);

            // Importante: forziamo Laravel a dimenticare istanze precedenti se siamo in un worker
            Mail::purge($mailerName);

            $attachments = $emailService->prepareAttachments($email);

            $mailable = new EmailMailable(
                subject: $email->subject,
                body: $email->body,
                fromAddress: $account->getFromAddress(),
                fromName: $account->getFromName(),
                attachments: $attachments,
            );

            $sentMessage = Mail::mailer($mailerName)
                ->to($this->recipientEmail)
                ->send($mailable);

            $messageId = $sentMessage->getMessageId();

            Log::info("Email inviata con successo", [
                'email_id' => $this->emailId,
                'message_id' => $messageId,
            ]);

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Verifichiamo se l'errore contiene il codice di blocco IP (554 o 5.7.1)
            $isIpBlocked = str_contains($errorMessage, '554') || str_contains($errorMessage, '5.7.1');

            \Filament\Notifications\Notification::make()
                ->title('Errore invio email da account ' . $account->username)
                ->body($errorMessage)
                ->danger()
                ->persistent()
                ->sendToDatabase($user);

            Log::error("Errore invio Email", [
                'email_id' => $this->emailId,
                'subject' => $email->subject ?? 'N/A',
                'recipient' => $this->recipientEmail,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
                'is_blocked' => $isIpBlocked
            ]);

            if ($isIpBlocked) {
                // Interrompiamo immediatamente i tentativi (fail) senza fare retry
                $this->fail($e);
                return;
            }

            // Se non è un blocco IP, rilancia l'eccezione per permettere i retry standard
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error("Invio Email fallito definitivamente", [
            'email_id' => $this->emailId,
            'recipient' => $this->recipientEmail,
            'error' => $exception?->getMessage(),
        ]);
    }
}
