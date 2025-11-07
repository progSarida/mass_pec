<?php

namespace App\Filament\Pages;

use App\Models\Sender;
use App\Filament\Resources\SenderResource;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SenderSettings extends Page
{
    protected static ?string $navigationIcon = 'fas-user-edit';
    protected static ?string $navigationGroup = 'Gestione';
    protected static ?string $navigationLabel = 'Mittente';
    protected static ?string $title = 'Configurazione Mittente';
    protected static ?string $slug = 'mittente';
    protected static string $view = 'filament.pages.sender-settings';

    public ?Sender $sender = null;
    public ?array $data = [];

    public function mount(): void
    {
        // carica o crea il record unico
        $this->sender = Sender::firstOrCreate([]);
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

        $this->notify('success', 'Dati del mittente salvati con successo!');
    }
}
