<?php

namespace App\Filament\Exports;

use App\Models\Recipient;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RecipientExporter extends Exporter
{
    protected static ?string $model = Recipient::class;

    public static function getColumns(): array
    {
        $maxItems = Recipient::withCount('emails')
                        ->orderBy('emails_count', 'desc')
                        ->limit(1)
                        ->value('emails_count') ?? 0;

        $recipientEmailColumns = [];

        for ($i = 0; $i < $maxItems; $i++) {
            $labelPrefix = 'Email ' . ($i + 1);

            $recipientEmailColumns[] = ExportColumn::make("email_{$i}_address")
                ->label("{$labelPrefix} - Indirizzo")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->emails instanceof \Illuminate\Support\Collection
                        ? $record->emails
                        : $record->invoiceItems()->get();
                    $item = $items[$i] ?? null;
                    return $item?->email;
                });

            $recipientEmailColumns[] = ExportColumn::make("email_{$i}_mail_type")
                ->label("{$labelPrefix} - Tipo mail")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->emails instanceof \Illuminate\Support\Collection
                        ? $record->emails
                        : $record->invoiceItems()->get();
                    $item = $items[$i] ?? null;
                    return $item?->mail_type?->getLabel();
                });

            $recipientEmailColumns[] = ExportColumn::make("email_{$i}_office_type")
                ->label("{$labelPrefix} - Ufficio")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->emails instanceof \Illuminate\Support\Collection
                        ? $record->emails
                        : $record->invoiceItems()->get();
                    $item = $items[$i] ?? null;
                    return $item?->officeType?->name;
                });
        }

        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('description')
                ->label('Nome e Cognome/Denominazione'),
            ExportColumn::make('recipient_type')
                ->label('Natura interlocutore')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('admin_type_id')
                ->label('Tipo interlocutore')
                ->formatStateUsing(fn ($state, $record) => $record->adminType?->name ?? '-'),
            ExportColumn::make('istat_type_id')
                ->label('Tipo Istat')
                ->formatStateUsing(fn ($state, $record) => $record->istatType?->name ?? '-'),
            ExportColumn::make('tax_code')
                ->label('Codice fiscale'),
            ExportColumn::make('vat_code')
                ->label('Partita IVA'),
            ExportColumn::make('code_ipa')
                ->label('Codice Ipa'),
            ExportColumn::make('acronym')
                ->label('Acronimo'),
            ExportColumn::make('address')
                ->label('Indirizzo'),
            ExportColumn::make('city_cap')
                ->label('Cap'),
            ExportColumn::make('city_id')
                ->label('Comune')
                ->formatStateUsing(fn ($state, $record) => $record->city?->name ?? '-'),
            ExportColumn::make('province')
                ->label('Provincia')
                ->formatStateUsing(fn ($state, $record) => $record->city?->province?->code ?? '-'),
            ExportColumn::make('region')
                ->label('Regione')
                ->formatStateUsing(fn ($state, $record) => $record->city?->province?->region?->name ?? '-'),
            ExportColumn::make('resp_title')
                ->label('Titolo resp.'),
            ExportColumn::make('resp_surname')
                ->label('Cognome resp.'),
            ExportColumn::make('resp_name')
                ->label('Nome resp.'),
            ExportColumn::make('resp_tax_code')
                ->label('CF resp.'),

            ...$recipientEmailColumns,

            ExportColumn::make('phone')
                ->label('Telefono'),
            ExportColumn::make('fax')
                ->label('Fax'),
            ExportColumn::make('site')
                ->label('Sito istituzionale'),
            ExportColumn::make('url_facebook')
                ->label('Facebook')
                ->enabledByDefault(false),
            ExportColumn::make('url_twitter')
                ->label('Twitter')
                ->enabledByDefault(false),
            ExportColumn::make('url_googleplus')
                ->label('Google')
                ->enabledByDefault(false),
            ExportColumn::make('url_youtube')
                ->label('Youtube')
                ->enabledByDefault(false),
            ExportColumn::make('created_at')->enabledByDefault(false),
            ExportColumn::make('updated_at')->enabledByDefault(false),
        ];
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your recipient export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
        $body = 'L\'esportazione degli interlocutori è stata completata ' . $addExported . "<br>";

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $addFailed = $failedRowsCount > 1 
                ? number_format($failedRowsCount) . " elementi non esportati" 
                : '1 elemento non esportato';
            $body .= $addFailed;
        }

        return $body;
    }
}
