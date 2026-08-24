@php
    $meta = \App\Support\Seo\MetaTagsResolver::resolve(get_defined_vars());
@endphp

<title>{{ $meta['title'] }}</title>
<meta name="title" content="{{ $meta['title'] }}">
<meta name="description" content="{{ $meta['description'] }}">
<meta name="keywords" content="{{ $meta['keywords'] }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">
<meta property="og:url" content="{{ $meta['canonical'] }}">
<meta property="og:image" content="{{ $meta['image'] }}">
<meta name="twitter:image" content="{{ $meta['image'] }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta name="twitter:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta name="twitter:description" content="{{ $meta['description'] }}">
<meta property="og:locale" content="pl_PL">
<meta property="og:site_name" content="Biuro Podróży RAFA">
<meta property="og:type" content="{{ $meta['og_type'] }}">
<meta name="twitter:card" content="summary_large_image">
