<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Enums\ManageEmailType;
use App\Filament\User\Resources\EmailSendResource\Pages;
use App\Models\Account;
use App\Models\Email;
use App\Models\OfficeType;
use App\Models\Recipient;
use App\Models\Signature;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EmailSendResource extends Resource
{
    protected static ?string $model = Email::class;

    public static ?string $pluralModelLabel = 'Gestione posta in uscita';
    protected static ?string $navigationIcon = 'fluentui-mail-inbox-arrow-up-20-o';
    protected static ?string $navigationLabel = 'Gestione posta in uscita';
    protected static ?string $navigationGroup = 'Email';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(24)
            ->schema([
                Forms\Components\Select::make('account_id')
                    ->label('Account')
                    ->required()
                    // ->extraInputAttributes(['class' => 'text-center'])
                    ->relationship(
                        name: 'account',
                        titleAttribute: 'public_name',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('mail_type', MailType::MAIL)
                            ->where('send', true)
                            ->whereHas('users', fn ($q) => $q->where('users.id', Auth::user()->id))
                    )
                    ->afterStateUpdated(function ($state, Set $set){
                        $account = Account::find($state);
                        $set('mail_type', $account->mail_type);
                    })
                    ->live()
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 7]),

                Forms\Components\TextInput::make('subject')
                    ->label('Oggetto')
                    ->required()
                    ->columnSpan(['sm' => 'full', 'md' => 12]),

                Select::make('signature_id')->label('Firma')
                    ->live()
                    ->options(Signature::pluck('description', 'id'))
                    ->afterStateUpdated(function(Set $set, Get $get, $state) {
                        $text = Signature::find($state)->text;
                        $msg = $get('body');
                        $set('body', $msg . '<br><br><br>' . $text);
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

                Forms\Components\Select::make('mail_type')
                    ->label('Tipo mail')
                    ->options(
                        collect(MailType::cases())
                            ->filter(fn (MailType $type) => $type->show())
                            ->mapWithKeys(fn (MailType $type) => [
                                $type->value => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    ->live()
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 6]),

                Forms\Components\Select::make('office_type_id')
                    ->label('Tipo ufficio')
                    // ->required()
                    // ->visible(fn($record, Get $get) => !$record || !$get('recipients'))
                    ->options(fn () => OfficeType::orderBy('position')->pluck('name', 'id')->toArray())
                    ->live()
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 10]),

                Select::make('scope_type_id')
                    ->label('Settore interno')
                    ->required()
                    ->relationship('scopeType', 'name')
                    ->columnSpan(['sm' => 'full', 'md' => 8]),

                Forms\Components\Select::make('recipients')
                    ->label('Destinatari')
                    ->multiple()
                    ->searchable()
                    ->required()
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
                        return collect($values)->mapWithKeys(fn ($email) => [$email => static::labelRecipient($email)])->toArray();
                    })
                    ->createOptionUsing(function (string $data) {
                        // Se l'utente scrive un'email a mano, il valore salvato sarà il testo inserito
                        return $data;
                    }),

                Forms\Components\RichEditor::make('body')
                    ->label('Messaggio')
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\DatePicker::make('created_at')
                    ->label('Data creazione')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->visible(fn($state) => $state)
                    ->columnSpan(['sm' => 'full', 'md' => 4]),
                Forms\Components\Select::make('create_user_id')
                    ->label('Creata da')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->relationship('createUser', 'name')
                    ->visible(fn($state) => $state)
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                DateTimePicker::make('send_date')
                    ->label('Inviato il')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->visible(fn($record) => $record?->send_date)
                    ->displayFormat('d/m/Y')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                Forms\Components\Select::make('send_user_id')
                    ->label('Inviato da')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->visible(fn($record) => $record?->send_user_id)
                    ->relationship('downloadUser', 'name')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                Placeholder::make('blank')
                    ->label('')
                    ->visible(fn($record) => !$record?->send_date && !$record?->send_user_id)
                    ->columnSpan(['sm' => 'full', 'md' => 8]),

                Select::make('manage_email_type')
                    ->label('Gestione')
                    ->options(ManageEmailType::class)
                    ->visible(fn($record) => $record)
                    ->afterStateUpdated(function(Set $set) {
                        //
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                DatePicker::make('manage_email_date')
                    ->label('Gestito il')
                    ->visible(fn($record) => $record)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),

                FileUpload::make('attachments')
                    ->label('Carica allegati')
                    ->multiple()
                    ->directory('email_send/0')
                    ->preserveFilenames()
                    ->visible(fn($record) => !$record)
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        $disk = config('filesystems.default');
                        $directory = 'email_send/0';
                        // creo cartella temporanea se non esiste
                        if (!Storage::disk($disk)->exists('email_send/0')) {
                            Storage::disk($disk)->makeDirectory('email_send/0');
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
                                if (!$record || !$record->attachment_path) return false;
                                // Mostra il tasto solo se ci sono 2 o più file
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
            ->query(Email::sent())
            ->columns([
                Tables\Columns\TextColumn::make('account.public_name')
                    ->label('Mittente'),
                Tables\Columns\TextColumn::make('recipients')
                    ->label('Destinatari')
                    ->formatStateUsing(function ($state) {
                        if (blank($state)) return '0 destinatari';
                        // Conta gli elementi nell'array recipients
                        $emails = explode(',', $state);
                        $count = is_array($emails) ? count($emails) : 0;
                        return $count . ' ' . ($count === 1 ? 'destinatario' : 'destinatari');
                    })
                    ->tooltip(function ($state) {
                        // Se lo stato non è un array o è vuoto, restituisci null
                        if (!is_array($state) || empty($state)) {
                            return null;
                        }
                        // Unisce i nomi con una virgola e uno spazio per il tooltip
                        return implode(', ', $state);
                    }),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Oggetto')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->subject),
                Tables\Columns\TextColumn::make('create_date')
                    ->label('Data creazione')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('createUser.name')
                    ->label('Creato da')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('send_date')
                //     ->label('Data invio')
                //     ->date('d/m/Y H:i:s')
                //     ->sortable(),
                Tables\Columns\TextColumn::make('sendUser.name')
                    ->label('Inviata da')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersFormWidth('lg')
            ->filtersFormColumns(2)
            ->filters([
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
                    ->columnSpan(2),
                SelectFilter::make('receiving_mail')
                    ->label('Account')
                    ->preload()
                    ->options(fn () => Account::where('mail_type', MailType::MAIL)->pluck('public_name', 'address')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailSends::route('/'),
            'create' => Pages\CreateEmailSend::route('/create'),
            'view' => Pages\ViewEmailSend::route('/{record}'),
            'edit' => Pages\EditEmailSend::route('/{record}/edit'),
        ];
    }

    private static function labelRecipient($email): string
    {
        $rec = Recipient::whereHas('emails', function($query) use ($email) {
            $query->where('email', $email);
        })
        ->select('description', 'resp_surname', 'resp_name')
        ->first();

        if ($rec) {
            return "{$rec->description} <{$email}>";
        }

        return $email;
    }
}
