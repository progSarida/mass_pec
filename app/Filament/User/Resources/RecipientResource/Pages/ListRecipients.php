<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListRecipients extends ListRecords
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('fixSearch')
                ->label('Pulizia description/description_search')
                ->action(function () {
                    \App\Models\Recipient::all()->each(function ($recipient) {
                         // Pattern corretto:
                        // (?i) -> Case insensitive
                        // (?<![a-z]) -> Negative Lookbehind: non deve esserci una lettera prima
                        // (?!(?:de|ca)\') -> Controlla che NON stiamo per matchare "de'" o "ca'"
                        // ([aeiou])\' -> Cattura vocale + apostrofo
                        // Ma dobbiamo controllare TUTTA la parola prima della vocale

                        // Soluzione migliore: usa negative lookbehind per escludere 'd' o 'c' prima della vocale
                        $pattern = '/(?i)(?<![dc])([aeiou])\'/u';

                        $newDescription = preg_replace_callback($pattern, function ($matches) {
                            $vocaleOriginale = $matches[1];
                            $vocaleLower = mb_strtolower($vocaleOriginale);

                            $mappa = [
                                'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'
                            ];

                            $sostituta = $mappa[$vocaleLower] ?? $matches[0];

                            // Mantieni il case originale
                            if (mb_strtoupper($vocaleOriginale) === $vocaleOriginale && $vocaleOriginale !== $vocaleLower) {
                                return mb_strtoupper($sostituta);
                            }

                            return $sostituta;
                        }, $recipient->description);

                        $recipient->description = $newDescription;
                        $recipient->save();
                    });

                    \Filament\Notifications\Notification::make()
                        ->title('Operazione completata')
                        ->success()
                        ->send();
                }),
            // Actions\Action::make('fixSearch')
            //     ->label('Pulizia description/description_search')
            //     ->action(function () {
            //         \App\Models\Recipient::orderByDesc('id')->limit(1)->get()->each(function ($recipient) {
            //             // Pattern corretto:
            //             // (?i) -> Case insensitive
            //             // (?<![a-z]) -> Negative Lookbehind: non deve esserci una lettera prima
            //             // (?!(?:de|ca)\') -> Controlla che NON stiamo per matchare "de'" o "ca'"
            //             // ([aeiou])\' -> Cattura vocale + apostrofo
            //             // Ma dobbiamo controllare TUTTA la parola prima della vocale

            //             // Soluzione migliore: usa negative lookbehind per escludere 'd' o 'c' prima della vocale
            //             $pattern = '/(?i)(?<![dc])([aeiou])\'/u';

            //             $newDescription = preg_replace_callback($pattern, function ($matches) {
            //                 $vocaleOriginale = $matches[1];
            //                 $vocaleLower = mb_strtolower($vocaleOriginale);

            //                 $mappa = [
            //                     'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'
            //                 ];

            //                 $sostituta = $mappa[$vocaleLower] ?? $matches[0];

            //                 // Mantieni il case originale
            //                 if (mb_strtoupper($vocaleOriginale) === $vocaleOriginale && $vocaleOriginale !== $vocaleLower) {
            //                     return mb_strtoupper($sostituta);
            //                 }

            //                 return $sostituta;
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
