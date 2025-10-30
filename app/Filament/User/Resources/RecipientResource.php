<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Filament\User\Resources\RecipientResource\Pages;
use App\Filament\User\Resources\RecipientResource\RelationManagers;
use App\Models\City;
use App\Models\Recipient;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Platform;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecipientResource extends Resource
{
    protected static ?string $model = Recipient::class;
    public static ?string $pluralModelLabel = 'Destinatari';
    public static ?string $modelLabel = 'Destinatario';
    protected static ?string $navigationIcon = 'fluentui-person-mail-20';
    protected static ?string $navigationLabel = 'Destinatari';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('description')->label('Descrizione')
                    ->columnSpan('full'),
                Select::make('admin_type_id')->label('Tipo ente')
                    ->relationship(name: 'adminType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(6),
                Select::make('istat_type_id')->label('Tipo Istat')
                    ->relationship(name: 'istatType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(6),
                TextInput::make('code_ipa')->label('Codice Ipa')
                    ->columnSpan(3),
                TextInput::make('acronym')->label('Acronimo')
                    ->columnSpan(3),
                Select::make('city_id')->label('Comune')
                    ->relationship(name: 'city', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $city = City::find($state);
                        $set('city_code', $city->code);
                        $set('city_cap', $city->zip_code);
                        $set('city_province', $city->province->code);
                        $set('city_region', $city->province->region->name);
                    })
                    ->afterStateHydrated(function (callable $set, $state, $record) {
                        if($record){
                            $city = City::find($state);
                            $set('city_code', $city->code);
                            $set('city_cap', $city->zip_code);
                            $set('city_province', $city->province->code);
                            $set('city_region', $city->province->region->name);
                        }
                    })
                    ->columnSpan(6),
                TextInput::make('address')->label('Indirizzo')
                    ->columnSpan('full'),
                Placeholder::make('place_1')->label('')->columnSpan(3),
                TextInput::make('city_code')->label('CC')->disabled()->columnSpan(2),
                TextInput::make('city_cap')->label('Cap')->disabled(fn ($state) => !str_contains($state, 'xx'))->columnSpan(2),
                TextInput::make('city_province')->label('Provincia')->disabled()->columnSpan(2),
                TextInput::make('city_region')->label('Regione')->disabled()->columnSpan(3),
                Section::make('Responsabile')
                    // ->description('')
                    ->heading(fn ($record) => $record ? "Responsabile: {$record->resp_title} {$record->resp_surname} {$record->resp_name} - CF: {$record->resp_tax_code}" : 'Responsabile')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make('resp_title')->label('Titolo')
                            ->columnSpan(3),
                        TextInput::make('resp_surname')->label('Cognome')
                            ->columnSpan(3),
                        TextInput::make('resp_name')->label('Nome')
                            ->columnSpan(3),
                        TextInput::make('resp_tax_code')->label('Codice FIscale')
                            ->columnSpan(3),
                    ]),
                Section::make('Email')
                    ->heading(function ($get, $record) {
                        $mails = [
                            $get('mail_1') ?? ($record->mail_1 ?? ''),
                            $get('mail_2') ?? ($record->mail_2 ?? ''),
                            $get('mail_3') ?? ($record->mail_3 ?? ''),
                            $get('mail_4') ?? ($record->mail_4 ?? ''),
                            $get('mail_5') ?? ($record->mail_5 ?? ''),
                        ];

                        $filled = collect($mails)->filter(fn ($mail) => filled($mail))->count();
                        $total = 5;

                        if($record) return "Email ($filled/$total)";
                        else return "Email";
                    })
                    ->collapsed(fn ($record) => $record && ( filled($record->mail_1) || filled($record->mail_2) ||
                        filled($record->mail_3) || filled($record->mail_4) || filled($record->mail_5) )
                    )
                    ->columns(12)
                    ->schema([
                        TextInput::make('mail_1')->label('Mail 1')
                            ->columnSpan(6),
                        Placeholder::make('place_mail_1')->label('')->columnSpan(3),
                        Select::make('mail_type_1')->label('Tipo')
                            ->options(MailType::class)
                            ->columnSpan(3),
                        TextInput::make('mail_2')->label('Mail 2')
                            ->columnSpan(6),
                        Placeholder::make('place_mail_2')->label('')->columnSpan(3),
                        Select::make('mail_type_2')->label('Tipo')
                            ->options(MailType::class)
                            ->columnSpan(3),
                        TextInput::make('mail_3')->label('Mail 3')
                            ->columnSpan(6),
                        Placeholder::make('place_mail_3')->label('')->columnSpan(3),
                        Select::make('mail_type_3')->label('Tipo')
                            ->options(MailType::class)
                            ->columnSpan(3),
                        TextInput::make('mail_4')->label('Mail 4')
                            ->columnSpan(6),
                        Placeholder::make('place_mail_4')->label('')->columnSpan(3),
                        Select::make('mail_type_4')->label('Tipo')
                            ->options(MailType::class)
                            ->columnSpan(3),
                        TextInput::make('mail_5')->label('Mail 5')
                            ->columnSpan(6),
                        Placeholder::make('place_mail_5')->label('')->columnSpan(3),
                        Select::make('mail_type_5')->label('Tipo')
                            ->options(MailType::class)
                            ->columnSpan(3),
                    ]),
                Section::make('Altri recapiti')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make('site')->label('Sito istituzionale')
                            ->columnSpan(12),
                        TextInput::make('url_facebook')->label('Facebook')
                            ->columnSpan(6),
                        TextInput::make('url_twitter')->label('Twitter')
                            ->columnSpan(6),
                        TextInput::make('url_googleplus')->label('Google')
                            ->columnSpan(6),
                        TextInput::make('url_youtube')->label('Youtube')
                            ->columnSpan(6),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('adminType.name')
                    ->label('Tipo ente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('istatType.name')
                    ->label('Tipo Istat')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.name')
                    ->label('Comune'),
                TextColumn::make('city.province.code')
                    ->label('Provincia'),
                TextColumn::make('city.province.region.name')
                    ->label('Descrizione'),
                TextColumn::make('resp_title')
                    ->label('Titolo Resp.'),
                TextColumn::make('resp_surname')
                    ->label('Cognome Resp.')
                    ->searchable(),
                TextColumn::make('resp_name')
                    ->label('Nome Resp.')
                    ->searchable(),
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
            'index' => Pages\ListRecipients::route('/'),
            'create' => Pages\CreateRecipient::route('/create'),
            'edit' => Pages\EditRecipient::route('/{record}/edit'),
        ];
    }
}
