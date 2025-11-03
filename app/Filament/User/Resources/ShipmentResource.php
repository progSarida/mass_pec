<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\ShipmentResource\Pages;
use App\Filament\User\Resources\ShipmentResource\RelationManagers;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;
    public static ?string $pluralModelLabel = 'Spedizioni';
    public static ?string $modelLabel = 'Spedizione';
    protected static ?string $navigationIcon = 'fluentui-mail-arrow-forward-20';
    protected static ?string $navigationLabel = 'Spedizioni';

    public static function form(Form $form): Form
    {
        $time = now()->format('Y-m-d_H-i-s');
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('description')
                    ->label('Descrizione (non visibile ai destinatari)')
                    ->columnSpan('full'),
                Select::make('sender_id')
                    ->label('PEC Mittente')
                    ->relationship(name: 'sender', titleAttribute: 'public_name')
                    ->columnSpan(5),
                TextInput::make('mail_object')
                    ->label('Oggetto')
                    ->columnSpan(7),
                Textarea::make('mail_body')
                    ->label('Messaggio')
                    ->rows(6)
                    ->columnSpan('full'),
                TextInput::make('attachment')
                    ->label('Allegato')
                    ->default('allegati_' . $time . '.zip')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('insert_date')
                    ->label('Data inserimento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total_no_mails')
                    ->label('Totale email'),
                TextColumn::make('no_mails_sended')
                    ->label('Inviate'),
                TextColumn::make('no_mails_to_send')
                    ->label('Da inviare'),
                TextColumn::make('no_send_receipt')
                    ->label('Accettazioni'),
                TextColumn::make('no_delivery_receipt')
                    ->label('Consegne'),
                TextColumn::make('no_anomaly_receipt')
                    ->label('Anoomalie'),
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
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }
}
