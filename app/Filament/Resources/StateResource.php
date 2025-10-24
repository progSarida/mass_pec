<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StateResource\Pages;
use App\Filament\Resources\StateResource\RelationManagers;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StateResource extends Resource
{
    protected static ?string $model = State::class;
    public static ?string $pluralModelLabel = 'Stati';
    public static ?string $modelLabel = 'Stato';
    protected static ?string $navigationIcon = 'fas-globe-europe';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('alpha2')->label('Codice Alpha-2')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('alpha3')->label('Codice Alpha-3')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('country_code')->label('Codice Stato')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('iso_3166_2')->label('Codice ISO 3166-2')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('region')->label('Regione')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                Forms\Components\TextInput::make('sub_region')->label('Sub-Regione')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->toggleable(isToggledHiddenByDefault: true),
                Forms\Components\TextInput::make('intermediate_region')->label('Regione intermedia')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->toggleable(isToggledHiddenByDefault: true),
                Forms\Components\TextInput::make('region_code')->label('Codice Regione')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->toggleable(isToggledHiddenByDefault: true),
                Forms\Components\TextInput::make('sub_region_code')->label('Codice Sub-Regione')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->toggleable(isToggledHiddenByDefault: true),
                Forms\Components\TextInput::make('intermediate_region_code')->label('Codice Regione intermedia')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Stato')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('alpha2')->label('Codice Alpha-2')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('alpha3')->label('Codice Alpha-3')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_code')->label('Codice Stato')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('iso_3166_2')->label('Codice ISO 3166-2')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('region')->label('Regione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sub_region')->label('Sub-Regione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('intermediate_region')->label('Regione intermedia')
                    ->searchable()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('region_code')->label('Codice Regione')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sub_region_code')->label('Codice Sub-Regione')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('intermediate_region_code')->label('Codice Regione intermedia')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListStates::route('/'),
            'create' => Pages\CreateState::route('/create'),
            'edit' => Pages\EditState::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Anagrafiche territoriali';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
