<?php

namespace App\Filament\User\Resources;

use App\Enums\FlowType;
use App\Enums\MailType;
use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource\Pages;
use App\Filament\User\Resources\RegistryResource\RelationManagers;
use App\Filament\User\Resources\RegistryResource\RelationManagers\ParentChildLinkRelationManager;
use App\Filament\User\Resources\RegistryResource\RelationManagers\RegistryReceiversRelationManager;
use App\Models\Account;
use App\Models\Province;
use App\Models\Recipient;
use App\Models\Region;
use App\Models\Registry;
use App\Models\Sender;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RegistryResource extends Resource
{
    protected static ?string $model = Registry::class;

    public static ?string $pluralModelLabel = 'Protocollo';
    protected static ?string $navigationIcon = 'fluentui-book-20';
    protected static ?string $navigationLabel = 'Protocollo';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 1;

    private static $receiptsCache = [];

    public static function form(Form $form): Form
    {
        return $form
            ->columns(15)
            // ->disabled(function ($record, $livewire) {
            //     // 1. Sempre disabilitato in View
            //     if ($livewire instanceof \Filament\Resources\Pages\ViewRecord) {
            //         return true;
            //     }

            //     // 2. Mai disabilitato in Create (il record non esiste ancora)
            //     if (!$record) {
            //         return false;
            //     }

            //     // 3. Disabilita TUTTO se è in uscita ed è già stata inviata
            //     if ($record->isOutgoingEmail() && $record->send_date) {
            //         return true;
            //     }

            //     // NOTA: Non mettiamo il blocco per le mail ricevute qui,
            //     // altrimenti bloccheremmo anche 'other_senders'.
            //     return false;
            // })
            ->disabled(function ($record, $livewire) {
                 // Mai disabilitato in Create
                if (!$record) {
                    return false;
                }

                return true;
            })
            ->schema([
                Placeholder::make('')
                    ->content(fn ($record): string => $record?->void ? "Voce annullata il {$record?->void_date?->format('d/m/Y')}: {$record?->void_reason}"  : "")
                    ->extraAttributes(function ($record) {
                        $baseClasses = 'text-lg font-semibold border pb-1 pt-2 rounded-lg text-center';

                        return [
                            'class' => $baseClasses,
                            // Applichiamo lo stile inline per forzare sfondo, testo e bordo
                            'style' => 'background-color: #fee2e2 !important; color: #7f1d1d !important; border-color: #fecaca !important;',
                        ];
                    })
                    ->visible(fn($record) => $record?->void ?? false)
                    ->columnSpan(['sm' => 'full', 'md' => 'full']),
                Placeholder::make('')
                    ->content(fn ($record): string => Account::where('mail_type', MailType::PEC)->where('address', $record?->receiving_mail)->first()?->public_name ?? '')
                    ->extraAttributes(function ($record) {
                        $baseClasses = 'text-lg font-semibold border pb-1 pt-2';

                        $customClasses = [
                            'rounded-lg',           // Arrotondamento angoli
                            'text-center',          // Testo centrato
                            "bg-gray-100",          // Colore di sfondo dinamico
                            'text-gray-900',        // Assicura che il testo sia leggibile su sfondi chiari
                        ];

                        return [
                            'class' => $baseClasses . ' ' . implode(' ', $customClasses),
                        ];
                    })
                    ->visible(fn($record) => ($record && $record->flow_type == FlowType::RECEIVED) && $record->registry_origin_type !== RegistryOriginType::MANUAL)
                    ->columnSpan(['sm' => 'full', 'md' => 'full']),
                Placeholder::make('manage_registry_type')
                        ->label('')
                        // ->visible(fn($record) => $record && filled($record->pi_validation_id))
                        ->visible(fn($record) => $record?->manage_registry_type?->showType() )
                        ->content(function ($record) {
                            if (!$record?->manage_registry_type) {
                                return 'Nessuna gestione selezionata';
                            }

                            $dateString = $record?->manage_registry_date ? " il {$record?->manage_registry_date?->format('d/m/Y')}" : '';

                            return "{$record?->manage_registry_type->getLabel()}{$dateString}";
                        })
                        ->extraAttributes(function ($record) {
                            $statusEnum = $record?->manage_registry_type;

                            $color = $statusEnum?->getColor() ?? 'gray';

                            $bgColorClass = "bg-{$color}-100";

                            $borderColorClass = "border-{$color}-400";

                            $baseClasses = 'text-lg font-semibold border pb-1 pt-2';

                            $customClasses = [
                                'rounded-lg', // Arrotondamento angoli

                                'text-center', // Testo centrato

                                $bgColorClass, // Colore di sfondo dinamico
                                $borderColorClass,
                                'text-gray-900', // Assicura che il testo sia leggibile su sfondi chiari
                            ];

                            return [
                                'class' => $baseClasses . ' ' . implode(' ', $customClasses),
                            ];
                        })
                        ->columnSpan('full'),

                Section::make('Informazioni Principali')
                    ->columns(15)
                    ->schema([

                        TextInput::make('protocol_number')
                            ->label('Protocollo')
                            ->required()
                            // ->disabled()
                            ->dehydrated()
                            ->columnSpan(['sm' => 'full', 'md' => 2])
                            ->default(static::newProtocol()),

                        Select::make('flow_type')
                            ->label('Corrispondenza')
                            ->required()
                            ->live()
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->options(FlowType::class)
                            ->afterStateUpdated(function(Set $set, $state){
                                $lastIndex = Registry::where('flow_type', $state)->max('flow_index');
                                if ($lastIndex) {
                                    $newIndex = $lastIndex+1;
                                    $set('flow_index', $newIndex);
                                } else {
                                    $set('flow_index', 1);
                                }
                            })
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        Checkbox::make('is_email')
                            ->label('Posta elettronica')
                            ->live()
                            ->visible(fn($record) => $record?->flow_type !== FlowType::INTERNAL)
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->columnSpan(['sm' => 'full', 'md' => 3]),

                        // TextInput::make('flow_index')
                        //     ->label('Indice')
                        //     ->extraInputAttributes(['class' => 'text-right'])
                        //     ->required()
                        //     // ->disabled()
                        //     ->dehydrated()
                        //     ->columnSpan(['sm' => 'full', 'md' => 2]),

                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->required()
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->relationship('scopeType', 'name')
                            ->relationship(
                                name: 'scopeType',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->orderBy('position', 'asc')
                            )
                            ->columnSpan(['sm' => 'full', 'md' => 5]),

                        // TextInput::make('parent_reply')
                        //     ->label('Risposta a')
                        //     ->visible(fn ($record) => $record?->registry_origin_type == RegistryOriginType::REPLY)
                        //     ->disabled()
                        //     ->formatStateUsing(function ($record) {
                        //             $parent = $record?->registry;
                        //             return "[{$parent?->from}] {$parent?->subject} - {$parent?->receive_date?->format('d/m/Y H:m:s')}";
                        //         })
                        //     ->columnSpan(['sm' => 'full', 'md' => 'full'])
                        //     ->suffixAction(
                        //         Action::make('goToParent')
                        //             ->icon('heroicon-m-arrow-top-right-on-square')
                        //             ->tooltip('Vai alla mail originale')
                        //             ->url(function ($record) {
                        //                 $parent = $record?->registry;
                        //                 return $parent
                        //                     ? RegistryResource::getUrl('edit', ['record' => $parent])
                        //                     : null;
                        //             })
                        //             ->hidden(fn ($record) => !$record?->parent_id) // Nascondi se non c'è un parent
                        //     ),

                        // TextInput::make('forward_reply')
                        //     ->label('Inoltro di')
                        //     ->visible(fn ($record) => $record?->registry_origin_type == RegistryOriginType::FORWARD)
                        //     ->disabled()
                        //     ->formatStateUsing(function ($record) {
                        //             $parent = $record?->registry;
                        //             return "[{$parent?->from}] $record?->subject";
                        //         })
                        //     ->columnSpan(['sm' => 'full', 'md' => 'full'])
                        //     ->suffixAction(
                        //         Action::make('goToParent')
                        //             ->icon('heroicon-m-arrow-top-right-on-square')
                        //             ->tooltip('Vai alla mail originale')
                        //             ->url(function ($record) {
                        //                 $parent = $record?->registry;
                        //                 return $parent
                        //                     ? RegistryResource::getUrl('edit', ['record' => $parent])
                        //                     : null;
                        //             })
                        //             ->hidden(fn ($record) => !$record?->parent_id) // Nascondi se non c'è un parent
                        //     ),

                        Select::make('sender_id')
                            ->label('Mittente')
                            // ->hintAction(
                            //     Action::make('Nuovo')
                            //         ->icon('ri-user-2-line')
                            //         ->form(fn(Form $form) => RecipientResource::modalForm($form))
                            //         ->modalWidth('7xl')
                            //         ->modalHeading('')
                            //         ->modalSubmitActionLabel('Salva')
                            //         ->action(fn (array $data, Recipient $recipient, Set $set) => RegistryResource::saveRecipient($data, $recipient, $set))
                            //         ->hidden(fn ($livewire) => !$livewire instanceof \App\Filament\User\Resources\RegistryResource\Pages\CreateRegistry)
                            // )
                            // ->disabled(fn ($record) => $record?->isIngoingEmail() && $record->sender_id)
                            ->relationship(
                                name: 'sender',
                                titleAttribute: 'description',
                                modifyQueryUsing: fn ($query) => $query->with('adminType')
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->description . ($record->adminType ? ' - ' . $record->adminType?->name : ''))
                            ->getOptionLabelsUsing(fn ($record) => $record->description . ($record->adminType ? ' - ' . $record->adminType?->name : ''))
                            ->required()
                            ->live()
                            ->searchable()
                            ->visible(fn(Get $get, $record) => $get('flow_type') == FlowType::RECEIVED->value && $record->registry_origin_type !== RegistryOriginType::MANUAL)
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        Select::make('account_id')
                            ->label('Mittente')
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'public_name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->orderBy('position', 'asc')
                            )
                            ->required()
                            ->searchable()
                            ->visible(fn(Get $get, $record) => ($get('flow_type') == FlowType::ISSUED->value && !$record?->shipment_id) && $record->is_email)
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        TextInput::make('shipper')
                            ->label('Mittente')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state ?? Sender::first()?->public_name)
                            ->visible(fn(Get $get, $record) => $get('flow_type') == FlowType::ISSUED->value && $record?->shipment_id)
                            ->columnSpan(['sm' => 'full', 'md' => 8]),

                        TextInput::make('from')
                            ->label('Email mittente')
                            ->required()
                            ->visible(fn(Get $get, $record) => $get('flow_type') == (FlowType::RECEIVED->value || ($get('flow_type') == FlowType::ISSUED->value)) && $record->is_email)
                            ->columnSpan(['sm' => 'full', 'md' => 7]),

                        Placeholder::make('')
                            ->label('')
                            ->visible(fn(Get $get) => $get('flow_type') !== FlowType::RECEIVED->value)
                            ->columnSpan(['sm' => 'full', 'md' => 7]),

                        Select::make('other_senders')
                            ->label(fn($record) => $record->is_email ? 'Altri mittenti' : 'Mittenti')
                            ->multiple()
                            ->searchable()
                            // ->disabled(fn ($record) => $record?->other_senders != null)
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::RECEIVED->value)
                            ->live()
                            ->placeholder('Seleziona altri mittenti')
                            ->columnSpan(['sm' => 'full', 'md' => 'full'])
                            ->getSearchResultsUsing(function (string $search) {
                                if (strlen($search) < 3) {
                                    return [];
                                }
                                // Divido la ricerca in parole
                                $words = array_filter(explode(' ', $search));
                                $query = Recipient::query();
                                // Filtro per parole chiave
                                if (!empty($words)) {
                                    $query->where(function ($q) use ($words) {
                                        foreach ($words as $word) {
                                            $q->where(function ($subQuery) use ($word) {
                                                $subQuery->where('description', 'like', "%{$word}%")
                                                    ->orWhere('resp_surname', 'like', "%{$word}%")
                                                    ->orWhere('resp_name', 'like', "%{$word}%");
                                            });
                                        }
                                    });
                                }
                                return $query
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        // Qui decidi cosa salvare come valore (es. l'id o l'email)
                                        // e cosa mostrare come testo
                                        return [$item->id => "{$item->description}"];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelsUsing(function ($values) {
                                // Quando il record è salvato, voglio vedere l'email nei tag
                                return collect($values)->mapWithKeys(fn ($id) => [$id => static::labelRecipient($id)])->toArray();
                            }),

                        Select::make('registryReceivers')
                            ->label('Destinatari')
                            ->multiple()
                            ->visible(fn(Get $get, $record) => $get('flow_type') == FlowType::ISSUED->value && !$record?->shipment_id)
                            // Recuperiamo i record legati a questo modello specifico
                            ->options(function ($record) {
                                if (!$record) return [];
                                return $record->registryReceivers()
                                    ->with('recipient')
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => $item->recipient?->description ?  $item->recipient?->description . ' (' . $item->address . ')' : $item->address
                                    ]);
                            })
                            // Impostiamo i valori selezionati di default
                            ->formatStateUsing(fn ($record) => $record?->registryReceivers->pluck('id')->toArray())
                            ->dehydrated(false) // Non salva nulla al submit (sola lettura)
                            ->columnSpanFull(),

                        Select::make('interested_parties')
                            ->label('Altre parti interessate')
                            ->multiple()
                            ->searchable()
                            ->visible(fn(Get $get) => $get('flow_type') == FlowType::INTERNAL->value)
                            ->placeholder('Seleziona le parti interessate')
                            ->columnSpan(['sm' => 'full', 'md' => 15])
                            ->getSearchResultsUsing(function (string $search) {
                                if (strlen($search) < 3) {
                                    return [];
                                }
                                $words = array_filter(explode(' ', $search));
                                $query = Recipient::query();

                                if (!empty($words)) {
                                    $query->where(function ($q) use ($words) {
                                        foreach ($words as $word) {
                                            $q->where(function ($subQuery) use ($word) {
                                                $subQuery->where('description', 'like', "%{$word}%")
                                                    ->orWhere('resp_surname', 'like', "%{$word}%")
                                                    ->orWhere('resp_name', 'like', "%{$word}%");
                                            });
                                        }
                                    });
                                }

                                return $query
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        return [$item->id => $item->description];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelsUsing(function ($values) {
                                return collect($values)
                                    ->mapWithKeys(fn ($id) => [$id => Recipient::find($id)?->description ?? "ID: {$id}"])
                                    ->toArray();
                            }),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->required()
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->columnSpan(['sm' => 'full', 'md' => 15]),

                        // Textarea::make('body')
                        //     ->label('Messaggio')
                        //     ->rows(10)
                        //     ->columnSpan('full')
                        //     ->formatStateUsing(fn ($state) => $state ?? 'Nessun contenuto'),

                        Select::make('region_display')
                            ->label('Regione')
                            ->visible(fn($record) => $record && $record->shipment_id)
                            ->options(Region::pluck('name', 'id'))
                            ->formatStateUsing(fn ($record) => $record?->shipment?->region_id)
                            ->columnSpan(['sm' => 'full', 'md' => 7])
                            // ->disabled()
                            ->dehydrated(false), // Fondamentale: impedisce il salvataggio nel database

                        Select::make('province_display')
                            ->label('Provincia')
                            ->visible(fn($record) => $record && $record->shipment_id)
                            ->options(Province::pluck('name', 'id'))
                            ->formatStateUsing(fn ($record) => $record?->shipment?->province_id)
                            ->columnSpan(['sm' => 'full', 'md' => 8])
                            // ->disabled()
                            ->dehydrated(false),

                        // RichEditor::make('body')
                        //     ->label('Messaggio')
                        //     ->visible(fn ($record) => $record?->registry_origin_type?->showRich())
                        //     ->required(fn(Get $get) => $get('flow_type') !== FlowType::INTERNAL->value)
                        //     ->disabled(fn ($record) => $record?->isIngoingEmail())
                        //     ->default('') // Fondamentale per evitare l'errore "property not found"
                        //     ->formatStateUsing(fn ($record, $state) => $record->eml_body ?? ($state ?? ''))
                        //     ->columnSpanFull(),

                        RichEditor::make('body')
                            ->label('Messaggio')
                            ->visible(fn ($record) =>
                                !$record ||
                                $record?->registry_origin_type?->showRich() ||
                                ($record && static::containsHtml($record->eml_body ?? $record->body))
                            )
                            ->required(fn(Get $get) => $get('flow_type') !== FlowType::INTERNAL->value)
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->default('')
                            ->formatStateUsing(fn ($record, $state) => $record->eml_body ?? ($state ?? ''))
                            ->columnSpanFull(),

                        // Textarea::make('body')
                        //     ->label('Messaggio')
                        //     ->visible(fn ($record) => $record?->registry_origin_type?->showArea())
                        //     ->rows(10)
                        //     ->disabled(fn ($record) => $record?->isIngoingEmail())
                        //     ->columnSpan('full')
                        //     ->formatStateUsing(fn ($record, $state) => $record->eml_body ?? ($state ?? 'Nessun contenuto'))

                        Textarea::make('body')
                            ->label('Messaggio')
                            ->visible(fn ($record) =>
                                $record?->registry_origin_type?->showArea() &&
                                !($record && static::containsHtml($record->eml_body ?? $record->body))
                            )
                            ->rows(10)
                            // ->disabled(fn ($record) => $record?->isIngoingEmail())
                            ->columnSpan('full')
                            ->formatStateUsing(fn ($record, $state) => $record->eml_body ?? ($state ?? 'Nessun contenuto'))

                ]),

                DateTimePicker::make('receive_date')
                    ->label('Ricevuto il')
                    // ->disabled(fn ($record) => $record?->isIngoingEmail())
                    ->visible(fn (Get $get, $record) => $record?->isIngoingEmail() || $get('flow_type') == FlowType::RECEIVED->value)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->time(fn ($record) => $record?->is_email ?? true)
                    ->displayFormat('d/m/Y H:i:s')
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                DatePicker::make('download_date')
                    ->label('Scaricato il')
                    // ->disabled(fn ($record) => $record?->isIngoingEmail())
                    ->visible(fn ($record) => $record?->isIngoingEmail())
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('download_user_id')
                    ->label('Scaricato da')
                    // ->disabled(fn ($record) => $record?->isIngoingEmail())
                    ->visible(fn ($record) => $record?->isIngoingEmail())
                    ->relationship('downloadUser', 'name')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                DatePicker::make('send_date')
                    ->label('Inviato il')
                    ->visible(fn ($record) => $record?->isOutgoingEmail()
                                            || $record?->registry_origin_type == RegistryOriginType::SHIPMENT || ($record?->registry_origin_type == RegistryOriginType::MANUAL && $record->flow_type == FlowType::ISSUED))
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->displayFormat('d/m/Y')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),

                Forms\Components\Select::make('send_user_id')
                    ->label('Inviato da')
                    ->visible(fn ($record) => $record?->isOutgoingEmail()
                                            || $record?->registry_origin_type == RegistryOriginType::SHIPMENT)
                    ->relationship('downloadUser', 'name')
                    // ->visible(fn(Get $get) => $get('is_email'))
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                Placeholder::make('not_in')
                    ->label('')
                    ->visible(fn ($record) => $record?->isOutgoingEmail()
                                            || $record?->registry_origin_type == RegistryOriginType::SHIPMENT
                                            || $record?->registry_origin_type == RegistryOriginType::MANUAL)
                    // ->visible(fn(Get $get) => !$get('is_email'))
                    ->columnSpan(['sm' => '0', 'md' => 3]),

                Placeholder::make('manual')
                    ->label('')
                    ->visible(fn ($record) => $record?->registry_origin_type == RegistryOriginType::MANUAL)
                    // ->visible(fn(Get $get) => !$get('is_email'))
                    ->columnSpan(['sm' => '0', 'md' => 3]),

                DateTimePicker::make('created_at')
                    ->label('Registrato il')
                    // ->disabled()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(['sm' => 'full', 'md' => 3])
                    ->time(fn ($record) => $record?->is_email ?? true)
                    ->displayFormat('d/m/Y H:i:s')
                    // ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null)
                    ->visible(fn($record) => $record),

                Forms\Components\Select::make('register_user_id')
                    ->label('Registrato da')
                    // ->disabled()
                    ->relationship('registerUser', 'name')
                    ->visible(fn($record) => $record)
                    ->columnSpan(['sm' => 'full', 'md' => 3]),

                FileUpload::make('attachments')
                    ->label('Carica allegati')
                    ->multiple()
                    ->directory('registry/0')
                    ->preserveFilenames()
                    ->visible(fn($record) => !$record)
                    ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                        $disk = config('filesystems.default');
                        $directory = 'registry/0';
                        // creo cartella temporanea se non esiste
                        if (!Storage::disk($disk)->exists('registry/0')) {
                            Storage::disk($disk)->makeDirectory('registry/0');
                        }
                        // Estraiamo nome e estensione originali
                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension = $file->getClientOriginalExtension();

                        $protocol = explode('-', $record->protocol_number);
                        $protocolYear = $protocol[1] ?? 'XXXX';
                        $protocolCode = $protocol[2] ?? 'XXXXX';
                        $prefix = $protocolYear . '_' . $protocolCode . "_{$record->flow_type->getExt()}_";
                        $finalName = $prefix . $filename . '.' . $extension;
                        $counter = 1;

                        // Finché esiste un file con questo nome, incrementiamo il suffisso
                        while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                            $finalName = $filename . '_' . $counter . '.' . $extension;
                            $counter++;
                        }

                        return $finalName;
                    })
                    ->columnSpanFull(),

                Section::make('Allegati')
                    ->collapsed(fn($record) => $record)
                    ->visible(fn($record) => $record)
                    ->headerActions([
                        Action::make('downloadAll')
                            ->label('Scarica tutto (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            ->visible(function ($record) {
                                if (!$record || !$record?->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record?->attachment_path);
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('attachments.zip', [
                                'type' => $record?->getMorphClass(),
                                'id' => $record?->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('attachments')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record?->attachment_path) {
                                    return 'Nessuna cartella allegati trovata.';
                                }

                                $files = Storage::files($record?->attachment_path);

                                if (empty($files)) {
                                    return 'Nessun allegato.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));
                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📎</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->columnSpan('full'),
                    ]),

                Section::make('Documenti integrativi')
                    ->collapsed(fn($record) => $record)
                    ->visible(function ($record) {
                        $files = Storage::files($record?->attachment_path . '/related');
                        if (empty($files)) { return false; }
                        return true;
                    })
                    ->headerActions([
                        Action::make('downloadAll')
                            ->label('Scarica tutto (.zip)')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('gray')
                            ->size('sm')
                            ->visible(function ($record) {
                                if (!$record || !$record?->attachment_path) return false;
                                // Il pulsante appare solo se ci sono almeno 2 file
                                $files = Storage::files($record?->attachment_path . '/related');
                                return count($files) > 1;
                            })
                            ->url(fn ($record) => route('related.zip', [
                                'id' => $record?->id
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Placeholder::make('related')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record?->attachment_path) {
                                    return 'Nessuna cartella documenti integrativi trovata.';
                                }

                                $files = Storage::files($record?->attachment_path . '/related');

                                if (empty($files)) {
                                    return 'Nessun allegato.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));
                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📎</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->columnSpan('full'),
                    ]),

                Section::make('Allegati tecnici')
                    ->collapsed(fn($record) => $record)
                    ->visible(function ($record) {
                        $files = Storage::files($record?->attachment_path . '/tech');
                        if (empty($files)) { return false; }
                        return true;
                    })
                    ->schema([
                        Placeholder::make('tech')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record?->attachment_path) {
                                    return 'Nessuna cartella allegati tecnici trovata.';
                                }

                                $files = Storage::files($record?->attachment_path . '/tech');

                                if (empty($files)) {
                                    return 'Nessun allegato.';
                                }

                                return new HtmlString(
                                    collect($files)->map(function ($file) {
                                        $name = basename($file);
                                        $url = Storage::temporaryUrl($file, now()->addMinutes(15));
                                        return <<<HTML
                                        <div class="flex items-center gap-2 py-1">
                                            <span class="text-gray-400 text-xs">📎</span>
                                            <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })->implode('')
                                );
                            })
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // TextColumn::make('flow_type')
                //     ->label('Corr.')
                //     ->formatStateUsing(fn ($state) => $state?->getAcronym())
                //     ->badge()
                //     ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('flow_type')
                    ->label('')
                    ->tooltip(fn ($record) => $record->flow_type->getLabel())
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('registry_origin_type')
                    ->label('Origine')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('protocol_number')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scopeType.name')
                    ->label('Settore interno')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // TextColumn::make('from')
                //     ->label('Mittente')
                //     ->searchable()
                //     ->limit(250)
                //     ->tooltip(fn ($record) => $record?->from)
                //     ->sortable(),

                TextColumn::make('sender_info') // Usa un nome descrittivo
                    ->label('Mittente/Parti')
                    ->state(function ($record): string {
                        // Usiamo state() invece di formatStateUsing se la colonna non esiste nel DB
                        if ($record->sender_id && $record->sender) {
                            return $record->sender->description ?? '';
                        }
                        if ($record->account_id && $record->account) {
                            return $record->account->public_name ?? '';
                        }
                        if ($record->interested_parties && !empty($record->interested_parties)) {
                            // Recupera solo la colonna 'description' per ottimizzare la query
                            $descriptions = Recipient::whereIn('id', $record->interested_parties)
                                ->pluck('description') // Estrae solo i valori della colonna 'description'
                                ->implode(', ');      // Li concatena separandoli con virgola e spazio

                            return 'Sarida srl, ' . $descriptions;
                        }
                        if ($record->other_senders && !empty($record->other_senders)) {
                            // Recupera solo la colonna 'description' per ottimizzare la query
                            $descriptions = Recipient::whereIn('id', $record->other_senders)
                                ->pluck('description') // Estrae solo i valori della colonna 'description'
                                ->implode(', ');      // Li concatena separandoli con virgola e spazio

                            return $descriptions;
                        }
                        return '';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('from', 'like', "%{$search}%")
                            ->orWhereHas('sender', fn ($q) => $q->where('description', 'like', "%{$search}%"))
                            ->orWhereHas('account', fn ($q) => $q->where('public_name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable()
                    ->tooltip(function ($record): string {
                        if ($record->interested_parties && !empty($record->interested_parties)) {
                            // Recupera solo la colonna 'description' per ottimizzare la query
                            $descriptions = Recipient::whereIn('id', $record->interested_parties)
                                ->pluck('description') // Estrae solo i valori della colonna 'description'
                                ->implode(', ');      // Li concatena separandoli con virgola e spazio

                            return 'Sarida srl, ' . $descriptions;
                        }
                        if ($record->other_senders && !empty($record->other_senders)) {
                            // Recupera solo la colonna 'description' per ottimizzare la query
                            $descriptions = Recipient::whereIn('id', $record->other_senders)
                                ->pluck('description') // Estrae solo i valori della colonna 'description'
                                ->implode(', ');      // Li concatena separandoli con virgola e spazio

                            return $descriptions;
                        }
                        return '';
                    })
                    ->limit(250),

                Tables\Columns\TextColumn::make('receivers')
                    ->label('Destinatari')
                    ->state(fn ($record) => $record?->registryReceivers?->count() ?? 0)
                    ->formatStateUsing(function ($state) {
                        if ($state === 0) return '';
                        return $state . ' ' . ($state === 1 ? 'destinatario' : 'destinatari');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('registryReceivers', fn ($q) => $q->where('address', 'like', "%{$search}%"));
                        });
                    })
                    ->tooltip(function ($record) {
                        $receivers = $record?->registryReceivers;
                        if (! $receivers || $receivers->isEmpty()) {
                            // return 'Nessun destinatario';
                            return '';
                        }
                        return static::getRecipientsName($receivers->pluck('address')) ;
                    }),

                TextColumn::make('date')
                    ->label('Invio/Ricezione')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            // Usiamo una logica SQL identica a quella del tuo PHP
                            DB::raw('CASE WHEN flow_type = "' . FlowType::ISSUED->value . '" THEN send_date ELSE receive_date END'),
                            $direction
                        );
                    })
                    ->state(function ($record) {
                        if($record->flow_type == FlowType::ISSUED) return $record->send_date;
                        else if($record->flow_type == FlowType::RECEIVED) return $record->receive_date;
                    })
                    ->date('d/m/Y h:m:s'),

                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn ($record) => $record?->subject),

                IconColumn::make('attachment_path')
                    ->label('Allegati')
                    ->icon(function($record) {
                        $files = Storage::files($record?->attachment_path);
                        if (!empty($files)) { return 'fluentui-mail-attach-20'; }
                        else { return ''; }
                    })
                    ->color(function ($record) {
                        $files = Storage::files($record?->attachment_path);
                        if (!empty($files)) { return 'info'; }
                        else { return ''; }
                    })->tooltip(function ($record) {
                        $files = Storage::files($record->attachment_path);
                        $count = count($files);

                        if ($count === 0) {
                            return 'Nessun allegato presente';
                        }

                        return $count === 1
                            ? "C'è 1 allegato"
                            : "Ci sono {$count} allegati";
                    }),

                IconColumn::make('esito_report')
                    ->label('Esito invio')
                    ->getStateUsing(function ($record) {
                        return static::checkReceipts($record);
                    })
                    ->icon(function ($state, $record) {
                        if(!$state) {
                            return null;
                        }

                        [$sent, $accepted, $delivered] = explode(',', $state);                                              // inviate, accettate, consegnate
                        $count = $record->registryReceivers()->count();                                                     // numero destinatari

                        if($sent == 0) return 'heroicon-o-envelope';                                                        // nessuna mail inviata

                        if($sent == $count) {                                                                               // tutte le mail inviate
                            if($sent == $delivered) return 'heroicon-o-check-circle';                                       // numero inviate = numero consegnate
                            else if($sent == $accepted) return 'heroicon-o-clock';                                          // numero inviate = numero accettate
                            else return 'heroicon-o-exclamation-triangle';                                                  // numero accettate < numero inviate => errore invio
                        }
                        else return 'heroicon-o-exclamation-triangle';                                                      // non tutte le mail sono state elaborate
                    })
                    ->color(function ($state, $record) {
                        if(!$state) return 'gray';

                        [$sent, $accepted, $delivered] = explode(',', $state);
                        $count = $record->registryReceivers()->count();

                        if($sent == 0) return 'danger';

                        if($sent == $count) {                                                                               // tutte le mail inviate
                            if($sent == $delivered) return 'success';                                                       // numero inviate = numero consegnate
                            else if($sent == $accepted) return 'info';                                                      // numero inviate = numero accettate
                            else return 'warning';                                                                          // numero accettate < numero inviate => errore invio
                        }
                        else return 'warning';                                                                              // non tutte le mail sono state elaborate
                    })
                    ->tooltip(function ($state, $record) {
                        if (!$state) return null;

                        [$sent, $accepted, $delivered] = explode(',', $state);
                        $count = $record->registryReceivers()->count();

                        if($sent == 0) return 'Non inviata';

                        if($accepted == $delivered && $accepted == 0) return 'Inviata, ricevute da scaricare';

                        $tooltip = $sent == 1 ? "1 inviata" : "{$sent} inviate";
                        // $tooltip .= $accepted == 1 ? ", 1 accetatta" : ", {$accepted} accettate";
                        $tooltip .= $delivered == 1 ? " e 1 consegnata" : " e {$delivered} consegnate";

                        $tooltip .= " su {$count} destinatari";

                        return $tooltip;
                    }),

                IconColumn::make('manage_registry_type')
                    ->label('Gestione')
                    ->tooltip(fn (?ManageRegistryType $state): ?string => $state?->getLabel()),

                TextColumn::make('body')
                    ->label('Messaggio')
                    ->searchable()
                    ->limit(50)
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? Str::limit(strip_tags($state), 50) : '—')
                    ->tooltip(function ($record) {
                        if (!$record?->body_preview) return 'Nessun contenuto';
                        $preview = strip_tags($record?->body_preview);
                        return Str::limit($preview, 500);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('receive_date')
                    ->label('Ricevuto il')
                    ->date('d/m/Y H:i:S')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('send_date')
                    ->label('Data invio')
                    ->date('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sendUser.name')
                    ->label('Inviata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('download_date')
                    ->label('Scaricato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('downloadUser.name')
                    ->label('Scaricato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registerUser.name')
                    ->label('Registrato da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Tables\Columns\TextColumn::make('attachments')
                //     ->label('Allegati')
                //     ->formatStateUsing(fn ($state) => $state ? 'Apri cartella' : '—')
                //     ->url(fn ($record) => $record->attachment_path ? asset('storage/' . $record->attachment_path) : null)
                //     ->openUrlInNewTab()
                //     ->icon('heroicon-o-folder-open')
                //     ->color('primary'),
            ])
            ->filtersFormWidth('5xl')
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('flow_type')
                    ->label('Corrispondenza')
                    ->options(FlowType::class)
                    // ->searchable()
                    ->columnSpan(1),
                SelectFilter::make('registry_origin_type')
                    ->label('Origine')
                    ->options(RegistryOriginType::class)
                    // ->searchable()
                    ->columnSpan(1),
                SelectFilter::make('account_id')
                    ->label('Account')
                    ->options(Account::where('mail_type', MailType::PEC)->pluck('public_name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        $accountId = $data['value'] ?? null;

                        if (!$accountId) {
                            return $query;
                        }

                        $account = Account::find($accountId);

                        return $query->where(function ($q) use ($accountId, $account) {
                            $q->where('account_id', $accountId)
                            ->orWhere('receiving_mail', $account->address);
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $accountId = $data['value'] ?? null;

                        if (!$accountId) {
                            return [];
                        }

                        $account = Account::find($accountId);

                        return ['Account: ' . $account->public_name];
                    })
                    ->columnSpan(1),
                SelectFilter::make('sender')
                    ->label('Mittente')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array =>
                        Recipient::where('description', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('description', 'id')
                            ->toArray()
                    )
                    ->getOptionLabelsUsing(fn (array $values): array =>
                        Recipient::whereIn('id', $values)->pluck('description', 'id')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $senderIds = $data['values'] ?? [];

                        if (empty($senderIds)) {
                            return $query;
                        }

                        return $query->where(function ($q) use ($senderIds) {
                            // Ricerca sul mittente principale (molto veloce se sender_id ha un indice)
                            $q->whereIn('sender_id', $senderIds)
                            // Ricerca nel campo JSON
                            ->orWhere(function ($subQuery) use ($senderIds) {
                                foreach ($senderIds as $id) {
                                    // Usiamo orWhereJsonContains, ma limitato ai record necessari
                                    $subQuery->orWhereJsonContains('other_senders', (string)$id)
                                            ->orWhereJsonContains('other_senders', (int)$id);
                                }
                            });
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['values'])) return [];

                        $labels = Recipient::whereIn('id', $data['values'])
                            ->pluck('description')
                            ->toArray();

                        return ['Mittenti: ' . implode(', ', $labels)];
                    })
                    ->columnSpan(2),
                SelectFilter::make('is_email')
                    ->label('Tipo')
                    ->options([
                        'si' => 'Posta elettronica',
                        'no' => 'Posta ordinaria',
                    ])
                    ->placeholder('Tutta')
                    ->query(function (Builder $query, array $data): Builder {
                        // Recuperiamo il valore. In Filament SelectFilter, il dato è in $data['value']
                        $value = $data['value'] ?? null;

                        // Se non è selezionato nulla, non applichiamo filtri alla query
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'si') {
                            // Mostra Registry relativo ad una comunicazione tramite posta elettronica
                            return $query->where('is_email', true);
                        }

                        if ($value === 'no') {
                            // Mostra Registry relativo ad una comunicazione tramite posta ordinaria
                            return $query->where('is_email', false);
                        }

                        return $query;
                    }),
                SelectFilter::make('recipient')
                    ->label('Destinatario')
                    ->multiple()
                    ->searchable()
                    // 1. NON usare ->options() qui se hai molti record.
                    // Usiamo getSearchResultsUsing per caricare solo i primi 50 che corrispondono alla ricerca.
                    ->getSearchResultsUsing(fn (string $search): array =>
                        Recipient::where('description', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('description', 'id')
                            ->toArray()
                    )
                    // 2. Serve a Filament per visualizzare il nome corretto dei tag selezionati
                    ->getOptionLabelsUsing(fn (array $values): array =>
                        Recipient::whereIn('id', $values)->pluck('description', 'id')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $recipientIds = $data['values'] ?? [];

                        if (empty($recipientIds)) {
                            return $query;
                        }

                        // 3. whereHas è corretto se hai una relazione Many-to-Many o One-to-Many
                        return $query->whereHas('registryReceivers', function ($q) use ($recipientIds) {
                            $q->whereIn('recipient_id', $recipientIds);
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['values'])) return [];

                        // 4. Ottimizzazione: ritorniamo un array per gli indicatori (Filament v3 style)
                        $recipients = Recipient::whereIn('id', $data['values'])
                            ->pluck('description')
                            ->toArray();

                        return ['Destinatari: ' . implode(', ', $recipients)];
                    })
                    ->columnSpan(2),
                SelectFilter::make('manage_registry_type')
                    ->label('Gestione')
                    ->options(ManageRegistryType::class)
                    ->multiple()
                    ->columnSpan(1),
                Filter::make('registration_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('registration_from_date')
                            ->label('Registrazione dal')
                            ->columnSpan(1),
                        DatePicker::make('registration_to_date')
                            ->label('Registrazione al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['registration_from_date'])) {
                            $query->whereDate('created_at', '>=', $data['registration_from_date']);
                        }
                        if (! empty($data['registration_to_date'])) {
                            $query->whereDate('created_at', '<=', $data['registration_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['registration_from_date'] && $data['registration_to_date']) {
                            return "Registrazione dal {$data['registration_from_date']} al {$data['registration_to_date']}";
                        }
                        if ($data['registration_from_date']) {
                            return "Registrazione dal {$data['registration_from_date']}";
                        }
                        if ($data['registration_to_date']) {
                            return "Registrazione al {$data['registration_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
                SelectFilter::make('register_user_id')
                    ->label('Registrato da')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->columnSpan(1),
                Filter::make('send_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('send_from_date')
                            ->label('Invio dal')
                            ->columnSpan(1),
                        DatePicker::make('send_to_date')
                            ->label('Invio al')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['send_from_date'])) {
                            $query->whereDate('send_date', '>=', $data['send_from_date']);
                        }
                        if (! empty($data['send_to_date'])) {
                            $query->whereDate('send_date', '<=', $data['send_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['send_from_date'] && $data['send_to_date']) {
                            return "Invio dal {$data['send_from_date']} al {$data['send_to_date']}";
                        }
                        if ($data['send_from_date']) {
                            return "Invio dal {$data['send_from_date']}";
                        }
                        if ($data['send_to_date']) {
                            return "Invio al {$data['send_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
                SelectFilter::make('esito_invio')
                    ->label('Esito invio')
                    ->options([
                        'non_inviato' => 'Non inviato',
                        'consegnato' => 'Tutto consegnato',
                        'parziale' => 'Consegnato parzialmente',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        // Filtriamo solo le email in uscita
                        $query->where('is_email', true);

                        if ($value === 'non_inviato') {
                            // Email in uscita non ancora inviate
                            return $query->whereIn('registry_origin_type', [RegistryOriginType::SEND_EMAIL, RegistryOriginType::REPLY, RegistryOriginType::FORWARD])
                                        ->whereNull('send_date');
                        }

                        if ($value === 'consegnato') {
                            // Tutte le email sono state consegnate
                            return $query->whereNotNull('send_date')
                                ->whereHas('registryReceivers')
                                ->whereDoesntHave('registryReceivers', function ($q) {
                                    $q->whereIn('pec_status', [
                                        PecStatus::WAITING,
                                        PecStatus::ACCEPTED,
                                        PecStatus::NOT_DELIVERED,
                                        PecStatus::NOT_ACCEPTED
                                    ]);
                                });
                        }

                        if ($value === 'parziale') {
                            // Almeno una email non è stata consegnata
                            return $query->whereNotNull('send_date')
                                ->whereHas('registryReceivers', function ($q) {
                                    $q->whereIn('pec_status', [
                                        PecStatus::WAITING,
                                        PecStatus::ACCEPTED,
                                        PecStatus::NOT_DELIVERED,
                                        PecStatus::NOT_ACCEPTED
                                    ]);
                                });
                        }

                        return $query;
                    })
                    ->columnSpan(1),
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
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
            RegistryReceiversRelationManager::class,
            // ForwardsRelationManager::class,
            // RepliesRelationManager::class,
            ParentChildLinkRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistries::route('/'),
            'create' => Pages\CreateRegistry::route('/create'),
            'edit' => Pages\EditRegistry::route('/{record}/edit'),
            'view' => Pages\ViewRegistry::route('/{record}'),
        ];
    }

    private static function newProtocol(): string
    {
        $lastRegistry = Registry::orderBy('created_at', 'desc')->first();

        if ($lastRegistry) {
            $parts = explode('-', $lastRegistry->protocol_number);

            if (count($parts) !== 3 || $parts[0] !== 'P') {
                return 'P-' . today()->year . '-00001';
            }

            $lastYear = (int) $parts[1];
            $lastNumber = (int) $parts[2];
            $currentYear = today()->year;

            if ($lastYear === $currentYear) {
                $newNumber = $lastNumber + 1;
                return 'P-' . $currentYear . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            } else {
                return 'P-' . $currentYear . '-00001';
            }
        }
        return 'P-' . today()->year . '-00001';
    }

    private static function labelRecipient($id): string
    {
        $rec = Recipient::find($id);

        if ($rec) {
            // return "{$rec->description} - {$rec->resp_surname} {$rec->resp_name} <{$email}>";
            return "{$rec->description}";
        }

        return "[]";
    }

    private static function checkReceiptsOld($registry)
    {
// Log::info("{$registry->protocol_number} -----------------------------------------------------");
        if(!$registry->isOutgoingEmail()) { return null; }
        $sent = 0;
        $delivered = 0;
        foreach($registry->registryReceivers as $receiver){
// Log::info("{$receiver->id}: {$receiver->pec_status->getLabel()}");
            if($receiver->pec_status == PecStatus::ACCEPTED) { $sent = ++$sent; }
            if($receiver->pec_status == PecStatus::NOT_ACCEPTED) {  }
            if($receiver->pec_status == PecStatus::DELIVERED) { $sent = ++$sent; $delivered = ++$delivered; }
            if($receiver->pec_status == PecStatus::NOT_DELIVERED) { $sent = ++$sent; }
        }
// Log::info("Inviati: {$sent} - Consegnati: {$delivered} ---------------------------------------");
        $report = [
            'sent' => $sent,
            'delivered' => $delivered,
        ];
        return $report;
    }

    private static function checkReceipts($registry)
    {
        $cacheKey = $registry->id;

        if (isset(self::$receiptsCache[$cacheKey])) {
            return self::$receiptsCache[$cacheKey];
        }

        // Log::info("{$registry->protocol_number} -----------------------------------------------------");

        if(!$registry->isOutgoingEmail()) {
            self::$receiptsCache[$cacheKey] = null;
            return null;
        }

        $sent = 0;
        $accepted = 0;
        $delivered = 0;

        foreach($registry->registryReceivers as $receiver){
            // Log::info("{$receiver->id}: {$receiver->pec_status->getLabel()}");

            if($receiver->message_id) {
                $sent++;
            }
            if($receiver->pec_status == PecStatus::ACCEPTED) {
                $accepted++;
            }
            if($receiver->pec_status == PecStatus::DELIVERED) {
                $accepted++;
                $delivered++;
            }
            if($receiver->pec_status == PecStatus::NOT_DELIVERED) {
                $accepted++;
            }
        }

        // Log::info("Inviati: {$sent} - Consegnati: {$delivered} ---------------------------------------");

        // Restituisci una stringa invece di un array
        $report = "{$sent},{$accepted},{$delivered}";

        self::$receiptsCache[$cacheKey] = $report;
        return $report;
    }

    private static function getRecipientsName($addresses)
    {
        $output = "";
        $count = count($addresses);
        foreach($addresses as $key =>$address){
            $recipient = Recipient::findByEmail($address);
            if($recipient) {
                $output .= $recipient->description;
                if($key < $count - 1)
                    $output .= ", ";
            }
        }
        return $output;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('shipment');
    }

    /**
     * Verifica se il contenuto contiene tag HTML
     */
    private static function containsHtml(?string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        // Rimuove spazi bianchi e confronta con la versione stripped
        $stripped = strip_tags($content);

        // Se dopo aver rimosso i tag il contenuto è diverso, significa che c'erano tag HTML
        return $content !== $stripped;
    }
}
