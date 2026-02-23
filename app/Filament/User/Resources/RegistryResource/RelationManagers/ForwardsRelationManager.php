<?php

namespace App\Filament\User\Resources\RegistryResource\RelationManagers;

use App\Enums\FlowType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource;
use App\Models\Registry;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ForwardsRelationManager extends RelationManager
{
    protected static string $relationship = 'forwards';

    protected static ?string $title = 'Inoltri';

    protected static ?string $modelLabel = 'Inoltro';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isIngoingEmail();                                                         // mostrata solo se email protocollata in uscita
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(15)
            ->schema([
                Section::make('Informazioni Principali')
                    ->columns(15)
                    ->schema([
                        TextInput::make('protocol_number')
                            ->label('Protocollo')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(['sm' => 'full', 'md' => 2])
                            ->default(static::newProtocol()),

                        Select::make('flow_type')
                            ->label('Corrispondenza')
                            ->required()
                            ->live()
                            ->options(FlowType::class)
                            ->afterStateUpdated(function(Set $set, $state){
                                $lastIndex = Registry::where('flow_type', $state)->max('flow_index');
                                if ($lastIndex) {
                                    $newIndex = $lastIndex+1;
                                    $set('flow_index', $newIndex);
                                } else {
                                    $set('flow_index', 1);
                                }
                            })
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        TextInput::make('flow_index')
                            ->label('Indice')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(['sm' => 'full', 'md' => 2]),

                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->required()
                            ->relationship('scopeType', 'name')
                            ->columnSpan(['sm' => 'full', 'md' => 5]),

                        Checkbox::make('is_email')
                            ->label('Posta elettronica')
                            ->live()
                            // ->disabled()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        TextInput::make('parent_reply')
                            ->label('Risposta a')
                            ->visible(fn ($record) => $record->registry_origin_type == RegistryOriginType::REPLY)
                            ->disabled()
                            ->formatStateUsing(function ($record) {
                                    $parent = $record->registry;
                                    return "[{$parent?->from}] $record->subject";
                                })
                            ->columnSpan(['sm' => 'full', 'md' => 'full']),

                        TextInput::make('forward_reply')
                            ->label('Inoltro di')
                            ->visible(fn ($record) => $record->registry_origin_type == RegistryOriginType::FORWARD)
                            ->disabled()
                            ->formatStateUsing(function ($record) {
                                    $parent = $record->registry;
                                    return "[{$parent?->from}] $record->subject";
                                })
                            ->columnSpan(['sm' => 'full', 'md' => 'full']),

                        TextInput::make('from')
                            ->label('Mittente')
                            ->disabled()
                            ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 9]),

                        // Textarea::make('body')
                        //     ->label('Messaggio')
                        //     ->rows(10)
                        //     ->columnSpan('full')
                        //     ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),

                        Select::make('region_display')
                            ->label('Regione')
                            ->visible(fn($record) => $record && $record->shipment_id)
                            ->options(\App\Models\Region::pluck('name', 'id'))
                            ->formatStateUsing(fn ($record) => $record?->shipment?->region_id)
                            ->columnSpan(['sm' => 'full', 'md' => 7])
                            ->disabled()
                            ->dehydrated(false), // Fondamentale: impedisce il salvataggio nel database

                        Select::make('province_display')
                            ->label('Provincia')
                            ->visible(fn($record) => $record && $record->shipment_id)
                            ->options(\App\Models\Province::pluck('name', 'id'))
                            ->formatStateUsing(fn ($record) => $record?->shipment?->province_id)
                            ->columnSpan(['sm' => 'full', 'md' => 8])
                            ->disabled()
                            ->dehydrated(false),

                        RichEditor::make('body')
                            ->label('Messaggio')
                            ->required()
                            ->default('') // Fondamentale per evitare l'errore "property not found"
                            ->columnSpanFull(),
                            ]),

                DateTimePicker::make('receive_date')
                    ->label('Ricevuto il')
                    ->visible(fn ($record) => $record->isIngoingEmail())
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                DatePicker::make('download_date')
                    ->label('Scaricato il')
                    ->visible(fn ($record) => $record->isIngoingEmail())
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->date('d/m/Y')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->visible(fn ($record) => $record->isIngoingEmail())
                    ->relationship('downloadUser', 'name')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                DatePicker::make('send_date')
                    ->label('Inviato il')
                    ->visible(fn ($record) => $record->isOutgoingEmail()
                                            || $record->registry_origin_type == RegistryOriginType::SHIPMENT)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->date('d/m/Y')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('send_user_id')
                    ->label('Inviato da')
                    ->visible(fn ($record) => $record->isOutgoingEmail()
                                            || $record->registry_origin_type == RegistryOriginType::SHIPMENT)
                    ->relationship('downloadUser', 'name')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Placeholder::make('not_in')
                    ->label('')
                    ->visible(fn ($record) => $record->isOutgoingEmail()
                                            || $record->registry_origin_type == RegistryOriginType::SHIPMENT
                                            || $record->registry_origin_type == RegistryOriginType::MANUAL)
                    // ->visible(fn(Get $get) => !$get('is_email'))
                    ->columnSpan(['sm' => '0', 'md' => 3]),

                Placeholder::make('manual')
                    ->label('')
                    ->visible(fn ($record) => $record->registry_origin_type == RegistryOriginType::MANUAL)
                    // ->visible(fn(Get $get) => !$get('is_email'))
                    ->columnSpan(['sm' => '0', 'md' => 6]),

                DateTimePicker::make('created_at')
                    ->label('Registrato il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(['sm' => 'full', 'md' => 3])
                    ->displayFormat('d/m/Y H:i:s')
                    ->visible(fn($record) => $record),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('register_user_id')
                    ->label('Registrato da')
                    ->relationship('registerUser', 'name')
                    ->visible(fn($record) => $record)
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Section::make('Allegati')
                    ->collapsed(fn($record) => $record)
                    ->visible(fn($record) => $record)
                    ->headerActions([
                        Action::make('downloadAll')
                            ->label('Scarica tutto (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            ->visible(function ($record) {
                                if (!$record || !$record->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record->attachment_path);
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('attachments.zip', [
                                'type' => $record->getMorphClass(),
                                'id' => $record->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                $files = Storage::files($record->attachment_path);

                                if (empty($files)) {
                                    return 'Nessun allegato.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));
                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📎</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->columnSpan('full'),
                    ]),

                Section::make('Documenti integrativi')
                    ->collapsed(fn($record) => $record)
                    ->visible(function ($record) {
                        $files = Storage::files($record->attachment_path . '/related');
                        if (empty($files)) { return false; }
                        return true;
                    })
                    ->headerActions([
                        Action::make('downloadAll')
                            ->label('Scarica tutto (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            ->visible(function ($record) {
                                if (!$record || !$record->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record->attachment_path . '/related');
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('related.zip', [
                                'id' => $record->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('related')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return 'Nessuna cartella documenti integrativi trovata.';
                                }

                                $files = Storage::files($record->attachment_path . '/related');

                                if (empty($files)) {
                                    return 'Nessun allegato.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));
                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📎</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
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
            ->recordTitleAttribute('protocol_number')
            ->columns([
                TextColumn::make('flow_type')
                    ->label('Corrispondenza')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registry_origin_type')
                    ->label('Origine')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('protocol_number')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scopeType.name')
                    ->label('Settore interno')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('from')
                    ->label('Mittente')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->from)
                    ->sortable(),

                Tables\Columns\TextColumn::make('receivers')
                    ->label('Destinatari')
                    ->state(fn ($record) => $record->registryReceivers?->count() ?? 0)
                    ->formatStateUsing(function ($state) {
                        if ($state === 0) return '0 destinatari';
                        return $state . ' ' . ($state === 1 ? 'destinatario' : 'destinatari');
                    })
                    ->tooltip(function ($record) {
                        $receivers = $record->registryReceivers;

                        if (! $receivers || $receivers->isEmpty()) {
                            return 'Nessun destinatario';
                        }

                        return $receivers->pluck('address')->implode(', ');
                    }),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->subject),

                TextColumn::make('esito_report') // Usa un nome che NON esiste nel database
                    ->label('Esito')
                    ->state(function ($record) {
                        // Qui eseguiamo il calcolo e restituiamo l'array
                        return static::checkReceipts($record);
                    })
                    ->formatStateUsing(function ($state) {
                        // Se per qualche motivo checkReceipts fallisce o non è un array, evitiamo il crash
                        if(!$state) return '';
                        $report = explode(', ', $state);
                        return $report[0] . " ( " . $report[1] . " )";
                    })
                    ->tooltip(function ($state) {
                        if (! is_array($state)) return null;

                        $sent = $state['sent'];
                        $delivered = $state['delivered'];

                        $tooltip = "Inviat" . ($sent == 1 ? "a 1 email" : "e {$sent} email");
                        $tooltip .= " e consegnat" . ($delivered == 1 ? "a 1" : "e {$delivered}");

                        return $tooltip;
                    }),

                TextColumn::make('body')
                    ->label('Messaggio')
                    ->limit(100)
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? Str::limit(strip_tags($state), 50) : '—')
                    ->tooltip(function ($record) {
                        if (!$record->body_preview) return 'Nessun contenuto';
                        $preview = strip_tags($record->body_preview);
                        return Str::limit($preview, 500);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('receive_date')
                    ->label('Ricevuto il')
                    ->date('d/m/Y H:i:S')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('send_date')
                    ->label('Data invio')
                    ->date('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sendUser.name')
                    ->label('Inviata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('download_date')
                    ->label('Scaricato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('downloadUser.name')
                    ->label('Scaricato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registerUser.name')
                    ->label('Registrato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->modalWidth('7xl'),
                Tables\Actions\Action::make('openFullRecord')
                    ->label('Vai all\'inoltro')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Registry $record): string => RegistryResource::getUrl('view', ['record' => $record])),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function newProtocol(): string
    {
        $lastRegistry = Registry::orderBy('created_at', 'desc')->first();

        if ($lastRegistry) {
            $parts = explode('-', $lastRegistry->protocol_number);

            if (count($parts) !== 3 || $parts[0] !== 'P') {
                return 'P-' . today()->year . '-00001';
            }

            $lastYear = (int) $parts[1];
            $lastNumber = (int) $parts[2];
            $currentYear = today()->year;

            if ($lastYear === $currentYear) {
                $newNumber = $lastNumber + 1;
                return 'P-' . $currentYear . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            } else {
                return 'P-' . $currentYear . '-00001';
            }
        }
        return 'P-' . today()->year . '-00001';
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
