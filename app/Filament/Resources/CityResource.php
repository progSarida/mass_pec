<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CityResource extends Resource
{
    protected static ?string $model = City::class;
    public static ?string $pluralModelLabel = 'Comuni';
    public static ?string $modelLabel = 'Comuni';
    protected static ?string $navigationIcon = 'fas-city';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('province_id')->label('Provincia')
                    ->relationship('province', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')->label('Codice Catastale')
                    ->required()
                    ->maxLength(4),
                Forms\Components\TextInput::make('zip_code')->label('CAP')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Comune')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('province.code')->label('Provincia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zip_code')->label('CAP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Codice Catastale')
                    ->searchable()
                    ->sortable(),
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Anagrafiche territoriali';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }
}
