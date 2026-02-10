<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Enums\MailType;
use App\Filament\User\Resources\ShipmentResource;
use App\Models\AdminType;
use App\Models\Attachment;
use App\Models\IstatType;
use App\Models\OfficeType;
use App\Models\Recipient;
use App\Models\Region;
use App\Models\Province;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use ZipArchive;

class CreateShipment extends CreateRecord
{
    protected static string $resource = ShipmentResource::class;
    public $selectedReceiversCount = 0;

    public function getTitle(): string
    {
        return "Nuova spedizione";
    }

    public function mount(): void
    {
        parent::mount();                                                                                                // IMPORTANTE: chiamo prima il parent

        $this->selectedReceiversCount = $this->countSelectedEmails();

        if (!isset($this->data['mail_body'])) {                                                                         // Inizializzo esplicitamente mail_body
            $this->data['mail_body'] = '';                                                                              // (necessario per far funzionare RichEditor)
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['out_password'])) {
            $data['out_password'] = encrypt($data['out_password']);
        }
        if (!empty($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        return $data;
    }

    // Stato persistente
    public array $attachmentList = [];                                                                                  // id degli allegati selezionati
                                                                                                                        // [1, 3, 7]
    public array $receiverList = [];                                                                                    // id e campi dei destinatari selezionati
                                                                                                                        // [12 => ['mail_1', 'mail_3'], 15 => ['mail_2']]
    public array $receiverFilters = [                                                                                   // filtri ricerca destinatari
        'mail_type' => null,
        'region_id' => null,
        'province_id' => null,
        'deselect_all' => false,
        'admin_types' => null,
        'office_types' => null,
    ];

    public $mail_type = null;
    public $region_id = null;
    public $province_id = null;
    public $admin_types = null;
    public $office_types = null;

    // public function mount(): void
    // {
    //     $this->selectedReceiversCount = $this->countSelectedEmails();
    // }

    protected function getHeaderActions(): array
    {
        return [
            // === ALLEGATI ===
            Actions\Action::make('attachments')
                ->label(fn () => 'Allegati' . (!empty($this->attachmentList) ? ' (' . count($this->attachmentList) . ')' : ''))
                ->modalHeading('Selezione allegati')
                ->form(function () {
                    $attachments = $this->getAttachmentsForForm();

                    if (empty($attachments)) {
                        return [
                            Placeholder::make('no_attachments')
                                ->label('')
                                ->content(new HtmlString('
                                    <div class="text-center py-8">
                                        <p class="text-gray-500 dark:text-gray-400">
                                            Non ci sono allegati disponibili
                                        </p>
                                    </div>
                                '))
                        ];
                    }

                    return [
                        Repeater::make('attachments')
                            ->label('')
                            ->schema([
                                TextInput::make('description')->label('Descrizione')->disabled()->columnSpan(6),
                                DatePicker::make('date')->label('Data caricamento')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->disabled()
                                    ->displayFormat('d/m/Y')
                                    ->columnSpan(3),
                                Placeholder::make('blank')->label('')->columnSpan(1),
                                Checkbox::make('selected')->label('Allega')->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->statePath('attachments')
                    ];
                })
                ->fillForm(fn () => ['attachments' => $this->getAttachmentsForForm()])
                ->action(function (array $data) {
                    $this->attachmentList = collect($data['attachments'] ?? [])
                        ->filter(fn($item) => !empty($item['selected']))
                        ->pluck('id')
                        ->toArray();
                    $count = count($this->attachmentList);
                    $this->notifySelection($count, 'allegato', 'allegato(i) selezionato(i)');
                })
                ->modalSubmitActionLabel('Conferma')
                ->modalCancelActionLabel('Annulla'),

            // === DESTINATARI PEC ===
            Actions\Action::make('receivers')
                ->label(fn () => $this->selectedReceiversCount > 0
                    ? "Destinatari PEC ({$this->selectedReceiversCount})"
                    : 'Destinatari PEC'
                )
                ->modalHeading('Selezione Destinatari PEC')
                ->modalWidth('5xl')
                ->form([
                    // Filtri persistenti
                    Grid::make(9)
                        ->schema([
                            Select::make('mail_type')
                                ->label('Tipo email')
                                ->required()
                                // ->options(MailType::class)
                                ->options(
                                    collect(MailType::cases())
                                        ->filter(fn (MailType $type) => $type->show())
                                        ->mapWithKeys(fn (MailType $type) => [
                                            $type->value => $type->getLabel() // Forza il recupero della stringa
                                        ])
                                        ->toArray()
                                )
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $this->receiverFilters['mail_type'] = $state;
                                    $set('region_id', null);
                                    $set('province_id', null);
                                    $set('admin_types', null);
                                    $set('office_types', null);
                                    $this->receiverFilters['region_id'] = null;
                                    $this->receiverFilters['province_id'] = null;
                                    $this->receiverFilters['admin_types'] = null;
                                    $this->receiverFilters['office_types'] = null;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(2),
                            Select::make('region_id')
                                ->label('Regione')
                                ->required()
                                ->options(Region::pluck('name', 'id'))
                                ->default($this->receiverFilters['region_id'])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('province_id', null);
                                    $set('admin_types', null);
                                    $set('office_types', null);
                                    $this->receiverFilters['region_id'] = $state;
                                    $this->receiverFilters['province_id'] = null;
                                    $this->receiverFilters['admin_types'] = null;
                                    $this->receiverFilters['office_types'] = null;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(2),
                            Select::make('province_id')
                                ->label('Provincia')
                                // ->required()
                                ->options(fn (callable $get) => $get('region_id')
                                    ? Province::where('region_id', $get('region_id'))->pluck('name', 'id')
                                    : []
                                )
                                ->default($this->receiverFilters['province_id'])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('admin_types', null);
                                    $set('office_types', null);
                                    $this->receiverFilters['province_id'] = $state;
                                    $this->receiverFilters['admin_types'] = null;
                                    $this->receiverFilters['office_types'] = null;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(3),
                            Checkbox::make('deselect_all')
                                ->label('Deseleziona tutti')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $this->receiverFilters['deselect_all'] = $state;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(2),
                            Select::make('admin_types')
                                ->label('Tipo ente')
                                ->options(AdminType::pluck('name', 'id'))
                                ->multiple()
                                ->default($this->receiverFilters['admin_types'])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('office_types', null);
                                    $this->receiverFilters['admin_types'] = $state;
                                    $this->receiverFilters['office_types'] = null;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(6),
                            Select::make('office_types')
                                ->label('Ufficio')
                                ->options(OfficeType::pluck('name', 'id'))
                                ->multiple()
                                ->default($this->receiverFilters['office_types'])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $this->receiverFilters['office_types'] = $state;
                                    $this->receiverList = [];
                                })
                                ->columnSpan(3),
                        ]),

                    Placeholder::make('recipients_list')                                                                // elenco dinamico con checkbox persistenti
                        ->label('Destinatari')
                        ->content(fn (callable $get) => $this->renderRecipientsList(
                            $get('mail_type') ?? $this->receiverFilters['mail_type'],
                            $get('region_id') ?? $this->receiverFilters['region_id'],
                            $get('province_id') ?? $this->receiverFilters['province_id'],
                            $get('deselect_all') ?? $this->receiverFilters['deselect_all'],
                            $get('admin_types') ?? $this->receiverFilters['admin_types'],
                            $get('office_types') ?? $this->receiverFilters['office_types']
                        ))
                        ->extraAttributes([
                            'style' => 'min-height: 4vh; max-height: 60vh; overflow-y: auto;'
                        ])
                        ->visible(fn (callable $get) =>
                            !empty($get('region_id') ?? $this->receiverFilters['region_id']) ||
                            !empty($get('province_id') ?? $this->receiverFilters['province_id'])
                        ),
                ])
                ->fillForm(fn () => [
                    'region_id' => $this->receiverFilters['region_id'],
                    'province_id' => $this->receiverFilters['province_id'],
                    'mail_type' => $this->receiverFilters['mail_type'] ?? MailType::PEC,
                    'admin_types' => $this->receiverFilters['admin_types'],
                    'office_types' => $this->receiverFilters['office_types'],
                ])
                ->action(function () {
                    // Rimuovo le email deselezionate (false o null)
                    foreach ($this->receiverList as $recipientId => $emails) {
                        $this->receiverList[$recipientId] = array_filter(
                            $emails,
                            fn($value) => $value === true
                        );

                        // Rimuovo il recipient se non ha più email selezionate
                        if (empty($this->receiverList[$recipientId])) {
                            unset($this->receiverList[$recipientId]);
                        }
                    }
                    $count = $this->countSelectedEmails();
                    $this->selectedReceiversCount = $count;
                    $this->notifySelection($count, 'destinatario', 'destinatario(i) selezionato(i)');
                })
                ->modalSubmitActionLabel('Conferma selezione')
                ->modalCancelActionLabel('Annulla'),
        ];
    }

    // === UTILITY ===
    private function notifySelection(int $count, string $singular, string $plural): void
    {
        if ($count === 0) {
            Notification::make()->title("Nessun $singular selezionato")->warning()->send();
            return;
        }
        Notification::make()->title("$count $plural")->success()->send();
    }

    private function countSelectedEmails(): int
    {
        // Conta solo le email SPUNTATE (in $receiverList)
        return collect($this->receiverList)->sum(fn($emails) => count($emails));
    }

    // === ALLEGATI ===
    private function getAttachmentsForForm(): array
    {
        return Attachment::all()->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'description' => $attachment->description,
                'date' => Carbon::parse($attachment->upload_date)->format('Y-m-d'),
                'selected' => in_array($attachment->id, $this->attachmentList),
            ];
        })->toArray();
    }

