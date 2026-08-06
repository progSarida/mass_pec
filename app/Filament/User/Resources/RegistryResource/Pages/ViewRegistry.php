<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Filament\User\Resources\RegistryResource;
use App\Models\Company;
use App\Models\Registry;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;

class ViewRegistry extends ViewRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Visualizza {$this->record->protocol_number}";
    }

    protected function getHeaderActions(): array
    {
        $currentRegistry = $this->record;
        $previousNRegistry = Registry::where('protocol_number', '<', $currentRegistry->protocol_number)->orderBy('protocol_number', 'desc')->first();
        $nextNRegistry = Registry::where('protocol_number', '>', $currentRegistry->protocol_number)->orderBy('protocol_number', 'asc')->first();
        $previousIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '<', $currentRegistry->flow_index)->orderBy('flow_index', 'desc')->first();
        $nextIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '>', $currentRegistry->flow_index)->orderBy('flow_index', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento protocollo
            Actions\Action::make('previous_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousNRegistry) { return $previousNRegistry;})
                ->action(function () use ($previousNRegistry) {
                    $this->redirect(RegistryResource::getUrl('view', ['record' => $previousNRegistry->id]));
                }),
            Actions\Action::make('next_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextNRegistry) { return $nextNRegistry;})
                ->action(function () use ($nextNRegistry) {
                    $this->redirect(RegistryResource::getUrl('view', ['record' => $nextNRegistry->id]));
                }),
            // Scorrimento tipo flusso
            Actions\Action::make('previous_i_registry')
                ->label('Corrispondenza')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIRegistry) { return $previousIRegistry;})
                ->action(function () use ($previousIRegistry) {
                    $this->redirect(RegistryResource::getUrl('view', ['record' => $previousIRegistry->id]));
                }),
            Actions\Action::make('next_i_registry')
                ->label('Corrispondenza')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIRegistry) { return $nextIRegistry;})
                ->action(function () use ($nextIRegistry) {
                    $this->redirect(RegistryResource::getUrl('view', ['record' => $nextIRegistry->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->label('Stampa')
                    ->tooltip('Stampa')
                    ->color('print')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()
                            ->streamDownload(function () use ($record) {
                                echo Pdf::loadHTML(
                                    Blade::render('print.registry', [
                                        'company' => Company::first(),
                                        'registry' => $record,
                                    ])
                                )
                                ->setPaper('A4', 'portrait')
                                ->stream();
                            }, "Voce protocollo_{$record->protocol_number}.pdf");
                    }),
                Actions\EditAction::make()
                    ->label('Gestisci'),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
