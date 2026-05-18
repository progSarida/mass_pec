<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Filament\User\Resources\RegistryResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListRegistries extends ListRecords
{
    protected static string $resource = RegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
            Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco voci')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()
                                ->orderBy('created_at', 'asc')
                                ->get();
                    $filters = $livewire->tableFilters ?? [];
                    $search = $livewire->tableSearch ?? null;

                    if(count($records) === 0){
                        Notification::make()
                            ->title('Nessun elemento da stampare')
                            ->warning()
                            ->send();
                        return false;
                    }

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            echo Pdf::loadHTML(
                                Blade::render('print.registries', [
                                    'registries' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, "Registro protocollo.pdf");
                }),
            // ExportAction::make('esporta')
            //     ->icon('heroicon-s-table-cells')
            //     ->label('Esporta')
            //     ->tooltip('Esporta elenco gare')
            //     ->color(Color::rgb('rgb(0, 153, 0)'))
            //     ->exporter(BiddingExporter::class)
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
