<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Documents\EventPackageFileSignedUrlService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Publiczny (signed) download pliku z pakietu PDF imprezy — bez logowania.
 */
class EventPackageFileDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Event $event,
        string $audience,
        string $kind,
        string $ref,
        EventPackageFileSignedUrlService $signedUrls,
        int $fileIndex = 0,
    ): BinaryFileResponse {
        abort_unless($request->hasValidSignature(), 403);

        $resolved = $signedUrls->resolveDownload($event, $audience, $kind, $ref, $fileIndex);

        $headers = [];
        if (! empty($resolved['mime'])) {
            $headers['Content-Type'] = $resolved['mime'];
        }

        return response()->download(
            $resolved['absolute_path'],
            $resolved['download_name'],
            $headers,
        );
    }
}
