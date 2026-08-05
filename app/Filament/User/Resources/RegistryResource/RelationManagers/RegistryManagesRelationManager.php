<?php

namespace App\Filament\User\Resources\RegistryResource\RelationManagers;

use App\Enums\ManageRegistryType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegistryManagesRelationManager extends RelationManager
{
    protected static string $relationship = 'registryManages';

    protected static ?string $title = 'Gestione';

    protected static ?string $modelLabel = 'Stato';

    public function form(Form $form): Form
    {
        return $form
            ->disabled()
            ->columns()
            ->schema([
                Forms\Components\Select::make('manage_registry_type')
                    ->label('Stato gestione')
                    ->required()
                    ->options(ManageRegistryType::class),
                Forms\Components\DatePicker::make('manage_registry_date')
                    ->label('Data evasione')
                    ->visible(fn ($record) => $record->manage_registry_date)
                    ->required()
                    ->default(now()),
                Forms\Components\Placeholder::make('fil_date')
                    ->label('')
                    ->visible(fn ($record) => !$record->manage_registry_date),
                Forms\Components\TextArea::make('manage_registry_mode')
                    ->label('Modalità evasione')
                    ->visible(fn ($record) => $record->manage_registry_mode)
                    ->required()
                    ->rows(8)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('manage_registration_datetime')
                    ->label('Data registrazione stato')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('manage_registration_user_id')
                    ->label('Registrato da')
                    ->relationship('user', 'name')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('manage_registry_type')
            ->columns([
                Tables\Columns\TextColumn::make('manage_registry_type')
                    ->label('Stato gestione'),
                Tables\Columns\TextColumn::make('manage_registry_date')
                    ->label('Data evasione')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('manage_registry_mode')
                    ->label('Modalità evasione'),
                Tables\Columns\TextColumn::make('manage_registration_datetime')
                    ->label('Data registrazione stato')
                    ->dateTime('d/m/Y H:i:s'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Registrato da'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Dettaglio stato gestione'),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica stato gestione'),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
