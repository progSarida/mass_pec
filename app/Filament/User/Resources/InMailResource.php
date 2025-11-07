<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\InMailResource\Pages;
use App\Models\InMail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InMailResource extends Resource
{
    protected static ?string $model = InMail::class;

    public static ?string $pluralModelLabel = 'Casella massiva';
    public static ?string $modelLabel = 'Mail';
    protected static ?string $navigationIcon = 'fluentui-mail-inbox-20';
    protected static ?string $navigationLabel = 'Casella massiva';
    protected static ?int $navigationSort = 4;

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

                        Textarea::make('body_preview')
                            ->label('Messaggio (Anteprima)')
                            ->rows(6)
                            ->columnSpan('full')
                            ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),

                        Placeholder::make('full_message')
                            ->label('Messaggio Completo')
                            ->content(function ($record) {
                                $path = "in_mail/{$record->id}/original_message.txt";
                                if (!Storage::disk('public')->exists($path)) {
                                    return 'Il messaggio completo non è disponibile.';
                                }

                                $url = Storage::disk('public')->url($path);
                                $sizeKb = number_format(Storage::disk('public')->size($path) / 1024, 1);

                                return view('in_mail.full-message-link', [
                                    'url' => $url,
                                    'size' => $sizeKb,
                                ]);
                            })
                            ->columnSpan('full'),
                    ]),

                Section::make('Metadati')
                    ->columns(12)
                    ->schema([
                        TextInput::make('receive_date')
                            ->label('Ricevuto il')
                            ->columnSpan(4)
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : null),

                        TextInput::make('created_at')
                            ->label('Scaricato il')
                            ->columnSpan(4)
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : null),

                        Forms\Components\Select::make('download_user_id')
                            ->label('Scaricato da')
                            ->relationship('downloadUser', 'name')
                            ->columnSpan(4),
                    ]),

                Section::make('Allegati')
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('Allegati')
                            ->content(function ($record) {
                                if (!$record || !$record->attachments_path) {
                                    return 'Nessun allegato.';
                                }

                                $files = Storage::files($record->attachments_path);
                                if (empty($files)) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                return collect($files)->map(function ($file) {
                                    $name = basename($file);
                                    $url = Storage::url($file);
                                    return "Clip <a href='{$url}' target='_blank' class='text-primary-600 hover:underline'>{$name}</a>";
                                })->implode('<br>');
                            })
                            ->extraAttributes(['style' => 'line-height:1.8'])
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from')
                    ->label('Mittente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->subject),

                TextColumn::make('body_preview')
                    ->label('Messaggio')
                    ->limit(100)
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? Str::limit(strip_tags($state), 100) : '—')
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

                Tables\Columns\TextColumn::make('attachments_path')
                    ->label('Allegati')
                    ->formatStateUsing(fn ($state) => $state ? 'Apri cartella' : '—')
                    ->url(fn ($record) => $record->attachments_path ? asset('storage/' . $record->attachments_path) : null)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-folder-open')
                    ->color('primary'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('receive_date', 'desc');
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
        ];
    }
}
