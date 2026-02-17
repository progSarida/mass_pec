<?php

namespace App\Http\Controllers;

use App\Models\Registry;
use App\Models\RegistryReceiver;
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

        $zipFileName = "{$type}_{$id}_attachments.zip";
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

    public function downloadZipReceipts($id)
    {
        $record = RegistryReceiver::findOrFail($id);

        $path = $record->registry->attachment_path . '/receipts';
        $allFiles = Storage::files($path);
        $files = collect($allFiles)->filter(function ($file) use ($record) {
            // basename($file) estrae solo il nome del file dal percorso completo
            return str_contains(basename($file), $record->address);
        });

        if ($files->isEmpty()) abort(404, "Nessun file trovato.");

        $zipFileName = "{$record->address}_receipts.zip";
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

    public function downloadZipRelated($id)
    {
        $record = Registry::findOrFail($id);

        $path = $record->attachment_path . '/related';
        $allFiles = Storage::files($path);
        $files = collect($allFiles)->filter(function ($file) use ($record) {
            // basename($file) estrae solo il nome del file dal percorso completo
            return str_contains(basename($file), $record->address);
        });

        if ($files->isEmpty()) abort(404, "Nessun file trovato.");

        $zipFileName = "{$record->protocol_number}_related.zip";
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
