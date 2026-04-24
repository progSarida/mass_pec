<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use App\Models\Recipient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipient extends CreateRecord
{
    protected static string $resource = RecipientResource::class;

    protected function beforeCreate(): void
    {
// dd($this->data['emails']);
        $emails = $this->data['emails'];

        foreach ($emails as $email) {
// dd($email['email']);
            $address = $email['email'];
            if(!$address || $address == '') {
                continue;
            }

            $recipient = static::getRecipient($address);

            if ($recipient) {
                Notification::make()
                    ->title("Indirizzo {$address} presente in archivio")
                    ->body("L'indirizzo {$address} è già associato a {$recipient->description}")
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt(); // blocca il salvataggio
            }
        }
    }

    // private static function getRecipientOld($from): Recipient|null
    // {
    //     $recipient = Recipient::where('mail_1', $from)
    //                     ->orWhere('mail_2', $from)
    //                     ->orWhere('mail_3', $from)
    //                     ->orWhere('mail_4', $from)
    //                     ->orWhere('mail_5', $from)
    //                     ->first();
    //     return $recipient;
    // }

    private static function getRecipient($from): Recipient|null
    {
        return Recipient::findByEmail($from);
    }
}
