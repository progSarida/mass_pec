<?php

namespace App\Filament\User\Resources;

use App\Enums\FlowType;
use App\Filament\User\Resources\ArchivedEmailResource\Pages;
// use App\Filament\User\Resources\ArchivedEmailResource\RelationManagers;
use App\Filament\User\Resources\ArchivedEmailResource\RelationManagers\ArchivedReceiversRelationManager;
use App\Models\ArchivedEmail;
use App\Models\Recipient;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ArchivedEmailResource extends Resource
{
    protected static ?string $model = ArchivedEmail::class;

    public static ?string $pluralModelLabel = 'Archivio email';
    protected static ?string $navigationIcon = 'heroicon-m-rectangle-stack';
    protected static ?string $navigationLabel = 'Archivio email';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
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
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        Select::make('flow_type')
                            ->label('Corrispondenza')
                            ->required()
                            ->live()
                            ->options(FlowType::class)
                            ->columnSpan(['sm' => 'full', 'md' => 4]),

                        TextInput::make('from')
                            ->label('Email mittente')
                            ->required()
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::RECEIVED->value)
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        Select::make('sender_id')
                            ->label('Mittente')
                            ->hintAction(
                                Action::make('Nuovo')
                                    ->icon('ri-user-2-line')
                                    ->modalSubmitActionLabel('Salva')
                                    ->form(fn(Form $form, Get $get) => RecipientResource::modalForm($form, $get('from')))
                                    ->fillForm(function (Get $get) {
                                        return [
                                            'email' => $get('from'),
                                        ];
                                    })
                                    ->modalWidth('7xl')
                                    ->modalHeading('')
                                    ->action(fn (array $data, Set $set) => ArchivedEmailResource::saveRecipient($data, $set))
                                    ->hidden(fn ($livewire, $record, Get $get) => $livewire instanceof \App\Filament\User\Resources\DownloadEmailResource\Pages\ViewDownloadEmail
                                                                                                    || $record?->sender_id
                                                                                                    || $get('sender_id'))
                            )
                            ->relationship(name: 'sender', titleAttribute: 'description')
                            ->required()
                            ->live()
                            ->searchable()
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::RECEIVED->value)
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        Select::make('account_id')
                            ->label('Mittente')
                            ->relationship(name: 'account', titleAttribute: 'public_name')
                            ->required()
                            ->searchable()
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::ISSUED->value)
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        Select::make('other_senders')
                            ->label('Altri mittenti')
                            ->multiple()
                            ->searchable()
                            ->disabled(fn ($record) => $record?->other_senders != null)
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::RECEIVED->value)
                            ->live()
                            ->placeholder('Seleziona altri mittenti')
                            ->columnSpan(['sm' => 'full', 'md' => 9])
                            ->getSearchResultsUsing(function (string $search) {
                                if (strlen($search) < 3) {
                                    return [];
                                }
                                // Divido la ricerca in parole
                                $words = array_filter(explode(' ', $search));
                                $query = Recipient::query();
                                // Filtro per parole chiave
                                if (!empty($words)) {
                                    $query->where(function ($q) use ($words) {
                                        foreach ($words as $word) {
                                            $q->where(function ($subQuery) use ($word) {
                                                $subQuery->where('description', 'like', "%{$word}%")
                                                    ->orWhere('resp_surname', 'like', "%{$word}%")
                                                    ->orWhere('resp_name', 'like', "%{$word}%");
                                            });
                                        }
                                    });
                                }
                                return $query
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        // Qui decidi cosa salvare come valore (es. l'id o l'email)
                                        // e cosa mostrare come testo
                                        return [$item->id => "{$item->description}"];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelsUsing(function ($values) {
                                // Quando il record è salvato, voglio vedere l'email nei tag
                                return collect($values)->mapWithKeys(fn ($id) => [$id => static::labelRecipient($id)])->toArray();
                            }),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 15]),

                        RichEditor::make('body')
                            ->label('Messaggio')
                            ->required()
                            ->default('') // Fondamentale per evitare l'errore "property not found"
                            ->columnSpanFull(),
                ]),

                DateTimePicker::make('send_date')
                    ->label('Inviato il')
                    ->visible(fn ($record) => $record?->send_date)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y')
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

                DateTimePicker::make('receive_date')
                    ->label('Ricevuto il')
                    ->visible(fn (Get $get, $record) => $record?->receive_date)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

                DatePicker::make('created_at')
                    ->label('Scaricato il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y')
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

                Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->relationship('downloadUser', 'name')
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

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
                                if (!$record || !$record?->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record?->attachment_path);
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('attachments.zip', [
                                'type' => $record?->getMorphClass(),
                                'id' => $record?->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record?->attachment_path) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                $files = Storage::files($record?->attachment_path);

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
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderByRaw('CASE
                    WHEN flow_type = "' . FlowType::ISSUED->value . '" THEN send_date
                    ELSE receive_date
                END DESC');
            })
            ->columns([
                IconColumn::make('flow_type')
                    ->label('')
                    ->tooltip(fn ($record) => $record->flow_type->getLabel())
                    ->toggleable(isToggledHiddenByDefault: false),

                // TextColumn::make('protocol_number')
                //     ->label('Protocollo')
                //     ->searchable()
                //     ->sortable(),

                TextColumn::make('sender_info') // Usa un nome descrittivo
                    ->label('Mittente')
                    ->state(function ($record): string {
                        // Usiamo state() invece di formatStateUsing se la colonna non esiste nel DB
                        if ($record->sender_id && $record->sender) {
                            return $record->sender->description ?? '';
                        }
                        if ($record->account_id && $record->account) {
                            return $record->account->public_name ?? '';
                        }
                        return 'Mittente non registrato';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('from', 'like', "%{$search}%")
                            ->orWhereHas('sender', fn ($q) => $q->where('description', 'like', "%{$search}%"))
                            ->orWhereHas('account', fn ($q) => $q->where('public_name', 'like', "%{$search}%"));
                        });
                    })
                    // ->sortable()
                    // 2. Attivio il badge solo se la relazione manca
                    ->badge(fn ($record) => !$record->sender && !$record->account)
                    // 3. Colore rosso solo per il badge "non registrato"
                    ->color(fn ($record) => (!$record->sender && !$record->account) ? 'danger' : null)
                    // ->tooltip(fn ($record) => $record?->from)
                    ->limit(250)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('receivers')
                    ->label('Destinatari')
                    ->state(fn ($record) => $record?->archivedReceivers?->count() ?? 0)
                    ->formatStateUsing(function ($state) {
                        if ($state === 0) return '';
                        return $state . ' ' . ($state === 1 ? 'destinatario' : 'destinatari');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('archivedReceivers', fn ($q) => $q->where('address', 'like', "%{$search}%"));
                        });
                    })
                    ->tooltip(function ($record) {
                        $receivers = $record?->archivedReceivers;
                        if (! $receivers || $receivers->isEmpty()) {
                            // return 'Nessun destinatario';
                            return '';
                        }
                        return static::getRecipientsName($receivers->pluck('address')) ?? implode(', ', $receivers->pluck('address')->toArray());
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date')
                    ->label('Invio/Ricezione')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            // Usiamo una logica SQL identica a quella del tuo PHP
                            DB::raw('CASE WHEN flow_type = "' . FlowType::ISSUED->value . '" THEN send_date ELSE receive_date END'),
                            $direction
                        );
                    })
                    ->state(function ($record) {
                        if($record->flow_type == FlowType::ISSUED) return $record->send_date;
                        else if($record->flow_type == FlowType::RECEIVED) return $record->receive_date;
                    })
                    ->date('d/m/Y h:m:s')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn ($record) => $record?->subject)
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('attachment_path')
                    ->label('Allegati')
                    ->icon(function($record) {
                        $files = Storage::files($record?->attachment_path);
                        if (!empty($files)) { return 'fluentui-mail-attach-20'; }
                        else { return ''; }
                    })
                    ->color(function ($record) {
                        $files = Storage::files($record?->attachment_path);
                        if (!empty($files)) { return 'info'; }
                        else { return ''; }
                    })->tooltip(function ($record) {
                        $files = Storage::files($record->attachment_path);
                        $count = count($files);

                        if ($count === 0) {
                            return 'Nessun allegato presente';
                        }

                        return $count === 1
                            ? "C'è 1 allegato"
                            : "Ci sono {$count} allegati";
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filtersFormWidth('lg')
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('sender')
                    ->label('Mittente')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array =>
                        Recipient::where('description', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('description', 'id')
                            ->toArray()
                    )
                    ->getOptionLabelsUsing(fn (array $values): array =>
                        Recipient::whereIn('id', $values)->pluck('description', 'id')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $senderIds = $data['values'] ?? [];

                        if (empty($senderIds)) {
                            return $query;
                        }

                        return $query->where(function ($q) use ($senderIds) {
                            // Ricerca sul mittente principale (molto veloce se sender_id ha un indice)
                            $q->whereIn('sender_id', $senderIds)
                            // Ricerca nel campo JSON
                            ->orWhere(function ($subQuery) use ($senderIds) {
                                foreach ($senderIds as $id) {
                                    // Usiamo orWhereJsonContains, ma limitato ai record necessari
                                    $subQuery->orWhereJsonContains('other_senders', (string)$id)
                                            ->orWhereJsonContains('other_senders', (int)$id);
                                }
                            });
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['values'])) return [];

                        $labels = Recipient::whereIn('id', $data['values'])
                            ->pluck('description')
                            ->toArray();

                        return ['Mittenti: ' . implode(', ', $labels)];
                    })
                    ->columnSpan(2),

                SelectFilter::make('recipient')
                    ->label('Destinatario')
                    ->multiple()
                    ->searchable()
                    // 1. NON usare ->options() qui se hai molti record.
                    // Usiamo getSearchResultsUsing per caricare solo i primi 50 che corrispondono alla ricerca.
                    ->getSearchResultsUsing(fn (string $search): array =>
                        Recipient::where('description', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('description', 'id')
                            ->toArray()
                    )
                    // 2. Serve a Filament per visualizzare il nome corretto dei tag selezionati
                    ->getOptionLabelsUsing(fn (array $values): array =>
                        Recipient::whereIn('id', $values)->pluck('description', 'id')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $recipientIds = $data['values'] ?? [];

                        if (empty($recipientIds)) {
                            return $query;
                        }

                        // 3. whereHas è corretto se hai una relazione Many-to-Many o One-to-Many
                        return $query->whereHas('registryReceivers', function ($q) use ($recipientIds) {
                            $q->whereIn('recipient_id', $recipientIds);
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['values'])) return [];

                        // 4. Ottimizzazione: ritorniamo un array per gli indicatori (Filament v3 style)
                        $recipients = Recipient::whereIn('id', $data['values'])
                            ->pluck('description')
                            ->toArray();

                        return ['Destinatari: ' . implode(', ', $recipients)];
                    })
                    ->columnSpan(2),

                SelectFilter::make('flow_type')
                    ->label('Corrispondenza')
                    ->placeholder('Tutta')
                    ->options(
                        collect(FlowType::cases())
                            ->filter(fn ($enum) => $enum->showArchive() === true)
                            ->mapWithKeys(fn ($enum) => [$enum->value => $enum->getLabel()])
                            ->toArray()
                    )
                    ->default(null),

                Filter::make('date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('from_date')
                            ->label('Data dal')
                            ->columnSpan(1),
                        DatePicker::make('to_date')
                            ->label('Data al')
                            ->columnSpan(1),
                    ])->query(function (Builder $query, array $data) {
                        // Usiamo un gruppo di clausole where per isolare la logica OR
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->where(function ($q) use ($date) {
                                    $q->where(function ($sub) use ($date) {
                                        $sub->where('flow_type', FlowType::ISSUED->value)
                                            ->whereDate('send_date', '>=', $date);
                                    })->orWhere(function ($sub) use ($date) {
                                        $sub->where('flow_type', FlowType::RECEIVED->value)
                                            ->whereDate('receive_date', '>=', $date);
                                    });
                                }),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->where(function ($q) use ($date) {
                                    $q->where(function ($sub) use ($date) {
                                        $sub->where('flow_type', FlowType::ISSUED->value)
                                            ->whereDate('send_date', '<=', $date);
                                    })->orWhere(function ($sub) use ($date) {
                                        $sub->where('flow_type', FlowType::RECEIVED->value)
                                            ->whereDate('receive_date', '<=', $date);
                                    });
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from_date'] && $data['to_date']) {
                            return "Data dal {$data['from_date']} al {$data['to_date']}";
                        }
                        if ($data['from_date']) {
                            return "Data dal {$data['from_date']}";
                        }
                        if ($data['to_date']) {
                            return "Data al {$data['to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            ArchivedReceiversRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchivedEmails::route('/'),
            'create' => Pages\CreateArchivedEmail::route('/create'),
            'view' => Pages\ViewArchivedEmail::route('/{record}'),
            'edit' => Pages\EditArchivedEmail::route('/{record}/edit'),
        ];
    }

    private static function getRecipientsName($addresses)
    {
        $output = null;
        $count = count($addresses);
        foreach($addresses as $key =>$address){
            $recipient = Recipient::findByEmail($address);
            if($recipient) {
                $output .= $recipient->description;
                if($key < $count - 1)
                    $output .= ", ";
            }
        }
        return $output;
    }

    public static function saveRecipient(array $data, Set $set): void
    {
        // 1. Estraiamo i dati del repeater
        $emails = $data['emails'] ?? [];
        unset($data['emails']);

        // 2. Controllo se un interlocutore ha già uno degl indirizzi del nuovo
        foreach ($emails as $email) {
            $rec = Recipient::findByEmail($email['email']);
            if ($rec) {
                Notification::make()
                    ->title("Indirizzo {$email['email']} presente in archivio")
                    ->body("L'indirizzo {$email['email']} è già associato a {$rec->description}")
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }

        // 3. Creiamo il record principale (modifica il percorso del Model se necessario)
        $recipient = Recipient::create($data);

        // 4. Salviamo le relazioni (il repeater)
        if (!empty($emails)) {
            $recipient->emails()->createMany($emails);
        }

        // 5. Selezioniamo automaticamente il nuovo record nel Select
        $set('sender_id', $recipient->id);

        // 6. Opzionale: Notifica di successo
        Notification::make()
            ->title('Interlocutore creato con successo')
            ->success()
            ->send();
    }
}
