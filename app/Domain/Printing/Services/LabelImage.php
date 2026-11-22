<?php

namespace App\Domain\Printing\Services;

use App\Domain\Companies\Company;
use App\Domain\Reporting\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images on labels: the company logo (stored, owner uploads it on the company page) and the public
 * studio's own image (never stored, sent with every request). Thermal printers print only black, so
 * a black-and-white version is made for printing. Labels embed images as data URIs (dompdf has no
 * remote access) — the company logo's data URI is built once per request / job, not per label.
 *
 * An image for the renderer is ['uri' => data URI, 'w' => px, 'h' => px].
 */
class LabelImage
{
    public const DISK = 'public';
    public const MAX_SIDE = 600;   // px, long side of the print version
    public const MAX_KB = 1024;    // uploads (company logo and studio image)

    /** @var array<string, array|null> data URIs of company logos, keyed by print path */
    private array $cache = [];

    /** Neutral sample logo for template previews and the editor. */
    public const SAMPLE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="60" viewBox="0 0 120 60">'
        . '<rect x="2" y="2" width="116" height="56" rx="8" fill="#e7e5e4" stroke="#78716c" stroke-width="3"/>'
        . '<text x="60" y="40" font-family="DejaVu Sans, sans-serif" font-size="26" font-weight="bold" fill="#57534e" text-anchor="middle">LOGO</text></svg>';

    public static function sample(): array
    {
        return ['uri' => 'data:image/svg+xml;base64,' . base64_encode(self::SAMPLE_SVG), 'w' => 120, 'h' => 60];
    }

    // ---- company logo -------------------------------------------------------------------------

    /** Stores the original and the black-and-white print version; replaces the previous logo. */
    public function storeCompanyLogo(Company $company, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension()) === 'svg' ? 'svg' : (strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg');
        $bytes = (string) file_get_contents($file->getRealPath());
        $dir = 'logos/' . $company->id;
        $name = Str::random(16);

        if ($ext === 'svg') {
            if (!self::safeSvg($bytes)) {
                throw new \InvalidArgumentException('svg');
            }
            $png = self::svgToPng($bytes);
            $print = $png !== null ? self::blackWhitePng($png) : null;
            // no SVG converter: the SVG itself is printed (dompdf draws simple SVG)
            [$printBytes, $printExt] = $print !== null ? [$print, 'png'] : [$bytes, 'svg'];
        } else {
            $print = self::blackWhitePng($bytes);
            if ($print === null) {
                throw new \InvalidArgumentException('image');
            }
            [$printBytes, $printExt] = [$print, 'png'];
        }

        $original = "$dir/$name.$ext";
        $printPath = "$dir/$name-print.$printExt";
        Storage::disk(self::DISK)->put($original, $bytes);
        Storage::disk(self::DISK)->put($printPath, $printBytes);

        $this->deleteFiles($company);
        $company->forceFill(['logo_path' => $original, 'logo_print_path' => $printPath, 'logo_version' => (int) $company->logo_version + 1])->save();
        AuditLog::record('company.logo_saved', 'company', $company->id);
    }

    public function removeCompanyLogo(Company $company): void
    {
        $this->deleteFiles($company);
        $company->forceFill(['logo_path' => null, 'logo_print_path' => null, 'logo_version' => (int) $company->logo_version + 1])->save();
        AuditLog::record('company.logo_removed', 'company', $company->id);
    }

