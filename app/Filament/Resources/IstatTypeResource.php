<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IstatTypeResource\Pages;
use App\Filament\Resources\IstatTypeResource\RelationManagers;
use App\Models\IstatType;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IstatTypeResource extends Resource
{
    protected static ?string $model = IstatType::class;
    public static ?string $pluralModelLabel = 'Tipi Istat';
    public static ?string $modelLabel = 'Tipo Istat';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Tipi Istat';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('name')->label('Nome tipo')
                    ->columnSpan(6),
                TextInput::make('position')->label('Posizione')
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->label('Posizione'),
                TextColumn::make('name')
                    ->label('Nome tipo'),
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
            'index' => Pages\ListIstatTypes::route('/'),
            'create' => Pages\CreateIstatType::route('/create'),
            'edit' => Pages\EditIstatType::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestione';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }
}
