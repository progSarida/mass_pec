<?php

namespace App\Filament\Pages;

use App\Models\Sender;
use App\Filament\Resources\SenderResource;
use Filament\Forms\Form;
use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;

class SenderSettings extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'fas-user-edit';
    protected static ?string $navigationLabel = 'Mittente Pec Massiva';
    protected static ?string $title = 'Configurazione Mittente Pec Massiva';
    protected static ?string $slug = 'sender';
    protected static string $view = 'filament.pages.sender-settings';
    protected static ?string $navigationGroup = 'Parametri';
    protected static ?int $navigationSort = 2;

    public ?Sender $sender = null;
    public ?array $data = [];

    public function mount(): void
    {
        // carica o crea il record unico
        $this->sender = Sender::firstOrCreate(
                [], // cerca il primo record esistente
                [
                    // Valori di default per la creazione
                    'cc' => null,
                    'management_type' => 'iab',
                    'mail_type' => 'pec',
                    'address' => '',
                    'username' => '',
                    'password' => '',
                    'public_name' => '',
                    'connection_safety_type' => 'ssl',
                    'in_mail_server' => '',
                    'in_mail_protocol_type' => 'pop3',
                    'in_mail_port' => '',
                    'out_mail_server' => '',
                    'out_mail_protocol_type' => 'pop3',
                    'out_mail_port' => '',
                    'out_authentication' => '1',
                    'out_username' => '',
                    'out_password' => '',
                ]
            );
        $this->form->fill($this->sender->toArray());
    }

    public function form(Form $form): Form
    {
        // riusa lo schema del form della Resource
        return SenderResource::form($form)
            ->model($this->sender)
            ->statePath('data');
    }

    public function save(): void
    {
        $validated = $this->form->getState();

        // aggiorna i campi del record
        $this->sender->update($validated);

        // $this->notify('success', 'Dati del mittente salvati con successo!');

        Notification::make()
            ->title("Dati del mittente salvati con successo!")
            ->success()
            ->send();
    }
}
