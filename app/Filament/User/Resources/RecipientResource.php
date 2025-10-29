<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\RecipientResource\Pages;
use App\Filament\User\Resources\RecipientResource\RelationManagers;
use App\Models\Recipient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecipientResource extends Resource
{
    protected static ?string $model = Recipient::class;
    public static ?string $pluralModelLabel = 'Destinatari';
    public static ?string $modelLabel = 'Destinatario';
    protected static ?string $navigationIcon = 'ri-user-received-2-fill';
    protected static ?string $navigationLabel = 'Destinatari';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('adminType.name')
                    ->label('Tipo ente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('istatType.name')
                    ->label('Tipo Istat')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.name')
                    ->label('Comune'),
                TextColumn::make('city.province.code')
                    ->label('Provincia'),
                TextColumn::make('city.province.region.name')
                    ->label('Descrizione'),
                TextColumn::make('resp_title')
                    ->label('Titolo Resp.'),
                TextColumn::make('resp_surname')
                    ->label('Cognome Resp.')
                    ->searchable(),
                TextColumn::make('resp_name')
                    ->label('Nome Resp.')
                    ->searchable(),
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
            'index' => Pages\ListRecipients::route('/'),
            'create' => Pages\CreateRecipient::route('/create'),
            'edit' => Pages\EditRecipient::route('/{record}/edit'),
        ];
    }
}
