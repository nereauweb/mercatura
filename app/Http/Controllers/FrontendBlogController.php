<?php

namespace App\Http\Controllers;

use App\Models\BlogArticle;
use App\Models\BlogTag;
use Illuminate\Http\Request;

class FrontendBlogController extends Controller
{
    public function index(Request $request)
    {
        $tagSlug = $request->query('tag');
        $tags = BlogTag::orderBy('position')->orderBy('name')->get();
        $articles = BlogArticle::active()
            ->with('tags')
            ->orderBy('position')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('frontend.blog.index', compact('articles', 'tags', 'tagSlug'));
    }

    public function show(string $slug)
    {
        $article = BlogArticle::active()->where('slug', $slug)->with('tags')->firstOrFail();

        return view('frontend.blog.show', compact('article'));
    }
}