    // === DESTINATARI ===
    // private function renderRecipientsList($mailType, $regionId, $provinceId, $istatTypes, $officeTypes): HtmlString
    // {
    //     if (!$mailType || !$regionId || !$provinceId) {
    //         return new HtmlString('<em class="text-gray-500">Impostare tutti i filtri obbligatori per vedere i destinatari.</em>');
    //     }

    //     $officeNames = OfficeType::pluck('name', 'id');

    //     $recipients = Recipient::with('city.province.region')
    //         ->when($provinceId, function ($q) use ($provinceId, $regionId) {
    //             $validProvince = $regionId
    //                 ? Province::where('id', $provinceId)->where('region_id', $regionId)->exists()
    //                 : false;

    //             if ($validProvince) {
    //                 return $q->whereHas('city.province', fn($p) => $p->where('id', $provinceId));
    //             }

    //             return $q;
    //         })
    //         ->when(!$provinceId && $regionId, fn($q) => $q->whereHas('city.province.region', fn($r) => $r->where('id', $regionId)))
    //         ->when(!$provinceId && !$regionId, fn($q) => $q->whereRaw('1 = 0'))
    //         ->when(!empty($istatTypes), fn($q) => $q->whereIn('istat_type_id', $istatTypes))
    //         ->get();

    //     if ($recipients->isEmpty()) {
    //         return new HtmlString('<em class="text-gray-500">Nessun destinatario trovato per i filtri selezionati.</em>');
    //     }

