<?php

namespace App\Filament\User\Resources;

use App\Enums\PreservationState;
use App\Filament\User\Resources\DailySummaryResource\Pages;
use App\Filament\User\Resources\DailySummaryResource\RelationManagers;
use App\Models\DailySummary;
use App\Models\Registry;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DailySummaryResource extends Resource
{
    protected static ?string $model = DailySummary::class;

    public static ?string $pluralModelLabel = 'Registri giornalieri';
    protected static ?string $navigationIcon = 'fluentui-pen-20';
    protected static ?string $navigationLabel = 'Registri giornalieri';
    protected static ?string $navigationGroup = 'Protocollo';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\DateTimePicker::make('registration_date'),
                // Forms\Components\TextInput::make('filename')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('protocol_from')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('protocol_to')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('preservation_state')
                //     ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('registration_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Data registrazione')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Nome file'),
                Tables\Columns\TextColumn::make('file_date')
                    ->label('Data e ora creazione')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_protocol')
                    ->label('Da protocollo'),
                Tables\Columns\TextColumn::make('to_protocol')
                    ->label('A protocollo'),
                Tables\Columns\TextColumn::make('preservation_state')
                    ->label('Stato preservazione')
                    ->placeholder('......')
                    ->alignCenter()
                    // ->formatStateUsing(fn ($state) => $state ?? '......')
                    ->tooltip('Clicca per modificare lo stato')
                    ->action(
                        Tables\Actions\Action::make('updatePreservation')
                            ->label('Cambia stato preservazione')
                            ->icon('heroicon-o-pencil')
                            ->color('warning')
                            ->form([
                                Forms\Components\Select::make('preservation_state')
                                    ->label('Stato preservazione')
                                    ->options(PreservationState::class)
                                    ->default(fn ($record) => $record->preservation_state),
                            ])
                            ->action(function (array $data, $record) {
                                $record->update([
                                    'preservation_state' => $data['preservation_state']
                                ]);

                                Notification::make()
                                    ->title('Stato preservazione aggiornato')
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation()           // ← chiede conferma
                            ->modalHeading('Aggiorna stato preservazione')
                            ->modalDescription('Sei sicuro di voler modificare lo stato?')
                            ->modalSubmitActionLabel('Sì, aggiorna')
                            ->modalCancelActionLabel('Annulla')
                    ),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersFormWidth('md')
            ->filtersFormColumns(2)
            ->filters([
                // Filtro inytervallo data registrazione
                Filter::make('registration_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('registration_from_date')
                            ->label('Data registrazione dal')
                            ->columnSpan(1),
                        DatePicker::make('registration_to_date')
                            ->label('Data registrazione al')
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
                            return "Data registrazione dal {$data['registration_from_date']} al {$data['registration_to_date']}";
                        }
                        if ($data['registration_from_date']) {
                            return "Data registrazione dal {$data['registration_from_date']}";
                        }
                        if ($data['registration_to_date']) {
                            return "Data registrazione al {$data['registration_to_date']}";
                        }
                        return null;
                    })
                    ->columnSpan(2),
                // Filtro per ricerca tramite numero di protocollo
                Filter::make('protocol_in_range')
                    ->label('Cerca per numero protocollo')
                    ->form([
                        TextInput::make('protocol')
                            ->label('Numero di protocollo')
                            ->maxLength(30)
                            ->autocomplete(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['protocol'])) {
                            return $query;
                        }

                        $input = trim($data['protocol']);

                        // Normalizziamo l'input per estrarre solo le 5 cifre finali
                        $searchNumber = self::extractProtocolNumber($input);

                        if ($searchNumber === null) {
                            return $query; // input non valido
                        }

                        // Cerchiamo i record dove il numero cercato è compreso tra from e to
                        return $query->whereRaw('
                            SUBSTRING_INDEX(from_protocol, "-", -1) <= ?
                            AND SUBSTRING_INDEX(to_protocol,   "-", -1) >= ?
                        ', [$searchNumber, $searchNumber]);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!empty($data['protocol'])) {
                            return "Protocollo: " . str_pad($data['protocol'], 5, '0', STR_PAD_LEFT);
                        }
                        return null;
                    }),
                SelectFilter::make('preservation_state')
                    ->label('Stato preservazione')
                    ->options([
                        '' => 'Non specificato',
                        ...collect(PreservationState::cases())
                            ->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                            ->toArray(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $state = $data['value'] ?? null;

                        return match (true) {
                            $state === ''   => $query->whereNull('preservation_state'),
                            $state !== null => $query->where('preservation_state', $state),
                            default         => $query,
                        };
                    }),
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('hugeicons-pdf-02')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->filename ? Storage::temporaryUrl("daily_summaries/{$record->filename}.pdf", now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        return $record &&
                            !empty($record->filename) &&
                            Storage::disk(config('filesystems.default'))->exists("daily_summaries/{$record->filename}.pdf");
                    }),

                Tables\Actions\Action::make('download_xls')
                    ->label('')
                    ->tooltip('Scarica Excel')
                    ->icon('hugeicons-xls-02')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->filename ? Storage::temporaryUrl("daily_summaries/{$record->filename}.xlsx", now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        return $record &&
                            !empty($record->filename) &&
                            Storage::disk(config('filesystems.default'))->exists("daily_summaries/{$record->filename}.xlsx");
                    }),

                Tables\Actions\Action::make('create_daily')
                    ->label('Crea registro')
                    ->color('danger')
                    ->action(function ($record) {
                        try {
                            $date = $record->registration_date->format('Y-m-d');
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
                                ->tooltip('Errore durante la creazione del registro giornaliero')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => !$record->file_date)
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailySummaries::route('/'),
            // 'create' => Pages\CreateDailySummary::route('/create'),
            // 'view' => Pages\ViewDailySummary::route('/{record}'),
            // 'edit' => Pages\EditDailySummary::route('/{record}/edit'),
        ];
    }

    public static function extractProtocolNumber(string $input): ?string
    {
        $input = trim($input);

        // Rimuove eventuale prefisso "P-" o "P"
        $input = preg_replace('/^P-?/i', '', $input);

        // Estrae solo le cifre
        $numbers = preg_replace('/\D/', '', $input);

        if (empty($numbers)) {
            return null;
        }

        // Prende solo le ultime 5 cifre (o meno) e le completa con zeri a sinistra fino a 5 cifre
        return str_pad(substr($numbers, -5), 5, '0', STR_PAD_LEFT);
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
