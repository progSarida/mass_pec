<?php

namespace App\Filament\User\Resources;

use App\Collections\AttachmentCollection;
use App\Filament\User\Resources\AttachmentResource\Pages;
use App\Filament\User\Resources\AttachmentResource\RelationManagers;
use App\Models\Attachment;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;
    public static ?string $pluralModelLabel = 'Allegati';
    public static ?string $modelLabel = 'Allegato';
    protected static ?string $navigationIcon = 'fluentui-mail-attach-20';
    protected static ?string $navigationLabel = 'Allegati';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome file')
                    ->columnSpan('full'),
                FileUpload::make('path')
                    ->label('')
                    ->disk('public')
                    ->directory('attachments')
                    ->visibility('public')
                    ->columnSpan('full')
                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, callable $get) {
                        if ($get('name') && trim($get('name')) !== '') {
                            $extension = $file->getClientOriginalExtension();
                            return sprintf('%s.%s', $get('name'), $extension);
                        }
                        return $file->getClientOriginalName();
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome File')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('Percorso Relativo')
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extension')
                    ->label('Estensione'),
                TextColumn::make('upload_date')
                    ->label('Data caricamento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('uploadUser.name')
                    ->label('Caricato da'),
            ])
            ->filters([
                Filter::make('upload_date_range')
                    ->label('Intervallo Date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Da data'),
                        DatePicker::make('date_to')
                            ->label('A data')
                            ->minDate(fn (callable $get) => $get('date_from') ?: null),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $q) => $q->whereDate('upload_date', '>=', $data['date_from'])
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $q) => $q->whereDate('upload_date', '<=', $data['date_to'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['date_from'] ? \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y') : null;
                        $to = $data['date_to'] ? \Carbon\Carbon::parse($data['date_to'])->format('d/m/Y') : null;
                        if ($from && $to) { return "Dal {$from} al {$to}"; }
                        if ($from) { return "Dal {$from}"; }
                        if ($to) { return "Al {$to}"; }
                        return null;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Apri file')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => asset('storage/' . $record->path))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->label('Elimina file'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            collect($records)->each(fn ($record) => File::delete(storage_path('app/public/' . $record->path)));
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttachments::route('/'),
            // 'create' => Pages\CreateAttachment::route('/create'),
            // 'edit' => Pages\EditAttachment::route('/{record}/edit'),
        ];
    }
}
