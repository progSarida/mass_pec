<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\PecInteractionResource\Pages;
use App\Filament\User\Resources\PecInteractionResource\RelationManagers;
use App\Models\PecInteraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PecInteractionResource extends Resource
{
    protected static ?string $model = PecInteraction::class;

    public static ?string $pluralModelLabel = 'Interazioni PEC';
    protected static ?string $navigationIcon = 'fluentui-search-20-o';
    protected static ?string $navigationLabel = 'Interazioni PEC';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // IconColumn::make('pec_interaction_type')
                //     ->label('')
                //     ->tooltip(fn ($record) => $record->pec_interaction_type->getLabel())
                //     ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('pec_interaction_type')
                    ->label('Tipo interazione')
                    ->sortable(),

                TextColumn::make('registry.protocol_number')
                    ->label('Protocollo')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data interazione')
                    ->sortable()
                    ->date('d/m/Y h:m:s'),

                TextColumn::make('user.name')
                    ->label('Utente')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPecInteractions::route('/'),
            // 'create' => Pages\CreatePecInteraction::route('/create'),
            // 'view' => Pages\ViewPecInteraction::route('/{record}'),
            // 'edit' => Pages\EditPecInteraction::route('/{record}/edit'),
        ];
    }
}
