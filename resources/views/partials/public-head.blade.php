{{-- <head> content shared by the public pages: SEO meta, language alternates, fonts.
     Expects: $seoTitle, $seoDescription, $seoRoute (route name); optional $seoParams (route parameters), $ogTitle, $ogDescription, $jsonLd (array of schema.org objects). --}}
@php
    use App\Http\Controllers\Guest\HomeController;
    use App\Http\Middleware\SetLocale;
    $seoParams ??= [];
    $localized = fn (string $locale) => HomeController::localizedUrl($seoRoute, $locale, $seoParams);
    $canonical = $localized(app()->getLocale());
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
@if(\App\Support\Seo::noindex())
<meta name="robots" content="noindex, nofollow">
@endif
@foreach(['google' => 'google-site-verification', 'yandex' => 'yandex-verification', 'bing' => 'msvalidate.01', 'baidu' => 'baidu-site-verification'] as $key => $meta)
@if($code = \App\Support\Seo::get($key))
<meta name="{{ $meta }}" content="{{ $code }}">
@endif
@endforeach
<link rel="canonical" href="{{ $canonical }}">
@foreach(SetLocale::HREFLANG as $locale => $hreflang)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $localized($locale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ route($seoRoute, $seoParams) }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $ogTitle ?? $seoTitle }}">
<meta property="og:description" content="{{ $ogDescription ?? $seoDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('-', '_', SetLocale::HREFLANG[app()->getLocale()] ?? 'uz') }}">
<meta property="og:image" content="{{ url('/images/og.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
@include('partials.icons')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=unbounded:500,700|golos-text:400,500,600|jetbrains-mono:400,500&display=swap" rel="stylesheet">
@foreach($jsonLd ?? [] as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if(app()->isProduction() && ($ga4 = \App\Support\Seo::get('ga4')))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@js($ga4));</script>
@endif
@if(app()->isProduction() && ($metrika = \App\Support\Seo::get('metrika')))
<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym({{ (int) $metrika }},'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true});</script>
<noscript><div><img src="https://mc.yandex.ru/watch/{{ (int) $metrika }}" style="position:absolute;left:-9999px" alt=""></div></noscript>
@endif
