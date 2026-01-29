<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use App\Models\Receiver;
use App\Models\Shipment;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->description;
        return "Visualizza spedizione";
    }

    protected function getHeaderActions(): array
    {
        $currentShipment = $this->record;
        $previousCShipment = Shipment::where('created_at', '<=', $currentShipment->created_at)->where('id', '!=', $currentShipment->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCShipment = Shipment::where('created_at', '>=', $currentShipment->created_at)->where('id', '!=', $currentShipment->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        $previousIRegistry = Shipment::where('id', $currentShipment->flow_type)->orderBy('id', 'desc')->first();
        $nextIRegistry = Shipment::where('id', $currentShipment->flow_type)->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_shipment')
                ->label('Precedente')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCShipment) { return $previousCShipment;})
                ->action(function () use ($previousCShipment) {
                    $this->redirect(ShipmentResource::getUrl('view', ['record' => $previousCShipment->id]));
                }),
            Actions\Action::make('next_c_shipment')
                ->label('Successivo')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCShipment) { return $nextCShipment;})
                ->action(function () use ($nextCShipment) {
                    $this->redirect(ShipmentResource::getUrl('view', ['record' => $nextCShipment->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('receivers')
                    ->label('Pec  destinatari')
                    ->icon('fluentui-people-team-toolbox-20-o')
                    ->modalHeading('Pec destinatari')
                    ->modalWidth('5xl')
                    ->form([
                        Placeholder::make('receivers_list')
                            ->label('')
                            ->content(function () {
                                $receivers = $this->getReceiversForForm();
                                if (empty($receivers)) {
                                    return 'Nessun destinatario';
                                }

                                $html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">';
                                foreach ($receivers as $receiver) {
                                    $html .= '<div class="p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-900">';
                                    $html .= e($receiver['address']);
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->extraAttributes([
                                'style' => 'min-height: 10vh; max-height: 67vh; overflow-y: auto;'
                            ])
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
                Actions\EditAction::make(),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    private function getReceiversForForm(): array
    {
        $record = $this->record;
        if (!$record) return [];

        return Receiver::where('shipment_id', $record->id)
            ->get()
            ->map(fn($receiver) => ['address' => $receiver->address])
            ->toArray();
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
