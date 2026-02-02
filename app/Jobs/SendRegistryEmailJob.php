<?php

namespace App\Jobs;

use App\Mail\RegistryMailable;
use App\Models\Registry;
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
        public string $recipientName,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RegistryEmailService $registryEmailService): void
    {
        // Controlla se il batch è stato cancellato
        if ($this->batch()?->cancelled()) {
            return;
        }

        $registry = Registry::find($this->registryId);

        if (!$registry) {
            Log::error("Registry non trovato", ['id' => $this->registryId]);
            return;
        }

        // Verifica che sia un'email in uscita non ancora inviata
        if ($registry->send_date) {
            Log::warning("Email già inviata", [
                'registry_id' => $this->registryId,
                'protocol_number' => $registry->protocol_number,
            ]);
            return;
        }

        if (!$registry->account_id) {
            throw new Exception("Account non specificato per Registry #{$this->registryId}");
        }

        try {
            // Imposta l'account
            $registryEmailService->setAccount($registry->account_id);
            $account = $registryEmailService->getAccount();

            // Configura il mailer dinamico
            $mailerName = "registry_{$registry->id}_{$account->id}";
            Config::set("mail.mailers.{$mailerName}", $account->getSmtpMailerConfig());

            // Prepara gli allegati
            $attachments = $registryEmailService->prepareAttachments($registry);

            // Crea e invia la mail
            $mailable = new RegistryMailable(
                subject: $registry->subject,
                body: $registry->body,
                fromAddress: $account->getFromAddress(),
                fromName: $account->getFromName(),
                attachments: $attachments,
                protocolNumber: $registry->protocol_number,
            );

            Mail::mailer($mailerName)
                ->to($this->recipientEmail, $this->recipientName)
                ->send($mailable);

            Log::info("Email Registry inviata con successo", [
                'registry_id' => $this->registryId,
                'protocol_number' => $registry->protocol_number,
                'recipient' => $this->recipientEmail,
            ]);

        } catch (Exception $e) {
            Log::error("Errore invio email Registry", [
                'registry_id' => $this->registryId,
                'protocol_number' => $registry->protocol_number ?? 'N/A',
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

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
