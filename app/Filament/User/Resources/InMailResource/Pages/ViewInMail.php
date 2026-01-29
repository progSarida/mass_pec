<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use Filament\Actions;
use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use App\Models\Registry;
use App\Models\ScopeType;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $previousCInMail = InMail::where('created_at', '<=', $currentInMail->created_at)->where('id', '!=', $currentInMail->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCInMail = InMail::where('created_at', '>=', $currentInMail->created_at)->where('id', '!=', $currentInMail->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        $previousRInMail = InMail::where('receive_date', '<=', $currentInMail->receive_date)->where('id', '!=', $currentInMail->id)
                                ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();
        $nextRInMail = InMail::where('receive_date', '>=', $currentInMail->receive_date)->where('id', '!=', $currentInMail->id)
                                ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
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
            // Actions\Action::make('register')
            //     ->label('Protocolla')
            //     ->icon('fluentui-pen-20-o')
            //     ->color('warning')
            //     ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
            //     ->requiresConfirmation()
            //     ->modalHeading('Protocolla email')
            //     ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
            //     ->modalSubmitActionLabel('Protocolla')
            //     ->form([
            //         Select::make('scope_type_id')
            //             ->label('Ambito')
            //             ->options(ScopeType::pluck('name', 'id'))
            //             ->searchable()
            //             ->placeholder('Seleziona l\'ambito della registrazione')
            //     ])
            //     ->action(function ($record, $data) {
            //         try {
            //             $this->registerEmail($record, $data['scope_type_id']);
            //             Notification::make()
            //                 ->title('Mail protocollata')
            //                 ->body('La mail e i suoi allegati sono stati protocollati con successo.')
            //                 ->success()
            //                 ->send();
            //             $resource = $this->getResource();
            //             return $this->redirect($resource::getUrl('index'));
            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore registrazione')
            //                 ->body($e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     }),
        ];
    }

    private function registerEmail($record, $scopeTypeId)
    {
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();
            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'received',
                'flow_index' => static::newIndex('received'),
                'registry_origin_type' => 'in_mail',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'send_date' => null,
                'send_user_id' => null,
                'shipment_id' => null,
                'send_email_id' => null,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::id(),
            ]);

            // Sposta l'intera cartella degli allegati
            if ($oldPath && Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            }

            // Elimina il record originale
            Model::withoutEvents(function () use ($record) {
                $record->delete();
            });

            DB::commit();

            return $registry;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore protocollazione email: " . $e->getMessage() . ' - Linea: ' . $e->getLine());
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

    private static function newIndex($flow_type): int
    {
        $lastIndex = Registry::where('flow_type', $flow_type)->max('flow_index');

        if ($lastIndex) {
            return $lastIndex++;
        }
        return 1;
    }
}
