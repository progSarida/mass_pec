<?php

namespace App\Filament\User\Resources\RegistryResource\RelationManagers;

use App\Enums\RelationshipType;
use App\Filament\User\Resources\RegistryResource;
use App\Models\Registry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParentChildLinkRelationManager extends RelationManager
{
    protected static string $relationship = 'parentRegistries'; // non verrà usato realmente

    protected static ?string $title = 'Collegamenti';
    protected static ?string $recordTitleAttribute = 'protocol_number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('registry_id')
                    ->label('Protocollo da collegare')
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
            ->columns([
                // Tables\Columns\TextColumn::make('direction')
                //     ->label('Direzione')
                //     ->badge()
                //     ->color(fn (string $state): string => $state === 'parent' ? 'danger' : 'success')
                //     ->formatStateUsing(fn (string $state): string =>
                //         $state === 'parent' ? '← Genitore' : '→ Figlio'
                //     ),

                Tables\Columns\TextColumn::make('relationship_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(function (string $state, $record) {
                        // Trasformiamo la stringa "reply" o "forward" nell'oggetto Enum
                        $enum = RelationshipType::from($state);

                        // Verifichiamo la direzione (iniettata tramite addSelect nella query)
                        $direction = $record->direction ?? 'child';

                        // Usiamo i metodi dell'Enum che hai creato
                        return $direction === 'parent'
                            ? $enum->parentLabel()
                            : $enum->childLabel();
                    })
                    ->color(function (string $state, $record) {
                        // Recuperiamo l'enum dal valore della stringa
                        $enum = RelationshipType::from($state);

                        // Recuperiamo la direzione dal record
                        $direction = $record->direction ?? 'child';

                        // Usiamo i tuoi nuovi metodi dell'Enum
                        return $direction === 'parent'
                            ? $enum->parentColor()
                            : $enum->childColor();
                    }),

                Tables\Columns\TextColumn::make('protocol_number')
                    ->label('Protocollo')
                    // ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Oggetto')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->subject),
            ])
            ->defaultSort('linked_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')
                    ->label('Collega')
                    ->modalHeading('Crea collegamento tra voci protocollo')
                    ->modalDescription('Seleziona la voce del protocollo a cui fa riferimento quella corrente e il tipo di collegamento che le lega')
                    // ->icon('heroicon-o-plus')
                    // ->icon('fluentui-document-text-link-20-o')
                    ->icon('fluentui-link-20-o')
                    ->hidden(fn ($livewire) => $livewire->isReadOnly())
                    // ->form(fn (Form $form) => $this->form($form))
                    ->form([
                        Forms\Components\Grid::make(36)
                            ->schema([
                                Forms\Components\Select::make('registry_id')
                                    ->label('Protocollo da collegare')
                                    ->required()
                                    ->searchable()
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Inserire il numero di protocollo o una parola chiave dell\'oggetto')
                                    ->columnSpan(27)
                                    ->getSearchResultsUsing(function (string $search) {
                                        $owner = $this->getOwnerRecord();

                                        return Registry::query()
                                            ->where('id', '!=', $owner?->id)
                                            ->where('created_at', '<=', $owner?->created_at ?? now())
                                            ->whereAny([
                                                'protocol_number',
                                                'subject',
                                            ], 'like', "%{$search}%")
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
                    ->hidden(function ($record) {
                        if ($record->direction === 'parent') {
                            return false;
                        } else  if ($record->direction === 'child') {
                            return true;
                        }
                        return false;
                    })
                    ->requiresConfirmation()
                    ->action(function (Registry $record) {
                        $this->ownerRecord->parentRegistries()->detach($record->id);
                    }),
            ]);
    }

    // Query personalizzata per mostrare sia parent che child
    protected function getTableQuery(): Builder
    {
        $ownerId = $this->ownerRecord->id;

        $parents = Registry::query()
            ->join('registry_relationships', 'registries.id', '=', 'registry_relationships.parent_id')
            ->where('registry_relationships.child_id', $ownerId)
            ->select('registries.*',
                     'registry_relationships.relationship_type',
                     'registry_relationships.created_at as linked_at')
            ->addSelect(\DB::raw("'parent' as direction"));

        $children = Registry::query()
            ->join('registry_relationships', 'registries.id', '=', 'registry_relationships.child_id')
            ->where('registry_relationships.parent_id', $ownerId)
            ->select('registries.*',
                     'registry_relationships.relationship_type',
                     'registry_relationships.created_at as linked_at')
            ->addSelect(\DB::raw("'child' as direction"));

        return $parents->union($children);
    }
}
