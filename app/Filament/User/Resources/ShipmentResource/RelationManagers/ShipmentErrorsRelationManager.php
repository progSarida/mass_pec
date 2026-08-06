<?php

namespace App\Filament\User\Resources\ShipmentResource\RelationManagers;

use App\Enums\ShipmentErrorType;
use App\Filament\User\Resources\RecipientResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

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
            ->recordUrl(function (Model $record): string {
                return RecipientResource::getUrl('view', ['record' => $record->recipient_id]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('shipment.description')
                    ->label('Spedizione')
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->shipment->description),
                Tables\Columns\TextColumn::make('recipient.description')
                    ->label('Interlocutore')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->recipient->description),
                Tables\Columns\TextColumn::make('address')
                    ->label('Indirizzo')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->address),
                Tables\Columns\TextColumn::make('send_date')
                    ->label('Data invio')
                    ->date('d/m/Y H:i:s'),
                Tables\Columns\IconColumn::make('shipment_error_type')
                    ->label('Tipo')
                    ->tooltip(fn (ShipmentErrorType $state): string => $state->getLabel()),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->label('Stampa')
                    ->tooltip('Stampa elenco errori')
                    ->color('print')
                    ->action(function ($livewire) {

                        $shipment = $this->getOwnerRecord();

                        $records = $livewire->getFilteredTableQuery()
                                    ->orderBy('created_at', 'asc')
                                    ->get();

                        if(count($records) === 0){
                            Notification::make()
                                ->title('Nessun elemento da stampare')
                                ->warning()
                                ->send();
                            return false;
                        }

                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()
                            ->streamDownload(function () use ($records, $shipment) {
                                echo Pdf::loadHTML(
                                    Blade::render('print.shipment_errors', [
                                        'shipment' => $shipment,
                                        'errors' => $records,
                                    ])
                                )
                                    ->setPaper('A4', 'landscape')
                                    ->stream();
                            }, "Errori spedizione_$shipment->id.pdf");
                }),
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
