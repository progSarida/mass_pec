<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\DownloadEmailResource\Pages;
use App\Filament\User\Resources\DownloadEmailResource\RelationManagers;
use App\Models\DownloadEmail;
use App\Models\Registry;
use App\Models\ScopeType;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DownloadEmailResource extends Resource
{
    protected static ?string $model = DownloadEmail::class;

    public static ?string $pluralModelLabel = 'Scarico email';
    protected static ?string $navigationIcon = 'fluentui-mail-arrow-down-20';
    protected static ?string $navigationLabel = 'Scarico email';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->disabled()
            ->columns(12)
            ->schema([
                Section::make('Informazioni Principali')
                    ->columns(12)
                    ->schema([
                        TextInput::make('from')
                            ->label('Mittente')
                            ->columnSpan(5),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->columnSpan(7),

                        Textarea::make('body')
                            ->label('Messaggio')
                            ->rows(10)
                            ->columnSpan('full')
                            ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),
                    ]),

                TextInput::make('receive_date')
                    ->label('Ricevuto il')
                    ->columnSpan(4)
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                TextInput::make('created_at')
                    ->label('Scaricato il')
                    ->columnSpan(4)
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->relationship('downloadUser', 'name')
                    ->columnSpan(4),

                Section::make('Allegati')
                    ->collapsed(fn($record) => $record)
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return 'Nessun allegato.';
                                }
                                $files = Storage::disk('public')->files($record->attachment_path);
                                if (empty($files)) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::url($file);
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
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(50)
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
                    }),

                TextColumn::make('receive_date')
                    ->label('Ricevuto il')
                    ->date('d/m/Y')
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
                    ->modalSubmitActionLabel('Protocolla')->form([
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
            'index' => Pages\ListDownloadEmails::route('/'),
            'create' => Pages\CreateDownloadEmail::route('/create'),
            'edit' => Pages\EditDownloadEmail::route('/{record}/edit'),
            'view' => Pages\ViewDownloadEmail::route('/{record}'),
        ];
    }

    private static function registerEmail($record, $scopeTypeId){
        try {
            DB::beginTransaction();
            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->makeDirectory($newPath);

                $files = Storage::disk('public')->allFiles($oldPath);
                foreach ($files as $file) {
                    $relativePath = str_replace($oldPath . '/', '', $file);
                    $newFilePath = $newPath . '/' . $relativePath;

                    $directory = dirname($newFilePath);
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }

                    Storage::disk('public')->put($newFilePath, Storage::disk('public')->get($file));
                }
            }

            Registry::create([
                'protocol_number' => $protocolNumber,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::user()->id,
            ]);

            $record->delete();

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->deleteDirectory($oldPath);
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
}
