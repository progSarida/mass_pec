<?php

namespace App\Filament\User\Pages;

use App\Enums\PreservationState;
use App\Models\DailySummary;
use App\Models\Registry;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomDashboard extends BaseDashboard
{
    // Questo metodo viene eseguito ogni volta che la pagina viene caricata
    public function mount(): void
    {
        DB::beginTransaction();
        try {
            // Controllo se nella sessione dell'utente esiste già il "marchio"
            if (!session()->has('daily_summary')) {

                $today = now()->format('Y-m-d');

                $registryDates = Registry::selectRaw('DATE(created_at) as date')
                    ->whereDate('created_at', '<', $today)
                    ->orderBy('date', 'asc')
                    ->distinct()
                    ->pluck('date');

                $processedDates = DailySummary::whereNotNull('file_date')
                    ->pluck('registration_date')
                    ->map(fn($date) => $date->format('Y-m-d'));

                $datesToProcess = $registryDates->diff($processedDates);

                $list = '';
                foreach($datesToProcess as $date){
                    $data = \Carbon\Carbon::parse($date)->format('d/m/Y');
                    $list .= $data . '<br>';
                    $summary = DailySummary::create([
                        'registration_date' => $date,
                        'filename' => null,
                        'file_date' => null,
                        'from_protocol' => null,
                        'to_protocol' => null,
                        'preservation_state' => null,
                    ]);
                    Log::info("Creato registro giornaliero per la data {$data}");
                    static::createDailySummaryFiles($summary);
                    Log::info("Creati file registro giornaliero per la data {$data}");
                }

                if ($datesToProcess->isNotEmpty()) {
                    if (count($datesToProcess) == 1) {
                        Notification::make()
                            ->title('Creato registro giornaliero per la data')
                            ->body($list)
                            ->success()
                            ->sendToDatabase(auth()->user())    // Salva nel DB
                            ->send();                           // Invia all'interfaccia
                    }
                    else if (count($datesToProcess) > 1) {
                        Notification::make()
                            ->title('Creati registri giornalieri per le date')
                            ->body($list)
                            ->success()
                            ->sendToDatabase(auth()->user())    // Salva nel DB
                            ->send();                           // Invia all'interfaccia
                    }

                    // Scrivo in sessione che abbiamo già inviato la notifica
                    session()->put('daily_summary', true);
                } else {
                     Notification::make()
                            ->title('Nessun registro giornaliero creato')
                            ->info()
                            ->sendToDatabase(auth()->user())    // Salva nel DB
                            ->send();                           // Invia all'interfaccia
                }
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Errore durante la creazione del registro giornaliero");
            Log::error($e->getMessage());
            Notification::make()
                ->title('Errore durante la creazione del registro giornaliero')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private static function createDailySummaryFiles($summary)
    {
        try {
            $date = $summary->registration_date->format('Y-m-d');
            $records = Registry::whereDate('created_at', $date)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($records->isEmpty()) {
                return;;
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
            $xlsxContent = self::generateExcel($records, $dateFormatted, $protocolYear, str_pad($fromNumber, 5, '0', STR_PAD_LEFT), str_pad($toNumber, 5, '0', STR_PAD_LEFT));

            // Salva XLSX su Storage
            Storage::put($storagePath . '/' . $xlsxFilename, $xlsxContent);

            // Crea record DailySummary
            DailySummary::where('registration_date', $date)->update([
                'filename' => $dateFormatted,
                'file_date' => now(),
                'from_protocol' => "{$protocolYear}-". Str::padLeft($fromNumber, 5, '0'),
                'to_protocol' => "{$protocolYear}-" . Str::padLeft($toNumber, 5, '0'),
                'preservation_state' => PreservationState::NOT_SENT,
            ]);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore durante la creazione del registro giornaliero')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }



    private static function generateExcel($records, $dateFormatted, $protocolYear, $fromNumber, $toNumber)
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
