<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? PostCategory::where('slug', $request->string('category'))->first()
            : null;

        $posts = Post::query()
            ->published()
            ->with(['category', 'media'])
            ->when($category, fn ($q) => $q->where('post_category_id', $category->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString();

        return view('news.index', [
            'posts' => $posts,
            'categories' => PostCategory::orderBy('sort_order')->orderBy('name')->withCount(['posts' => fn ($q) => $q->published()])->get(),
            'activeCategory' => $category,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->with(['category', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->with('media')
            ->where('id', '!=', $post->id)
            ->when($post->post_category_id, fn ($q) => $q->where('post_category_id', $post->post_category_id))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('news.show', [
            'post' => $post,
            'related' => $related,
        ]);
    }
}
