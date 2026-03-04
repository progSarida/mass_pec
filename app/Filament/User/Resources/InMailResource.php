<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\InMailResource\Pages;
use App\Models\InMail;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\ScopeType;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class InMailResource extends Resource
{
    protected static ?string $model = InMail::class;

    public static ?string $pluralModelLabel = 'Leggi mail sped. massive';
    public static ?string $modelLabel = 'Mail';
    protected static ?string $navigationIcon = 'fluentui-mail-inbox-arrow-down-20-o';
    protected static ?string $navigationLabel = 'Leggi mail sped. massive';
    protected static ?string $navigationGroup = 'Pec Massiva';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            // ->disabled()
            ->columns(12)
            ->schema([
                Section::make('Informazioni Principali')
                    ->columns(12)
                    ->schema([
                        TextInput::make('from')
                            ->label('Email mittente')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        Select::make('sender_id')
                            ->label('Mittente')
                            ->hintAction(
                                Action::make('Nuovo')
                                    ->icon('ri-user-2-line')
                                    ->form(fn(Form $form) => RecipientResource::modalForm($form))
                                    ->modalWidth('7xl')
                                    ->modalHeading('')
                                    ->action(fn (array $data, Recipient $recipient, Set $set) => InMailResource::saveRecipient($data, $recipient, $set))
                                    ->hidden(fn ($record) => $record->sender_id)
                                    ->hidden(fn ($livewire, $record) => $livewire instanceof \App\Filament\User\Resources\DownloadEmailResource\Pages\ViewDownloadEmail || $record->sender_id)
                            )
                            ->searchable()
                            ->relationship(name: 'sender', titleAttribute: 'description')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->columnSpan(['sm' => 'full', 'md' => 12]),

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
                    ->date('d/m/Y')
                    ->columnSpan(['sm' => 'full', 'md' => 4]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->relationship('downloadUser', 'name')
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
                            ->visible(function ($record) {
                                if (!$record || !$record->attachment_path) return false;
                                // Mostra il tasto solo se ci sono almeno 2 file
                                return count(Storage::files($record->attachment_path)) > 1;
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
            ->defaultSort('receive_date', 'asc')
            ->columns([
                // TextColumn::make('from')
                //     ->label('Mittente')
                //     ->searchable()
                //     ->limit(25)
                //     ->tooltip(fn ($record) => $record->from),

                TextColumn::make('sender.description')
                    ->label('Mittente')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->from),

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
                        if (!$record->body_preview) return 'Nessun contenuto';
                        $preview = strip_tags($record->body_preview);
                        return Str::limit($preview, 500);
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
                    ->sortable(),

                // Tables\Columns\TextColumn::make('attachments')
                //     ->label('Allegati')
                //     ->formatStateUsing(fn ($state) => $state ? 'Apri cartella' : '—')
                //     ->url(fn ($record) => $record->attachment_path ? asset('storage/' . $record->attachment_path) : null)
                //     ->openUrlInNewTab()
                //     ->icon('heroicon-o-folder-open')
                //     ->color('primary'),
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
                    ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla email')
                    ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->options(ScopeType::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona il settore interno della registrazione')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Tables\Actions\BulkAction::make('register_selected')
                    //     ->label('Protocolla selezionate')
                    //     ->icon('fluentui-pen-20-o')
                    //     ->color('warning')
                    //     ->requiresConfirmation()
                    //     ->modalHeading('Protocolla email selezionate')
                    //     ->modalDescription('Le mail selezionate verranno inserite nel protocollo ed eliminate dall\'elenco.')
                    //     ->modalSubmitActionLabel('Protocolla')
                    //     ->form([
                    //         Select::make('scope_type_id')
                    //             ->label('Ambito')
                    //             ->options(ScopeType::pluck('name', 'id'))
                    //             ->searchable()
                    //             ->required()
                    //             ->placeholder('Seleziona l\'ambito per tutte le email')
                    //     ])
                    //     ->action(function (Collection $records, array $data) {
                    //         $successCount = 0;
                    //         $errorMessages = [];

                    //         foreach ($records as $record) {
                    //             try {
                    //                 static::registerEmail($record, $data['scope_type_id']);
                    //                 $successCount++;
                    //             } catch (\Exception $e) {
                    //                 $errorMessages[] = "Errore su ID {$record->id}: " . $e->getMessage();
                    //             }
                    //         }

                    //         // Notifica finale
                    //         if ($successCount > 0) {
                    //             Notification::make()
                    //                 ->title("Protocollate {$successCount} email")
                    //                 ->body('Operazione completata con successo.')
                    //                 ->success()
                    //                 ->send();
                    //         }

                    //         if (!empty($errorMessages)) {
                    //             $body = "Alcune email non sono state protocollate:\n" . implode("\n", $errorMessages);
                    //             Notification::make()
                    //                 ->title('Errori parziali')
                    //                 ->body($body)
                    //                 ->danger()
                    //                 ->send();
                    //         }
                    //     })
                    //     ->deselectRecordsAfterCompletion()
                    //     ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInMails::route('/'),
            'create' => Pages\CreateInMail::route('/create'),
            'edit' => Pages\EditInMail::route('/{record}/edit'),
            'view' => Pages\ViewInMail::route('/{record}')
        ];
    }

    private static function registerEmail($record, $scopeTypeId)
    {
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'received',
                'flow_index' => static::newIndex('received'),
                'registry_origin_type' => 'in_mail',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'sender_id' => $record->sender_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'account_id' => null,
                'send_date' => null,
                'send_user_id' => null,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la mail
            // Model::withoutEvents(function () use ($record) {
            //     $record->delete();
            // });

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            // Copio cartella allegati
            if ($oldPath && $storage->exists($oldPath)) {
                // Storage::disk($disk)->makeDirectory($newPath);

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }
                $files = Storage::disk($disk)->allFiles($oldPath);

                // foreach ($files as $file) {
                //     $relativePath = str_replace($oldPath . '/', '', $file);
                //     $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $relativePath;

                //     $directory = dirname($newFilePath);
                //     if (!Storage::disk($disk)->exists($directory)) {
                //         Storage::disk($disk)->makeDirectory($directory);
                //     }

                //     Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                // }

                foreach ($files as $file) {
                    $fileName = basename($file);
                    $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $fileName;
                    $finalPath = $newPath . '/' . $newFileName;

                    try {
                        // Usiamo lo Stream per bypassare i limiti del comando COPY di S3
                        $stream = $storage->readStream($file);

                        if ($stream === null) {
                            throw new \Exception("Impossibile leggere il file sorgente: $file");
                        }

                        // Scriviamo il file nella nuova posizione
                        // Il terzo parametro 'visibility' assicura che il nuovo file sia scrivibile
                        $result = $storage->writeStream($finalPath, $stream, [
                            'visibility' => 'private'
                        ]);

                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        if (!$result) {
                            throw new \Exception("Scrittura fallita per: $finalPath");
                        }

                        Log::info("File copiato con successo: $finalPath");

                    } catch (\Exception $e) {
                        Log::error("Errore durante la copia stream: " . $e->getMessage());
                        // Fallback estremo se lo stream fallisce (usa più RAM)
                        $storage->put($finalPath, $storage->get($file));
                    }
                }
            } else {
                Log::warning("Percorso non trovato o vuoto: " . ($oldPath ?? 'NULL'));
            }

            // Elimino la mail in uscita
            // Model::withoutEvents(function () use ($record) {
                $record->delete();
            // });
            // Elimina solo se hai effettivamente trovato dei file o se vuoi pulire comunque
            // $storage->deleteDirectory($oldPath);

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

    public static function saveRecipient(array $data, Recipient $recipient, Set $set): void
    {
        for($i = 1; $i <= 5; $i++){
            $address = $data["mail_{$i}"];
            if(!$address || $address == '') {
                Log::info("Mail_{$i} è vuoto o nullo");
                continue;
            }
Log::info("Mail {$i}: {$address}");
            $recipient = static::getRecipient($address);
            if ($recipient) {
                Notification::make()
                    ->title("Indirizzo {$address} presente in archivio")
                    ->body("L'indirizzo {$address} è già associato a {$recipient->description}")
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }
        $recipient->description = $data['description'] ?? null;
        $recipient->admin_type_id = $data['admin_type_id'] ?? null;
        $recipient->istat_type_id = $data['istat_type_id'] ?? null;
        $recipient->codfe_ipa = $data['code_ipa'] ?? null;
        $recipient->acronym = $data['acronym'] ?? null;
        $recipient->city_id = $data['city_id'] ?? null;
        $recipient->address = $data['address'] ?? null;
        $recipient->city_cap = $data['city_cap'] ?? null;
        $recipient->resp_title = $data['resp_title'] ?? null;
        $recipient->resp_surname = $data['resp_surname'] ?? null;
        $recipient->resp_name = $data['resp_name'] ?? null;
        $recipient->resp_tax_code = $data['resp_tax_code'] ?? null;
        $recipient->mail_1 = $data['mail_1'] ?? null;
        $recipient->mail_type_1 = $data['mail_type_1'] ?? null;
        $recipient->office_type_id_1 = $data['office_type_id_1'] ?? null;
        $recipient->mail_2 = $data['mail_2'] ?? null;
        $recipient->mail_type_2 = $data['mail_type_2'] ?? null;
        $recipient->office_type_id_2 = $data['office_type_id_2'] ?? null;
        $recipient->mail_3 = $data['mail_3'] ?? null;
        $recipient->mail_type_3 = $data['mail_type_3'] ?? null;
        $recipient->office_type_id_3 = $data['office_type_id_3'] ?? null;
        $recipient->mail_4 = $data['mail_4'] ?? null;
        $recipient->mail_type_4 = $data['mail_type_4'] ?? null;
        $recipient->office_type_id_4 = $data['office_type_id_4'] ?? null;
        $recipient->mail_5 = $data['mail_5'] ?? null;
        $recipient->mail_type_5 = $data['mail_type_5'] ?? null;
        $recipient->office_type_id_5 = $data['office_type_id_5'] ?? null;
        $recipient->site = $data['site'] ?? null;
        $recipient->url_facebook = $data['url_facebook'] ?? null;
        $recipient->url_twitter = $data['url_twitter'] ?? null;
        $recipient->url_googleplus = $data['url_googleplus'] ?? null;
        $recipient->url_youtube = $data['url_youtube'] ?? null;
        $recipient->save();

        $set('sender_id', $recipient->id);
        Notification::make()
            ->title('Interlocutore salvato con successo')
            ->success()
            ->send();
    }
}
