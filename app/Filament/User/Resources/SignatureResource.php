<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\SignatureResource\Pages;
use App\Filament\User\Resources\SignatureResource\RelationManagers;
use App\Models\Signature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SignatureResource extends Resource
{
    protected static ?string $model = Signature::class;

    public static ?string $pluralModelLabel = 'Firme';
    protected static ?string $navigationIcon = 'fluentui-signature-20-o';
    protected static ?string $navigationLabel = 'Firme';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(10)
            ->schema([
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(8),
                Forms\Components\TextInput::make('position')->label('Posizione')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->maxLength(2)
                    ->columnSpan(1),
                Forms\Components\RichEditor::make('text')->label('Testo')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('position')->label('Posizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('text')->label('Testo')
                    ->formatStateUsing(fn (string $state): string => strip_tags($state))
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->subject),
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
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSignatures::route('/'),
            'create' => Pages\CreateSignature::route('/create'),
            'view' => Pages\ViewSignature::route('/{record}'),
            'edit' => Pages\EditSignature::route('/{record}/edit'),
        ];
    }
}
