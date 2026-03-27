<?php

namespace App\Filament\User\Resources\ArchivedEmailResource\RelationManagers;

use App\Enums\FlowType;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArchivedReceiversRelationManager extends RelationManager
{
    protected static string $relationship = 'archivedReceivers';

    protected static ?string $title = 'Destinatari';

    protected static ?string $modelLabel = 'Destinatario';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->flow_type === FlowType::ISSUED;                                                         // mostrata solo se email protocollata in uscita
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('recipient.description')
                    ->formatStateUsing(function ($record) {
                            $recipient = $record?->recipient;
                            $show = $recipient?->description ?? 'Destinatario non registrato';
                            return "{$show}";
                    }),
                TextInput::make('address')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                TextColumn::make('recipient_info') // Usa un nome descrittivo
                    ->label('Destinatario')
                    ->state(function ($record): string {
                        if ($record->recipient_id && $record->recipient) {
                            return $record->recipient->description ?? '';
                        }
                        return 'Destinatario non registrato';
                    })
                    ->badge(fn ($record) => !$record->sender && !$record->account)
                    ->color(fn ($record) => (!$record->sender && !$record->account) ? 'danger' : null)
                    ->limit(250),
                TextColumn::make('address'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
