<?php

namespace App\Filament\User\Resources\ArchivedEmailResource\Pages;

use App\Enums\MailboxType;
use App\Enums\MailType;
use App\Filament\User\Resources\ArchivedEmailResource;
use App\Jobs\DownloadArchivedEmailsJob;
use App\Models\Account;
use DateTime;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Search\Date\Before;

class ListArchivedEmails extends ListRecords
{
    protected static string $resource = ArchivedEmailResource::class;

    protected $corrIn = [
                            'S-1-2014' => 'I semestre 2014',
                            'S-2-2014' => 'II semestre 2014',
                            'S-1-2015' => 'I semestre 2015',
                            'S-2-2015' => 'II semestre 2015',
                            'T-1-2016' => 'I trimestre 2016',
                            'T-2-2016' => 'II trimestre 2016',
                            'T-3-2016' => 'III trimestre 2016',
                            'T-4-2016' => 'IV trimestre 2016',
                        ];

    protected $corrOut = [
                            'S-1-2021' => 'I semestre 2021',
                            'S-2-2021' => 'II semestre 2021',
                            'T-1-2022' => 'I trimestre 2022',
                            'T-2-2022' => 'II trimestre 2022',
                            'T-3-2022' => 'III trimestre 2022',
                            'T-4-2022' => 'IV trimestre 2022',
                            'S-1-2023' => 'I semestre 2023',
                            'S-2-2023' => 'II semestre 2023',
                            'S-1-2024' => 'I semestre 2024',
                            'S-2-2024' => 'II semestre 2024',
                        ];

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),

            Actions\Action::make('download_in')
                ->label('Scarico email ricevute')
                ->icon('fluentui-mail-arrow-down-20')
                // ->visible(function () {
                //     $now = new DateTime('');
                //     $limitDate = new DateTime('2026-04-30 23:59:59');
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
                        ->live()
                        ->required()
                        ->options(Auth::user()->accounts->where('mail_type', MailType::PEC)->where('download', true)->pluck('public_name', 'id')),
                    Select::make('year')
                        ->label('Periodo')
                        ->required()
                        ->options(function (Get $get) {
                            $account = Account::find($get('account_id'));
                            return $account?->public_name == 'Corrispondenza' ?  $this->corrIn : [];
                        }),
                ])
                ->action(function ($data) {
                    // $this->connectionTest($data['account_id'], MailboxType::RECEIVED->getParameter(), $data['year']); dd('STOP');                                           // test connessione
                    try {
                        // Dispatch job combinato in background
                        DownloadArchivedEmailsJob::dispatch(Auth::id(), $data['account_id'], MailboxType::RECEIVED, $data['year'] ?? null);

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
                //     $limitDate = new DateTime('2026-04-30 23:59:59');
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
                        ->live()
                        ->required()
                        ->options(Auth::user()->accounts->where('mail_type', MailType::PEC)->where('download', true)->pluck('public_name', 'id')),
                    Select::make('year')
                        ->label('Periodo')
                        ->required()
                        ->options(function (Get $get) {
                            $account = Account::find($get('account_id'));
                            return $account?->public_name == 'Corrispondenza' ?  $this->corrOut : [];
                        }),
                ])
                ->action(function ($data) {
                    // $this->connectionTest($data['account_id'], MailboxType::ISSUED->getParameter(), $data['year']); dd('STOP');                                             // test connessione
                    try {
                        // Dispatch job combinato in background
                        DownloadArchivedEmailsJob::dispatch(Auth::id(), $data['account_id'], MailboxType::ISSUED, $data['year'] ?? null);

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

    public function getMaxContentWidth(): MaxWidth|string|null                                                                              // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }

    private function connectionTest($account_id, $box, $semester): void
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
            // $mailboxes = $connection->getMailboxes();
// dd($mailboxes);

            $mailbox = $connection->getMailbox($box);

            [$startYear, $endYear, $year, $period, $number] = $this->getDates($semester);
// dd($startYear, $endYear, 'STOP');
            $search = new SearchExpression();
            $search->addCondition(new Since($startYear));
            $search->addCondition(new Before($endYear));

            $messages = $mailbox->getMessages($search);

            Log::info("Totali {$number} {$period} {$year}: " . count($messages));

            foreach ($messages as $message) {
                $messageDate = $message->getDate(); // Ottieni l'oggetto DateTime
// dd($messageDate?->format('Y-m-d H:i:s'));
// dd($message->getTo());
$name = $message->getTo()[0]->getName() ? $message->getTo()[0]->getName() . ' - ' : '';
// dd("{$name}{$message->getTo()[0]->getAddress()}");                                                                                                              // recupero nome e indirizzo destinatario
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
// Log::info("RICEVUTA {$tipoRicevuta} - Data: {$messageDate?->format('Y-m-d')} - ID {$message->getId()}");
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

    private function getDates($semester): array
    {
        $semArray = explode('-', $semester);
        $year = (int) $semArray[2];

        if($semArray[0] == 'S'){
            $period = 'semestre';
            if($semArray[1] == '1'){
                $startYear = new DateTime("{$year}-01-01 00:00:00");
                $endYear = new DateTime(($year) . "-07-01 00:00:00");
            }
            else{
                $startYear = new DateTime("{$year}-07-01 00:00:00");
                $endYear = new DateTime(($year + 1) . "-01-01 00:00:00");
            }
        } else {
            $period = 'trimestre';
            if($semArray[1] == '1'){
                $startYear = new DateTime("{$year}-01-01 00:00:00");
                $endYear = new DateTime(($year) . "-04-01 00:00:00");
            }
            else if($semArray[1] == '2'){
                $startYear = new DateTime("{$year}-04-01 00:00:00");
                $endYear = new DateTime(($year) . "-07-01 00:00:00");
            }
            else if($semArray[1] == '3'){
                $startYear = new DateTime("{$year}-07-01 00:00:00");
                $endYear = new DateTime(($year) . "-10-01 00:00:00");
            }
            else{
                $startYear = new DateTime("{$year}-10-01 00:00:00");
                $endYear = new DateTime(($year + 1) . "-01-01 00:00:00");
            }
        }
        return [$startYear, $endYear, $year, $period, $semArray[1]];
    }
}
