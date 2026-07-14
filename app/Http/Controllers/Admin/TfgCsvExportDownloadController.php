<?php

namespace App\Http\Controllers\Admin;

use App\Models\TfgFeedLog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TfgCsvExportDownloadController
{
    public function __invoke(TfgFeedLog $feedLog): Response
    {
        abort_unless(filled($feedLog->payload_path), 404);
        abort_unless(str_ends_with($feedLog->payload_path, '.csv'), 404);
        abort_unless(Storage::disk('local')->exists($feedLog->payload_path), 404);

        $content = Storage::disk('local')->get($feedLog->payload_path);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.basename($feedLog->payload_path).'"',
        ]);
    }
}
