<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Filament\User\Resources\SendEmailResource\Pages;
use App\Filament\User\Resources\SendEmailResource\RelationManagers;
use App\Models\Account;
use App\Models\OfficeType;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\ScopeType;
use App\Models\SendEmail;
use App\Models\Signature;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SendEmailResource extends Resource
{
    protected static ?string $model = SendEmail::class;

    public static ?string $pluralModelLabel = 'Invio posta';
    protected static ?string $navigationIcon = 'fluentui-mail-arrow-forward-20';
    protected static ?string $navigationLabel = 'Invio posta';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 3;

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
                    ->visible(fn($record) => !$record)
                    ->options(Signature::pluck('description', 'id'))
                    ->afterStateUpdated(function(Set $set, Get $get, $state) {
                        $text = Signature::find($state)->text;
                        $msg = $get('body');
                        $set('body', $msg . '<br><br><br>' . $text);
                    })
                    ->dehydrated(false)
                    ->columnSpan(['sm' => 'full', 'md' => 5]),

                Forms\Components\Select::make('mail_type')
                    ->label('Tipo mail')
                    // ->required()
                    // ->visible(fn($record, Get $get) => !$record || !$get('recipients'))
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
                    ->columnSpan(['sm' => 'full', 'md' => 8]),

                Forms\Components\Select::make('office_type_id')
                    ->label('Tipo ufficio')
                    // ->required()
                    // ->visible(fn($record, Get $get) => !$record || !$get('recipients'))
                    ->options(fn () => OfficeType::orderBy('position')->pluck('name', 'id')->toArray())
                    ->live()
                    ->searchable()
                    ->preload()
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
// dd($mailType);
                        $officeTypeId = $get('office_type_id');
// dd($officeTypeId);
                        // Divido la ricerca in parole
                        $words = array_filter(explode(' ', $search));

                        $query = \App\Models\Recipient::query();

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
                            ->flatMap(function ($item) use ($mailType, $officeTypeId) {
                                $out = [];

                                // Controllo ogni campo mail solo se mail_type e office_type_id corrispondono
                                for ($i = 1; $i <= 5; $i++) {
                                    $mailField = "mail_{$i}";
                                    $mailTypeField = "mail_type_{$i}";
                                    $officeTypeField = "office_type_id_{$i}";

                                    // Verifico che l'email esista e i tipi corrispondono (se mail_type e office_type_id sono selezionati)
                                    if (!empty($item->$mailField)) {
                                        if ((!$mailType || $item->$mailTypeField == $mailType) && (!$officeTypeId || $item->$officeTypeField == $officeTypeId)) {
                                            // $label = "{$item->description} - {$item->resp_surname} {$item->resp_name} <{$item->$mailField}>";
                                            $label = "{$item->description} - <{$item->$mailField}>";
                                            $out[$item->$mailField] = $label;
                                        }
                                    }
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

                FileUpload::make('attachments')
                    ->label('Carica allegati')
                    ->multiple()
                    ->directory('send_email/0')
                    ->preserveFilenames()
                    ->visible(fn($record) => !$record)
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        $disk = config('filesystems.default');
                        $directory = 'send_email/0';
                        // creo cartella temporanea se non esiste
                        if (!Storage::disk($disk)->exists('send_email/0')) {
                            Storage::disk($disk)->makeDirectory('send_email/0');
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

                Forms\Components\DatePicker::make('create_date')
                    ->label('Data creazione')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->visible(fn($state) => $state)
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                Forms\Components\Select::make('create_user_id')
                    ->label('Creata da')
                    ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->relationship('createUser', 'name')
                    ->visible(fn($state) => $state)
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                // Forms\Components\DateTimePicker::make('send_date')
                //     ->label('Data invio')
                //     ->disabled()
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->visible(fn($state) => $state)
                //     ->displayFormat('d/m/Y H:i:s')
                //     ->columnSpan(['sm' => 'full', 'md' => 3]),
                // Forms\Components\Select::make('send_user_id')
                //     ->label('Inviata da')
                //     ->disabled()
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->relationship('sendUser', 'name')
                //     ->visible(fn($state) => $state)
                //     ->columnSpan(['sm' => 'full', 'md' => 3]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account.public_name')
                    ->label('Mittente')
                    ->numeric()
                    ->sortable(),
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
                    ->label('Inviata da'),
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
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                                                // && $record->send_date
                                                // && !Registry::where('uid', '#send_email' . $record->id)->exists()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla email')
                    ->modalDescription('La mail verrà inserita nel protocollo')
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
                            static::registerEmail($record, $data['scope_type_id']);
                            Notification::make()
                                ->title('Mail protocollata')
                                ->body('La mail e i suoi allegati sono stati protocollati con successo.')
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
                // Tables\Actions\Action::make('registered')
                //     ->label('Protocollata')
                //     ->icon('heroicon-o-information-circle')
                //     ->color('success')
                //     ->tooltip('Spedizione già inserita nel protocollo.')
                //     ->visible(fn($record) => Registry::where('uid', '#send_email' . $record->id)->exists())
                //     ->action(fn () => null),
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
            'index' => Pages\ListSendEmails::route('/'),
            'create' => Pages\CreateSendEmail::route('/create'),
            'view' => Pages\ViewSendEmail::route('/{record}'),
            'edit' => Pages\EditSendEmail::route('/{record}/edit'),
        ];
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

    private static function registerEmail($record, $scopeTypeId){
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'issued',
                'flow_index' => static::newIndex('issued'),
                'registry_origin_type' => 'send_email',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#send_email' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'from' => $record->account->public_name,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => null,
                'account_id' => $record->account_id,
                'recipients' => $record->recipients,
                'send_date' => $record->send_date,
                'send_user_id' => $record->send_user_id,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la mail in uscita
            Model::withoutEvents(function () use ($record) {
                $record->delete();
            });

            $disk = config('filesystems.default');

            // copio cartella allegati
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->makeDirectory($newPath);

                $files = collect(Storage::disk($disk)->files($oldPath))
                    // ->filter(function ($path) {
                    //     return Str::contains($path, 'estrazione');
                    // })
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

            // Elimino la vecchia cartella degli allegati
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->deleteDirectory($oldPath);
            }

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
