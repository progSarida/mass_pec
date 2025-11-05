<?php

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\ScopeType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    public static ?string $pluralModelLabel = 'Utenti';
    public static ?string $modelLabel = 'Utente';
    protected static ?string $navigationIcon = 'heroicon-s-users';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('name')->label('Nome')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                TextInput::make('email')->label('Email')
                    ->required()
                    ->columnSpan(2)
                    ->maxLength(255),
                TextInput::make('password')->label('Password')
                    ->columnSpan(2)
                    ->maxLength(255)
                    ->password()
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\CreateUser),
                // Toggle::make('is_admin')->label('Amministratore')
                //     ->columnSpan(2)
                //     ->onColor('success')
                //     ->offColor('danger'),
                Placeholder::make('')->label(''),
                Forms\Components\Select::make('roles')
                    ->label('Ruolo')
                    ->relationship('roles', 'name')
                    // ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome'),
                TextColumn::make('email')->label('Email'),
                // ToggleColumn::make('is_admin')
                //     ->label('Amministratore')
                //     ->onIcon('heroicon-s-check-circle')
                //     ->offIcon('heroicon-s-x-circle')
                //     ->onColor('success')
                //     ->offColor('danger'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Ruolo')
                    ->badge()
                    ->separator(', '),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestione';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
