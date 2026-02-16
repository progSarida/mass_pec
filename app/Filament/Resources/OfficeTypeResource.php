<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfficeTypeResource\Pages;
use App\Models\OfficeType;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficeTypeResource extends Resource
{
    protected static ?string $model = OfficeType::class;
    public static ?string $pluralModelLabel = 'Uffici esterni';
    public static ?string $modelLabel = 'Ufficio esterno';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Uffici esterni';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 4;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('position'))
            ->columns([
                TextColumn::make('position')
                    ->label('Posizione'),
                TextColumn::make('name')
                    ->label('Nome tipo'),
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
            'index' => Pages\ListOfficeTypes::route('/'),
            'create' => Pages\CreateOfficeType::route('/create'),
            'edit' => Pages\EditOfficeType::route('/{record}/edit'),
        ];
    }
}
