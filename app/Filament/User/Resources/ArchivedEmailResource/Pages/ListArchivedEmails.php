<?php

namespace App\Filament\User\Resources\ArchivedEmailResource\Pages;

use App\Enums\MailboxType;
use App\Filament\User\Resources\ArchivedEmailResource;
use App\Jobs\DownloadArchivedEmailsJob;
use App\Models\Account;
use DateTime;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ListArchivedEmails extends ListRecords
{
    protected static string $resource = ArchivedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),

            Actions\Action::make('download_in')
                ->label('Scarico email ricevute')
                ->icon('fluentui-mail-arrow-down-20')
                // ->visible(function () {
                //     $now = new DateTime('');
                //     $limitDate = new DateTime('2026-04-12 23:59:59');
                //     return $now < $limitDate;
                // })
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email ricevute')
                ->modalDescription('Verranno scaricate tutte le mail ricevute dall\'account selezionato')
                ->modalSubmitActionLabel('Scarica')
                ->form([
                    Select::make('account_id')
                        ->label('Account')
                        ->options(Auth::user()->accounts->where('download', true)->pluck('public_name', 'id'))
                ])
                ->action(function ($data) {
                    // $this->connectionTest($data['account_id'], MailboxType::RECEIVED->getParameter()); dd('STOP');                                           // test connessione
                    try {
                        // Dispatch job combinato in background
                        DownloadArchivedEmailsJob::dispatch(Auth::id(), $data['account_id'], MailboxType::RECEIVED->getParameter());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download delle email è stato avviato in background. Riceverai una notifica al termine.')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio download')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('download_out')
                ->label('Scarico email inviate')
                ->icon('fluentui-mail-arrow-down-20')
                // ->visible(function () {
                //     $now = new DateTime('');
                //     $limitDate = new DateTime('2026-04-12 23:59:59');
                //     return $now < $limitDate;
                // })
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email inviate')
                ->modalDescription('Verranno scaricate tutte le mail inviate dall\'account selezionato')
                ->modalSubmitActionLabel('Scarica')
                ->form([
                    Select::make('account_id')
                        ->label('Account')
                        ->options(Auth::user()->accounts->where('download', true)->pluck('public_name', 'id'))
                ])
                ->action(function ($data) {
                    // $this->connectionTest($data['account_id'], MailboxType::ISSUED->getParameter()); dd('STOP');                                             // test connessione
                    try {
                        // Dispatch job combinato in background
                        DownloadArchivedEmailsJob::dispatch(Auth::id(), $data['account_id'], MailboxType::ISSUED->getParameter());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download delle email è stato avviato in background. Riceverai una notifica al termine.')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio download')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }

    private function connectionTest($account_id, $box): void
    {
        $account = Account::find($account_id);
        $host = $account->in_mail_server;
        $port = (int)$account->in_mail_port;
        $username = $account->username;
        $password = decrypt($account->password);
        $encryption = strtolower($account->connection_safety_type->value);

$newCount = 0;
$mailCount = 0;
$receiptCount = 0;

        $flags = '/' . $account->in_mail_protocol_type->value;
        if ($encryption === 'ssl') $flags .= '/ssl';
        elseif ($encryption === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';

        $server = new Server($host, $port, $flags);
        $connection = $server->authenticate($username, $password);
// dd($connection, 'STOP');

        $limitDate = new DateTime('2026-01-01 00:00:00');

        try {
            $mailboxes = $connection->getMailboxes();
// dd($mailboxes);

            $mailbox = $connection->getMailbox($box);                                                                                                           // posta in arrivo
            $messages = $mailbox->getMessages();
// dd(count($messages), 'IN');
Log::info("Totali: " . count($messages));
            foreach ($messages as $message) {
                $messageDate = $message->getDate(); // Ottieni l'oggetto DateTime
// dd($messageDate?->format('Y-m-d H:i:s'));
dd($message->getTo());
$name = $message->getTo()[0]->getName() ? $message->getTo()[0]->getName() . ' - ' : '';
dd("{$name}{$message->getTo()[0]->getAddress()}");                                                                                                              // recupero nome e indirizzo destinatario
                if (!$messageDate || $messageDate > $limitDate) {                                                                                               // controllo data messaggio superiore al limite
                    $newCount++;
                    continue;
                }
                $uid = $message->getNumber();
                $rawHeaders = $message->getRawHeaders();
// dd($rawHeaders);

                if (preg_match('/^X-Ricevuta:\s*(accettazione|avvenuta-consegna|non-accettazione|anomalia|errore-consegna)/mi', $rawHeaders)) {                 // controllo ricevuta PEC 1
                    $receiptCount++;
                    $tipoRicevuta = '';
                    if (preg_match('/^X-Ricevuta:\s*([^\r\n]+)/mi', $rawHeaders, $matches)) {
                        $tipoRicevuta = trim($matches[1]);
                    }
Log::info("RICEVUTA {$tipoRicevuta} - Data: {$messageDate?->format('Y-m-d')} - ID {$message->getId()}");
                    continue;
                }

                if (preg_match('/^X-TipoRicevuta:\s*(accettazione|consegna|mancata-accettazione|mancata-consegna|anomalia|errore-consegna)/mi', $rawHeaders)) { // controllo ricevuta PEC 2
                    $receiptCount++;
                    $tipoRicevuta = '';
                    if (preg_match('/^X-Ricevuta:\s*([^\r\n]+)/mi', $rawHeaders, $matches)) {
                        $tipoRicevuta = trim($matches[1]);
                    }
Log::info("RICEVUTA {$tipoRicevuta} - Data: {$messageDate?->format('Y-m-d')} - ID {$message->getId()}");
                    continue;
                }
$mailCount++;
// Log::info("{$mailCount}) - ID: {$message->getId()}");
            }
Log::info("Nuove: {$newCount} - Email: {$mailCount} - Ricevute: {$receiptCount}");
// dd("Nuove: {$newCount} - Email: {$mailCount} - Ricevute: {$receiptCount}");

// dd('STOP');
        } catch (\Exception $e) {
                Log::info($e->getMessage());
                Notification::make()
                    ->title('Errore registrazione')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
        }
    }
}