    //     // NUOVO: Inizializza receiverList solo per nuovi destinatari
    //     foreach ($recipients as $recipient) {
    //         for ($i = 1; $i <= 5; $i++) {
    //             $mail = $recipient->{"mail_$i"};
    //             $type = $recipient->{"mail_type_$i"};
    //             $oType = $recipient->{"office_type_id_$i"};

    //             $officeFilter = !empty($officeTypes) ? in_array($oType, $officeTypes) : true;

    //             // if (!empty($mail) && $type === MailType::PEC) {
    //             if (!empty($mail) && $type === $mailType && $officeFilter) {
    //                 // Inizializza solo se il destinatario non ha ancora selezioni
    //                 if (!isset($this->receiverList[$recipient->id])) {
    //                     $this->receiverList[$recipient->id] = [];
    //                 }

    //                 // Spunta di default solo se non è mai stato selezionato prima
    //                 if (!array_key_exists("mail_{$i}", $this->receiverList[$recipient->id])) {
    //                     $this->receiverList[$recipient->id]["mail_{$i}"] = true;
    //                 }
    //             }
    //         }
    //     }

    //     // $html = '<div class="space-y-4 max-h-96 overflow-y-auto p-1">';
    //     $html = '<div class="space-y-4 p-1">';

    //     foreach ($recipients as $recipient) {
    //         $emails = [];
    //         for ($i = 1; $i <= 5; $i++) {
    //             $mail = $recipient->{"mail_$i"};
    //             $type = $recipient->{"mail_type_$i"};
    //             $oType = $recipient->{"office_type_id_$i"};
    //             $officeFilter = !empty($officeTypes) ? in_array($oType, $officeTypes) : true;
    //             // $officeFilter = true;
    //             if (!empty($mail) && $type === $mailType && $officeFilter) {
    //                 $emails[] = ['field' => "mail_$i", 'email' => $mail, 'mtype' => $type, 'otype' => $oType];
    //             }
    //         }
    //         if (empty($emails)) continue;

