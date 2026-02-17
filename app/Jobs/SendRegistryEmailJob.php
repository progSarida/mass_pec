<?php

namespace App\Jobs;

use App\Mail\RegistryMailable;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Services\RegistryEmailService;
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

class SendRegistryEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $registryId,
        public string $recipientEmail,
        public int $registryReceiverId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RegistryEmailService $registryEmailService): void
    {
        if ($this->batch()?->cancelled()) return;

        $registry = Registry::find($this->registryId);
        if (!$registry || $registry->send_date) return;

        try {
            $registryEmailService->setAccount($registry->account_id);
            $account = $registryEmailService->getAccount();

            // Usiamo un nome mailer standard o univoco per il processo
            $mailerName = "dynamic_smtp";

            Config::set("mail.mailers.{$mailerName}", $account->getSmtpMailerConfig());

            $attachments = $registryEmailService->prepareAttachments($registry);

            $mailable = new RegistryMailable(
                subject: $registry->subject,
                body: $registry->body,
                fromAddress: $account->getFromAddress(),
                fromName: $account->getFromName(),
                attachments: $attachments,
                protocolNumber: $registry->protocol_number,
            );

            // Forzo l'utilizzo del mailer appena configurato
            $sentMessage = Mail::mailer($mailerName)
                // ->to($this->recipientEmail, $this->recipientName)
                ->to($this->recipientEmail)
                ->send($mailable);

            // Recupero l'ID univoco (formato: <stringa@dominio.it>)
            $messageId = $sentMessage->getMessageId();

            // Salvo sul record del destinatario
            RegistryReceiver::where('id', $this->registryReceiverId)->update(['message_id' => $messageId]);

            Log::info("Email Registry inviata", ['recipient' => $this->recipientEmail, 'message_id' => $messageId]);

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Verifichiamo se l'errore contiene il codice di blocco IP (554 o 5.7.1)
            $isIpBlocked = str_contains($errorMessage, '554') || str_contains($errorMessage, '5.7.1');

            Log::error("Errore invio email Registry", [
                'registry_id' => $this->registryId,
                'protocol_number' => $registry->protocol_number ?? 'N/A',
                'recipient' => $this->recipientEmail,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
                'is_blocked' => $isIpBlocked
            ]);

            if ($isIpBlocked) {
                // Opzionale: segna il destinatario o l'invio come "fallito per blocco" nel DB
                // RegistryReceiver::where('id', $this->registryReceiverId)->update(['status' => 'blocked']);

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
        Log::error("Invio email Registry fallito definitivamente", [
            'registry_id' => $this->registryId,
            'recipient' => $this->recipientEmail,
            'error' => $exception?->getMessage(),
        ]);
    }
}
