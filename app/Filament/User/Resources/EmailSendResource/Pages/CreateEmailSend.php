<?php

namespace App\Filament\User\Resources\EmailSendResource\Pages;

use App\Enums\FlowType;
use App\Filament\User\Resources\EmailSendResource;
use App\Models\Email;
use DB;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailSend extends CreateRecord
{
    protected static string $resource = EmailSendResource::class;

    public function getTitle(): string
    {
        return "Nuova email in uscita";
    }

    public function mount(): void
    {
        parent::mount();                                                                                            // IMPORTANTE: chiamo prima il parent
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        DB::beginTransaction();
        try {
            // dd($data);
            $data['flow_type'] = FlowType::ISSUED;
            $data['flow_index'] = Email::getNextIndex(FlowType::ISSUED);
            $emailSend = parent::handleRecordCreation($data);
            DB::commit();
            return $emailSend;
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
