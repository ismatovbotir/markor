{{-- Template preview at real proportions. Needs $template and $previewHtml; optional $maxW / $maxH in px. --}}
@php
    $pxPerMm = 3.7795;
    $w = $template->width_mm * $pxPerMm;
    $h = $template->height_mm * $pxPerMm;
    $scale = min(($maxW ?? 260) / $w, ($maxH ?? 170) / $h, 2.5);
@endphp
<div class="relative bg-white shadow-sm outline outline-1 outline-stone-300 overflow-hidden mx-auto"
     style="width: {{ round($w * $scale) }}px; height: {{ round($h * $scale) }}px;">
    <iframe srcdoc="{{ $previewHtml }}" sandbox="" scrolling="no" tabindex="-1" aria-hidden="true" loading="lazy"
            style="border:0; width: {{ ceil($w) }}px; height: {{ ceil($h) }}px; transform: scale({{ $scale }}); transform-origin: 0 0; pointer-events: none;"></iframe>
</div>