    //         $cityName = $recipient->city?->name ?? 'N/D';
    //         $provinceCode = $recipient->city?->province?->code ?? 'N/D';

    //         $html .= '<div class="border rounded-lg p-4 bg-gray-50">';
    //         $html .= '<p class="font-medium text-sm mb-2">' . e($recipient->description) . ' - ' . e($cityName) . ' (' . e($provinceCode) . ')' . '</p>';
    //         $html .= '<div class="space-y-1 text-sm">';

    //         foreach ($emails as $email) {
    //             $field = "receiverList.{$recipient->id}.{$email['field']}";
    //             $checkboxId = 'rcpt-' . $recipient->id . '-' . $email['field'];

    //             // Verifica se è spuntato
    //             $checked = !empty($this->receiverList[$recipient->id][$email['field']]);

    //             $office = $officeNames[$email['otype']] ?? 'N/D';

    //             $html .= '
    //             <div class="flex items-center gap-3">
    //                 <input
    //                     type="checkbox"
    //                     wire:model.live="' . $field . '"
    //                     id="' . $checkboxId . '"
    //                     class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4 flex-shrink-0"
    //                     ' . ($checked ? 'checked' : '') . '
    //                 >
    //                 <label for="' . $checkboxId . '" class="cursor-pointer select-none text-sm">
    //                     <span class="font-medium">' . e($email['email']) . '</span>
    //                     <span class="text-gray-500 text-xs ml-1">(' . $email['mtype']->getLabel() . ' - ' . $office . ')</span>
    //                 </label>
    //             </div>';
    //         }

    //         $html .= '</div></div>';
    //     }

    //     $html .= '</div>';
    //     return new HtmlString($html);
    // }

