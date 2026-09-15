{{-- @mercatura-view frontend.blog.show @version 3 --}
@extends('frontend.public.layout')
@php $title = $article->seo_title ?: $article->title; $description = $article->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($article->excerpt ?: $article->text), 160); @endphp
@section('title', $title.' | '.config('brand.name'))
@section('head_meta')
	<x-frontend::seo.meta :title="$title" :description="$description" :url="\App\Support\CanonicalUrl::absolute('/blog/'.$article->slug)" :image="$article->cover ? '/storage/blog/img/'.$article->cover : null" type="article" :model="$article" />
@endsection
@section('content')
	<article class="mx-auto max-w-4xl px-4 py-6">
		<x-frontend::breadcrumb :items="[['name' => config('brand.name'), 'url' => route('frontend.home')], ['name' => __('frontend.blog.breadcrumb'), 'url' => route('frontend.blog.index')], ['name' => $article->title, 'url' => \App\Support\CanonicalUrl::absolute('/blog/'.$article->slug)]]" class="mb-4" />
		@if($article->cover)<img src="/storage/blog/img/{{ $article->cover }}" alt="{{ $article->title }}" width="1200" height="600" class="mb-6 w-full rounded-card object-cover" fetchpriority="high">@endif
		<h1 class="text-3xl font-bold uppercase text-primary">{{ $article->title }}</h1>
		<div class="prose mt-4 max-w-none text-primary">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $article->text) !!}</div>
	</article>
@endsection
