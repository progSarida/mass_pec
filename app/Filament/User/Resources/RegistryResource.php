<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\RegistryResource\Pages;
use App\Filament\User\Resources\RegistryResource\RelationManagers;
use App\Models\Registry;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RegistryResource extends Resource
{
    protected static ?string $model = Registry::class;

    public static ?string $pluralModelLabel = 'Protocollo';
    protected static ?string $navigationIcon = 'fluentui-book-20';
    protected static ?string $navigationLabel = 'Protocollo';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->disabled()
            ->columns(15)
            ->schema([
                Section::make('Informazioni Principali')
                    ->columns(15)
                    ->schema([
                        TextInput::make('protocol_number')
                            ->label('Protocollo')
                            ->columnSpan(7),

                        Select::make('scope_type_id')
                            ->label('Ambito')
                            ->relationship('scopeType', 'name')
                            ->columnSpan(8),

                        TextInput::make('from')
                            ->label('Mittente')
                            ->columnSpan(6),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->columnSpan(9),

                        Textarea::make('body')
                            ->label('Messaggio')
                            ->rows(10)
                            ->columnSpan('full')
                            ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),
                    ]),

                TextInput::make('receive_date')
                    ->label('Ricevuto il')
                    ->columnSpan(3)
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                TextInput::make('download_date')
                    ->label('Scaricato il')
                    ->columnSpan(3)
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    ->relationship('downloadUser', 'name')
                    ->columnSpan(3),

                TextInput::make('created_at')
                    ->label('Registrato il')
                    ->columnSpan(3)
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('register_user_id')
                    ->label('Registrato da')
                    ->relationship('registerUser', 'name')
                    ->columnSpan(3),

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
                TextColumn::make('protocol_number')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scopeType.name')
                    ->label('Ambito')
                    ->sortable(),

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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('download_date')
                    ->label('Scaricato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('downloadUser.name')
                    ->label('Scaricato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registerUser.name')
                    ->label('Registrato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            'index' => Pages\ListRegistries::route('/'),
            'create' => Pages\CreateRegistry::route('/create'),
            'edit' => Pages\EditRegistry::route('/{record}/edit'),
            'view' => Pages\ViewRegistry::route('/{record}'),
        ];
    }
}
