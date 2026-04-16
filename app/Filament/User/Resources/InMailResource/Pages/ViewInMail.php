<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use Filament\Actions;
use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewInMail extends ViewRecord
{
    protected static string $resource = InMailResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Visualizza email ricevuta";
    }

    protected function getHeaderActions(): array
    {
        $currentInMail = $this->record;
        $previousCInMail = InMail::where('created_at', '<=', $currentInMail->created_at)->where('id', '<', $currentInMail->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCInMail = InMail::where('created_at', '>=', $currentInMail->created_at)->where('id', '>', $currentInMail->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        // $previousRInMail = InMail::where('receive_date', '<=', $currentInMail->receive_date)->where('id', '<', $currentInMail->id)
        //                         ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();
        // $nextRInMail = InMail::where('receive_date', '>=', $currentInMail->receive_date)->where('id', '>', $currentInMail->id)
        //                         ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        $previousRInMail = null;
        $nextRInMail = null;
        if (!empty($currentInMail->receive_date)) {

            $previousRInMail = InMail::whereNotNull('receive_date')
                ->where('receive_date', '<', $currentInMail->receive_date)
                ->orWhere(function ($query) use ($currentInMail) {
                    $query->where('receive_date', $currentInMail->receive_date)
                        ->where('id', '<', $currentInMail->id);
                })
                ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();

            $nextRInMail = InMail::whereNotNull('receive_date')
                ->where('receive_date', '>', $currentInMail->receive_date)
                ->orWhere(function ($query) use ($currentInMail) {
                    $query->where('receive_date', $currentInMail->receive_date)
                        ->where('id', '>', $currentInMail->id);
                })
                ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        }
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCInMail) { return $previousCInMail;})
                ->action(function () use ($previousCInMail) {
                    $this->redirect(InMailResource::getUrl('view', ['record' => $previousCInMail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCInMail) { return $nextCInMail;})
                ->action(function () use ($nextCInMail) {
                    $this->redirect(InMailResource::getUrl('view', ['record' => $nextCInMail->id]));
                }),
            // Scorrimento ricezione
            Actions\Action::make('previous_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousRInMail) { return $previousRInMail;})
                ->action(function () use ($previousRInMail) {
                    $this->redirect(InMailResource::getUrl('view', ['record' => $previousRInMail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextRInMail) { return $nextRInMail;})
                ->action(function () use ($nextRInMail) {
                    $this->redirect(InMailResource::getUrl('view', ['record' => $nextRInMail->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
