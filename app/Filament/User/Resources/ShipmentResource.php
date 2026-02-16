<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Filament\User\Resources\ShipmentResource\Pages;
use App\Filament\User\Resources\ShipmentResource\RelationManagers;
use App\Filament\User\Resources\ShipmentResource\RelationManagers\ShipmentErrorsRelationManager;
use App\Models\Province;
use App\Models\Region;
use App\Models\Registry;
use App\Models\ScopeType;
use App\Models\Sender;
use App\Models\Shipment;
use App\Models\Signature;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;
    public static ?string $pluralModelLabel = 'Spedizioni';
    public static ?string $modelLabel = 'Spedizione';
    protected static ?string $navigationIcon = 'fluentui-send-20-o';
    protected static ?string $navigationLabel = 'Spedizioni';
    protected static ?string $navigationGroup = 'Pec Massiva';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $time = now()->format('Y-m-d_H-i-s');
        return $form
            ->columns(24)
            ->schema([
                TextInput::make('description')
                    ->required()
                    ->label('Descrizione (non visibile ai destinatari)')
                    ->columnSpan(['sm' => 'full', 'md' => 19]),
                Select::make('signature_id')->label('Firma')
                    ->required()
                    ->live()
                    ->visible(fn($record) => !$record)
                    ->options(Signature::pluck('description', 'id'))
                    ->afterStateUpdated(function(Set $set, Get $get, $state) {
                        $text = Signature::find($state)->text;
                        $msg = $get('mail_body');
                        $set('mail_body', $msg . '<br><br><br>' . $text);
                    })
                    ->dehydrated(false)
                    ->columnSpan(['sm' => 'full', 'md' => 5]),
                // TextInput::make('sender_name')
                //     ->label('PEC Mittente')
                //     ->disabled()
                //     ->default(function () {
                //         $sender = \App\Models\Sender::find(1);
                //         return $sender?->public_name ?? 'Mittente non trovato';
                //     })
                //     ->afterStateHydrated(function (TextInput $component, $record) {
                //         if ($record?->sender) {
                //             $component->state($record->sender->public_name);
                //             return;
                //         }
                //         $sender = \App\Models\Sender::find(1);
                //         $component->state($sender?->public_name ?? 'Mittente non trovato');
                //     })
                //     ->columnSpan(10),
                Placeholder::make('sender_name')
                    ->label('PEC Mittente')
                    ->content(function ($record) {
                        if ($record?->sender?->public_name) {
                            return $record->sender->public_name . " <" . $record->sender->address . ">";
                        }
                        $sender = \App\Models\Sender::find(1);
                        return $sender?->public_name ? $sender?->public_name . " <" . $sender?->address . ">" : 'Mittente non trovato';
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 10]),
                TextInput::make('mail_object')
                    ->label('Oggetto')
                    ->required()
                    ->columnSpan(['sm' => 'full', 'md' => 14]),
                // Textarea::make('mail_body')
                //     ->label('Messaggio')
                //     ->required()
                //     ->rows(6)
                //     ->columnSpan('full'),
                RichEditor::make('mail_body')
                    ->label('Messaggio')
                    ->required()
                    ->default('') // Fondamentale per evitare l'errore "property not found"
                    ->columnSpanFull(),
                TextInput::make('attachment')
                    ->label('Allegati')
                    // ->default('allegati_' . $time . '.zip')
                    ->disabled()
                    // ->dehydrated()
                    ->visible(fn($record): bool => $record && $record->attachment)
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_attachment_zip')
                        ->label('Scarica zip allegati')
                        ->icon('fluentui-drawer-arrow-download-20-o')
                        // ->url(fn($record): ?string => $record && $record->attachment ? Storage::url($record->shipment_path . '/' . $record->attachment) : null)
                        ->url(fn($record): ?string => $record->attachment ? Storage::temporaryUrl($record->shipment_path . '/' . $record->attachment,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => $record && $record->attachment)
                        ->color('primary'),
                ])->columnSpan(['sm' => 'full', 'md' => 5]),
                TextInput::make('extraction_zip_file')
                    ->label('Estrazione')
                    ->default('allegati_' . $time . '.zip')
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->extraction_zip_file)
                    ->columnSpan(['sm' => 'full', 'md' => 7]),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_extraction_zip')
                        ->label('Scarica zip estrazione')
                        ->icon('fluentui-drawer-arrow-download-20-o')
                        // ->url(fn($record): ?string => $record && $record->extraction_zip_file ? Storage::url($record->shipment_path . '/' . $record->extraction_zip_file) : null)
                        ->url(fn($record): ?string => $record->extraction_zip_file ? Storage::temporaryUrl($record->shipment_path . '/' . $record->extraction_zip_file,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => $record && $record->extraction_zip_file)
                        ->color('primary'),
                ])->columnSpan(['sm' => 'full', 'md' => 6]),
                Section::make('Resoconto mail')
                    ->visible(fn ($record) => $record)
                    ->collapsed()
                    ->columns(24)
                    ->schema([
                        TextInput::make('total_no_mails')
                            ->label('Totali')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_mails_to_send')
                            ->label('Da inviare')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_mails_sended')
                            ->label('Inviate')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_send_receipt')
                            ->label('Ricevute')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_missed_send_receipt')
                            ->label('Non ricevute')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_delivery_receipt')
                            ->label('Consegnate')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_missed_delivery_receipt')
                            ->label('Non consegnate')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('no_anomaly_receipt')
                            ->label('Anomalie')
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('insert_date', 'desc')
            ->columns([
                TextColumn::make('mail_type')
                    ->label('Tipo mail')
                    // ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region.name')
                    ->label('Regione')
                    ->sortable(),
                TextColumn::make('province.code')
                    ->label('Provincia')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description),
                TextColumn::make('insert_date')
                    ->label('Data inserimento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('send_date')
                    ->label('Data invio')
                    ->date('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sendUser.name')
                    ->label('Inviata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_no_mails')
                    ->label('Totale email')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('no_mails_to_send')
                    ->label('Da inviare')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('no_mails_sended')
                    ->label('Inviate')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('no_send_receipt')
                    ->label('Accettazioni')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('no_delivery_receipt')
                    ->label('Consegne')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('no_anomaly_receipt')
                    ->label('Anomalie')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filtersFormWidth('3xl')
            ->filtersFormColumns(12)
            ->filters([
                // SelectFilter::make('mail_type')
                //     ->label('Tipo')
                //     ->options(fn () => MailType::class)
                //     ->searchable(),
                SelectFilter::make('region_id')
                    ->label('Regione')
                    ->options(fn () => Region::pluck('name', 'id')->toArray())
                    // ->query(function (Builder $query, array $data) {
                    //     $value = $data['value'] ?? null;
                    //     if ($value) {
                    //         $query->whereHas('city.province.region', fn (Builder $q) =>
                    //             $q->where('id', $value)
                    //         );
                    //     }
                    // })
                    ->searchable()
                    ->columnSpan(4),
                SelectFilter::make('province_id')
                    ->label('Provincia')
                    ->options(fn () => Province::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->columnSpan(4),
                SelectFilter::make('send_user_id')
                    ->label('Inviato da')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->columnSpan(4),
                SelectFilter::make('mail_type')
                    ->label('Tipo')
                    ->options(
                        collect(MailType::cases())
                            ->filter(fn (MailType $type) => $type->show())
                            ->mapWithKeys(fn (MailType $type) => [
                                $type->value => $type->getLabel() // Forza il recupero della stringa
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->columnSpan(3),
                SelectFilter::make('sent')
                    ->label('Inviate')
                    ->options([
                        'si' => 'Si',
                        'no' => 'No',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->when($data['value'] === 'si', function ($q) {
                                return $q->where('no_mails_sended', '>', 0);
                            })->when($data['value'] === 'no', function ($q) {
                                return $q->where('no_mails_sended', '=', 0);
                            });
                    })
                    ->preload()
                    ->columnSpan(2),
                SelectFilter::make('delivered')
                    ->label('Tutte consegnate')
                    ->options([
                        'si' => 'Si',
                        'no' => 'No',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->when($data['value'] === 'si', function ($q) {
                                return $q->where('no_mails_sended', '>', 0)
                                            ->whereRaw('no_mails_sended = no_delivery_receipt');
                            })->when($data['value'] === 'no', function ($q) {
                                return $q->where('no_mails_sended', '>', 0)
                                            ->whereRaw('no_mails_sended > no_delivery_receipt');;
                            });
                    })
                    ->preload()
                    ->columnSpan(3),
                SelectFilter::make('is_registered')
                    ->label('Stato Protocollo')
                    ->options([
                        'si' => 'Protocollate',
                        'no' => 'Non protocollate',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Recuperiamo il valore. In Filament SelectFilter, il dato è in $data['value']
                        $value = $data['value'] ?? null;

                        // Se non è selezionato nulla, non applichiamo filtri alla query
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'si') {
                            // Mostra Shipment che hanno un record collegato in Registry
                            return $query->whereHas('registry');
                        }

                        if ($value === 'no') {
                            // Mostra Shipment che NON hanno un record collegato in Registry
                            return $query->whereDoesntHave('registry');
                        }

                        return $query;
                    })
                    ->columnSpan(4),
                Filter::make('insert_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('insert_from_date')
                            ->label('Inserimento dal')
                            ->columnSpan(1),
                        DatePicker::make('insert_to_date')
                            ->label('Inserimento al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['insert_from_date'])) {
                            $query->whereDate('insert_date', '>=', $data['insert_from_date']);
                        }
                        if (! empty($data['insert_to_date'])) {
                            $query->whereDate('insert_date', '<=', $data['insert_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['insert_from_date'] && $data['insert_to_date']) {
                            return "Inserimento dal {$data['insert_from_date']} al {$data['insert_to_date']}";
                        }
                        if ($data['insert_from_date']) {
                            return "Inserimento dal {$data['insert_from_date']}";
                        }
                        if ($data['insert_to_date']) {
                            return "Inserimento al {$data['insert_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(6),
                Filter::make('send_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('send_from_date')
                            ->label('Invio dal')
                            ->columnSpan(1),
                        DatePicker::make('send_to_date')
                            ->label('Invio al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['send_from_date'])) {
                            $query->whereDate('send_date', '>=', $data['send_from_date']);
                        }
                        if (! empty($data['send_to_date'])) {
                            $query->whereDate('send_date', '<=', $data['send_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['send_from_date'] && $data['send_to_date']) {
                            return "Invio dal {$data['send_from_date']} al {$data['send_to_date']}";
                        }
                        if ($data['send_from_date']) {
                            return "Invio dal {$data['send_from_date']}";
                        }
                        if ($data['send_to_date']) {
                            return "Invio al {$data['send_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(6),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn($record) => $record->no_mails_sended > 0),
                Tables\Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                                                && $record->extraction_zip_file
                                                && !Registry::where('uid', '#shipment' . $record->id)->exists()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla spedizione')
                    ->modalDescription('La spedizione verrà inserita nel protocollo')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Ambito')
                            ->options(ScopeType::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona l\'ambito della registrazione')
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            static::registerShipment($record, $data['scope_type_id']);
                            Notification::make()
                                ->title('Mail protocollata')
                                ->body('La spedizione e i suoi allegati sono stati protocollati con successo.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore registrazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('registered')
                    ->label('Protocollata')
                    ->icon('heroicon-o-information-circle')
                    ->color('success')
                    ->tooltip('Spedizione già inserita nel protocollo.')
                    ->visible(fn($record) => Registry::where('uid', '#shipment' . $record->id)->exists())
                    ->action(fn () => null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ShipmentErrorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
            'view' => Pages\ViewShipment::route('/{record}')
        ];
    }

    private static function registerShipment($record, $scopeTypeId)
    {
        try {
            DB::beginTransaction();

            $oldPath = $record->shipment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'issued',
                'flow_index' => static::newIndex('issued'),
                'registry_origin_type' => 'shipment',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#shipment' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'from' => $record->sender->public_name,
                'subject' => $record->mail_object,
                'body' => $record->mail_body,
                'receive_date' => null,
                'account_id' => null,
                'send_date' => $record->send_date,
                'send_user_id' => $record->send_user_id,
                'shipment_id' => $record->id,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la spedizione
            // Model::withoutEvents(function () use ($record) {
            //     $record->delete();
            // });

            $disk = config('filesystems.default');

            // copio cartella allegati
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->makeDirectory($newPath);

                $files = collect(Storage::disk($disk)->files($oldPath))
                    ->filter(function ($path) {
                        return Str::contains($path, 'estrazione');
                    })
                    ->all();

                foreach ($files as $file) {
                    $relativePath = str_replace($oldPath . '/', '', $file);
                    $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $relativePath;
// dd('oldPath: ' . $oldPath . ' - ' . 'relativePath: ' . $relativePath . ' - ' . 'newFilePath: ' . $newFilePath);
                    $directory = dirname($newFilePath);
                    if (!Storage::disk($disk)->exists($directory)) {
                        Storage::disk($disk)->makeDirectory($directory);
                    }

                    Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                }
            }

            // Elimino la vecchia cartella della spedizione
            // if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
            //     Storage::disk($disk)->deleteDirectory($oldPath);
            // }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
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

    private static function newIndex($flow_type): int
    {
        $lastIndex = Registry::where('flow_type', $flow_type)->max('flow_index');
        if ($lastIndex) {
            $newIndex = $lastIndex+1;
            return $newIndex;
        }
        return 1;
    }
}
