<?php

namespace App\Filament\User\Resources;

use App\Enums\MailType;
use App\Filament\User\Resources\RecipientResource\Pages;
use App\Models\AdminType;
use App\Models\City;
use App\Models\IstatType;
use App\Models\OfficeType;
use App\Models\Province;
use App\Models\Recipient;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecipientResource extends Resource
{
    protected static ?string $model = Recipient::class;
    public static ?string $pluralModelLabel = 'Interlocutori';
    public static ?string $modelLabel = 'Interlocutore';
    protected static ?string $navigationIcon = 'fluentui-person-mail-20-o';
    protected static ?string $navigationLabel = 'Interlocutori';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('description')->label('Nome e Cognome/Denominazione')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, $record, $livewire) {
                        if (blank($state)) {
                            // Se svuota il campo, chiudiamo l'eventuale notifica aperta
                            \Filament\Notifications\Notification::make('duplicate-alert')
                                ->duration(1)
                                ->send();
                            return;
                        }

                        $normalized = str($state)->trim()->squish()->lower()->toString();

                        $existing = Recipient::where('description_search', $normalized)
                            ->when($record, fn($q) => $q->where('id', '!=', $record?->id))
                            ->first();

                        if ($existing) {
                            // $allMails = collect(range(1, 5)) // Rimesso a 5 per coerenza col DB
                            //     ->map(function($i) use ($existing) {
                            //         $mail = $existing->{"mail_$i"};
                            //         $type = $existing->{"mail_type_$i"};
                            //         if (blank($mail)) return null;
                            //         $typeName = $type ? $type->getLabel() : 'Email';
                            //         return "• {$typeName}: {$mail}";
                            //     })
                            //     ->filter()
                            //     ->implode('<br>');

                            $allMails = $existing->emails
                                ->map(function($email) {
                                    $typeName = $email->mail_type ? $email->mail_type->getLabel() : 'Email';
                                    return "• {$typeName}: {$email->email}";
                                })
                                ->implode('<br>');

                            // Recupero il nome dell'AdminType in modo più sicuro
                            $adminTypeName = $existing->adminType?->name ?? 'Tipo interlocutore non specificato';
                            $mailList = filled($allMails) ? $allMails : 'Nessuna mail registrata';

                            \Filament\Notifications\Notification::make('duplicate-alert') // ID statico importante!
                                ->warning()
                                ->title('Possibile Duplicato Rilevato')
                                ->body("
                                    <b>{$existing->description}</b><br>
                                    {$adminTypeName}<br>
                                    <b>Città:</b> {$existing->city?->name}<br>
                                    <b>Email:</b><br>
                                    {$mailList}
                                ")
                                ->duration(10000)
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('view')
                                        ->label('Vedi scheda')
                                        ->url(RecipientResource::getUrl('view', ['record' => $existing->id]))
                                        ->openUrlInNewTab(),
                                ])
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make('duplicate-alert') // ID statico importante!
                                ->duration(1)
                                ->send();
                        }
                    })
                    // ->hint(function ($state, $record) {
                    //     if (blank($state)) return null;

                    //     $normalized = str($state)->trim()->squish()->lower()->toString();
                    //     $existing = \App\Models\Recipient::where('description_search', $normalized)
                    //         ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                    //         ->first();

                    //     if ($existing) {
                    //         $description = $existing->description;
                    //         $comune = $existing->city?->name;
                    //         $pec = $existing->mail_type_1 == MailType::PEC ? $existing->mail_1
                    //                 : ($existing->mail_type_2 == MailType::PEC ? $existing->mail_2
                    //                 : ($existing->mail_type_3 == MailType::PEC ? $existing->mail_3
                    //                 : ($existing->mail_type_4 == MailType::PEC ? $existing->mail_4
                    //                 : ($existing->mail_type_5 == MailType::PEC ? $existing->mail_5 : ''))));
                    //         return "$description già presente, $comune, PEC: $pec";
                    //     }

                    //     return null;
                    // })
                    // ->hintColor('danger')
                    // ->hintIcon(function ($state, $record) {
                    //     if (blank($state)) return null;

                    //     $normalized = str($state)->trim()->squish()->lower()->toString();
                    //     $existing = \App\Models\Recipient::where('description_search', $normalized)
                    //         ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                    //         ->first();

                    //     if ($existing) {
                    //         return 'heroicon-m-exclamation-triangle';
                    //     }

                    //     return null;
                    // })
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, $fail) use ($record) {
                            // 1. Normalizziamo l'input attuale
                            $normalized = str($value)->trim()->squish()->lower()->toString();

                            // 2. Query personalizzata sulla colonna description_search
                            $exists = Recipient::where('description_search', $normalized)
                                ->when($record, fn($q) => $q->where('id', '!=', $record?->id)) // Ignora il record attuale in edit
                                ->exists();

                            if ($exists) {
                                $fail('Esiste già un interlocutore con questa descrizione o simile.');
                            }
                        },
                    ])
                    ->columnSpan('full'),
                Select::make('admin_type_id')->label('Tipo interlocutore')
                    // ->required()
                    ->relationship(name: 'adminType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                Select::make('istat_type_id')->label('Tipo Istat')
                    // ->required()
                    ->relationship(name: 'istatType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                TextInput::make('code_ipa')->label('Codice Ipa')
                    // ->required()
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                TextInput::make('acronym')->label('Acronimo')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                Select::make('city_id')->label('Comune')
                    ->required()
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
                        if($record && $state){
                            $city = City::find($state);
                            $set('city_code', $city->code);
                            // $set('city_cap', $city->zip_code);
                            $set('city_province', $city->province->code);
                            $set('city_region', $city->province->region->name);
                        }
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                TextInput::make('address')->label('Indirizzo')
                    // ->required()
                    ->columnSpan('full'),
                Placeholder::make('place_1')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                TextInput::make('city_code')->label('CC')->disabled()->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_cap')->label('Cap')
                    // ->disabled(fn ($state) => !str_contains($state, 'xx'))
                    ->default(fn ($record) => $record?->city_cap ?? $record?->city->zip_code)->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_province')->label('Provincia')->disabled()->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_region')->label('Regione')->disabled()->columnSpan(['sm' => 'full', 'md' => 3]),
                Section::make('Responsabile')
                    // ->description('')
                    ->heading(fn ($record) => $record ? "Responsabile: {$record->resp_title} {$record->resp_surname} {$record->resp_name} - CF: {$record->resp_tax_code}" : 'Responsabile')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make('resp_title')->label('Titolo')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_surname')->label('Cognome')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_name')->label('Nome')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_tax_code')->label('Codice FIscale')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                    ]),
                // Section::make('Email')
                //     ->heading(function ($get, $record) {
                //         $mails = [
                //             $get('mail_1') ?? ($record?->mail_1 ?? ''),
                //             $get('mail_2') ?? ($record?->mail_2 ?? ''),
                //             $get('mail_3') ?? ($record?->mail_3 ?? ''),
                //             $get('mail_4') ?? ($record?->mail_4 ?? ''),
                //             $get('mail_5') ?? ($record?->mail_5 ?? ''),
                //         ];

                //         $filled = collect($mails)->filter(fn ($mail) => filled($mail))->count();
                //         $total = 5;

                //         if($record) return "Email ($filled/$total)";
                //         else return "Email";
                //     })
                //     ->collapsed(fn ($record) => $record && ( filled($record->mail_1) || filled($record->mail_2) ||
                //         filled($record->mail_3) || filled($record->mail_4) || filled($record->mail_5) )
                //     )
                //     ->columns(12)
                //     ->schema([
                //         TextInput::make('mail_1')->label('Mail 1')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_1')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_1')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_1')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_2')->label('Mail 2')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_2')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_2')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_2')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_3')->label('Mail 3')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_3')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_3')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_3')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_4')->label('Mail 4')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_4')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_4')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_4')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_5')->label('Mail 5')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_5')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_5')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_5')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //     ]),
                Section::make('Email')
                    ->heading(function ($record) {
                        if ($record) {
                            $filled = $record->emails()->count();
                            if ($filled > 0)
                                return "Email ({$filled})";
                            else
                                return "Nessuna email inserita";
                        }

                        return "Email";
                    })
                    ->collapsed(fn ($record) => $record && $record->emails()->count() > 0)
                    ->columns(12)
                    ->schema([
                        Repeater::make('emails')
                            ->relationship('emails')
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Repeater $component, $state) {
                                if (blank($state)) {
                                    // Se lo stato è vuoto o null, inizializzalo con un array contenente
                                    // un set di chiavi vuote corrispondenti ai tuoi input
                                    $component->state([
                                        'item1' => [
                                            'email' => null,
                                            'mail_type' => null,
                                            'office_type_id' => null,
                                        ],
                                    ]);
                                }
                            })
                            ->label('')
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                                Select::make('mail_type')
                                    ->label('Tipo')
                                    ->options(MailType::class)
                                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                                Select::make('office_type_id')
                                    ->label('Ufficio')
                                    ->options(OfficeType::pluck('name', 'id'))
                                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Aggiungi Email')
                            ->reorderable(true)
                            ->orderColumn('order')
                            ->collapsed(fn ($record) => $record && $record->email)
                            ->itemLabel(fn (array $state): ?string => $state['email'] ?? null)
                            ->columnSpan('full'),
                    ]),
                Section::make('Altri recapiti')
                    ->collapsed(fn ($record) => $record)
                    ->columns(12)
                    ->schema([
                        TextInput::make(name: 'phone')->label('Telefono')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('site')->label('Sito istituzionale')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_facebook')->label('Facebook')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_twitter')->label('Twitter')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_googleplus')->label('Google')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_youtube')->label('Youtube')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('description_search', 'like', "%{$search}%");
                    }),
                    // ->searchable(),
                TextColumn::make('adminType.name')
                    ->label('Tipo interlocutore'),
                TextColumn::make('istatType.name')
                    ->label('Tipo Istat'),
                TextColumn::make('city.name')
                    ->label('Comune'),
                TextColumn::make('city.province.code')
                    ->label('Provincia'),
                TextColumn::make('city.province.region.name')
                    ->label('Regione'),
                TextColumn::make('resp_title')
                    ->label('Titolo Resp.')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resp_surname')
                    ->label('Cognome Resp.')
                    // ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resp_name')
                    ->label('Nome Resp.')
                    // ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('region_id')
                    ->label('Regione')
                    ->options(fn () => Region::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            $query->whereHas('city.province.region', fn (Builder $q) =>
                                $q->where('id', $value)
                            );
                        }
                    })
                    ->searchable()
                    ->preload(false),

                SelectFilter::make('province_id')
                    ->label('Provincia')
                    ->options(fn () => Province::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            $query->whereHas('city.province', fn (Builder $q) =>
                                $q->where('id', $value)
                            );
                        }
                    })
                    ->searchable()
                    ->preload(false),

                SelectFilter::make('city_id')
                    ->label('Comune')
                    ->options(fn () => City::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            $query->where('city_id', $value);
                        }
                    })
                    ->searchable()
                    ->preload(false),

                SelectFilter::make('admin_type_id')
                    ->label('Tipo interlocutore')
                    ->options(fn () => AdminType::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            $query->where('admin_type_id', $value);
                        }
                    })
                    ->searchable()
                    ->preload(false),

                SelectFilter::make('istat_type_id')
                    ->label('Tipo istat')
                    ->options(fn () => IstatType::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            $query->where('istat_type_id', $value);
                        }
                    })
                    ->searchable()
                    ->preload(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
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
            'view' => Pages\ViewRecipient::route('/{record}')
        ];
    }

    // public static function modalForm(Form $form): Form
    public static function modalForm(Form $form, $from): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, $record, $livewire) {
                        if (blank($state)) {
                            // Se svuota il campo, chiudiamo l'eventuale notifica aperta
                            \Filament\Notifications\Notification::make('duplicate-alert')
                                ->duration(1)
                                ->send();
                            return;
                        }

                        $normalized = str($state)->trim()->squish()->lower()->toString();

                        $existing = Recipient::where('description_search', $normalized)
                            ->when($record, fn($q) => $q->where('id', '!=', $record?->id))
                            ->first();

                        if ($existing) {
                            // $allMails = collect(range(1, 5)) // Rimesso a 5 per coerenza col DB
                            //     ->map(function($i) use ($existing) {
                            //         $mail = $existing->{"mail_$i"};
                            //         $type = $existing->{"mail_type_$i"};
                            //         if (blank($mail)) return null;
                            //         $typeName = $type ? $type->getLabel() : 'Email';
                            //         return "• {$typeName}: {$mail}";
                            //     })
                            //     ->filter()
                            //     ->implode('<br>');

                            $allMails = $existing->emails
                                ->map(function($email) {
                                    $typeName = $email->mail_type ? $email->mail_type->getLabel() : 'Email';
                                    return "• {$typeName}: {$email->email}";
                                })
                                ->implode('<br>');

                            // Recupero il nome dell'AdminType in modo più sicuro
                            $adminTypeName = $existing->adminType?->name ?? 'Tipo interlocutore non specificato';
                            $mailList = filled($allMails) ? $allMails : 'Nessuna mail registrata';

                            \Filament\Notifications\Notification::make('duplicate-alert') // ID statico importante!
                                ->warning()
                                ->title('Possibile Duplicato Rilevato')
                                ->body("
                                    <b>{$existing->description}</b><br>
                                    {$adminTypeName}<br>
                                    <b>Città:</b> {$existing->city?->name}<br>
                                    <b>Email:</b><br>
                                    {$mailList}
                                ")
                                ->duration(10000)
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('view')
                                        ->label('Vedi scheda')
                                        ->url(RecipientResource::getUrl('view', ['record' => $existing->id]))
                                        ->openUrlInNewTab(),
                                ])
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make('duplicate-alert') // ID statico importante!
                                ->duration(1)
                                ->send();
                        }
                    })
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, $fail) use ($record) {
                            // 1. Normalizziamo l'input attuale
                            $normalized = str($value)->trim()->squish()->lower()->toString();

                            // 2. Query personalizzata sulla colonna description_search
                            $exists = Recipient::where('description_search', $normalized)
                                ->when($record, fn($q) => $q->where('id', '!=', $record?->id)) // Ignora il record attuale in edit
                                ->exists();

                            if ($exists) {
                                $fail('Esiste già un interlocutore con questa descrizione o simile.');
                            }
                        },
                    ])
                    ->columnSpan('full'),
                Select::make('admin_type_id')->label('Tipo interlocutore')
                    // ->required()
                    ->relationship(name: 'adminType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                Select::make('istat_type_id')->label('Tipo Istat')
                    // ->required()
                    ->relationship(name: 'istatType', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                TextInput::make('code_ipa')->label('Codice Ipa')
                    // ->required()
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                TextInput::make('acronym')->label('Acronimo')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                Select::make('city_id')->label('Comune')
                    ->required()
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
                            // $set('city_cap', $city->zip_code);
                            $set('city_province', $city->province->code);
                            $set('city_region', $city->province->region->name);
                        }
                    })
                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                TextInput::make('address')->label('Indirizzo')
                    // ->required()
                    ->columnSpan('full'),
                Placeholder::make('place_1')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                TextInput::make('city_code')->label('CC')->disabled()->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_cap')->label('Cap')
                    // ->disabled(fn ($state) => !str_contains($state, 'xx'))
                    ->default(fn ($record) => $record?->city_cap ?? $record?->city->zip_code)->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_province')->label('Provincia')->disabled()->columnSpan(['sm' => 'full', 'md' => 2]),
                TextInput::make('city_region')->label('Regione')->disabled()->columnSpan(['sm' => 'full', 'md' => 3]),
                Section::make('Responsabile')
                    // ->description('')
                    ->heading(fn ($record) => $record ? "Responsabile: {$record->resp_title} {$record->resp_surname} {$record->resp_name} - CF: {$record->resp_tax_code}" : 'Responsabile')
                    ->collapsed()
                    ->columns(12)
                    ->schema([
                        TextInput::make('resp_title')->label('Titolo')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_surname')->label('Cognome')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_name')->label('Nome')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                        TextInput::make('resp_tax_code')->label('Codice FIscale')
                            // ->required()
                            ->columnSpan(['sm' => 'full', 'md' => 3]),
                    ]),
                // Section::make('Email')
                //     ->heading(function ($get, $record) {
                //         $mails = [
                //             $get('mail_1') ?? ($record?->mail_1 ?? ''),
                //             $get('mail_2') ?? ($record?->mail_2 ?? ''),
                //             $get('mail_3') ?? ($record?->mail_3 ?? ''),
                //             $get('mail_4') ?? ($record?->mail_4 ?? ''),
                //             $get('mail_5') ?? ($record?->mail_5 ?? ''),
                //         ];

                //         $filled = collect($mails)->filter(fn ($mail) => filled($mail))->count();
                //         $total = 5;

                //         if($record) return "Email ($filled/$total)";
                //         else return "Email";
                //     })
                //     // ->collapsed(fn ($record) => $record && ( filled($record->mail_1) || filled($record->mail_2) ||
                //     //     filled($record->mail_3) || filled($record->mail_4) || filled($record->mail_5) )
                //     // )
                //     ->collapsed(false)
                //     ->columns(12)
                //     ->schema([
                //         TextInput::make('mail_1')->label('Mail 1')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_1')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_1')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_1')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_2')->label('Mail 2')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_2')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_2')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_2')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_3')->label('Mail 3')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_3')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_3')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_3')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_4')->label('Mail 4')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_4')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_4')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_4')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         TextInput::make('mail_5')->label('Mail 5')
                //             ->columnSpan(['sm' => 'full', 'md' => 6]),
                //         // Placeholder::make('place_mail_5')->label('')->columnSpan(['sm' => 0, 'md' => 3]),
                //         Select::make('mail_type_5')->label('Tipo')
                //             ->options(MailType::class)
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //         Select::make('office_type_id_5')->label('Ufficio')
                //             ->options(OfficeType::pluck('name', 'id'))
                //             ->columnSpan(['sm' => 'full', 'md' => 3]),
                //     ]),
                Section::make('Email')
                     ->heading(function ($record) {
                        if ($record) {
                            $filled = $record->emails()->count();
                            if ($filled > 0)
                                return "Email ({$filled})";
                            else
                                return "Nessuna email inserita";
                        }

                        return "Email";
                    })
                    ->collapsed(fn ($record) => $record && $record->emails()->count() > 0)
                    ->columns(12)
                    ->schema([
                        Repeater::make('emails')
                            // ->relationship('emails')
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Repeater $component, $state) use ($from) {
                                if (blank($state)) {
                                    // Se lo stato è vuoto o null, inizializzalo con un array contenente
                                    // un set di chiavi vuote corrispondenti ai tuoi input
                                    $component->state([
                                        'item1' => [
                                            'email' => $from,
                                            'mail_type' => null,
                                            'office_type_id' => null,
                                        ],
                                    ]);
                                }
                            })
                            ->label('')
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->columnSpan(['sm' => 'full', 'md' => 6]),
                                Select::make('mail_type')
                                    ->label('Tipo')
                                    ->options(MailType::class)
                                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                                Select::make('office_type_id')
                                    ->label('Ufficio')
                                    ->options(OfficeType::pluck('name', 'id'))
                                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Aggiungi Email')
                            ->reorderable(true)
                            ->orderColumn('order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['email'] ?? null)
                            ->columnSpan('full'),
                    ]),
                Section::make('Altri recapiti')
                    // ->collapsed(fn ($record) => $record)
                    ->collapsed()
                    ->columns(12)
                    ->schema([
                        TextInput::make(name: 'phone')->label('Telefono')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('site')->label('Sito istituzionale')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_facebook')->label('Facebook')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_twitter')->label('Twitter')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_googleplus')->label('Google')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                        TextInput::make('url_youtube')->label('Youtube')
                            ->columnSpan(['sm' => 'full', 'md' => 6]),
                    ]),
            ]);
    }
}
