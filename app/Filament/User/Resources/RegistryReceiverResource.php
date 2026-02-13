<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\RegistryReceiverResource\Pages;
use App\Models\RegistryReceiver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegistryReceiverResource extends Resource
{
    protected static ?string $model = RegistryReceiver::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return false;                                                                                   // nascondo la risorsa dal menu di navigazione
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('registry_id')
                    ->numeric(),
                Forms\Components\TextInput::make('protocol_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('recipient_id')
                    ->numeric(),
                Forms\Components\TextInput::make('address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('pec_status')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registry_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('protocol_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pec_status')
                    ->searchable(),
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
            'index' => Pages\ListRegistryReceivers::route('/'),
            'create' => Pages\CreateRegistryReceiver::route('/create'),
            'view' => Pages\ViewRegistryReceiver::route('/{record}'),
            'edit' => Pages\EditRegistryReceiver::route('/{record}/edit'),
        ];
    }
}
