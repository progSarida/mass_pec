<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\Exports\RecipientExporter;
use App\Filament\User\Resources\RecipientResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

class ListRecipients extends ListRecords
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco voci')
                ->color('print')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()
                        ->with(['emails', 'city.province.region'])
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
                            ini_set('memory_limit', '1G');
                            echo Pdf::loadHTML(
                                Blade::render('print.recipients', [
                                    'recipients' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, "Elenco interlocutori.pdf");
                }),
            ExportAction::make('esporta')
                ->icon('heroicon-s-table-cells')
                ->label('Esporta')
                ->tooltip('Esporta elenco interlocutori')
                ->modalWidth(MaxWidth::FitContent)
                ->color('export')
                ->exporter(RecipientExporter::class)
                ->form(fn (ExportAction $action): array => [
                    \Filament\Forms\Components\Fieldset::make(__('filament-actions::export.modal.form.columns.label'))
                        ->columns(3)
                        ->inlineLabel()
                        ->schema(function () use ($action): array {
                            return array_map(
                                fn (\Filament\Actions\Exports\ExportColumn $column): \Filament\Forms\Components\Split => \Filament\Forms\Components\Split::make([
                                    \Filament\Forms\Components\Checkbox::make('isEnabled')
                                        ->label(__('filament-actions::export.modal.form.columns.form.is_enabled.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->isEnabledByDefault())
                                        ->live()
                                        ->grow(false),
                                    \Filament\Forms\Components\TextInput::make('label')
                                        ->label(__('filament-actions::export.modal.form.columns.form.label.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->getLabel())
                                        ->placeholder($column->getLabel())
                                        ->disabled(fn (\Filament\Forms\Get $get): bool => ! $get('isEnabled'))
                                        ->required(fn (\Filament\Forms\Get $get): bool => (bool) $get('isEnabled')),
                                ])
                                    ->verticallyAlignCenter()
                                    ->statePath($column->getName()),
                                $action->getExporter()::getColumns(),
                            );
                        })
                        ->statePath('columnMap'),
                    ...$action->getExporter()::getOptionsFormComponents(),
                ])
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
