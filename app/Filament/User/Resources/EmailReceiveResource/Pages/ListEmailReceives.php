<?php

namespace App\Filament\User\Resources\EmailReceiveResource\Pages;

use App\Filament\User\Resources\EmailReceiveResource;
use App\Jobs\EmailReceiveJob;
use App\Models\Account;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class ListEmailReceives extends ListRecords
{
    protected static string $resource = EmailReceiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_with_receipts')
                ->label('Scarico email')
                ->icon('fluentui-mail-inbox-arrow-down-20-o')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email e ricevute')
                ->modalDescription('Verranno scaricate tutte le mail degli account previsti e processate le ricevute PEC in background')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    try {
                        // static::connectionTestAccount(Account::find(6));dd('STOP');

                        EmailReceiveJob::dispatch(Auth::id());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download di email e ricevute è stato avviato in background. Riceverai una notifica al termine.')
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

    private static function connectionTestAccount(Account $account)
    {
        $host = $account->in_mail_server;
        $port = (int)$account->in_mail_port;
        $username = $account->username;
        $password = decrypt($account->password);
// dd($password);
        $encryption = strtolower($account->connection_safety_type->value);

        $flags = '/' . $account->in_mail_protocol_type->value;
        if ($encryption === 'ssl') $flags .= '/ssl';
        elseif ($encryption === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';
// dd($flags);

        $server = new Server($host, $port, $flags);
        $connection = $server->authenticate($username, $password);

dd($connection);
    }

    private static function connectionTest(Account $account)
    {
        $server = new Server(
            'imap.sarida.it',
            993,
            '/imap/ssl/novalidate-cert'
        );

        try {
            $connection = $server->authenticate(
                'programmazione@sarida.it',
                'S@rid@123'
            );

dd($connection);

            $mailbox = $connection->getMailbox('INBOX');
            $messages = $mailbox->getMessages();

            foreach ($messages as $message) {
                echo $message->getSubject() . "\n";
            }

        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
