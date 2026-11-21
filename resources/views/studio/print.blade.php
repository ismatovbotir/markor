<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>{{ __('studio.print_title', ['from' => $from, 'to' => $to, 'total' => $total]) }}</title>
<style>
  /* one printed page = one row of labels, exactly the label size: set the same paper size in the printer driver */
  @page { size: {{ $pageW }}mm {{ $height }}mm; margin: 0; }
  html, body { margin: 0; padding: 0; }
  .page { position: relative; width: {{ $pageW }}mm; height: {{ $height }}mm; overflow: hidden; break-after: page; page-break-after: always; }
  .page:last-child { break-after: auto; page-break-after: auto; }
  .label { position: absolute; top: 0; width: {{ $width }}mm; height: {{ $height }}mm; overflow: hidden; }
  img { image-rendering: pixelated; image-rendering: crisp-edges; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  @media screen {
    body { background: #e7e5e4; font-family: system-ui, sans-serif; padding: 72px 16px 32px; }
    .page { background: #fff; margin: 0 auto 6mm; box-shadow: 0 1px 4px rgb(0 0 0 / .25); }
    .bar { position: fixed; inset: 0 0 auto 0; background: #fff; border-bottom: 1px solid #d6d3d1; padding: 10px 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; font-size: 14px; color: #44403c; z-index: 1; }
    .bar button { background: #b45309; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font-size: 14px; cursor: pointer; }
    .bar small { color: #78716c; display: block; }
  }
  @media print { .bar { display: none; } }
</style>
</head>
<body>
<div class="bar">
    <div>
        <strong>{{ __('studio.print_title', ['from' => $from, 'to' => $to, 'total' => $total]) }}</strong>
        <small>{{ __('studio.print_hint', ['size' => rtrim(rtrim(number_format($pageW, 1, '.', ''), '0'), '.') . '×' . rtrim(rtrim(number_format($height, 1, '.', ''), '0'), '.') . ' mm']) }}</small>
    </div>
    <button type="button" onclick="window.print()">{{ __('studio.print') }}</button>
</div>
@foreach($pages as $row)
    <div class="page">
        @foreach($row as $i => $label)
            <div class="label" style="left: {{ $i * ($width + $gap) }}mm;">{!! $label !!}</div>
        @endforeach
    </div>
@endforeach
<script>
  // open the system print dialog once every barcode image is loaded
  window.addEventListener('load', function () { setTimeout(function () { window.focus(); window.print(); }, 200); });
</script>
</body>
</html>
