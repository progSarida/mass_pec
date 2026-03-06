<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Log;

class ListRecipients extends ListRecords
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // Actions\Action::make('fixSearch')
            //     ->label('Job Pulizia description/description_search')
            //     ->requiresConfirmation()
            //     ->modalHeading('Conferma operazione')
            //     ->modalDescription('Questa operazione verrà eseguita in background. Riceverai una notifica al completamento.')
            //     ->action(function () {
            //         // Dispatch del job in background
            //         \App\Jobs\FixRecipientDescriptionsJob::dispatch(auth()->id());

            //         // Notifica immediata che il job è stato avviato
            //         \Filament\Notifications\Notification::make()
            //             ->title('Operazione avviata')
            //             ->body('La pulizia delle descrizioni è stata avviata in background.')
            //             ->info()
            //             ->send();
            //     }),

//             Actions\Action::make('fixSearch')
//                 ->label('Pulizia description/description_search')
//                 ->action(function () {
//                     \App\Models\Recipient::all()->each(function ($recipient) {
//                         // Pattern che cattura: inizio parola + eventuali lettere + vocale + apostrofo
//                         // \b -> word boundary (inizio parola)
//                         // ([a-z]*?) -> cattura eventuali lettere prima della vocale (non greedy)
//                         // ([aeiou]) -> cattura la vocale
//                         // \' -> apostrofo letterale
//                         // (?=\s|$|[^\w]) -> lookahead: dopo l'apostrofo deve esserci spazio, fine stringa o non-word char
//                         $pattern = '/\b([a-z]*?)([aeiou])\'(?=\s|$|[^\w])/ui';
// Log::info("Id interlocutore: {$recipient->id} -------------------------------------------------------------------");
//                         $newDescription = preg_replace_callback($pattern, function ($matches) {
//                             $prefisso = mb_strtolower($matches[1]);
//                             $vocaleOriginale = $matches[2];
//                             $parolaCompleta = $prefisso . mb_strtolower($vocaleOriginale);

//                             // Se la parola è "de" o "ca", mantieni l'apostrofo
//                             if (in_array($parolaCompleta, ['de', 'ca'])) {
//                                 return $matches[0]; // Ritorna "de'" o "ca'" invariato
//                             }

//                             // Altrimenti sostituisci con vocale accentata
//                             $vocaleLower = mb_strtolower($vocaleOriginale);
//                             $mappa = [
//                                 'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'
//                             ];
//                             $sostituta = $mappa[$vocaleLower] ?? $vocaleOriginale;

//                             // Mantieni il case originale della vocale
//                             if (mb_strtoupper($vocaleOriginale) === $vocaleOriginale && $vocaleOriginale !== $vocaleLower) {
//                                 $sostituta = mb_strtoupper($sostituta);
//                             }

//                             return $matches[1] . $sostituta;
//                         }, $recipient->description);
// Log::info("Descrizione: {$recipient->description}");
//                         $recipient->description = $newDescription;
// Log::info("Descrizione modificata: {$newDescription}");
//                         $recipient->save();
//                     });

//                     \Filament\Notifications\Notification::make()
//                         ->title('Operazione completata')
//                         ->success()
//                         ->send();
//                 }),

            // Actions\Action::make('fixSearch')
            //     ->label('TEST')
            //     ->action(function () {
            //         \App\Models\Recipient::orderByDesc('id')->limit(1)->get()->each(function ($recipient) {
            //             // Pattern che cattura: inizio parola + eventuali lettere + vocale + apostrofo
            //             // \b -> word boundary (inizio parola)
            //             // ([a-z]*?) -> cattura eventuali lettere prima della vocale (non greedy)
            //             // ([aeiou]) -> cattura la vocale
            //             // \' -> apostrofo letterale
            //             // (?=\s|$|[^\w]) -> lookahead: dopo l'apostrofo deve esserci spazio, fine stringa o non-word char
            //             $pattern = '/\b([a-z]*?)([aeiou])\'(?=\s|$|[^\w])/ui';

            //             $newDescription = preg_replace_callback($pattern, function ($matches) {
            //                 $prefisso = mb_strtolower($matches[1]);
            //                 $vocaleOriginale = $matches[2];
            //                 $parolaCompleta = $prefisso . mb_strtolower($vocaleOriginale);

            //                 // Se la parola è "de" o "ca", mantieni l'apostrofo
            //                 if (in_array($parolaCompleta, ['de', 'ca'])) {
            //                     return $matches[0]; // Ritorna "de'" o "ca'" invariato
            //                 }

            //                 // Altrimenti sostituisci con vocale accentata
            //                 $vocaleLower = mb_strtolower($vocaleOriginale);
            //                 $mappa = [
            //                     'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'
            //                 ];
            //                 $sostituta = $mappa[$vocaleLower] ?? $vocaleOriginale;

            //                 // Mantieni il case originale della vocale
            //                 if (mb_strtoupper($vocaleOriginale) === $vocaleOriginale && $vocaleOriginale !== $vocaleLower) {
            //                     $sostituta = mb_strtoupper($sostituta);
            //                 }

            //                 return $matches[1] . $sostituta;
            //             }, $recipient->description);

            //             $recipient->description = $newDescription;
            //             $recipient->save();
            //         });

            //         \Filament\Notifications\Notification::make()
            //             ->title('Operazione completata')
            //             ->success()
            //             ->send();
            //     })
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
