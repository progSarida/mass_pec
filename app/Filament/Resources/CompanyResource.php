<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\City;
use App\Models\Company;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    public static ?string $pluralModelLabel = 'Gestore';
    public static ?string $modelLabel = 'Dati gestore';
    protected static ?string $navigationIcon = 'fas-building';
    protected static ?string $navigationGroup = 'Parametri';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {

        $italyId = State::where('name', 'Italy')->first()->id;
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('name')->label('Denominazione - Cognome e Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(6),
                TextInput::make('vat_number')->label('Partita Iva')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                TextInput::make('tax_number')->label('Codice fiscale')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Select::make('state_id')->label('Paese')
                    ->options(State::all()->pluck('name', 'id')->toArray())
                    ->placeholder('')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->default($italyId)
                    ->afterStateUpdated(function (callable $set, $state) {
                        //
                    })
                    ->columnspan(2),
                TextInput::make('city_code')
                    ->label('Codice catastale')
                    ->required()
                    ->maxLength(4)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $city = City::where('code', $state)->first();
                        if ($city) {
                            $set('city', $city->code);
                        } else {
                            $set('city', null);
                        }
                    })
                    ->columnSpan(2),
                TextInput::make('address')->label('Indirizzo')
                    ->maxLength(255)
                    ->required()
                    ->columnSpan(6),
                Select::make('city')->label('Città')
                    ->relationship(name: 'city', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('state_id') == $italyId)
                    ->columnSpan(4),
                TextInput::make('place')->label('Città')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('state_id') != $italyId)
                    ->columnspan(4),
                TextInput::make('phone')->label('Telefono')
                    ->maxLength(255)
                    ->columnSpan(3),
                TextInput::make('email')->label('Email')
                    ->maxLength(255)
                    ->columnSpan(3),
                TextInput::make('pec')->label('Pec')
                    ->maxLength(255)
                    ->columnSpan(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
