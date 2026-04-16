<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\User\Resources\DownloadEmailResource;
use App\Models\DownloadEmail;
use Illuminate\Contracts\Support\Htmlable;

class ViewDownloadEmail extends ViewRecord
{
    protected static string $resource = DownloadEmailResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Visualizza email ricevuta";
    }


    protected function getHeaderActions(): array
    {
        $currentDownloadEmail = $this->record;
        $previousCDownloadEmail = DownloadEmail::where('created_at', '<=', $currentDownloadEmail->created_at)->where('id', '<', $currentDownloadEmail->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCDownloadEmail = DownloadEmail::where('created_at', '>=', $currentDownloadEmail->created_at)->where('id', '>', $currentDownloadEmail->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        // $previousRDownloadEmail = DownloadEmail::where('receive_date', '<=', $currentDownloadEmail->receive_date)->where('id', '<', $currentDownloadEmail->id)
        //                         ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();
        // $nextRDownloadEmail = DownloadEmail::where('receive_date', '>=', $currentDownloadEmail->receive_date)->where('id', '>', $currentDownloadEmail->id)
        //                         ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        $previousRDownloadEmail = null;
        $nextRDownloadEmail = null;
        if (!empty($currentDownloadEmail->receive_date)) {

            $previousRDownloadEmail = DownloadEmail::whereNotNull('receive_date')
                ->where('receive_date', '<', $currentDownloadEmail->receive_date)
                ->orWhere(function ($query) use ($currentDownloadEmail) {
                    $query->where('receive_date', $currentDownloadEmail->receive_date)
                        ->where('id', '<', $currentDownloadEmail->id);
                })
                ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();

            $nextRDownloadEmail = DownloadEmail::whereNotNull('receive_date')
                ->where('receive_date', '>', $currentDownloadEmail->receive_date)
                ->orWhere(function ($query) use ($currentDownloadEmail) {
                    $query->where('receive_date', $currentDownloadEmail->receive_date)
                        ->where('id', '>', $currentDownloadEmail->id);
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
                ->visible(function () use ($previousCDownloadEmail) { return $previousCDownloadEmail;})
                ->action(function () use ($previousCDownloadEmail) {
                    $this->redirect(DownloadEmailResource::getUrl('view', ['record' => $previousCDownloadEmail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCDownloadEmail) { return $nextCDownloadEmail;})
                ->action(function () use ($nextCDownloadEmail) {
                    $this->redirect(DownloadEmailResource::getUrl('view', ['record' => $nextCDownloadEmail->id]));
                }),
            // Scorrimento ricezione
            Actions\Action::make('previous_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousRDownloadEmail) { return $previousRDownloadEmail;})
                ->action(function () use ($previousRDownloadEmail) {
                    $this->redirect(DownloadEmailResource::getUrl('view', ['record' => $previousRDownloadEmail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextRDownloadEmail) { return $nextRDownloadEmail;})
                ->action(function () use ($nextRDownloadEmail) {
                    $this->redirect(DownloadEmailResource::getUrl('view', ['record' => $nextRDownloadEmail->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
