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

        // Forza l'utilizzo del mailer appena configurato
        Mail::mailer($mailerName)
            ->to($this->recipientEmail, $this->recipientName)
            ->send($mailable);

        Log::info("Email Registry inviata", ['recipient' => $this->recipientEmail]);

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
