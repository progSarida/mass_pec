<?php

namespace App\Http\Controllers;

use App\Models\YourModel; // Sostituisci con il tuo modello
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AttachmentController extends Controller
{
    public function downloadZip(string $type, $id)
    {
        // Recupera la classe dal morphMap
        $modelClass = Relation::getMorphedModel($type);

        if (!$modelClass) abort(404, "Tipo di allegato non valido.");

        $record = $modelClass::findOrFail($id);

        // Assumiamo che tutti i modelli abbiano la colonna 'attachment_path'
        $path = $record->attachment_path;
        $files = Storage::files($path);

        if (empty($files)) abort(404, "Nessun file trovato.");

        $zipFileName = "{$type}_{$id}.zip";
        $zipPath = storage_path("app/temp/{$zipFileName}");

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                $zip->addFromString(basename($file), Storage::get($file));
            }
            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
