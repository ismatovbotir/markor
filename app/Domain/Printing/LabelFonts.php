<?php

namespace App\Domain\Printing;

/**
 * Fonts for label text (editor, browser print, PDF). Files: public/fonts/label/{key}-{400|700}.ttf,
 * free to embed (OFL / Apache 2.0, see public/fonts/label/LICENSES.md). No font set = DejaVu Sans,
 * as before. CSS family names are prefixed "lbl-" so a font installed on the viewer's computer
 * never replaces the embedded one.
 */
final class LabelFonts
{
    /**
     * key => [name, pdf line-height, has Uzbek ʻ (U+02BB)].
     * PDF line-height: dompdf spaces lines by line-height × (ascender − descender) × 1.1, so each font
     * gets the factor that gives the editor's 1.2 em: 1.2 / (dompdf font height), scaled like DejaVu's
     * measured 0.93 (LabelTemplate::PDF_LINE_HEIGHT_CSS). Recompute when a font file changes.
     */
    public const FONTS = [
        'roboto'           => ['Roboto', 0.92, false],
        'open-sans'        => ['Open Sans', 0.80, false],
        'montserrat'       => ['Montserrat', 0.89, true],
        'pt-sans'          => ['PT Sans', 0.84, false],
        'pt-serif'         => ['PT Serif', 0.82, false],
        'noto-sans'        => ['Noto Sans', 0.80, true],
        'roboto-condensed' => ['Roboto Condensed', 0.92, false],
        'oswald'           => ['Oswald', 0.73, true],
        'arimo'            => ['Arimo', 0.94, true],
        'tinos'            => ['Tinos', 0.94, true],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FONTS);
    }

    public static function valid(?string $key): bool
    {
        return $key !== null && isset(self::FONTS[$key]);
    }

    /** CSS font-family for an element, or '' for the default font (inherited from the label). */
    public static function familyCss(?string $key): string
    {
        return self::valid($key) ? "font-family:lbl-{$key},'DejaVu Sans',sans-serif;" : '';
    }

    /** Text as printed in this font: ʻ becomes the look-alike ‘ where the font has no ʻ (no empty box in the PDF). */
    public static function text(string $text, ?string $key): string
    {
        return self::valid($key) && !self::FONTS[$key][2] ? str_replace("\u{02BB}", "\u{2018}", $text) : $text;
    }

    /**
     * @font-face rules. Browser: public URLs (a font is downloaded only when a label uses it).
     * PDF: local file paths, only for fonts that appear in $html (dompdf loads every rule it sees).
     */
    public static function faceCss(bool $pdf = false, ?string $html = null): string
    {
        $css = '';
        foreach (self::FONTS as $key => $font) {
            if ($html !== null && !str_contains($html, "lbl-{$key},")) {
                continue;
            }
            foreach ([400 => 'normal', 700 => 'bold'] as $weight => $name) {
                $file = "fonts/label/{$key}-{$weight}.ttf";
                $src = $pdf ? public_path($file) : asset($file);
                $css .= "@font-face{font-family:'lbl-{$key}';font-style:normal;font-weight:{$name};src:url('{$src}') format('truetype');"
                    . ($pdf ? '' : 'font-display:swap;') . "}\n";
            }
        }

        return $css;
    }

    /** PDF: each font's own line-height in place of the editor's 1.2 (see LabelTemplate::textCss). */
    public static function pdfLineHeights(string $html): string
    {
        return preg_replace_callback(
            "/font-family:lbl-([a-z-]+),'DejaVu Sans',sans-serif;line-height:1\\.2;/",
            fn ($m) => isset(self::FONTS[$m[1]])
                ? "font-family:lbl-{$m[1]},'DejaVu Sans',sans-serif;line-height:" . self::FONTS[$m[1]][1] . ';'
                : $m[0],
            $html
        );
    }

    /** For the editor dropdown: [{value, label}], the default font first. */
    public static function options(): array
    {
        return [
            ['value' => '', 'label' => 'DejaVu Sans'],
            ...collect(self::FONTS)->map(fn ($f, $k) => ['value' => $k, 'label' => $f[0]])->values()->all(),
        ];
    }
}
