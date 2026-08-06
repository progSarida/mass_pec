<?php

namespace App\Filament\Exports;

use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\Sender;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Storage;

class RegistryExporter extends Exporter
{
    protected static ?string $model = Registry::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('protocol_number')
                ->label('Numero Protocollo'),
            ExportColumn::make('flow_type')
                ->label('Tipo Flusso')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('flow_index')
                ->label('Indice Flusso'),
            ExportColumn::make('registry_origin_type')
                ->label('Tipo Origine Registro')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null)
                ->enabledByDefault(false),
            ExportColumn::make('receiving_mail')
                ->label('Email Ricezione')
                ->enabledByDefault(false),
            ExportColumn::make('parent_id')
                ->label('ID Genitore')
                ->enabledByDefault(false),
            ExportColumn::make('is_email')
                ->label('È email'),
            ExportColumn::make('scopeType.name')
                ->label('Tipo Ambito'),
            ExportColumn::make('uid')
                ->label('UID')
                ->enabledByDefault(false),
            ExportColumn::make('message_id')
                ->label('ID Messaggio')
                ->enabledByDefault(false),
            ExportColumn::make('sender_id')
                ->label('Mittente')
                ->formatStateUsing(function ($record) {
                    if ($record->sender_id && $record->sender) {
                        return $record->sender->description ?? '';
                    }
                    if ($record->account_id && $record->account) {
                        return $record->account->public_name ?? '';
                    }
                    if ($record->registry_origin_type == RegistryOriginType::SHIPMENT){
                        return Sender::first()->public_name;
                    }
                    return 'Sarida srl (tramite posta)';                                      // caso in cui si tratti di posta fisica
                }),
            ExportColumn::make('other_senders')
                ->label('Altri Mittenti')
                ->formatStateUsing(function ($state) {
                    // Se $state è una stringa JSON (es. '[1, 2]'), la converte in array
                    if (is_string($state)) {
                        $state = json_decode($state, true);
                    }
                    // Se è null, vuoto o non è un array, restituisce una stringa vuota
                    if (empty($state) || !is_array($state)) {
                        return '';
                    }
                    return \App\Models\Recipient::whereIn('id', $state)
                        ->pluck('denomination')
                        ->implode(', ');
                }),
            ExportColumn::make('interested_parties')
                ->label('Parti Interessate')
                ->formatStateUsing(function ($state) {
                    // Se $state è una stringa JSON (es. '[1, 2]'), la converte in array
                    if (is_string($state)) {
                        $state = json_decode($state, true);
                    }
                    // Se è null, vuoto o non è un array, restituisce una stringa vuota
                    if (empty($state) || !is_array($state)) {
                        return '';
                    }
                    return \App\Models\Recipient::whereIn('id', $state)
                        ->pluck('denomination')
                        ->implode(', ');
                }),
            ExportColumn::make('from')
                ->label('Email Mittente')
                ->enabledByDefault(false),
            ExportColumn::make('subject')
                ->label('Oggetto'),
            ExportColumn::make('body')
                ->label('Corpo')
                ->enabledByDefault(false),
            ExportColumn::make('eml_body')
                ->label('Corpo EML')
                ->enabledByDefault(false),
            ExportColumn::make('receive_date')
                ->label('Data Ricezione')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : '';
                }),
            ExportColumn::make('account.public_name')
                ->label('Account'),
            ExportColumn::make('send_date')
                ->label('Data Invio')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : '';
                }),
            ExportColumn::make('sendUser.name')
                ->label('Nome Utente Invio'),
            ExportColumn::make('shipment_id')
                ->label('ID Spedizione'),
            ExportColumn::make('attachment_path')
                ->label('Allegati')
                ->formatStateUsing(function ($record, $state) {
                        $files = Storage::files($record?->attachment_path);
                        if (!empty($files)) { return 'SI'; }
                        else { return 'NO'; }
                    }),
            ExportColumn::make('linked')
                ->label('Collegato')
                ->formatStateUsing(fn ($record) => $record->hasRelatedRegistries() ? 'SI' : 'NO'),
            ExportColumn::make('outcome')
                ->label('Esito')
                ->formatStateUsing(function ($record) {
                    $stato = static::checkReceipts($record);
                    if(!$stato) { return null; }
                    switch($stato){
                        case 'manual':
                        case 'download':
                        case 'shipment':
                            return '';
                        default:
                            [$sent, $accepted, $delivered] = explode(',', $stato);                                              // inviate, accettate, consegnate
                            $count = $record->registryReceivers()->count();                                                     // numero destinatari

                            if($sent == 0) return 'Non inviata';                                                                // nessuna mail inviata

                            if($sent == $count) {                                                                               // tutte le mail inviate
                                if($sent == $delivered) return 'Consegnata';                                                    // numero inviate = numero consegnate
                                else if($sent == $accepted) return 'Accettata';                                                 // numero inviate = numero accettate
                                else return 'Parzialmente consegnata';                                                          // numero accettate < numero inviate => errore invio
                            }
                            else return 'Errore invio';                                                                         // non tutte le mail sono state elaborate
                    }
                }),
            ExportColumn::make('download_date')
                ->label('Data Download')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : '';
                }),
            ExportColumn::make('downloadUser.name')
                ->label('Utente Download'),
            ExportColumn::make('manage_registry_type')
                ->label('Stato Gestione')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('manage_registry_date')
                ->label('Data Gestione')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : '';
                }),
            ExportColumn::make('manage_registry_mode')
                ->label('Modalità Gestione')
                ->formatStateUsing(fn ($record) => $record->latestManage?->manage_registry_mode ?? null  ),
            ExportColumn::make('void')
                ->label('Annullato'),
            ExportColumn::make('void_reason')
                ->label('Motivo Annullamento'),
            ExportColumn::make('void_date')
                ->label('Data Annullamento')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : '';
                }),
            ExportColumn::make('registerUser.name')
                ->label('Utente Registrazione'),
            ExportColumn::make('created_at')
                ->label('Data Creazione')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : '';
                })
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->label('Data Aggiornamento')
                ->formatStateUsing(function ($state) {
                    return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : '';
                })
                ->enabledByDefault(false),
        ];
    }

    public static function checkReceipts(Registry $registry)
    {
        switch($registry->registry_origin_type){
            case RegistryOriginType::MANUAL:
                return 'manual';
            case RegistryOriginType::DOWNLOAD_EMAIL:
                return 'download';
            case RegistryOriginType::SHIPMENT:
                return 'shipment';
        }
        $sent = 0;
        $accepted = 0;
        $delivered = 0;
        foreach($registry->registryReceivers as $receiver){          
            if ($receiver->pec_status == PecStatus::ACCEPTED) {
                $sent++;
                $accepted++;
            } else if ($receiver->pec_status == PecStatus::DELIVERED) {
                $sent++;
                $delivered++;
            } else if ($receiver->pec_status == PecStatus::NOT_DELIVERED) {
                $sent++;
            } else if ($receiver->message_id) {
                $sent++;
            }
        }
        // Restituisci una stringa invece di un array
        $report = "{$sent},{$accepted},{$delivered}";
        return $report;
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your registry export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

    //     if ($failedRowsCount = $export->getFailedRowsCount()) {
    //         $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
    //     }

    //     return $body;
    // }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $addExported = $export->successful_rows > 1 
            ? number_format($export->successful_rows) . " elementi esportati" 
            : '1 elemento esportato';
        $body = 'L\'esportazione del protocollo è stata completata ' . $addExported . "<br>";

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $addFailed = $failedRowsCount > 1 
                ? number_format($failedRowsCount) . " elementi non esportati" 
                : '1 elemento non esportato';
            $body .= $addFailed;
        }
        return $body;
    }
}
