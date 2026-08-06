<?php

namespace App\Filament\User\Resources\RegistryResource\RelationManagers;

use App\Enums\RelationshipType;
use App\Filament\User\Resources\RegistryResource;
use App\Models\Registry;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class ParentChildLinkRelationManager extends RelationManager
{
    protected static string $relationship = 'parentRegistries'; // non verrà usato realmente

    protected static ?string $title = 'Collegamenti';
    protected static ?string $recordTitleAttribute = 'protocol_number';

    protected ?array $connectedGraph = null;

    protected array $linkMeta = []; // id => ['depth'=>, 'relationship_type'=>, 'direction'=>, 'from'=>id]

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('registry_id')
                    ->label('Protocollo precedente da collegare')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        return Registry::where('protocol_number', 'like', "%{$search}%")
                            ->orWhere('subject', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($reg) => [$reg->id => "{$reg->protocol_number} - {$reg->subject}"]);
                    }),

                Forms\Components\Select::make('relationship_type')
                    ->label('Tipo di collegamento')
                    ->options(RelationshipType::class)
                    ->required(),

                Forms\Components\Select::make('direction')
                    ->label('Direzione')
                    ->options([
                        'child'  => 'Risposta/Inoltro',
                        'parent' => 'Origine',
                    ])
                    ->default('child')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->defaultGroup('depth')
            // ->groups([
            //     Group::make('depth')
            //         ->label('Grado di Parentela')
            //         ->getTitleFromRecordUsing(fn ($record) => match ((int) $record->depth) {
            //             1 => '1° Grado (Diretti)',
            //             default => "{$record->depth}° Grado (Rete Estesa)",
            //         }),
            // ])
            // ->groupingSettingsHidden()
            ->defaultSort('created_at', 'desc')
            ->columns([

                Tables\Columns\TextColumn::make('relationship_type')
                    ->label('Relazione')
                    ->badge()
                    ->state(fn ($record) => $this->meta($record->id)['relationship_type'] ?? null)
                    ->icon(function ($record) {
                        $meta = $this->meta($record->id);
                        return $meta['relationship_type']
                            ? RelationshipType::from($meta['relationship_type'])->getRelationIcon($meta['direction'], $meta['depth'])
                            : 'heroicon-m-link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        $meta = $this->meta($record->id);
                        return $meta['relationship_type']
                            ? RelationshipType::from($meta['relationship_type'])->getRelationLabel($meta['direction'], $meta['depth'])
                            : '—';
                    })
                    ->color(function ($record) {
                        $meta = $this->meta($record->id);
                        return $meta['relationship_type']
                            ? RelationshipType::from($meta['relationship_type'])->getRelationColor($meta['direction'], $meta['depth'])
                            : 'gray';
                    }),

                Tables\Columns\TextColumn::make('relation_chain')
                    ->label('Collegamento')
                    ->state(function ($record) {
                        $meta = $this->meta($record->id);
                        $fromId = $meta['from'];

                        $fromProtocol = $fromId
                            ? (Registry::where('id', $fromId)->value('protocol_number') ?? '?')
                            : $this->ownerRecord->protocol_number;

                        return "{$fromProtocol} → {$record->protocol_number}";
                    }),

                // Oggetto del protocollo collegato
                Tables\Columns\TextColumn::make('subject')
                    ->label('Oggetto')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->subject),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('print_connected')
                    ->label('Stampa Collegamenti')
                    ->modalHeading('Stampa Report Collegamenti')
                    ->modalDescription('Seleziona le opzioni per la generazione del PDF dei protocolli collegati.')
                    ->icon('heroicon-o-printer')
                    ->color('print')
                    ->form([
                        Forms\Components\Toggle::make('include_details')
                            ->label('Includi scheda dettagliata per ogni voce collegata')
                            ->helperText('Se disattivato, verrà stampato solo l\'elenco riepilogativo delle relazioni.')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        $owner = $this->getOwnerRecord();
                        
                        // Recuperiamo la mappa dei metadati calcolata dalla BFS
                        $linkMeta = $this->getConnectedGraph();
                        
                        // Carichiamo i record dei collegamenti ordinati per data
                        $connectedRecords = Registry::query()
                            ->whereIn('id', array_keys($linkMeta))
                            ->orderBy('created_at', 'desc')
                            ->get();

                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()->streamDownload(function () use ($owner, $connectedRecords, $linkMeta, $data) {
                            echo Pdf::loadHTML(
                                Blade::render('print.registry-connected', [
                                    'company'          => \App\Models\Company::first(),
                                    'ownerRecord'      => $owner,
                                    'connectedRecords' => $connectedRecords,
                                    'linkMeta'         => $linkMeta,
                                    'includeDetails'   => $data['include_details'],
                                ])
                            )
                            ->setPaper('A4', 'portrait')
                            ->stream();
                        }, "Collegamenti_Protocollo_{$owner->protocol_number}.pdf");
                    }),
                Tables\Actions\Action::make('attach')
                    ->label('Collega')
                    ->modalHeading('Crea collegamento tra voci protocollo')
                    ->modalDescription('Seleziona la voce del protocollo precedente a cui fa riferimento quella corrente e il tipo di collegamento che le lega')
                    ->icon('fluentui-link-20-o')
                    ->hidden(fn ($livewire) => $livewire->isReadOnly())
                    ->modalSubmitActionLabel('Salva collegamento')
                    ->form([
                        Forms\Components\Grid::make(36)
                            ->schema([
                                Forms\Components\Select::make('registry_id')
                                    ->label('Protocollo precedente da collegare')
                                    ->required()
                                    ->searchable()
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Inserire il numero di protocollo o una parola chiave dell\'oggetto')
                                    ->columnSpan(27)
                                    ->getSearchResultsUsing(function (string $search) {
                                        $owner = $this->getOwnerRecord();
                                        $excludedIds = array_keys($this->getConnectedGraph());

                                        return Registry::query()
                                            ->where('id', '!=', $owner?->id)
                                            ->whereNotIn('id', $excludedIds)
                                            ->where('created_at', '<=', $owner?->created_at ?? now())
                                            ->where(function ($query) use ($search) {
                                                // cerco prima gli ID dei Recipient che corrispondono al testo cercato.
                                                $matchingRecipientIds = \App\Models\Recipient::query()
                                                    ->where('description', 'like', "%{$search}%")
                                                    ->pluck('id');

                                                // ricerca diretta su Numero Protocollo e Oggetto
                                                $query->whereAny([
                                                    'protocol_number',
                                                    'subject',
                                                ], 'like', "%{$search}%")

                                                // ricerca sul Mittente principale (via chiave esterna/relazione)
                                                ->orWhereHas('sender', function ($subQuery) use ($search) {
                                                    $subQuery->where('description', 'like', "%{$search}%");
                                                });

                                                // se trovo dei Recipient corrispondenti, usiamo whereJsonContains e relazioni rapide
                                                if ($matchingRecipientIds->isNotEmpty()) {
                                                    foreach ($matchingRecipientIds as $recipientId) {
                                                        // Ricerca istantanea nei campi JSON usando whereJsonContains
                                                        $query->orWhereJsonContains('other_senders', $recipientId)
                                                            ->orWhereJsonContains('interested_parties', $recipientId);
                                                    }

                                                    // ricerca nei Receivers filtrando direttamente per recipient_id
                                                    $query->orWhereHas('registryReceivers', function ($subQuery) use ($matchingRecipientIds) {
                                                        $subQuery->whereIn('recipient_id', $matchingRecipientIds);
                                                    });
                                                }
                                            })
                                            ->orderBy('created_at', 'desc')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn(Registry $reg) => [
                                                $reg->id => "{$reg->protocol_number} - {$reg->subject}"
                                            ]);
                                    }),

                                Forms\Components\Select::make('relationship_type')
                                    ->label('Tipo di collegamento')
                                    ->options(RelationshipType::class)
                                    ->columnSpan(9)
                                    ->required(),
                            ])
                    ])
                    ->action(function (array $data) {
                        $this->ownerRecord->parentRegistries()->attach($data['registry_id'], [
                                'relationship_type' => $data['relationship_type']
                            ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('go_to')
                    ->label('Vai al protocollo')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('info')
                    ->tooltip('Apri il protocollo collegato')
                    ->url(function (Registry $record) {
                        return RegistryResource::getUrl('view', ['record' => $record->id]);
                    })
                    ->openUrlInNewTab(true),

                Tables\Actions\Action::make('detach')
                    ->label('Rimuovi')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(function ($record) {
                        $meta = $this->meta($record->id);
                        return $meta['depth'] === 1 && $meta['direction'] === 'parent';
                    })
                    ->requiresConfirmation()
                    ->action(function (Registry $record) {
                        $this->ownerRecord->parentRegistries()->detach($record->id);
                    }),
            ]);
    }

    // BFS che restituisce la struttura del grafo di cui fa parte l'owner
    protected function getConnectedGraph(): array
    {
        if ($this->connectedGraph !== null) {
            return $this->connectedGraph;
        }

        $ownerId = (int) $this->ownerRecord->id;
        $maxDepth = 10;

        $visited = [$ownerId => ['depth' => 0, 'relationship_type' => null, 'direction' => null, 'from' => null]];
        $frontier = [$ownerId];
        $depth = 0;

        while (!empty($frontier) && $depth < $maxDepth) {
            $depth++;

            $edges = DB::table('registry_relationships')
                ->where(function ($q) use ($frontier) {
                    $q->whereIn('parent_id', $frontier)->orWhereIn('child_id', $frontier);
                })
                ->get(['parent_id', 'child_id', 'relationship_type']);

            $nextFrontier = [];

            foreach ($edges as $edge) {
                if (in_array($edge->parent_id, $frontier) && !isset($visited[$edge->child_id])) {
                    $visited[$edge->child_id] = [
                        'depth' => $depth,
                        'relationship_type' => $edge->relationship_type,
                        'direction' => 'child',
                        'from' => $edge->parent_id,
                    ];
                    $nextFrontier[] = $edge->child_id;
                }

                if (in_array($edge->child_id, $frontier) && !isset($visited[$edge->parent_id])) {
                    $visited[$edge->parent_id] = [
                        'depth' => $depth,
                        'relationship_type' => $edge->relationship_type,
                        'direction' => 'parent',
                        'from' => $edge->child_id,
                    ];
                    $nextFrontier[] = $edge->parent_id;
                }
            }

            $frontier = array_values(array_unique($nextFrontier));
        }

        unset($visited[$ownerId]);

        return $this->connectedGraph = $visited;
    }

    // Query personalizzata per mostrare gli elementi del grafo di relazioni
    protected function getTableQuery(): Builder
    {
        $this->linkMeta = $this->getConnectedGraph();

        if (empty($this->linkMeta)) {
            return Registry::query()->whereRaw('1 = 0');
        }

        return Registry::query()->whereIn('id', array_keys($this->linkMeta));
    }

    protected function meta(int $registryId): array
    {
        return $this->linkMeta[$registryId] ?? [
            'depth' => 1, 'relationship_type' => null, 'direction' => 'child', 'from' => null,
        ];
    }
}
