<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\ShipmentResource\Pages;
use App\Filament\User\Resources\ShipmentResource\RelationManagers;
use App\Models\Sender;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
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
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $time = now()->format('Y-m-d_H-i-s');
        return $form
            ->columns(24)
            ->schema([
                TextInput::make('description')
                    ->label('Descrizione (non visibile ai destinatari)')
                    ->columnSpan('full'),
                TextInput::make('sender_name')
                    ->label('PEC Mittente')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (TextInput $component, $record) {
                        if ($record?->sender) {
                            $component->state($record->sender->public_name);
                            return;
                        }
                        $sender = \App\Models\Sender::find(1);
                        $component->state($sender?->public_name ?? 'Mittente non trovato');
                    })
                    ->columnSpan(10),
                TextInput::make('mail_object')
                    ->label('Oggetto')
                    ->columnSpan(14),
                Textarea::make('mail_body')
                    ->label('Messaggio')
                    ->rows(6)
                    ->columnSpan('full'),
                TextInput::make('attachment')
                    ->label('Allegato')
                    ->default('allegati_' . $time . '.zip')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(8),
                Section::make('Resoconto mail')
                    ->visible(fn ($record) => $record)
                    ->collapsed()
                    ->columns(24)
                    ->schema([
                        TextInput::make('total_no_mails ')
                            ->label('Totali')
                            ->columnSpan(3),
                        TextInput::make('no_mails_sended ')
                            ->label('Inviate')
                            ->columnSpan(3),
                        TextInput::make('no_mails_to_send ')
                            ->label('Da inviare')
                            ->columnSpan(3),
                        TextInput::make('no_send_receipt ')
                            ->label('Ricevute')
                            ->columnSpan(3),
                        TextInput::make('no_missed_send_receipt ')
                            ->label('Non ricevute')
                            ->columnSpan(3),
                        TextInput::make('no_delivery_receipt ')
                            ->label('Consegnate')
                            ->columnSpan(3),
                        TextInput::make('no_missed_delivery_receipt')
                            ->label('Non consegnate')
                            ->columnSpan(3),
                        TextInput::make('no_anomaly_receipt ')
                            ->label('Anomalie')
                            ->columnSpan(3),
                    ])
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
                TextColumn::make('no_delivery_receipt')
                    ->label('Consegne'),
                TextColumn::make('no_send_receipt')
                    ->label('Accettazioni'),
                TextColumn::make('no_anomaly_receipt')
                    ->label('Anomalie'),
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
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
            'view' => Pages\ViewShipment::route('/{record}')
        ];
    }
}
