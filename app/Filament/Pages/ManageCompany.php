<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\Company;
use App\Models\State;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageCompany extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'fas-building';
    protected static ?string $navigationGroup = 'Parametri';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Dati Gestore';
    protected static string $view = 'filament.pages.manage-company';
    protected static ?string $title = 'Dati Gestore';

    public ?array $data = [];

    public function mount(): void
    {
        $company = Company::first();
        $this->form->fill($company?->toArray() ?? []);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Assegna city_code al campo city
        $data['city'] = $data['city_code'] ?? null;

        return $data;
    }

    public function form(Form $form): Form
    {
        $italyId = State::where('name', 'Italy')->first()?->id;

        return $form
            ->schema([
                TextInput::make('name')->label('Denominazione - Cognome e Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(6),
                TextInput::make('vat_number')->label('Partita Iva')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                TextInput::make('tax_number')->label('Codice fiscale')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Select::make('state_id')->label('Paese')
                    ->options(State::all()->pluck('name', 'id')->toArray())
                    ->placeholder('')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default($italyId)
                    ->columnspan(2),
                TextInput::make('city_code')
                    ->label('Codice catastale')
                    ->required()
                    ->maxLength(4)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $city = City::where('code', $state)->first();
                        if ($city) {
                            $set('city_id', $city->id);
                        } else {
                            $set('city_id', null);
                        }
                    })
                    ->columnSpan(2),
                TextInput::make('address')->label('Indirizzo')
                    ->maxLength(255)
                    ->required()
                    ->columnSpan(6),
                // CAMBIATO: usa Select normale invece di relationship
                Select::make('city_id')->label('Città')
                    ->options(function () {
                        return City::all()->pluck('name', 'id')->toArray();
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $city = City::where('id', $state)->first();
                        if ($city) {
                            $set('city_code', $city->code);
                        } else {
                            $set('city_code', null);
                        }
                    })
                    ->live()
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('state_id') == $italyId)
                    ->columnSpan(4),
                TextInput::make('place')->label('Città')
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('state_id') != $italyId)
                    ->columnspan(4),
                TextInput::make('phone')->label('Telefono')
                    ->maxLength(255)
                    ->columnSpan(3),
                TextInput::make('email')->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->columnSpan(3),
                TextInput::make('pec')->label('Pec')
                    ->email()
                    ->maxLength(255)
                    ->columnSpan(3),
            ])
            ->columns(12)
            ->statePath('data');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Rimuovi il campo city e mantieni solo city_code
        if (isset($data['city'])) {
            unset($data['city']);
        }

        return $data;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $company = Company::first();

        if ($company) {
            $company->update($data);
        } else {
            Company::create($data);
        }

        Notification::make()
            ->success()
            ->title('Salvato')
            ->body('I dati del gestore sono stati salvati correttamente.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Salva')
                ->submit('save'),
        ];
    }
}
