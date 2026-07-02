<?php

namespace App\Filament\User\Resources;

use App\Enums\FlowType;
use App\Filament\User\Resources\ManualInsertResource\Pages;
// use App\Filament\User\Resources\ManualInsertResource\RelationManagers;
use App\Models\ManualInsert;
use App\Models\Recipient;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
// use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManualInsertResource extends Resource
{
    protected static ?string $model = ManualInsert::class;
    public static ?string $pluralModelLabel = 'Inserimento manuale';
    protected static ?string $navigationIcon = 'fluentui-book-add-20';
    protected static ?string $navigationLabel = 'Inserimento manuale';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        $waiting = static::getModel()::count();

        return $waiting > 0
            ? "Inserimento manuale ({$waiting})"
            : 'Inserimento manuale';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(15)
            ->schema([
                Select::make('flow_type')
                    ->label('Corrispondenza')
                    ->required()
                    ->live()
                    // ->disabled(fn ($record) => $record?->isIngoingEmail())
                    ->options(FlowType::class)
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                DatePicker::make('receive_date')
                    ->label('Ricevuto il')
                    ->required()
                    ->visible(fn(Get $get) => $get('flow_type') === FlowType::RECEIVED->value)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                DatePicker::make('send_date')
                    ->label('Inviato il')
                    ->required()
                    ->visible(fn(Get $get) => $get('flow_type') === FlowType::ISSUED->value)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                DatePicker::make('internal_date')
                    ->label('Comunicato il')
                    ->required()
                    ->visible(fn(Get $get) => $get('flow_type') === FlowType::INTERNAL->value)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Placeholder::make('first_row_placeholder')
                    ->label('')
                    ->columnSpan(['sm' => 'full', 'md' => 9]),

                // Select::make('scope_type_id')
                //     ->label('Settore interno')
                //     ->required()
                //     // ->disabled(fn ($record) => $record?->isIngoingEmail())
                //     ->relationship('scopeType', 'name')
                //     ->relationship(
                //         name: 'scopeType',
                //         titleAttribute: 'name',
                //         modifyQueryUsing: fn ($query) => $query->orderBy('position', 'asc')
                //     )
                //     ->columnSpan(['sm' => 'full', 'md' => 5]),

                // Select::make('receivers')
                //     ->label('Destinatari')
                //     ->multiple()
                //     ->searchable()
                //     ->visible(fn(Get $get) => $get('flow_type') === FlowType::ISSUED->value)
                //     ->required()
                //     ->live()
                //     ->placeholder('Inizia a scrivere per cercare un\'email o una descrizione...')
                //     ->columnSpanFull()
                //     ->getSearchResultsUsing(function (string $search) {
                //         if (strlen($search) < 3) {
                //             return [];
                //         }

                //         $words = array_filter(explode(' ', $search));

                //         $query = Recipient::query();

                //         if (!empty($words)) {
                //             $query->where(function ($q) use ($words) {
                //                 foreach ($words as $word) {
                //                     $q->where(function ($subQuery) use ($word) {
                //                         $subQuery->where('description', 'like', "%{$word}%")
                //                             ->orWhere('resp_surname', 'like', "%{$word}%")
                //                             ->orWhere('resp_name', 'like', "%{$word}%");
                //                     });
                //                 }
                //             });
                //         }

                //         return $query
                //             ->limit(50)
                //             ->get()
                //             ->mapWithKeys(fn ($recipient) => [
                //                 $recipient->id => "{$recipient->description}"
                //             ])
                //             ->toArray();
                //     })
                //     ->getOptionLabelsUsing(function ($values) {
                //         return collect($values)->mapWithKeys(fn ($id) => [
                //             $id => static::labelRecipient($id)
                //         ])->toArray();
                //     })
                //     ->createOptionUsing(fn (string $data) => $data),

                Forms\Components\Select::make('receivers')
                    ->label('Destinatari')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->visible(fn(Get $get) => $get('flow_type') === FlowType::ISSUED->value)
                    ->live()
                    ->placeholder('Inizia a scrivere per cercare un\'email o una descrizione...')
                    ->columnSpan(['sm' => 'full', 'md' => 'full'])
                    ->getSearchResultsUsing(function (string $search, callable $get) {
                        if (strlen($search) < 3) {
                            return [];
                        }

                        // Ottengo i valori dei filtri
                        $mailType = $get('mail_type');
                        $officeTypeId = $get('office_type_id');

                        // Divido la ricerca in parole
                        $words = array_filter(explode(' ', $search));

                        $query = Recipient::query();

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
                            ->with(['emails' => function($q) use ($mailType, $officeTypeId) {
                                if ($mailType) {
                                    $q->where('mail_type', $mailType);
                                }
                                if ($officeTypeId) {
                                    $q->where('office_type_id', $officeTypeId);
                                }
                            }])
                            ->limit(50)
                            ->get()
                            ->flatMap(function ($recipient) {
                                $out = [];

                                foreach ($recipient->emails as $email) {
                                    $label = "{$recipient->description} - <{$email->email}>";
                                    $out[$email->email] = $label;
                                }

                                return $out;
                            })
                            ->toArray();
                    })
                    ->getOptionLabelsUsing(function ($values) {
                        // Quando il record è salvato, voglio vedere l'email nei tag
                        return collect($values)->mapWithKeys(fn ($email) => [$email => static::labelRecipientFromEmail($email)])->toArray();
                    })
                    ->createOptionUsing(function (string $data) {
                        // Se l'utente scrive un'email a mano, il valore salvato sarà il testo inserito
                        return $data;
                    }),

                Select::make('senders')   // ← cambiato da 'recipients' (era già senders, ma assicurati)
                    ->label('Mittenti')
                    ->multiple()
                    ->searchable()
                    ->visible(fn(Get $get) => $get('flow_type') === FlowType::RECEIVED->value)
                    ->required()
                    ->live()
                    ->placeholder('Inizia a scrivere per cercare un\'email o una descrizione...')
                    ->columnSpanFull()
                    ->getSearchResultsUsing(function (string $search) {   // stessa logica di sopra
                        if (strlen($search) < 3) {
                            return [];
                        }

                        $words = array_filter(explode(' ', $search));

                        $query = Recipient::query();

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
                            ->mapWithKeys(fn ($recipient) => [
                                $recipient->id => "{$recipient->description}"
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelsUsing(function ($values) {
                        return collect($values)->mapWithKeys(fn ($id) => [
                            $id => static::labelRecipient($id)
                        ])->toArray();
                    })
                    ->createOptionUsing(fn (string $data) => $data),

                Select::make('interested_parties')
                    ->label('Altre parti interessate')
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->placeholder('Seleziona altre parti interessate')
                    ->columnSpan(['sm' => 'full', 'md' => 'full'])
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
                    ->columnSpanFull(),

                RichEditor::make('body')
                    ->label('Messaggio')
                    ->default('')
                    ->formatStateUsing(fn ($record, $state) => $record->eml_body ?? ($state ?? ''))
                    ->columnSpanFull(),

                Placeholder::make('create_row_placeholder')
                    ->label('')
                    ->columnSpan(['sm' => 'full', 'md' => 9]),

                DateTimePicker::make('created_at')
                    ->label('Creato il')
                    ->disabled()
                    ->visible(fn($record) => $record?->created_at)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Select::make('create_user_id')
                    ->label('Creato da')
                    ->disabled()
                    ->visible(fn($record) => $record?->createUser)
                    ->relationship('createUser', 'name')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                FileUpload::make('attachments')
                    ->label('Carica allegati')
                    ->multiple()
                    ->directory('manual_insert/0')
                    ->preserveFilenames()
                    ->visible(fn($record) => !$record)
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        $disk = config('filesystems.default');
                        $directory = 'manual_insert/0';
                        // creo cartella temporanea se non esiste
                        if (!Storage::disk($disk)->exists('manual_insert/0')) {
                            Storage::disk($disk)->makeDirectory('manual_insert/0');
                        }
                        // Estraiamo nome e estensione originali
                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension = $file->getClientOriginalExtension();

                        $finalName = $filename . '.' . $extension;
                        $counter = 1;

                        // Finché esiste un file con questo nome, incrementiamo il suffisso
                        while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                            $finalName = $filename . '_' . $counter . '.' . $extension;
                            $counter++;
                        }

                        return $finalName;
                    })
                    ->columnSpanFull(),

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

                Section::make('Documenti integrativi')
                    ->collapsed(fn($record) => $record)
                    ->visible(function ($record) {
                        $files = Storage::files($record?->attachment_path . '/related');
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
                                if (!$record || !$record?->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record?->attachment_path . '/related');
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('related.zip', [
                                'id' => $record?->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('related')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record?->attachment_path) {
                                    return 'Nessuna cartella documenti integrativi trovata.';
                                }

                                $files = Storage::files($record?->attachment_path . '/related');

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
            ->columns([
                Tables\Columns\IconColumn::make('flow_type')
                    ->label('')
                    ->tooltip(fn ($record) => $record->flow_type?->getLabel())
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Oggetto')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receivers')
                    ->label('Destinatari')
                    ->state(function ($record) {
                        $count = is_array($record->receivers) ? count($record->receivers) : 0;
                        if ($count === 0) return '';
                        return $count . ' ' . ($count === 1 ? 'destinatario' : 'destinatari');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $userIds = Recipient::where('description', 'like', "%{$search}%")
                            ->pluck('id')
                            ->toArray();

                        return $query->where(function ($q) use ($userIds) {
                            foreach ($userIds as $id) {
                                $q->orWhereJsonContains('receivers', $id);
                            }
                        });
                    })
                    // Mostra i NOMI reali nel tooltip al passaggio del mouse
                    ->tooltip(function ($record) {
                        if (!is_array($record->receivers) || empty($record->receivers)) return '';
                        return Recipient::whereIn('id', $record->receivers)->pluck('description')->implode(', ');
                    }),

                Tables\Columns\TextColumn::make('senders')
                    ->label('Mittenti')
                    ->state(function ($record) {
                        $count = is_array($record->senders) ? count($record->senders) : 0;
                        if ($count === 0) return '';
                        return $count . ' ' . ($count === 1 ? 'mittente' : 'mittenti');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $userIds = Recipient::where('description', 'like', "%{$search}%")->pluck('id')->toArray();
                        return $query->where(function ($q) use ($userIds) {
                            foreach ($userIds as $id) {
                                $q->orWhereJsonContains('senders', $id);
                            }
                        });
                    })
                    ->tooltip(function ($record) {
                        if (!is_array($record->senders) || empty($record->senders)) return '';
                        // return Recipient::whereIn('id', $record->senders)->pluck('description')->implode(', ');
                        return Recipient::with('adminType')
                            ->whereIn('id', $record->senders)
                            ->get()
                            ->map(function ($recipient) {
                                // Verifichiamo se la relazione esiste per evitare errori sul log
                                $typeName = $recipient->adminType?->name; 
                                
                                if ($typeName) {
                                    return "{$recipient->description} ({$typeName})";
                                }
                                
                                return $recipient->description;
                            })
                            ->implode(', ');
                    }),

                Tables\Columns\TextColumn::make('interested_parties')
                    ->label('Parti Interessate')
                    ->state(function ($record) {
                        $count = is_array($record->interested_parties) ? count($record->interested_parties) : 0;
                        if ($count === 0) return '';
                        return $count . ' ' . ($count === 1 ? 'parte' : 'parti');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $userIds = Recipient::where('description', 'like', "%{$search}%")->pluck('id')->toArray();
                        return $query->where(function ($q) use ($userIds) {
                            foreach ($userIds as $id) {
                                $q->orWhereJsonContains('interested_parties', $id);
                            }
                        });
                    })
                    ->tooltip(function ($record) {
                        if (!is_array($record->interested_parties) || empty($record->interested_parties)) return '';
                        // return Recipient::whereIn('id', $record->interested_parties)->pluck('description')->implode(', ');
                        return Recipient::with('adminType')
                            ->whereIn('id', $record->interested_parties)
                            ->get()
                            ->map(function ($recipient) {
                                $typeName = $recipient->adminType?->name;

                                // Se il tipo esiste, lo accodiamo tra parentesi
                                return $typeName 
                                    ? "{$recipient->description} ({$typeName})" 
                                    : $recipient->description;
                            })
                            ->implode(', ');
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManualInserts::route('/'),
            'create' => Pages\CreateManualInsert::route('/create'),
            'view' => Pages\ViewManualInsert::route('/{record}'),
            'edit' => Pages\EditManualInsert::route('/{record}/edit'),
        ];
    }

    private static function labelRecipient($id): string
    {
        $rec = Recipient::find($id);

        if ($rec) {
            // return "{$rec->description} <{$email}>";
            return "{$rec->description}";
        }

        return $id;
    }

    private static function labelRecipientFromEmail($email): string
    {
        $rec = Recipient::whereHas('emails', function($query) use ($email) {
            $query->where('email', $email);
        })
        ->select('description', 'resp_surname', 'resp_name')
        ->first();

        if ($rec) {
            // return "{$rec->description} <{$email}>";
            return "{$rec->description}";
        }

        return $email;
    }
}
