<?php

namespace App\Filament\User\Pages;

use App\Models\DailySummary;
use App\Models\Registry;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class CustomDashboard extends BaseDashboard
{
    // Questo metodo viene eseguito ogni volta che la pagina viene caricata
    public function mount(): void
    {
        // Controllo se nella sessione dell'utente esiste già il "marchio"
        if (!session()->has('daily_summary')) {

            $today = now()->format('Y-m-d');

            $registryDates = Registry::selectRaw('DATE(created_at) as date')
                ->whereDate('created_at', '<', $today)
                ->orderBy('date', 'asc')
                ->distinct()
                ->pluck('date');

            $processedDates = DailySummary::pluck('registration_date')
                ->map(fn($date) => $date->format('Y-m-d'));

            $datesToProcess = $registryDates->diff($processedDates);

            $list = '';
            foreach($datesToProcess as $date){
                $list .= \Carbon\Carbon::parse($date)->format('d/m/Y') . '<br>';
                DailySummary::create([
                    'registration_date' => $date,
                    'filename' => null,
                    'file_date' => null,
                    'from_protocol' => null,
                    'to_protocol' => null,
                    'preservation_state' => null,
                ]);
            }

            if ($datesToProcess->isNotEmpty()) {
                Notification::make()
                    ->title('Creare registro giornaliero per le date')
                    ->body($list)
                    ->warning()
                    ->sendToDatabase(auth()->user())    // Salva nel DB
                    ->send();                           // Invia all'interfaccia

                // Scrivo in sessione che abbiamo già inviato la notifica
                session()->put('daily_summary', true);
            }
        }
    }
}
