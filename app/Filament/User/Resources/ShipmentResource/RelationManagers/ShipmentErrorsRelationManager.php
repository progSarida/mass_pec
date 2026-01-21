<?php

namespace App\Filament\User\Resources\ShipmentResource\RelationManagers;

use App\Enums\ShipmentErrorType;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShipmentErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipmentErrors';

    protected static ?string $title = 'Errori';

    protected static ?string $modelLabel = 'Errore';

    public function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Select::make('shipment_id')
                    ->label('Spedizione')
                    ->relationship('shipment', 'description')
                    ->columnSpan('full'),
                Select::make('recipient_id')
                    ->label('Interlocutore')
                    ->relationship('recipient', 'description')
                    ->columnSpan('full'),
                Forms\Components\TextInput::make('address')
                    ->label('Indirizzo')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(['sm' => 'full', 'md' => 8]),
                Select::make('shipment_error_type')
                    ->label('Tipo')
                    ->options(ShipmentErrorType::class)
                    ->columnSpan(['sm' => 'full', 'md' => 4]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('shipment.description')
                    ->label('Spedizione'),
                Tables\Columns\TextColumn::make('recipient.description')
                    ->label('Interlocutore'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Indirizzo'),
                Tables\Columns\TextColumn::make('send_date')
                    ->label('Data invio'),
                Tables\Columns\TextColumn::make('shipment_error_type')
                    ->label('Tipo'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
