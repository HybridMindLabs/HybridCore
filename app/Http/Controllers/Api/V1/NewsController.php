<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = NewsArticle::published()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate(10)
            ->through(fn (NewsArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'excerpt' => $a->excerpt,
                'image' => $a->featured_image_url,
                'category' => $a->category?->name,
                'author' => $a->author?->name,
                'reading_time' => $a->reading_time,
                'published_at' => $a->published_at?->toIso8601String(),
                'url' => route('news.show', $a->slug),
            ]);

        return response()->json($articles)
            ->header('Cache-Control', 'public, max-age=60');
    }
}
