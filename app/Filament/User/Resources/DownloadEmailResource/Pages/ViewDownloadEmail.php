<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use Filament\Actions;
use App\Models\Registry;
use App\Models\ScopeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\User\Resources\DownloadEmailResource;
use Illuminate\Database\Eloquent\Model;

class ViewDownloadEmail extends ViewRecord
{
    protected static string $resource = DownloadEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\EditAction::make(),
            Actions\Action::make('register')
                ->label('Protocolla')
                ->icon('fluentui-pen-20-o')
                ->color('warning')
                ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                ->requiresConfirmation()
                ->modalHeading('Protocolla email')
                ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
                ->modalSubmitActionLabel('Protocolla')
                ->form([
                    Select::make('scope_type_id')
                        ->label('Ambito')
                        ->options(ScopeType::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Seleziona l\'ambito della registrazione')
                ])
                ->action(function ($record, $data) {
                    try {
                        $this->registerEmail($record, $data['scope_type_id']);
                        Notification::make()
                            ->title('Mail protocollata')
                            ->body('La mail e i suoi allegati sono stati protocollati con successo.')
                            ->success()
                            ->send();
                        $resource = $this->getResource();
                        return $this->redirect($resource::getUrl('index'));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore registrazione')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function registerEmail($record, $scopeTypeId){
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::user()->id,
            ]);

            Model::withoutEvents(function () use ($record) {
                $record->delete();
            });

            // copio cartella allegati
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->makeDirectory($newPath);

                $files = Storage::disk('public')->allFiles($oldPath);
                foreach ($files as $file) {
                    $relativePath = str_replace($oldPath . '/', '', $file);
                    $newFilePath = $newPath . '/' . $relativePath;

                    $directory = dirname($newFilePath);
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }

                    Storage::disk('public')->put($newFilePath, Storage::disk('public')->get($file));
                }
            }

            // elimino la vecchia cartella degli allegati
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->deleteDirectory($oldPath);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
    }

    private static function newProtocol(): string
    {
        $lastRegistry = Registry::orderBy('created_at', 'desc')->first();

        if ($lastRegistry) {
            $parts = explode('-', $lastRegistry->protocol_number);

            if (count($parts) !== 3 || $parts[0] !== 'P') {
                return 'P-' . today()->year . '-00001';
            }

            $lastYear = (int) $parts[1];
            $lastNumber = (int) $parts[2];
            $currentYear = today()->year;

            if ($lastYear === $currentYear) {
                $newNumber = $lastNumber + 1;
                return 'P-' . $currentYear . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            } else {
                return 'P-' . $currentYear . '-00001';
            }
        }
        return 'P-' . today()->year . '-00001';
    }
}
