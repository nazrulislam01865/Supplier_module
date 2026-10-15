@php
    $brandName = trim((string) ($company->flowtrack_brand_name ?? '')) ?: 'FlowTrack';
    $brandLogo = $company->flowtrack_brand_logo_url ?? null;
@endphp
@if($brandLogo)<img src="{{ $brandLogo }}" class="flowtrack-synced-logo" alt="{{ $brandName }}" decoding="async">@else<span class="brand-mark"><span class="brand-dot"></span></span>@endif
<span data-flowtrack-brand-name @class(['hidden' => filled($brandLogo)])>{{ $brandName }}</span>