    private function renderRecipientsList($mailType, $regionId, $provinceId, $deselectAll, $adminTypes, $officeTypes): HtmlString
    {
        // 1. Verifica Filtri Base
        // if (!$mailType || !$provinceId) {
        //     return new HtmlString('<div class="p-4 text-orange-600 bg-orange-50 rounded-lg">Seleziona Tipo Email e Provincia per caricare i destinatari.</div>');
        // }

        $officeNames = OfficeType::pluck('name', 'id');
        $recipients = '';

        // 2. Query Destinatari
        if($provinceId){                // è indicata la provincia
            $recipients = Recipient::whereHas('city', fn($q) => $q->where('province_id', $provinceId))
                ->when(!empty($adminTypes), fn($q) => $q->whereIn('admin_type_id', $adminTypes))
                ->with('city.province')
                ->get();
        } else {                        // è indicata solo la regione
            $recipients = Recipient::whereHas('city.province', fn($q) => $q->where('region_id', $regionId))
                ->when(!empty($adminTypes), fn($q) => $q->whereIn('admin_type_id', $adminTypes))
                ->with('city.province')
                ->get();
        }

        if ($recipients->isEmpty()) {
            return new HtmlString('<div class="p-4 text-gray-500 italic">Nessun destinatario trovato per questa provincia.</div>');
        }

        $isFirstLoad = empty($this->receiverList) && !$deselectAll;

        $html = '<div class="space-y-4 p-1">';
        $foundAnyEmail = false;

        foreach ($recipients as $recipient) {
            $emailsHtml = '';

            for ($i = 1; $i <= 5; $i++) {
                $mail = $recipient->{"mail_$i"};
                $type = $recipient->{"mail_type_$i"}; // Questo di solito è un Enum
                $oType = $recipient->{"office_type_id_$i"};

                // Filtro Ufficio
                $officeFilter = empty($officeTypes) || in_array((string)$oType, array_map('strval', $officeTypes));

                // CONFRONTO (usiamo == meno rigido per evitare problemi di tipo stringa/int)
                if (!empty($mail) && $type == $mailType && $officeFilter) {
                    $foundAnyEmail = true;
                    $field = "receiverList.{$recipient->id}.mail_{$i}";
                    $checkboxId = "rcpt-{$recipient->id}-{$i}";

                    // Inizializza lo stato se non esiste
                    if (!isset($this->receiverList[$recipient->id]["mail_{$i}"])) {
                        $this->receiverList[$recipient->id]["mail_{$i}"] = $isFirstLoad;
                    }

                    $officeLabel = $officeNames[$oType] ?? 'N/D';

                    $emailsHtml .= '
                    <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                        <input type="checkbox"
                            wire:model.live="' . $field . '"
                            id="' . $checkboxId . '"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
                        <label for="' . $checkboxId . '" class="flex flex-col cursor-pointer">
                            <span class="text-sm font-semibold text-gray-900">' . e($mail) . '</span>
                            <span class="text-xs text-gray-500">' . e($type->getLabel()) . ' - ' . e($officeLabel) . '</span>
                        </label>
                    </div>';
                }
            }

            if ($emailsHtml !== '') {
                $html .= '<div class="border rounded-xl p-4 bg-white shadow-sm ring-1 ring-gray-200">';
                $html .= '<div class="mb-2 pb-2 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-blue-700">' . e($recipient->description) . ' (' . $recipient->city->province->code . ')' . '</div>';
                $html .= $emailsHtml;
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        if (!$foundAnyEmail) {
            return new HtmlString('<div class="p-4 text-red-500 bg-red-50 rounded-lg">Trovati destinatari, ma nessuno ha una mail di tipo "' . e($mailType) . '" negli uffici selezionati.</div>');
        }

        return new HtmlString($html);
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $emptyAttachment = false;
        $emptyReceivers = false;

        if (empty($this->attachmentList)) {
            $emptyAttachment = true;
            Notification::make('emptyAttachment')
                ->title('Nessun allegato selezionato')
                ->body('Devi selezionare almeno un allegato per creare la spedizione.')
                ->warning()
                ->duration(3000)
                ->send();
        }

        if (empty($this->receiverList)) {
            $emptyReceivers = true;
            Notification::make('emptyReceivers')
                ->title('Nessun destinatario selezionato')
                ->body('Devi selezionare almeno un destinatario per creare la spedizione.')
                ->warning()
                ->duration(5000)
                ->send();
        }

        if($emptyAttachment || $emptyReceivers){
            $this->halt();
        }

        DB::beginTransaction();

        try {
// dd($data);
            $shipment = parent::handleRecordCreation($data);                                                    // creo la spedizione base
// dd($this->attachmentList);
            // $shipment->receiverList = $this->receiverList;                                                   // aggiungo l'array con la lista dei destinatari
            // $shipment->attachmentList = $this->attachmentList;                                               // aggiungo l'array con la lista degli allegati
// dd($shipment);
// dd(count($this->receiverList));
            $shipment->update([
                'mail_type' => $this->receiverFilters['mail_type'],                                             // tipo email destinatari
                'region_id' => $this->receiverFilters['region_id'] ?? null,                                     // id regione destinatari
                'province_id' => $this->receiverFilters['province_id'] ?? null,                                 // id provincia destinatari
                'total_no_mails' => count($this->receiverList),                                                 // inserisco il numero di email totali della spedizione
                'no_mails_to_send' => count($this->receiverList)                                                // inserisco il numero di email da inviare
            ]);

            $shipment->createShipmentFolder();                                                                  // creo la cartella della spedizione

            if (!empty($this->receiverList)) {
                $shipment->createReceivers($this->receiverList);                                                // creo i destinatari
            }

            if (!empty($this->attachmentList)) {
                $shipment->createZip($this->attachmentList);                                                    // creo lo ZIP
            }
// dd($shipment);
// dd('STOP');
            DB::commit();                                                                                       // confermo il salvataggio dei dati

            Notification::make()
                ->title('Spedizione creata correttamente')
                ->success()
                ->send();

            return $shipment;

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Errore durante la creazione della spedizione')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
