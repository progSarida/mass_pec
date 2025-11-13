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
use Illuminate\Support\HtmlString;
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
            'view' => Pages\ViewInMail::route('/{record}')
        ];
    }
}
