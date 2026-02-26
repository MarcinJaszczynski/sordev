<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'filename', 'extension', 'mime', 'size', 'width', 'height',
        'title', 'alt', 'caption', 'description',
    ];

    protected $casts = [
        'size' => 'int',
        'width' => 'int',
        'height' => 'int',
    ];

    // Mutator: po ustawieniu path wylicz metadane
    public function setPathAttribute($value): void
    {
        $this->attributes['path'] = $value;

        $disk = $this->disk ?: 'public';
        $full = null;
        try {
            $full = \Illuminate\Support\Facades\Storage::disk($disk)->path($value);
        } catch (\Throwable $e) {
            $full = null;
        }

        $filename = basename((string) $value);
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: null;
        $size = null; $mime = null; $width = null; $height = null;
        if ($full && file_exists($full)) {
            $size = filesize($full) ?: null;
            $mime = function_exists('mime_content_type') ? @mime_content_type($full) : null;
            $dim = @getimagesize($full);
            if ($dim) { $width = $dim[0] ?? null; $height = $dim[1] ?? null; }
        }

        $this->attributes['filename'] = $filename;
        $this->attributes['extension'] = $extension;
        $this->attributes['size'] = $size;
        $this->attributes['mime'] = $mime;
        $this->attributes['width'] = $width;
        $this->attributes['height'] = $height;
    }

    // Accessor URL
    public function getUrlAttribute(): string
    {
        $base = rtrim((string) config('filesystems.disks.' . ($this->disk ?: 'public') . '.url'), '/');
        return $base . '/' . ltrim($this->path, '/');
    }

    // Helper
    public function url(): string { return $this->url; }

    public function scopeImages($q)
    {
        return $q->where('mime', 'like', 'image/%');
    }
}
