<?php

namespace App\Http\Controllers\Admin;

use App\Models\TfgFeedLog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TfgFeedLogDownloadController
{
    public function __invoke(TfgFeedLog $feedLog): Response
    {
        // Middleware office — dodatkowa warstwa na poziomie kontrolera.
        abort_unless(auth()->user()?->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc']), 403);

        abort_unless(filled($feedLog->payload_path), 404);
        abort_unless(Storage::disk('local')->exists($feedLog->payload_path), 404);

        $content = Storage::disk('local')->get($feedLog->payload_path);

        return response($content, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.basename($feedLog->payload_path).'"',
        ]);
    }
}
