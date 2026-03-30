<?php

namespace App\Filament\User\Resources;

use App\Enums\PreservationState;
use App\Filament\User\Resources\DailySummaryResource\Pages;
use App\Filament\User\Resources\DailySummaryResource\RelationManagers;
use App\Models\DailySummary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class DailySummaryResource extends Resource
{
    protected static ?string $model = DailySummary::class;

    public static ?string $pluralModelLabel = 'Registri giornalieri';
    protected static ?string $navigationIcon = 'fluentui-pen-20';
    protected static ?string $navigationLabel = 'Registri giornalieri';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\DateTimePicker::make('registration_date'),
                // Forms\Components\TextInput::make('filename')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('protocol_from')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('protocol_to')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('preservation_state')
                //     ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('registration_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Data registrazione')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Nome file')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data e ora creazione')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_protocol')
                    ->label('Da protocollo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('to_protocol')
                    ->label('A protocollo')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('preservation_state')
                //     ->label('Stato preservazione')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('preservation_state')
                    ->label('Stato preservazione')
                    ->searchable()
                    ->placeholder('......')
                    ->alignCenter()
                    // ->formatStateUsing(fn ($state) => $state ?? '......')
                    ->tooltip('Clicca per modificare lo stato')
                    ->action(
                        Tables\Actions\Action::make('updatePreservation')
                            ->label('Cambia stato preservazione')
                            ->icon('heroicon-o-pencil')
                            ->color('warning')
                            ->form([
                                Forms\Components\Select::make('preservation_state')
                                    ->label('Stato preservazione')
                                    ->options(PreservationState::class)
                                    ->default(fn ($record) => $record->preservation_state),
                            ])
                            ->action(function (array $data, $record) {
                                $record->update([
                                    'preservation_state' => $data['preservation_state']
                                ]);

                                Notification::make()
                                    ->title('Stato preservazione aggiornato')
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation()           // ← chiede conferma
                            ->modalHeading('Aggiorna stato preservazione')
                            ->modalDescription('Sei sicuro di voler modificare lo stato?')
                            ->modalSubmitActionLabel('Sì, aggiorna')
                            ->modalCancelActionLabel('Annulla')
                    ),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('hugeicons-pdf-02')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->filename ? Storage::temporaryUrl("daily_summaries/{$record->filename}.pdf", now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        return $record &&
                            !empty($record->filename) &&
                            Storage::disk(config('filesystems.default'))->exists("daily_summaries/{$record->filename}.pdf");
                    }),

                Tables\Actions\Action::make('download_xls')
                    ->label('')
                    ->tooltip('Scarica Excel')
                    ->icon('hugeicons-xls-02')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->filename ? Storage::temporaryUrl("daily_summaries/{$record->filename}.xlsx", now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        return $record &&
                            !empty($record->filename) &&
                            Storage::disk(config('filesystems.default'))->exists("daily_summaries/{$record->filename}.xlsx");
                    }),
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
            'index' => Pages\ListDailySummaries::route('/'),
            // 'create' => Pages\CreateDailySummary::route('/create'),
            // 'view' => Pages\ViewDailySummary::route('/{record}'),
            // 'edit' => Pages\EditDailySummary::route('/{record}/edit'),
        ];
    }
}
