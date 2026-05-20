<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Enums\PecStatus;
use App\Filament\User\Resources\RegistryResource;
use App\Models\RegistryReceiver;
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
            //     ->exporter(BiddingExporter::class),

            Actions\Action::make('send')
                ->label('Invia Email')
                ->icon('hugeicons-mail-send-01')
                ->color('success')
                ->visible(function() {
                    // se ci sono Registry che hanno almeno un registryReceiver con message_id null
                    $receivers = RegistryReceiver::whereHas('registry', function($q){
                                        $q->whereNotNull('send_date');
                                    })
                                    ->whereNull('message_id')
                                    ->where('pec_status', PecStatus::WAITING)
                                    ->select('registry_receivers.id')
                                    ->get();

                    return count($receivers) > 0;
                })
                ->requiresConfirmation()
                ->modalHeading('Conferma invio email')
                ->modalDescription(function () {
                    return "Le email in sospeso verranno inviate.";
                })
                ->modalSubmitActionLabel('Sì, invia')
                ->modalCancelActionLabel('Annulla')
                ->action(function () {
                    try {
                        $receivers = RegistryReceiver::whereHas('registry', function($q){
                                            $q->whereNotNull('send_date');
                                        })
                                        ->whereNull('message_id')
                                        ->where('pec_status', PecStatus::WAITING)
                                        ->select('registry_receivers.id', 'registry_receivers.registry_id', 'registry_receivers.address')
                                        ->get();
                        $count = count($receivers);

                        foreach($receivers as $receiver){
                            \App\Jobs\SendRegistryEmailJob::dispatch(
                                registryId: $receiver->registry_id,
                                recipientEmail: $receiver->address,
                                registryReceiverId: $receiver->id,
                            );
                        }

                        Notification::make()
                            ->title('Invio avviato')
                            ->body("Le email in sospeso {$count} verranno inviate in background.")
                            ->success()
                            ->duration(5000)
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio invio')
                            ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
