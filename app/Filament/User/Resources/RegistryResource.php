<?php

namespace App\Filament\User\Resources;

use App\Enums\FlowType;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource\Pages;
use App\Filament\User\Resources\RegistryResource\RelationManagers;
use App\Filament\User\Resources\RegistryResource\RelationManagers\RegistryReceiversRelationManager;
use App\Models\Province;
use App\Models\Recipient;
use App\Models\Region;
use App\Models\Registry;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RegistryResource extends Resource
{
    protected static ?string $model = Registry::class;

    public static ?string $pluralModelLabel = 'Protocollo';
    protected static ?string $navigationIcon = 'fluentui-book-20';
    protected static ?string $navigationLabel = 'Protocollo';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(15)
            ->disabled(function ($record, $livewire) {
                $operation = $livewire instanceof \Filament\Resources\Pages\ViewRecord
                                ? 'view'
                                : 'create';
                if ($operation === 'view') { return true; }                                                     // disabilito in view
                if (!$record) { return false; }                                                                 // non disabilito in create
                return $record->registry_origin_type != RegistryOriginType::SEND_EMAIL                          // disabilito in edit se non è una mail in uscita o
                    || ($record->registry_origin_type == RegistryOriginType::SEND_EMAIL && $record->send_date); // disabilito in edit se è una mail in uscita ed è stata inviata
            })
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
                            ->label('Ambito')
                            ->required()
                            ->relationship('scopeType', 'name')
                            ->columnSpan(['sm' => 'full', 'md' => 5]),

                        Checkbox::make('is_email')
                            ->label('Posta elettronica')
                            ->live()
                            // ->disabled()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        TextInput::make('from')
                            ->label('Mittente')
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
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                DatePicker::make('download_date')
                    ->label('Scaricato il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->date('d/m/Y')
                    ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->relationship('downloadUser', 'name')
                    ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Placeholder::make('not_email')
                    ->label('')
                    ->visible(fn(Get $get) => !$get('is_email'))
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                    ->label('Ambito')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('from')
                    ->label('Mittente')
                    ->searchable()
                    ->limit(250)
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
                    ->limit(100)
                    ->tooltip(fn ($record) => $record->subject),

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

                // Tables\Columns\TextColumn::make('attachments')
                //     ->label('Allegati')
                //     ->formatStateUsing(fn ($state) => $state ? 'Apri cartella' : '—')
                //     ->url(fn ($record) => $record->attachment_path ? asset('storage/' . $record->attachment_path) : null)
                //     ->openUrlInNewTab()
                //     ->icon('heroicon-o-folder-open')
                //     ->color('primary'),
            ])
            ->filtersFormWidth('lg')
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('flow_type')
                    ->label('Tipo')
                    ->options(FlowType::class)
                    ->searchable(),
                SelectFilter::make('is_email')
                    ->label('Posta elettronica')
                    ->options([
                        'si' => 'Si',
                        'no' => 'No',
                    ])
                    ->placeholder('Entrambi')
                    ->query(function (Builder $query, array $data): Builder {
                        // Recuperiamo il valore. In Filament SelectFilter, il dato è in $data['value']
                        $value = $data['value'] ?? null;

                        // Se non è selezionato nulla, non applichiamo filtri alla query
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'si') {
                            // Mostra Registry relativo ad una comunicazione tramite posta elettronica
                            return $query->where('is_email', true);
                        }

                        if ($value === 'no') {
                            // Mostra Registry relativo ad una comunicazione tramite posta ordinaria
                            return $query->where('is_email', false);
                        }

                        return $query;
                    }),
                Filter::make('registration_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('registration_from_date')
                            ->label('Registrazione dal')
                            ->columnSpan(1),
                        DatePicker::make('registration_to_date')
                            ->label('Registrazione al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['registration_from_date'])) {
                            $query->whereDate('created_at', '>=', $data['registration_from_date']);
                        }
                        if (! empty($data['registration_to_date'])) {
                            $query->whereDate('created_at', '<=', $data['registration_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['registration_from_date'] && $data['registration_to_date']) {
                            return "Registrazione dal {$data['registration_from_date']} al {$data['registration_to_date']}";
                        }
                        if ($data['registration_from_date']) {
                            return "Registrazione dal {$data['registration_from_date']}";
                        }
                        if ($data['registration_to_date']) {
                            return "Registrazione al {$data['registration_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
                SelectFilter::make('registry_origin_type')
                    ->label('Origine')
                    ->options(RegistryOriginType::class)
                    ->searchable()
                    ->columnSpan(1),
                SelectFilter::make('register_user_id')
                    ->label('Registrato da')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->columnSpan(1),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RegistryReceiversRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistries::route('/'),
            'create' => Pages\CreateRegistry::route('/create'),
            'edit' => Pages\EditRegistry::route('/{record}/edit'),
            'view' => Pages\ViewRegistry::route('/{record}'),
        ];
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

    private static function labelRecipient($email): string
    {
        $rec = Recipient::where(function ($query) use ($email) {
            $query->where('mail_1', $email)
                ->orWhere('mail_2', $email)
                ->orWhere('mail_3', $email)
                ->orWhere('mail_4', $email)
                ->orWhere('mail_5', $email);
        })
        ->select('description', 'resp_surname', 'resp_name')
        ->first();

        if ($rec) {
            // return "{$rec->description} - {$rec->resp_surname} {$rec->resp_name} <{$email}>";
            return "{$rec->description} <{$email}>";
        }

        return $email;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('shipment');
    }
}
