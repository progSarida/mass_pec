<?php

namespace App\Filament\User\Resources\RegistryResource\RelationManagers;

use App\Enums\PecStatus;
use App\Models\City;
use App\Models\Recipient;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class RegistryReceiversRelationManager extends RelationManager
{
    #[On('refreshRelationManager')]
    public function refreshTable(): void
    {
        // Questo metodo forza il ricaricamento dei dati della tabella
    }

    protected static string $relationship = 'registryReceivers';

    protected static ?string $title = 'Ricevute';

    protected static ?string $modelLabel = 'Ricevuta';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isOutgoingEmail();                                                         // mostrata solo se email protocollata in uscita
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->columns(12)
                    ->relationship('recipient') // <--- IMPORTANTE: sposta il contesto su Recipient
                    ->disabled()
                    ->schema([
                        TextInput::make('description')->label('Descrizione') // Senza "recipient."
                            ->required()
                            ->live(debounce: 500)
                            ->columnSpan('full'),

                        Select::make('admin_type_id')->label('Tipo interlocutore')
                            ->relationship(name: 'adminType', titleAttribute: 'name') // Ora lo trova!
                            ->searchable()
                            ->preload()
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        Select::make('istat_type_id')->label('Tipo Istat')
                            ->relationship(name: 'istatType', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        TextInput::make('code_ipa')->label('Codice Ipa')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        TextInput::make('acronym')->label('Acronimo')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        Select::make('city_id')->label('Comune')
                            ->required()
                            ->relationship(name: 'city', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateHydrated(function (callable $set, $state) {
                                if ($state) {
                                    $city = City::find($state);
                                    if ($city) {
                                        $set('city_code', $city->code);
                                        $set('city_province', $city->province?->code);
                                        $set('city_region', $city->province?->region?->name);
                                    }
                                }
                            })
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                    ])->columnSpan('full'),
                Forms\Components\TextInput::make('address')
                    ->label('Indirizzo')
                    ->required()
                    ->maxLength(255),
                Section::make('Ricevute')
                    ->collapsed(fn($record) => !$record)
                    ->visible(fn($record) => $record)
                    ->headerActions([
                        Action::make('downloadReceipts')
                            ->label('Scarica tutte (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            ->visible(function ($record) {
                                $path = $record->registry->attachment_path . '/receipts';
                                if ($record && Storage::exists($path)) {
                                    $allFiles = Storage::files($path);
                                    $filteredFiles = collect($allFiles)->filter(function ($file) use ($record) {
                                        return str_contains(basename($file), $record->address);
                                    });
                                    return $filteredFiles->count() > 1;
                                }

                                return false;
                            })
                            ->url(fn ($record) => route('receipts.zip', [
                                'id' => $record->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('receipts')
                            ->label('')
                            ->content(function ($record) {
                                // Definisco il percorso specifico delle ricevute
                                $receiptsPath = $record->registry->attachment_path . '/receipts';

                                // Verifico se la cartella esiste prima di leggere i file
                                if (!Storage::exists($receiptsPath)) {
                                    return 'Nessuna ricevuta trovata.';
                                }

                                $files = Storage::files($receiptsPath);

                                // Filtro i file: devono contenere l'indirizzo nel nome
                                $filteredFiles = collect($files)->filter(function ($file) use ($record) {
                                    return str_contains(basename($file), $record->address);
                                });

                                if ($filteredFiles->isEmpty()) {
                                    return "Nessuna ricevuta disponibile per {$record->address}.";
                                }

                                return new HtmlString(
                                    $filteredFiles->map(function ($file) {
                                        $name = basename($file);

                                        // Genero URL
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));

                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📄</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-primary-600 hover:underline transition">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('recipient.description')
                    ->label('Destinatario'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Indirizzo'),
                Tables\Columns\TextColumn::make('pec_status')
                    ->label('Ricevuta')
                    ->icon(fn ($state) => $state?->getIcon())
                    ->color(fn ($state) => $state?->getColor())
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Aggiungi Nuovo Destinatario')
                    ->visible(fn() => !$this->getOwnerRecord()->send_date)
                    ->mutateFormDataUsing(function (array $data) {
                        $registry = $this->getOwnerRecord();

                        $data['recipient_id'] = Recipient::findByEmail($data['address'])?->id;
                        $data['protocol_number'] = $registry->protocol_number;
                        $data['pec_status'] = PecStatus::WAITING;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Destinatario'),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Destinatario')
                    ->visible(fn($record) => $record->pec_status === PecStatus::WAITING),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->pec_status === PecStatus::WAITING),
                Tables\Actions\Action::make('resend')
                    ->label('Reinvia')
                    ->icon('heroicon-c-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn ($record, $livewire) => $livewire->pageClass === \App\Filament\User\Resources\RegistryResource\Pages\EditRegistry::class
                            && $record->registry->send_date
                            && !$record->message_id
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reinvia Email')
                    ->modalDescription(fn($record) => "Vuoi tentare un nuovo invio a {$record->address}?")
                    ->action(function ($record) {
                        try {

                            \App\Jobs\SendRegistryEmailJob::dispatch(
                                registryId: $record->registry_id,
                                recipientEmail: $record->address,
                                registryReceiverId: $record->id,
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Invio pianificato')
                                ->body("Il nuovo tentativo di invio per {$record->address} è stato accodato.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Errore durante il rinvio')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->checkReceipts()),
                ]),
            ]);
    }

    private static function checkReceipts($registry)
    {

        if(!$registry->isOutgoingEmail()) { return null; }
        $sent = 0;
        $delivered = 0;
        foreach($registry->registryReceivers as $receiver){
            if($receiver->pec_status == PecStatus::ACCEPTED) { $sent = ++$sent; }
            if($receiver->pec_status == PecStatus::NOT_ACCEPTED) {  }
            if($receiver->pec_status == PecStatus::DELIVERED) { $sent = ++$sent; $delivered = ++$delivered; }
            if($receiver->pec_status == PecStatus::NOT_DELIVERED) { $sent = ++$sent; }
        }
        $report = [
            'sent' => $sent,
            'delivered' => $delivered,
        ];
        return $report;
    }
}
