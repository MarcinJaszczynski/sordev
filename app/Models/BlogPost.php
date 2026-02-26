<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'is_featured',
        'is_published',
        'gallery',
        'published_at',
        'seo_meta',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'gallery' => 'array',
        'published_at' => 'datetime',
        'seo_meta' => 'array',
    ];

    /**
     * Model default attributes.
     * Ensures `status` is set to a safe default to avoid DB NOT NULL errors on insert.
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    public function scopePublished($query)
    {
        $now = now();

        return $query
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere('is_published', true);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', $now);
            });
    }

    public function tags()
    {
        return $this->belongsToMany(\App\Models\Tag::class, 'blog_post_tag');
    }

    protected static function booted()
    {
        static::creating(function ($post) {
            if (empty($post->slug) && !empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }
        });

        static::updating(function ($post) {
            if (empty($post->slug) && !empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }
        });

        static::saving(function ($post) {
            $publishedAt = null;
            if (!empty($post->published_at)) {
                $publishedAt = $post->published_at instanceof Carbon
                    ? $post->published_at
                    : Carbon::parse($post->published_at);
            }

            if ($post->is_published) {
                if (!$publishedAt || $publishedAt->isFuture()) {
                    $post->published_at = now();
                    $publishedAt = $post->published_at instanceof Carbon
                        ? $post->published_at
                        : Carbon::parse($post->published_at);
                }
                if ($post->status !== 'active') {
                    $post->status = 'active';
                }
            } else {
                if ($post->status === 'active') {
                    $post->status = 'draft';
                }
            }
        });
    }
}
