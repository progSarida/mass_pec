<?php

namespace App\Filament\User\Resources\DailySummaryResource\Pages;

use App\Enums\PreservationState;
use App\Filament\User\Resources\DailySummaryResource;
use App\Models\DailySummary;
use App\Models\Registry;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListDailySummaries extends ListRecords
{
    protected static string $resource = DailySummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
            Actions\Action::make('daily')
                ->icon('heroicon-o-printer')
                ->label('Crea registro giornaliero')
                ->visible(function () {
                    // Ottieni tutte le date distinte dai registries (solo data, senza ora)
                    $registryDates = Registry::selectRaw('DATE(created_at) as date')
                        ->distinct()
                        ->pluck('date');

                    // Ottieni tutte le date già processate in DailySummary
                    $processedDates = DailySummary::pluck('registration_date')
                        ->map(fn($date) => $date->format('Y-m-d'));

                    // Verifica se ci sono date con registries ma senza DailySummary
                    return $registryDates->diff($processedDates)->isNotEmpty();
                })
                ->tooltip('Stampa registro giornaliero')
                ->action(function ($livewire) {
                    try {
                        DB::beginTransaction();
                        // Ottieni le date che necessitano di essere processate
                        $registryDates = Registry::selectRaw('DATE(created_at) as date')
                            ->orderBy('date', 'asc')
                            ->distinct()
                            ->pluck('date');

                        $processedDates = DailySummary::pluck('registration_date')
                            ->map(fn($date) => $date->format('Y-m-d'));

                        $datesToProcess = $registryDates->diff($processedDates);

                        if ($datesToProcess->isEmpty()) {
                            Notification::make()
                                ->title('Nessuna data da processare')
                                ->warning()
                                ->send();
                            return false;
                        }

                        $processedCount = 0;

                        foreach ($datesToProcess as $date) {
                            // Ottieni tutti i registries per questa data specifica
                            $records = Registry::whereDate('created_at', $date)
                                ->orderBy('created_at', 'asc')
                                ->get();

                            if ($records->isEmpty()) {
                                continue;
                            }

                            // Determina i numeri di protocollo min e max
                            $protocolYear = Str::beforeLast($records[0]->protocol_number, '-');

                            $protocolNumbers = $records->map(function ($record) {
                                // Prende tutto dopo l'ultimo "-" e lo converte in intero
                                return (int) Str::afterLast($record->protocol_number, '-');
                            });

                            $fromNumber = $protocolNumbers->min();   // numero più piccolo (es. 21)
                            $toNumber   = $protocolNumbers->max();   // numero più grande (es. 45)

                            // Genera nome file
                            $dateFormatted = \Carbon\Carbon::parse($date)->format('Ymd');
                            $pdfFilename = "{$dateFormatted}.pdf";
                            $xlsxFilename = "{$dateFormatted}.xlsx";

                            // Path relativo per Storage
                            $storagePath = 'daily_summaries';

                            // Genera PDF
                            $pdf = Pdf::loadView('print.daily_summary', [
                                'registries' => $records,
                                'date' => $dateFormatted,
                                'year' => $protocolYear,
                                'fromNumber' => $fromNumber,
                                'toNumber' => $toNumber,
                            ])
                            ->setPaper('A4', 'landscape');

                            // Salva PDF su Storage (funziona sia con local che S3)
                            $pdfContent = $pdf->output();

                            Storage::put($storagePath . '/' . $pdfFilename, $pdfContent);

                            // Genera XLSX in memoria
                            $xlsxContent = $this->generateExcel($records, $dateFormatted, $protocolYear, $fromNumber, $toNumber);

                            // Salva XLSX su Storage
                            Storage::put($storagePath . '/' . $xlsxFilename, $xlsxContent);

                            // Crea record DailySummary
                            DailySummary::create([
                                'registration_date' => $date,
                                'filename' => $dateFormatted,
                                'from_protocol' => "{$protocolYear}-". Str::padLeft($fromNumber, 5, '0'),
                                'to_protocol' => "{$protocolYear}-" . Str::padLeft($toNumber, 5, '0'),
                                'presevation_state' => null,
                            ]);

                            $processedCount++;
                        }

                        DB::commit();

                        Notification::make()
                            ->title("Elaborati {$processedCount} registri giornalieri")
                            ->success()
                            ->send();

                        return true;
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error("Errore creazione registro giornaliero: " . $e->getMessage() . ' - ' . $e->getLine());
                        throw $e;
                    }
                }),
        ];
    }

    private function generateExcel($records, $dateFormatted, $protocolYear, $fromNumber, $toNumber)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Formatta la data per il display
        $displayDate = \Carbon\Carbon::parse($dateFormatted)->format('d/m/Y');

        // Header
        $sheet->setCellValue('A1', 'Registro Protocollo Giornaliero');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', "Data: {$displayDate}");
        $sheet->mergeCells('A2:F2');

        $sheet->setCellValue('A3', "Dal n. {$protocolYear}-{$fromNumber} al n. {$protocolYear}-{$toNumber}");
        $sheet->mergeCells('A3:F3');

        // Column headers
        $row = 5;
        $headers = [
            'A' => 'N. Protocollo',
            'B' => 'Tipo',
            'C' => 'Data Registrazione',
            'D' => 'Data Atto',
            'E' => 'Interlocutore',
            'F' => 'Oggetto'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        // Data rows
        $row = 6;
        foreach ($records as $record) {
            // Determina l'interlocutore
            $recipient = '';
            if ($record->flow_type == \App\Enums\FlowType::ISSUED) {
                $recipient = $record->registryReceivers?->pluck('recipient.description')->join(', ');
            } else if ($record->flow_type == \App\Enums\FlowType::RECEIVED) {
                $recipient = $record->sender?->description ?? '';
            }

            $sheet->setCellValue('A' . $row, $record->protocol_number);
            $sheet->setCellValue('B' . $row, $record->flow_type?->getLabel() ?? '');
            $sheet->setCellValue('C' . $row, $record->created_at->format('d/m/Y H:i'));

            $dataAtto = $record->send_date
                ? $record->send_date->format('d/m/Y')
                : ($record->receive_date ? $record->receive_date->format('d/m/Y') : '');
            $sheet->setCellValue('D' . $row, $dataAtto);

            $sheet->setCellValue('E' . $row, $recipient);
            $sheet->setCellValue('F' . $row, $record->subject ?? '');

            // Word wrap per la colonna oggetto
            $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Imposta larghezza fissa per la colonna Oggetto
        $sheet->getColumnDimension('F')->setWidth(50);

        // Bordi per tutta la tabella
        $lastRow = $row - 1;
        $sheet->getStyle('A5:F' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Genera il contenuto in memoria e restituiscilo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Usa un buffer temporaneo per catturare l'output
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content;
    }
}
