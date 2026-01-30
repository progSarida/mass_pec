<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\InMailResource\Pages;
use App\Models\InMail;
use App\Models\Registry;
use App\Models\ScopeType;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                            ->label('Mittente')
                            ->columnSpan(['sm' => 'full', 'md' => 5]),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->columnSpan(['sm' => 'full', 'md' => 7]),

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
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return 'Nessun allegato.';
                                }

                                // $files = Storage::disk('public')->files($record->attachment_path);
                                $files = Storage::files($record->attachment_path);

                                if (empty($files)) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        // $url = Storage::url($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(5));
                                        return <<<HTML
                                        <div class="flex items-center gap-2">
                                            📎 <a href="{$url}" target="_blank" class="text-blue-600 hover:underline">{$name}</a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->extraAttributes(['style' => 'line-height:1.8'])
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('receive_date', 'desc')
            ->columns([
                TextColumn::make('from')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('register_selected')
                        ->label('Protocolla selezionate')
                        ->icon('fluentui-pen-20-o')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Protocolla email selezionate')
                        ->modalDescription('Le mail selezionate verranno inserite nel protocollo ed eliminate dall\'elenco.')
                        ->modalSubmitActionLabel('Protocolla')
                        ->form([
                            Select::make('scope_type_id')
                                ->label('Ambito')
                                ->options(ScopeType::pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->placeholder('Seleziona l\'ambito per tutte le email')
                        ])
                        ->action(function (Collection $records, array $data) {
                            $successCount = 0;
                            $errorMessages = [];

                            foreach ($records as $record) {
                                try {
                                    static::registerEmail($record, $data['scope_type_id']);
                                    $successCount++;
                                } catch (\Exception $e) {
                                    $errorMessages[] = "Errore su ID {$record->id}: " . $e->getMessage();
                                }
                            }

                            // Notifica finale
                            if ($successCount > 0) {
                                Notification::make()
                                    ->title("Protocollate {$successCount} email")
                                    ->body('Operazione completata con successo.')
                                    ->success()
                                    ->send();
                            }

                            if (!empty($errorMessages)) {
                                $body = "Alcune email non sono state protocollate:\n" . implode("\n", $errorMessages);
                                Notification::make()
                                    ->title('Errori parziali')
                                    ->body($body)
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')),
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
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'account_id' => null,
                'recipients' => null,
                'send_date' => null,
                'send_user_id' => null,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la mail
            Model::withoutEvents(function () use ($record) {
                $record->delete();
            });

            $disk = config('filesystems.default');

            // Copio cartella allegati
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->makeDirectory($newPath);

                $files = Storage::disk($disk)->allFiles($oldPath);
                foreach ($files as $file) {
                    $relativePath = str_replace($oldPath . '/', '', $file);
                    $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $relativePath;
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
