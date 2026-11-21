<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;

/** Generated picture of a label size (labels on a roll liner, drawn to proportion, with dimensions). */
class LabelImageController extends Controller
{
    public function show(int $w, int $h)
    {
        abort_unless($w >= 10 && $w <= 300 && $h >= 10 && $h <= 300, 404);

        // canvas 320×220; labels scaled to fit, two labels visible on the liner
        $scale = min(200 / $w, 70 / $h);
        $lw = round($w * $scale, 1);
        $lh = round($h * $scale, 1);
        $gap = 10;
        $x = round((320 - $lw) / 2, 1);
        $y1 = 34;
        $y2 = $y1 + $lh + $gap;
        $linerX = $x - 14;
        $linerW = $lw + 28;
        $r = min(8, $lh / 6);
        $dimY = $y2 + $lh + 22;

        $label = fn ($y, $opacity) => <<<SVG
  <g opacity="$opacity">
    <rect x="$x" y="$y" width="$lw" height="$lh" rx="$r" fill="#fff" stroke="#d6d3d1"/>
    <rect x="{$this->n($x + $lw * 0.08)}" y="{$this->n($y + $lh * 0.18)}" width="{$this->n(min($lw, $lh) * 0.5)}" height="{$this->n(min($lw, $lh) * 0.5)}" fill="#e7e5e4"/>
    <rect x="{$this->n($x + $lw * 0.08 + min($lw, $lh) * 0.6)}" y="{$this->n($y + $lh * 0.22)}" width="{$this->n($lw * 0.36)}" height="{$this->n(max(3, $lh * 0.08))}" rx="2" fill="#e7e5e4"/>
    <rect x="{$this->n($x + $lw * 0.08 + min($lw, $lh) * 0.6)}" y="{$this->n($y + $lh * 0.40)}" width="{$this->n($lw * 0.24)}" height="{$this->n(max(3, $lh * 0.08))}" rx="2" fill="#e7e5e4"/>
  </g>
SVG;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 220" width="320" height="220" font-family="ui-monospace, Menlo, monospace">
  <rect width="320" height="220" fill="#fafaf9"/>
  <defs><pattern id="liner" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="10" height="10" fill="#efe9e1"/><rect width="5" height="10" fill="#e9e2d8"/></pattern></defs>
  <rect x="$linerX" y="14" width="$linerW" height="{$this->n($dimY - 30)}" rx="6" fill="url(#liner)"/>
{$label($y1, 1)}
{$label($y2, 0.85)}
  <g stroke="#b45309" stroke-width="1.2" fill="#b45309">
    <line x1="$x" y1="{$this->n($y1 - 10)}" x2="{$this->n($x + $lw)}" y2="{$this->n($y1 - 10)}"/>
    <line x1="$x" y1="{$this->n($y1 - 14)}" x2="$x" y2="{$this->n($y1 - 6)}"/>
    <line x1="{$this->n($x + $lw)}" y1="{$this->n($y1 - 14)}" x2="{$this->n($x + $lw)}" y2="{$this->n($y1 - 6)}"/>
    <line x1="{$this->n($x + $lw + 20)}" y1="$y1" x2="{$this->n($x + $lw + 20)}" y2="{$this->n($y1 + $lh)}"/>
    <line x1="{$this->n($x + $lw + 16)}" y1="$y1" x2="{$this->n($x + $lw + 24)}" y2="$y1"/>
    <line x1="{$this->n($x + $lw + 16)}" y1="{$this->n($y1 + $lh)}" x2="{$this->n($x + $lw + 24)}" y2="{$this->n($y1 + $lh)}"/>
  </g>
  <text x="{$this->n($x + $lw / 2)}" y="{$this->n($y1 - 15)}" text-anchor="middle" font-size="11" fill="#78350f">$w mm</text>
  <text x="{$this->n($x + $lw + 30)}" y="{$this->n($y1 + $lh / 2 + 4)}" font-size="11" fill="#78350f">$h mm</text>
  <text x="160" y="{$this->n(min(212, $dimY + 4))}" text-anchor="middle" font-size="15" font-weight="bold" fill="#1c1917">{$w}×{$h} mm</text>
</svg>
SVG;

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
