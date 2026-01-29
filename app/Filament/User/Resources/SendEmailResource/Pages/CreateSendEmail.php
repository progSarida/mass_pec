<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Filament\User\Resources\SendEmailResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSendEmail extends CreateRecord
{
    protected static string $resource = SendEmailResource::class;

    public function getTitle(): string
    {
        return "Nuova email in uscita";
    }

    public function mount(): void
    {
        parent::mount();                                                                                            // IMPORTANTE: chiamo prima il parent

        // if (!isset($this->data['body'])) {                                                                          // Inizializzo esplicitamente mail_body
        //     $this->data['body'] = '';                                                                               // (necessario per far funzionare RichEditor)
        // }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        DB::beginTransaction();
        try {
            // dd($data);
            $sendEmail = parent::handleRecordCreation($data);
            DB::commit();
            return $sendEmail;
        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Errore durante la creazione della mail')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
