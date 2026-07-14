<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user || ! ($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            abort(403, 'Brak dostępu.');
        }

        if (! preg_match('/^backup_\d{8}_\d{6}\.zip$/', $filename)) {
            abort(404, 'Nieprawidłowa nazwa pliku.');
        }

        $path = storage_path('backups/'.$filename);

        if (! file_exists($path)) {
            abort(404, 'Plik nie istnieje.');
        }

        return response()->download($path, $filename);
    }
}
