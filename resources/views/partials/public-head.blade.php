{{-- <head> content shared by the public pages: SEO meta, language alternates, fonts.
     Expects: $seoTitle, $seoDescription, $seoRoute (route name); optional $ogTitle, $ogDescription, $jsonLd (array of schema.org objects). --}}
@php
    use App\Http\Controllers\Guest\HomeController;
    use App\Http\Middleware\SetLocale;
    $canonical = HomeController::localizedUrl($seoRoute, app()->getLocale());
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $canonical }}">
@foreach(SetLocale::HREFLANG as $locale => $hreflang)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ HomeController::localizedUrl($seoRoute, $locale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ route($seoRoute) }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $ogTitle ?? $seoTitle }}">
<meta property="og:description" content="{{ $ogDescription ?? $seoDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('-', '_', SetLocale::HREFLANG[app()->getLocale()] ?? 'uz') }}">
<meta name="twitter:card" content="summary">
<meta name="theme-color" content="#b45309">
<link rel="icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=unbounded:500,700|golos-text:400,500,600|jetbrains-mono:400,500&display=swap" rel="stylesheet">
@foreach($jsonLd ?? [] as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
@vite(['resources/css/app.css', 'resources/js/app.js'])