    private function deleteFiles(Company $company): void
    {
        foreach (array_filter([$company->logo_path, $company->logo_print_path]) as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** The print version of a company's logo for the renderer, or null (no logo → nothing is drawn). */
    public function companyLogo(?Company $company): ?array
    {
        $path = $company?->logo_print_path;
        if (!$path) {
            return null;
        }
        if (!array_key_exists($path, $this->cache)) {
            if (count($this->cache) > 20) {
                $this->cache = []; // long-running queue worker: keep memory bounded
            }
            $this->cache[$path] = Storage::disk(self::DISK)->exists($path) ? self::fromBytes(Storage::disk(self::DISK)->get($path)) : null;
        }

        return $this->cache[$path];
    }

    // ---- public studio image -------------------------------------------------------------------

    /**
     * A studio visitor's own image (data URI from the browser): only PNG / JPEG, ≤ 1 MB decoded,
     * a real image, re-encoded through GD (drops anything else in the file); black-and-white unless
     * $grayscale. Nothing is written to disk. Returns null when the image is not acceptable.
     */
    public static function fromStudio(string $dataUri, bool $grayscale = false): ?array
    {
        if (!preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#', $dataUri, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_KB * 1024) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }
        $png = self::processRaster($bytes, !$grayscale);

        return $png !== null ? self::fromBytes($png) : null;
    }

    // ---- processing ----------------------------------------------------------------------------

    /** Black-and-white PNG of a raster image (PNG/JPEG/GIF/WebP bytes), or null if it is not an image. */
    public static function blackWhitePng(string $bytes): ?string
    {
        return self::processRaster($bytes, true);
    }

    /**
     * Downscale to MAX_SIDE, flatten transparency onto white, then grayscale and (if $threshold)
     * pure black / white. Nothing is trimmed.
     */
    private static function processRaster(string $bytes, bool $threshold): ?string
    {
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $k = min(1, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));

        $img = imagecreatetruecolor($nw, $nh);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagealphablending($img, true);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        imagefilter($img, IMG_FILTER_GRAYSCALE);

        if ($threshold) {
            $black = imagecolorallocate($img, 0, 0, 0);
            $white = imagecolorallocate($img, 255, 255, 255);
            for ($y = 0; $y < $nh; $y++) {
                for ($x = 0; $x < $nw; $x++) {
                    imagesetpixel($img, $x, $y, (imagecolorat($img, $x, $y) & 0xFF) < 128 ? $black : $white);
                }
            }
            imagetruecolortopalette($img, false, 2); // small 2-colour PNG; snap the palette to exact black / white
            for ($i = 0, $n = imagecolorstotal($img); $i < $n; $i++) {
                $v = imagecolorsforindex($img, $i)['red'] < 128 ? 0 : 255;
                imagecolorset($img, $i, $v, $v, $v);
            }
        }

        ob_start();
        imagepng($img, null, 9);
        imagedestroy($img);

        return ob_get_clean() ?: null;
    }

    /** SVG → PNG (long side MAX_SIDE) when Imagick can read SVG; null otherwise. */
    public static function svgToPng(string $svg): ?string
    {
        if (!class_exists(\Imagick::class) || !\Imagick::queryFormats('SVG')) {
            return null;
        }
        try {
            $probe = new \Imagick();
            $probe->readImageBlob($svg);
            $long = max($probe->getImageWidth(), $probe->getImageHeight(), 1);
            $probe->clear();

            $im = new \Imagick();
            $im->setBackgroundColor(new \ImagickPixel('white'));
            $density = max(10, min(1200, 72 * self::MAX_SIDE / $long));
            $im->setResolution($density, $density);
            $im->readImageBlob($svg);
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('png');
            $png = $im->getImageBlob();
            $im->clear();

            return $png !== '' ? $png : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Uploaded SVG must be plain drawing: an <svg> root, no DTD / entities, scripts, event handlers,
     * foreignObject or links to anything but in-document fragments and embedded images.
     */
    public static function safeSvg(string $svg): bool
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY|<script|<foreignObject|\son[a-z]+\s*=|javascript:|<\?php/i', $svg)) {
            return false;
        }
        if (preg_match_all('/(?:xlink:)?href\s*=\s*["\']([^"\']*)["\']/i', $svg, $m)) {
            foreach ($m[1] as $href) {
                if (!str_starts_with($href, '#') && !preg_match('#^data:image/(png|jpeg|gif);base64,#i', $href)) {
                    return false;
                }
            }
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml !== false && strtolower($xml->getName()) === 'svg';
    }

    /** Data URI + pixel size of stored image bytes (PNG/JPEG, or SVG with its declared size). */
    public static function fromBytes(string $bytes): ?array
    {
        if ($info = @getimagesizefromstring($bytes)) {
            return ['uri' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes), 'w' => $info[0], 'h' => $info[1]];
        }
        if (stripos($bytes, '<svg') === false) {
            return null;
        }
        [$w, $h] = self::svgSize($bytes);

        return ['uri' => 'data:image/svg+xml;base64,' . base64_encode($bytes), 'w' => $w, 'h' => $h];
    }

    /** @return array{0: float, 1: float} */
    private static function svgSize(string $svg): array
    {
        if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m) && (float) $m[1] > 0 && (float) $m[2] > 0) {
            return [(float) $m[1], (float) $m[2]];
        }
        if (preg_match('/<svg[^>]*\swidth\s*=\s*["\']([\d.]+)/i', $svg, $w) && preg_match('/<svg[^>]*\sheight\s*=\s*["\']([\d.]+)/i', $svg, $h) && (float) $w[1] > 0 && (float) $h[1] > 0) {
            return [(float) $w[1], (float) $h[1]];
        }

        return [1, 1];
    }
}
