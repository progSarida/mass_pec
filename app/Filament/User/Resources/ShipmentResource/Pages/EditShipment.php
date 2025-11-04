<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use App\Models\Receiver;
use App\Models\Sender;
use App\Models\Shipment;
use Carbon\Carbon;
use Exception;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            if (!empty($data['out_password'])) {
                $data['out_password'] = decrypt($data['out_password']);
            }
            if (!empty($data['password'])) {
                $data['password'] = decrypt($data['password']);
            }
        } catch (\Exception $e) {
            // Ignora se non criptato
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['out_password'])) {
            $data['out_password'] = encrypt($data['out_password']);
        }
        if (!empty($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        return $data;
    }

    protected $listeners = [                                                                                                // ascolto eventi action
        'start-shipment-send' => 'sendShipmentBackground',
        'shipment-sent-success' => 'onShipmentSuccess',
        'shipment-sent-error' => 'onShipmentError',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send')
                ->label('Invio PEC')
                ->icon('hugeicons-mail-send-01')
                // ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Conferma invio PEC')
                ->modalDescription('L\'invio partirà immediatamente. Continuare?')
                ->modalSubmitActionLabel('Sì, invia')
                ->action(function () {
                    $shipmentId = $this->record->id;
                    try {
                        $this->dispatch('start-shipment-send', shipmentId: $shipmentId);

                        Notification::make()
                            ->title('Invio PEC avviato')
                            ->body('L\'invio è in corso in background...')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('download')
                ->label('Scarico ricevute')
                ->icon('hugeicons-mail-receive-01')
                ->action(function (array $data) {
                    dd('SCARICO');
                }),
            Actions\Action::make('extract')
                ->label('Estrazione')
                ->action(function (array $data) {
                    dd('ESTRAZIONE');
                }),
            Actions\Action::make('receivers')
                ->label('Pec  destinatari')
                ->modalHeading('Pec destinatari')
                ->modalWidth('5xl')
                ->form([
                    Placeholder::make('receivers_list')
                        ->label('')
                        ->content(function () {
                            $receivers = $this->getReceiversForForm();
                            if (empty($receivers)) {
                                return 'Nessun destinatario';
                            }

                            $html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">';
                            foreach ($receivers as $receiver) {
                                $html .= '<div class="p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-900">';
                                $html .= e($receiver['address']);
                                $html .= '</div>';
                            }
                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        })
                ]),
            Actions\DeleteAction::make(),
        ];
    }

    private function getReceiversForForm(): array
    {
        $record = $this->record;
        if (!$record) return [];

        return Receiver::where('shipment_id', $record->id)
            ->get()
            ->map(fn($receiver) => ['address' => $receiver->address])
            ->toArray();
    }

    public function sendShipmentBackground($shipmentId)                                                                     // avvia invio in background
    {
        try {
            $this->sendShipment($shipmentId);

            $this->dispatch('shipment-sent-success');
        } catch (\Exception $e) {
            $this->dispatch('shipment-sent-error', message: $e->getMessage());
        }
    }

    public function sendShipment($id)
    {
        set_time_limit(120);
        ini_set('max_execution_time', 120);
        try {
            DB::beginTransaction();

            $shipment = Shipment::find($id);
            if (!$shipment) throw new \Exception("Spedizione non trovata!");

            $sender = Sender::find($shipment->sender_id);
            $recipients = Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $shipment->id)
                ->select('receivers.*', 'R.description as r_description')
                ->get();

            $sent = 0;
            $not_sent = 0;

            $smtp = strtolower($sender->out_mail_protocol_type->value) == 'smtp';
            $auth = (bool) $sender->out_authentication;
            $host = $sender->out_mail_server;
            $port = $sender->out_mail_port;
            $secure = $sender->connection_safety_type->value;
            $username = $sender->out_username;
            $password = decrypt($sender->out_password);
            $from = $sender->out_username;
            $name = $sender->public_name;
            $body = $shipment->mail_body;
            $attachmentPath = storage_path('app/public') . $shipment->shipment_path . $shipment->attachment;

            if (!file_exists($attachmentPath)) {
                throw new \Exception("Allegato non trovato: " . $attachmentPath);
            }

            // === TEST FINALE CON CREDENZIALI CORRETTE ===
            // set_time_limit(120);
            // ini_set('max_execution_time', 120);
            // $email = new PHPMailer(true);
            // $email->Timeout = 60;
            // $email->isSMTP();
            // $email->Host       = 'smtp.pec.it';                    // SERVER CORRETTO
            // $email->Port       = 465;
            // $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // SSL
            // $email->SMTPAuth   = true;
            // $email->Username   = 'corrispondenza@pec.sarida.it';
            // $email->Password   = '12111965Daniel@';                // PASSWORD CORRETTA
            // $email->setFrom('corrispondenza@pec.sarida.it', 'Corrispondenza Sarida s.r.l.');
            // $email->addAddress('fatture@pec.sarida.it');
            // $email->Subject    = 'TEST INVIO CORRETTO';
            // $email->Body       = 'Invio PEC funziona al 100%!';
            // $email->addAttachment($attachmentPath);
            // $email->SMTPDebug = 2;
            // $email->Debugoutput = function($str, $level) { Log::debug("FINAL TEST [$level]: $str"); };
            // try {
            //     $email->send();
            //     Log::info("PEC INVIATA CON SUCCESSO!");
            //     return 'PEC inviata con successo!'; // ferma esecuzione
            // } catch (Exception $e) {
            //     Log::error("ERRORE FINALE: " . $e->getMessage());
            //     return 'Errore: ' . $e->getMessage();
            // }

            foreach ($recipients as $recipient) {
                if (is_null($recipient->send_date)) {
                    $subject = $shipment->mail_object . " [" . $recipient->object_ref . "]";
                    $email = new PHPMailer(true);
                    $email->Timeout = 60;

                    if ($smtp) $email->isSMTP();
                    $email->Host = $host;
                    $email->Port = $port;

                    if ($auth) {
                        $email->SMTPAuth = true;
                        $email->Username = $username;
                        $email->Password = $password;
                    }

                    switch (strtolower($secure)) {
                        case 'ssl':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;                                               // porta 465
                            break;
                        case 'tls':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;                                            // porta 587
                            break;
                        default:
                            $email->SMTPSecure = '';                                                                        // nessuna crittografia
                    }

                    $email->setFrom($from, $name);
                    $email->addAddress($recipient->address, $recipient->r_description);
                    $email->Subject = $subject;
                    $email->Body = $body;
                    $email->addAttachment($attachmentPath);

                    // $email->SMTPDebug = 2;                                                                                  //
                    // $email->Debugoutput = function($str, $level) {                                                          // debug SMTP
                    //     Log::debug("SMTP [$level]: $str");                                                                  //
                    // };                                                                                                      //

                    // Log::info("=== CONFIGURAZIONE SMTP ===");
                    // Log::info("Host: " . $host);
                    // Log::info("Porta: " . $port);
                    // Log::info("Username: " . $username);
                    // Log::info("Password: " . $password);
                    // Log::info("Sicurezza: " . $secure);
                    // Log::info("SMTP: " . ($smtp ? 'true' : 'false'));
                    // Log::info("Auth: " . ($auth ? 'true' : 'false'));

                    // Log::info("=== PHPMailer CONFIG ===");
                    // Log::info("Host: " . $email->Host);
                    // Log::info("Port: " . $email->Port);
                    // Log::info("SMTPSecure: " . $email->SMTPSecure);
                    // Log::info("SMTPAuth: " . ($email->SMTPAuth ? 'true' : 'false'));
                    // Log::info("Username: " . $email->Username);
                    // Log::info("Password: " . $email->Password);
                    // Log::info("From: " . $email->From . " (" . $email->FromName . ")");
                    // Log::info("Subject: " . $email->Subject);
                    // Log::info("To: " . $email->getToAddresses()[0][0] ?? 'n/a');
                    // Log::info("Attachment: " . ($email->getAttachments()[0][0] ?? 'n/a'));

                    try {
                        if ($email->send()) {
                            $recipient->update(['send_date' => now()->format('Y-m-d')]);
                            $sent++;
                        } else {
                            $not_sent++;
                        }
                    } catch (Exception $e) {
                        $not_sent++;
                        Log::error("Errore invio PEC a {$recipient->address}: " . $e->getMessage());
                    }
                } else {
                    $sent++;
                }
            }

            $shipment->update([
                'no_mails_sended' => $sent,
                'no_mails_to_send' => $not_sent
            ]);

            DB::commit();

            // Notifica finale
            $this->dispatch('shipment-sent-success', sent: $sent, failed: $not_sent);

        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("Errore invio spedizione {$id}: " . $ex->getMessage());
            $this->dispatch('shipment-sent-error', message: $ex->getMessage());
        }
    }

    public function onShipmentSuccess()                                                                                         // notifica successo
    {
        Notification::make()
            ->title('Invio completato')
            ->success()
            ->send();

        $this->refreshFormData(['no_mails_sended', 'no_mails_to_send']);
    }

    public function onShipmentError($message)                                                                                   // notifica errore
    {
        Notification::make()
            ->title('Errore invio')
            ->body($message)
            ->danger()
            ->send();
    }
}
