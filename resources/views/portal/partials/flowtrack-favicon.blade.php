@php
    $faviconHref = $brandFavicon ?? ($company->flowtrack_brand_favicon_url ?? null);
@endphp
@if($faviconHref)
    <link rel="icon" href="{{ $faviconHref }}">
@endif
