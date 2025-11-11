<?php

namespace App\Filament\Resources;

use App\Enums\ConnectionSafetyType;
use App\Enums\MailProtocolType;
use App\Enums\MailType;
use App\Enums\ManagementType;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;
    public static ?string $pluralModelLabel = 'Account Posta';
    public static ?string $modelLabel = 'Account';
    protected static ?string $navigationIcon = 'heroicon-s-inbox-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('public_name')->label('Nome')->columnSpan(6)
                    ->required(),
                TextInput::make('address')->label('Indirizzo')->columnSpan(6)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('username', $state);
                        $set('out_username', $state);
                    }),
                Select::make('management_type')->label('Tipo gestione')->columnSpan(4)
                    ->required()
                    ->options(ManagementType::class),
                Select::make('mail_type')->label('Tipo mail')->columnSpan(4)
                    ->required()
                    ->options(MailType::class),
                Select::make('connection_safety_type')->label('Tipo cifratura')->columnSpan(4)
                    ->required()
                    ->options(ConnectionSafetyType::class),
                Section::make('Configurazione ricezione')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make('in_mail_server')->label('Server')->columnSpan(4)
                            ->required(),
                        Select::make('in_mail_protocol_type')->label('Protocollo')->columnSpan(3)
                            ->required()
                            ->options(MailProtocolType::class),
                        TextInput::make('in_mail_port')->label('Porta')->columnSpan(2)
                            ->required(),
                        Checkbox::make('delete')->label('Cancella mail')->columnSpan(2),
                        TextInput::make('deleta_after_days')->label('dopo (gg)')->columnSpan(1),
                        TextInput::make('username')->label('Username')->columnSpan(5)
                            ->required(),
                        TextInput::make('password')->label('Password')->columnSpan(5)
                            ->required()
                            ->password()
                            ->revealable()
                            ->afterStateHydrated(fn ($set, $record) => $set('password', $record?->password ? decrypt($record->password) : null) )
                            ->dehydrateStateUsing(fn ($state) => $state ? encrypt($state) : ''),
                        Checkbox::make('download')->label('Scarica mail')->columnSpan(2),
                    ]),
                Section::make('Configurazione invio')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make('out_mail_server')->label('Server')->columnSpan(6)
                            ->required(),
                        Select::make('out_mail_protocol_type')->label('Protocollo')->columnSpan(3)
                            ->required()
                            ->options(MailProtocolType::class),
                        TextInput::make('out_mail_port')->label('Porta')->columnSpan(3)
                            ->required(),
                        Checkbox::make('out_authentication')->label('Richiesta autenticazione')->columnSpan(4),
                        TextInput::make('out_username')->label('Username')->columnSpan(4)
                            ->required(),
                        TextInput::make('out_password')->label('Password')->columnSpan(4)
                            ->required()
                            ->password()
                            ->revealable()
                            ->afterStateHydrated(fn ($set, $record) => $set('out_password', $record?->out_password ? decrypt($record->out_password) : null) )
                            ->dehydrateStateUsing(fn ($state) => $state ? encrypt($state) : ''),
                    ]),
                ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Indirizzo')
                    ->searchable(),
                TextColumn::make('mail_type')
                    ->label('Tipo mail'),
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
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestione';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }
}
