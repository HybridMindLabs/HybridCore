<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsMediaController extends Controller
{
    public function index(): Response
    {
        $disk = Storage::disk('public');
        $files = collect($disk->files('news/images'))
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'path' => $path,
                'url' => $disk->url($path),
                'size' => $disk->size($path),
                'last_modified' => $disk->lastModified($path),
            ])
            ->sortByDesc('last_modified')
            ->values();

        return Inertia::render('Admin/News/Media/Index', [
            'files' => $files,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $file = $request->file('file');
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('news/images', $name, 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = 'news/images/'.basename($filename);
        Storage::disk('public')->delete($path);

        return back()->with('success', 'File deleted.');
    }
}
