<?php

namespace App\Services;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AttachmentLimitService
{
    protected static bool $notified = false;

    /**
     * Limite totale allegati di una PEC in uscita, in KB (formato usato da FileUpload::maxSize())
     */
    public static function maxSizeKb(): int
    {
        return (int) config('mail.max_attachments_size') * 1024;
    }

    /**
     * Regola di validazione per FileUpload: la somma dei file caricati
     * (più quelli già presenti in attachment_path del record se $countExisting) non deve superare il limite
     */
    public static function totalSizeRule(bool $countExisting = false): Closure
    {
        return fn (FileUpload $component, ?Model $record) => function (string $attribute, $value, Closure $fail) use ($component, $record, $countExisting) {
            $total = collect($component->getState() ?? [])
                ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
                ->sum(fn (TemporaryUploadedFile $file) => $file->getSize());

            $path = ltrim((string) $record?->attachment_path, '/');
            if ($countExisting && $path && Storage::exists($path)) {
                $total += collect(Storage::files($path))->sum(fn ($file) => Storage::size($file));
            }

            $maxMb = (int) config('mail.max_attachments_size');
            if ($total > $maxMb * 1024 * 1024) {
                $message = 'La dimensione totale degli allegati (' . number_format($total / 1024 / 1024, 1, ',', '.') . " MB) supera il limite di {$maxMb} MB.";

                // La regola viene valutata per ogni file: notifico una sola volta
                if (!self::$notified) {
                    self::$notified = true;

                    Notification::make()
                        ->title('Caricamento bloccato')
                        ->body($message . ($countExisting ? ' Nel conteggio sono inclusi gli allegati già presenti.' : '')
                            . ' La PEC supererebbe la dimensione accettata dai gestori: rimuovere o ridurre alcuni file.')
                        ->danger()
                        ->persistent()
                        ->send();
                }

                $fail($message);
            }
        };
    }

    /**
     * Testo informativo da mostrare sotto il campo di caricamento
     */
    public static function helperText(): string
    {
        return 'Dimensione massima totale degli allegati: ' . config('mail.max_attachments_size') . ' MB';
    }
}
