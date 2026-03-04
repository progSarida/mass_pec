<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScopeTypeResource\Pages;
use App\Models\ScopeType;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScopeTypeResource extends Resource
{
    protected static ?string $model = ScopeType::class;
    public static ?string $pluralModelLabel = 'Settori Interni';
    public static ?string $modelLabel = 'Settore Interno';
    protected static ?string $navigationIcon = 'fas-list';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                TextInput::make('name')->label('Nome')
                    ->required()
                    ->columnSpan(1),
                TextInput::make('description')->label('Descrizione')
                    ->columnSpan(2),
                TextInput::make('position')->label('Posizione')
                    ->required()
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('position'))
            ->columns([
                TextColumn::make('position')->label('Posizione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
            'index' => Pages\ListScopeTypes::route('/'),
            'create' => Pages\CreateScopeType::route('/create'),
            'edit' => Pages\EditScopeType::route('/{record}/edit'),
        ];
    }
}
