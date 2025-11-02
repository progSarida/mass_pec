<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use App\Models\Attachment;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use ZipArchive;

class CreateShipment extends CreateRecord
{
    protected static string $resource = ShipmentResource::class;

    // Stato persistente
    public array $attachmentList = [];                                                                                                                      // id degli allegati selezionati
                                                                                                                                                            // [1, 3, 7]
    public array $receiverList = [];                                                                                                                        // id e campi dei destinatari selezionati
                                                                                                                                                            // [12 => ['mail_1', 'mail_3'], 15 => ['mail_2']]
    public array $receiverFilters = [ // filtri ricerca destinatari
        'region_id' => null,
        'province_id' => null,
    ];

    protected function getHeaderActions(): array
    {
        return [
            // === ALLEGATI ===
            Actions\Action::make('attachments')
                ->label(fn () => 'Allegati' . (!empty($this->attachmentList) ? ' (' . count($this->attachmentList) . ')' : ''))
                ->modalHeading('Selezione allegati')
                ->form([
                    Repeater::make('attachments')
                        ->label('')
                        ->schema([
                            TextInput::make('name')->label('Nome')->disabled()->columnSpan(6),
                            DatePicker::make('date')->label('Data caricamento')->disabled()->displayFormat('d/m/Y')->columnSpan(3),
                            Placeholder::make('blank')->columnSpan(1),
                            Checkbox::make('selected')->label('Allega')->columnSpan(2),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->statePath('attachments')
                ])
                ->fillForm(fn () => ['attachments' => $this->getAttachmentsForForm()])
                ->action(function (array $data) {
                    $this->attachmentList = collect($data['attachments'])
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
                ->label(fn () => 'Destinatari PEC' . ($this->countSelectedEmails() > 0 ? ' (' . $this->countSelectedEmails() . ')' : ''))
                ->modalHeading('Selezione Destinatari PEC')
                ->modalWidth('5xl')
                ->form([
                    // Filtri persistenti
                    Grid::make(2)->schema([
                        Select::make('region_id')
                            ->label('Regione')
                            ->options(Region::pluck('name', 'id'))
                            ->default($this->receiverFilters['region_id'])
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('province_id', null);
                                $this->receiverFilters['region_id'] = $state;
                            }),
                        Select::make('province_id')
                            ->label('Provincia')
                            ->options(fn (callable $get) => $get('region_id')
                                ? Province::where('region_id', $get('region_id'))->pluck('name', 'id')
                                : []
                            )
                            ->default($this->receiverFilters['province_id'])
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->receiverFilters['province_id'] = $state),
                    ]),

                    Placeholder::make('recipients_list')                                                                                                    // elenco dinamico con checkbox persistenti
                        ->label('Destinatari')
                        ->content(fn (callable $get) => $this->renderRecipientsList(
                            $get('region_id') ?? $this->receiverFilters['region_id'],
                            $get('province_id') ?? $this->receiverFilters['province_id']
                        ))
                        ->visible(fn (callable $get) =>
                            !empty($get('region_id') ?? $this->receiverFilters['region_id']) ||
                            !empty($get('province_id') ?? $this->receiverFilters['province_id'])
                        ),
                ])
                ->fillForm(fn () => [
                    'region_id' => $this->receiverFilters['region_id'],
                    'province_id' => $this->receiverFilters['province_id'],
                ])
                ->action(function () {
                    $count = $this->countSelectedEmails();
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
                'date' => Carbon::parse($attachment->upload_date)->format('Y-m-d'),
                'selected' => in_array($attachment->id, $this->attachmentList),
            ];
        })->toArray();
    }

    // === DESTINATARI ===
    private function renderRecipientsList($regionId, $provinceId): HtmlString
    {
        if (!$regionId && !$provinceId) {
            return new HtmlString('<em class="text-gray-500">Seleziona almeno regione o provincia per vedere i destinatari.</em>');
        }

        
        $recipients = Recipient::with('city.province.region')                                                                                               // ricerca dinamica: solo regione, o regione e provincia
            ->when($provinceId, function ($q) use ($provinceId, $regionId) {
                $validProvince = $regionId                                                                                                                  // verifico che la provincia appartenga alla regione selezionata
                    ? Province::where('id', $provinceId)->where('region_id', $regionId)->exists()
                    : false;
                if ($validProvince) {
                    return $q->whereHas('city.province', fn($p) => $p->where('id', $provinceId));
                }

                return $q;                                                                                                                                  // altrimenti ignora province_id
            })
            ->when(!$provinceId && $regionId, fn($q) => $q->whereHas('city.province.region', fn($r) => $r->where('id', $regionId)))
            ->when(!$provinceId && !$regionId, fn($q) => $q->whereRaw('1 = 0'))
            ->get();

        if ($recipients->isEmpty()) {
            return new HtmlString('<em class="text-gray-500">Nessun destinatario trovato per i filtri selezionati.</em>');
        }

        $html = '<div class="space-y-4 max-h-96 overflow-y-auto p-1">';

        foreach ($recipients as $recipient) {
            $emails = [];
            for ($i = 1; $i <= 5; $i++) {
                $mail = $recipient->{"mail_$i"};
                if (!empty($mail)) {
                    $emails[] = ['field' => "mail_$i", 'email' => $mail];
                }
            }
            if (empty($emails)) continue;

            $cityName = $recipient->city?->name ?? 'N/D';
            $provinceCode = $recipient->city?->province?->code ?? 'N/D';

            $html .= '<div class="border rounded-lg p-4 bg-gray-50">';
            $html .= '<p class="font-medium text-sm mb-2">' . e($recipient->description) . ' - ' . e($cityName) . ' (' . e($provinceCode) . ')' . '</p>';
            $html .= '<div class="space-y-1 text-sm">';

            foreach ($emails as $index => $email) {
                $field = "receiverList.{$recipient->id}.{$email['field']}";
                $checkboxId = 'rcpt-' . $recipient->id . '-' . $email['field'];

                $hasSelection = isset($this->receiverList[$recipient->id]);                                                                                 // verifico se il Recipient ha già selezioni salvate

                $isFirstEmail = ($index === 0);                                                                                                             // spunto di default solo se è la prima email e non c'è selezione
                $checked = $hasSelection
                    ? in_array($email['field'], $this->receiverList[$recipient->id] ?? [])
                    : $isFirstEmail;

                // NON aggiungere automaticamente a $receiverList
                // Solo l'utente può modificare lo stato

                $html .= '
                <div class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model.live="' . $field . '"
                        id="' . $checkboxId . '"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4 flex-shrink-0"
                        ' . ($checked ? 'checked' : '') . '
                    >
                    <label for="' . $checkboxId . '" class="cursor-pointer select-none text-sm">
                        <span class="font-medium">' . e($email['email']) . '</span>
                        <span class="text-gray-500 text-xs ml-1">(' . $email['field'] . ')</span>
                    </label>
                </div>';
            }

            $html .= '</div></div>';
        }

        $html .= '</div>';
        return new HtmlString($html);
    }

    // === CREAZIONE ZIP ===
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model                                                               // sovrascrivo il salvataggio per creare lo ZIP
    {
        $data['receiverList'] = $this->receiverList;
        $data['attachmentList'] = $this->attachmentList;
        dd($data);
        $shipment = parent::handleRecordCreation($data);                                                                                                    // creo la spedizione

        if (!empty($this->attachmentList)) {
            $this->createZipFromAttachments($shipment);                                                                                                     // se ci sono allegati selezionati, creo lo ZIP
        }

        // Qui puoi usare $this->receiverList per inviare PEC
        // if (!empty($this->receiverList)) {
        //     $this->sendPecWithZip($shipment);
        // }

        return $shipment;
    }

    private function createZipFromAttachments($shipment): void
    {
        $attachments = Attachment::whereIn('id', $this->attachmentList)->get();                                                                             // recupera gli allegati dalla tabella attachments

        if ($attachments->isEmpty()) return;

        $zipFileName = $shipment->attachment . '.zip';                                                                                                      // nome del file ZIP (da $shipment->attachment)
        $zipPath = storage_path('app/temp/' . $zipFileName);
        $tempDir = dirname($zipPath);                                                                                                                       // creo la directory se non esiste
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()->title('Errore creazione ZIP')->danger()->send();
            return;
        }

        foreach ($attachments as $attachment) {                                                                                                             // inserisco file in ZIP
            $filePath = storage_path('app/public/' . $attachment->path);                                                                                    // recupero il path del file
            if (file_exists($filePath)) {
                $fileName = pathinfo($attachment->path, PATHINFO_BASENAME);
                $zip->addFile($filePath, $fileName);
            }
        }

        $zip->close();                                                                                                                                      // chiudo ZIP

        $publicPath = 'shipments/zips/' . $zipFileName;                                                                                                     // sposto lo ZIP in storage pubblico
        Storage::disk('public')->putFileAs('shipments/zips', $zipPath, $zipFileName);
        @unlink($zipPath);                                                                                                                                  // pulizia file temporaneo

        Notification::make()
            ->title('ZIP creato: ' . $zipFileName)
            ->success()
            ->send();
    }
}