<?php

namespace App\Filament\User\Resources\ManualInsertResource\Pages;

use App\Filament\User\Resources\ManualInsertResource;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Blade;

class ViewManualInsert extends ViewRecord
{
    protected static string $resource = ManualInsertResource::class;

    public function getTitle(): string
    {
        return "Visualizza inserimento manuale";
    }

    protected function getHeaderActions(): array
    {
        return [
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
                                    Blade::render('print.manual_insert', [
                                        'company' => Company::first(),
                                        'element' => $record,
                                    ])
                                )
                                ->setPaper('A4', 'portrait')
                                ->stream();
                            }, "Inserimento manuale_{$record->id}.pdf");
                    }),
                Actions\EditAction::make(),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }
}
