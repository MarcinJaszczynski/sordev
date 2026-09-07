<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskAttachment;
use App\Support\Tasks\TaskAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, TaskAttachment $attachment): StreamedResponse
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($attachment->task, 404);

        abort_unless(TaskAuthorization::canAccessTask($user, $attachment->task), 403);

        $path = $attachment->file_path;

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $filename = $attachment->name ?: basename($path);
        $disk = Storage::disk('public');

        if ($request->boolean('download')) {
            return $disk->download($path, $filename);
        }

        // Domyślnie inline — PDF/zdjęcia otwierają się w karcie zamiast wymuszać pobranie.
        return $disk->response($path, $filename);
    }
}
