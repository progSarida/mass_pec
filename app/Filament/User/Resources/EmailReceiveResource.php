<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Enums\ManageEmailType;
use App\Filament\User\Resources\EmailReceiveResource\Pages;
use App\Models\Account;
use App\Models\Email;
use App\Models\Recipient;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EmailReceiveResource extends Resource
{
    protected static ?string $model = Email::class;

    public static ?string $pluralModelLabel = 'Gestione posta in arrivo';
    protected static ?string $navigationIcon = 'fluentui-mail-inbox-arrow-down-20-o';
    protected static ?string $navigationLabel = 'Gestione posta in arrivo';
    protected static ?string $navigationGroup = 'Email';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(20)
            ->schema([
                Section::make('Informazioni Principali')
                    ->columns(12)
                    ->schema([
                        Placeholder::make('')
                            ->content(fn ($record): string => Account::where('mail_type', MailType::MAIL)->where('address', $record->receiving_mail)->first()?->public_name ?? '')
                            ->extraAttributes(function ($record) {
                                $baseClasses = 'text-lg font-semibold border pb-1 pt-2';

                                $customClasses = [
                                    'rounded-lg',           // Arrotondamento angoli
                                    'text-center',          // Testo centrato
                                    "bg-gray-100",          // Colore di sfondo dinamico
                                    'text-gray-900',        // Assicura che il testo sia leggibile su sfondi chiari
                                ];

                                return [
                                    'class' => $baseClasses . ' ' . implode(' ', $customClasses),
                                ];
                            })
                            ->columnSpan(['sm' => 'full', 'md' => 'full']),

                        TextInput::make('from')
                            ->label('Email mittente')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        Select::make('sender_id')
                            ->label('Mittente')
                            ->hintAction(
                                Action::make('Nuovo')
                                    ->icon('ri-user-2-line')
                                    // ->form(fn(Form $form) => RecipientResource::modalForm($form))
                                    ->modalSubmitActionLabel('Salva')
                                    ->form(fn(Form $form, Get $get) => RecipientResource::modalForm($form, $get('from')))
                                    ->fillForm(function (Get $get) {
                                        return [
                                            'email' => $get('from'),
                                        ];
                                    })
                                    ->modalWidth('7xl')
                                    ->modalHeading('')
                                    ->action(fn (array $data, Set $set) => EmailReceiveResource::saveRecipient($data, $set))
                                    // ->hidden(fn ($record) => $record->sender_id)
                                    ->hidden(fn ($livewire, $record, Get $get) => $livewire instanceof \App\Filament\User\Resources\DownloadEmailResource\Pages\ViewDownloadEmail
                                                                                                    || $record?->sender_id
                                                                                                    || $get('sender_id'))
                            )
                            ->live()
                            ->searchable()
                            ->relationship(name: 'sender', titleAttribute: 'description')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->required()
                            ->relationship('scopeType', 'name')
                            ->columnSpan(['sm' => 'full', 'md' => 4]),

                        Textarea::make('body')
                            ->label('Messaggio')
                            ->rows(10)
                            ->columnSpan('full')
                            ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),
                    ]),

                DateTimePicker::make('receive_date')
                    ->label('Ricevuto il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                DatePicker::make('created_at')
                    ->label('Scaricato il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->relationship('downloadUser', 'name')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                Select::make('manage_email_type')
                    ->label('Gestione')
                    ->options(ManageEmailType::class)
                    ->afterStateUpdated(function(Set $set) {
                        //
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                DateTimePicker::make('manage_email_date')
                    ->label('Gestito il')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                Section::make('Allegati')
                    ->collapsed(fn($record) => $record)
                    ->visible(fn($record) => $record)
                    ->headerActions([
                        Action::make('downloadAll')
                            ->label('Scarica tutto (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            // Il pulsante appare solo se c'è più di un file
                            ->visible(function ($record) {
                                if (!$record || !$record->attachment_path) return false;
                                return count(Storage::files($record->attachment_path)) > 0;
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
            ->poll('20s')
            ->query(Email::received())
            ->columns([
                TextColumn::make('receiving_mail')
                    ->label('Account')
                    ->state(function ($record) {
                        return Account::where('mail_type', MailType::PEC)->where('address', $record->receiving_mail)->first()?->public_name;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sender_id') // Puntiamo a una colonna che esiste sempre sul modello DownloadEmail
                    ->label('Mittente')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('from', 'like', "%{$search}%")
                                ->orWhereHas('sender', fn ($q) => $q->where('description', 'like', "%{$search}%"));
                        });
                    })
                    // 1. Definisco cosa mostrare
                    ->state(function ($record) {
                        return $record->sender?->description ?? 'Mittente non registrato';
                    })
                    // 2. Attivio il badge solo se la relazione manca
                    ->badge(fn ($record) => ! $record->sender)
                    // 3. Colore rosso solo per il badge "non registrato"
                    ->color(fn ($record) => ! $record->sender ? 'danger' : null)
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->from),

                TextColumn::make('from')
                    ->label('indirizzo mittente')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->subject),

                TextColumn::make('body')
                    ->label('Messaggio')
                    ->limit(80)
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? Str::limit(strip_tags($state), 50) : '—')
                    ->tooltip(function ($record) {
                        if (!$record->body) return 'Nessun contenuto';
                        $preview = strip_tags($record->body);
                        return Str::limit($preview, 500);
                    }),

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
                    }),

                TextColumn::make('receive_date')
                    ->label('Ricevuto il')
                    ->date('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Scaricato il')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('downloadUser.name')
                    ->label('Scaricato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                            $q->whereIn('sender_id', $senderIds);
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
                Filter::make('receive_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('receive_from_date')
                            ->label('Ricezione dal')
                            ->columnSpan(1),
                        DatePicker::make('receive_to_date')
                            ->label('Ricezione al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['receive_from_date'])) {
                            $query->whereDate('receive_date', '>=', $data['receive_from_date']);
                        }
                        if (! empty($data['receive_to_date'])) {
                            $query->whereDate('receive_date', '<=', $data['receive_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['receive_from_date'] && $data['receive_to_date']) {
                            return "Ricezione dal {$data['receive_from_date']} al {$data['receive_to_date']}";
                        }
                        if ($data['receive_from_date']) {
                            return "Ricezione dal {$data['receive_from_date']}";
                        }
                        if ($data['receive_to_date']) {
                            return "Ricezione al {$data['receive_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
                SelectFilter::make('receiving_mail')
                    ->label('Account')
                    ->preload()
                    ->options(fn () => Account::where('mail_type', MailType::MAIL)->orderBy('position', 'asc')->pluck('public_name', 'address')),
                SelectFilter::make('missing_sender')
                    ->label('Mittenti mancanti')
                    ->placeholder('Includi')
                    ->options([
                        'select' => 'Seleziona',
                        'exclude' => 'Escludi',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        // Se non è selezionato nulla, non applico filtri alla query
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'select') {
                            // Mostro solo le mail senza mittente associato
                            return $query->whereNull('sender_id');
                        }

                        if ($value === 'exclude') {
                            // Escludo le mail senza mittente associato
                            return $query->whereNotNull('sender_id');
                        }

                        return $query;
                    }),
            ])
            ->emptyStateHeading(fn () => session('email_receives')
                ? 'Nessuna mail scaricata' 
                : ''
            )
            ->emptyStateDescription(fn () => session('email_receives')
                ? 'Non sono state trovate nuove email nelle caselle' 
                : ''
            )
            ->emptyStateIcon(fn () => session('email_receives') 
                ? 'fluentui-mail-dismiss-20-o' 
                : null
            )
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('list')
                        ->label('Stampa selezionate')
                        // ->icon('heroicon-m-arrow-down-tray')
                        ->icon('heroicon-o-printer')
                        ->color(Color::rgb('rgb(255, 0, 0)'))
                        ->openUrlInNewTab()
                        // ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $fileName = 'Email_' . Carbon::today()->format('d-m-Y') . '.pdf';
                            return response()
                                ->streamDownload(function () use ($records) {
                                    $pdf = Pdf::loadHTML(
                                        Blade::render('print.email_receives', [
                                            'emails' => $records,
                                        ])
                                    )
                                    ->setPaper('A4', 'landscape')
                                    ->setOptions([
                                        'isHtml5ParserEnabled' => true, // Abilita parser HTML5 per CSS avanzato
                                        'isPhpEnabled' => true, // Abilita PHP nel template
                                        'isFontSubsettingEnabled' => true, // Ottimizza i font
                                    ]);

                                    echo $pdf->stream();
                                }, $fileName);
                        }),
                ])
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
            'index' => Pages\ListEmailReceives::route('/'),
            'create' => Pages\CreateEmailReceive::route('/create'),
            'view' => Pages\ViewEmailReceive::route('/{record}'),
            'edit' => Pages\EditEmailReceive::route('/{record}/edit'),
        ];
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
