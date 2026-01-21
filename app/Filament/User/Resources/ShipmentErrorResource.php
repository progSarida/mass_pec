<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\ShipmentErrorResource\Pages;
use App\Filament\User\Resources\ShipmentErrorResource\RelationManagers;
use App\Models\ShipmentError;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShipmentErrorResource extends Resource
{
    protected static ?string $model = ShipmentError::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return false;                                                                                   // nascondo la risorsa dal menu di navigazione
    }

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
                //
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
            'index' => Pages\ListShipmentErrors::route('/'),
            'create' => Pages\CreateShipmentError::route('/create'),
            'edit' => Pages\EditShipmentError::route('/{record}/edit'),
        ];
    }
}
