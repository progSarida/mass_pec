<?php

namespace App\Filament\User\Resources;

use App\Collections\AttachmentCollection;
use App\Filament\User\Resources\AttachmentResource\Pages;
use App\Filament\User\Resources\AttachmentResource\RelationManagers;
use App\Models\Attachment;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
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
    protected static ?string $navigationIcon = 'heroicon-s-folder';
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
                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('path')
                    ->label('Percorso Relativo')
                    ->color('success'),
                TextColumn::make('extension')
                    ->label('Estensione'),
                TextColumn::make('insert_date')
                    ->label('Data inserimento')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('full_path')
                    ->label('Link Download')
                    ->url(fn ($record) => asset('storage/' . $record->path)) // Assumendo storage/public
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-download'),
            ])
            ->filters([
                // Aggiungi filtri, es. per estensione
                // Tables\Filters\SelectFilter::make('extension')
                //     ->options([
                //         'pdf' => 'PDF',
                //         'jpg' => 'Immagini',
                //         // ...
                //     ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => asset('storage/' . $record->path)),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Elimina il file fisico prima
                        File::delete(storage_path('app/public/' . $record->path));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Elimina file in bulk
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
