<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnsiArt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnsiArtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AnsiArt::with('user:id,handle');

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $sort = $request->get('sort', 'recent');
        switch ($sort) {
            case 'popular':
                $query->popular();
                break;
            case 'downloads':
                $query->orderByDesc('download_count');
                break;
            default:
                $query->latest();
        }

        $art = $query->paginate($request->get('per_page', 20));

        return response()->json($art);
    }

    public function show(int $id): JsonResponse
    {
        $art = AnsiArt::with('user:id,handle')->findOrFail($id);
        $art->incrementViews();

        return response()->json([
            'data' => $art,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'content' => 'required|string',
            'width' => 'nullable|integer|min:1|max:320',
            'height' => 'nullable|integer|min:1|max:200',
            'category' => 'nullable|string|in:' . implode(',', AnsiArt::CATEGORIES),
        ]);

        $art = AnsiArt::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'message' => __('ansi.upload_success'),
            'data' => $art,
        ], 201);
    }

    public function categories(): JsonResponse
    {
        $categories = collect(AnsiArt::CATEGORIES)->map(function ($category) {
            return [
                'slug' => $category,
                'name' => __("ansi.category_{$category}"),
                'count' => AnsiArt::byCategory($category)->count(),
            ];
        });

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function featured(): JsonResponse
    {
        $featured = AnsiArt::with('user:id,handle')
            ->featured()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $featured,
        ]);
    }

    public function download(int $id): JsonResponse
    {
        $art = AnsiArt::findOrFail($id);
        $art->incrementDownloads();

        return response()->json([
            'content' => $art->content,
            'filename' => "{$art->title}.ans",
        ]);
    }

    public function toggleFeatured(int $id): JsonResponse
    {
        $art = AnsiArt::findOrFail($id);
        $art->is_featured = !$art->is_featured;
        $art->save();

        return response()->json([
            'message' => $art->is_featured 
                ? __('ansi.featured_added') 
                : __('ansi.featured_removed'),
            'is_featured' => $art->is_featured,
        ]);
    }
}
