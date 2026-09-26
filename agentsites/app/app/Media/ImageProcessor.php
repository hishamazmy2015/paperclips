<?php

declare(strict_types=1);

namespace App\Media;

use GdImage;
use InvalidArgumentException;

/**
 * Photos and logos with GD (spec §16, §17): decoded from the upload, auto-oriented, re-encoded
 * (EXIF and everything else stripped) as WebP. Also the "from my logo" dominant colour (§13 S3).
 */
final class ImageProcessor
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** Centre-crop to a square of $size px and encode as WebP. */
    public function squareWebp(string $binary, int $size = 512, int $quality = 82): string
    {
        $image = $this->decode($binary);
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $x = intdiv($width - $side, 2);
        $y = intdiv($height - $side, 2);

        $square = imagecreatetruecolor($size, $size);
        if ($square === false) {
            throw new InvalidArgumentException('cannot allocate image');
        }
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopyresampled($square, $image, 0, 0, $x, $y, $size, $size, $side, $side);

        return $this->encode($square, $quality);
    }

    /** Fit inside $max px (no crop) and encode as WebP — logos keep their shape. */
    public function fitWebp(string $binary, int $max = 640, int $quality = 85): string
    {
        $image = $this->decode($binary);
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $fitted = imagecreatetruecolor($w, $h);
        if ($fitted === false) {
            throw new InvalidArgumentException('cannot allocate image');
        }
        imagealphablending($fitted, false);
        imagesavealpha($fitted, true);
        imagecopyresampled($fitted, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        return $this->encode($fitted, $quality);
    }

    /**
     * Listing photo variants (spec §16): thumb / card / hero WebP, fitted inside 400 / 800 / 1600 px.
     *
     * @return array{thumb: string, card: string, hero: string, width: int, height: int}
     */
    public function variants(string $binary): array
    {
        $image = $this->decode($binary);

        return [
            'thumb' => $this->fitWebp($binary, 400, 78),
            'card' => $this->fitWebp($binary, 800, 80),
            'hero' => $this->fitWebp($binary, 1600, 82),
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    /**
     * The dominant saturated colour of an image as #rrggbb (spec §13 S3 "From my logo"):
     * pixels are sampled on a small grid, near-white/black and grey pixels ignored, hues
     * bucketed and the strongest bucket averaged. Null when the image has no colour at all.
     */
    public function dominantColor(string $binary): ?string
    {
        $image = $this->decode($binary);
        $sample = imagecreatetruecolor(32, 32);
        if ($sample === false) {
            return null;
        }
        imagecopyresampled($sample, $image, 0, 0, 0, 0, 32, 32, imagesx($image), imagesy($image));

        /** @var array<int, array{weight: float, r: float, g: float, b: float, n: int}> $buckets */
        $buckets = [];
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                if ($rgb === false) {
                    continue;
                }
                if (((int) $rgb >> 24) > 64) {
                    continue; // mostly transparent
                }
                $r = ((int) $rgb >> 16) & 0xFF;
                $g = ((int) $rgb >> 8) & 0xFF;
                $b = (int) $rgb & 0xFF;
                [$h, $s, $l] = self::hsl($r, $g, $b);
                if ($s < 0.25 || $l < 0.12 || $l > 0.9) {
                    continue;
                }
                $bucket = (int) floor($h / 15);
                $buckets[$bucket] ??= ['weight' => 0.0, 'r' => 0.0, 'g' => 0.0, 'b' => 0.0, 'n' => 0];
                $buckets[$bucket]['weight'] += $s;
                $buckets[$bucket]['r'] += $r;
                $buckets[$bucket]['g'] += $g;
                $buckets[$bucket]['b'] += $b;
                $buckets[$bucket]['n']++;
            }
        }
        if ($buckets === []) {
            return null;
        }
        uasort($buckets, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);
        $top = reset($buckets);

        return self::hex((int) round($top['r'] / $top['n']), (int) round($top['g'] / $top['n']), (int) round($top['b'] / $top['n']));
    }

    /**
     * A custom palette derived from one brand colour: the primary darkened until white text
     * reads on it, a deeper secondary and a lighter accent.
     *
     * @return array{primary: string, secondary: string, accent: string}
     */
    public static function paletteFrom(string $hex): array
    {
        [$r, $g, $b] = self::rgb($hex);
        [$h, $s, $l] = self::hsl($r, $g, $b);
        $primary = self::fromHsl($h, $s, min($l, 0.42));
        $secondary = self::fromHsl($h, min(1.0, $s * 0.8), max(0.12, min($l, 0.42) * 0.55));
        $accent = self::fromHsl(fmod($h + 30, 360), min(1.0, $s * 0.9), min(0.62, max(0.45, $l + 0.18)));

        return ['primary' => $primary, 'secondary' => $secondary, 'accent' => $accent];
    }

    private function decode(string $binary): GdImage
    {
        if (strlen($binary) > self::MAX_BYTES) {
            throw new InvalidArgumentException('image larger than 8 MB');
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new InvalidArgumentException('not a JPEG, PNG, WebP or GIF image');
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new InvalidArgumentException('cannot decode image');
        }
        imagepalettetotruecolor($image);

        // honour the camera orientation before EXIF is dropped
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $stream = fopen('php://memory', 'r+');
            if ($stream !== false) {
                fwrite($stream, $binary);
                rewind($stream);
                $exif = @exif_read_data($stream);
                fclose($stream);
                $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
                $rotated = match ($orientation) {
                    3 => imagerotate($image, 180, 0),
                    6 => imagerotate($image, -90, 0),
                    8 => imagerotate($image, 90, 0),
                    default => false,
                };
                if ($rotated !== false) {
                    $image = $rotated;
                }
            }
        }

        return $image;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $out = (string) ob_get_clean();
        if (! $ok || $out === '') {
            throw new InvalidArgumentException('cannot encode WebP');
        }

        return $out;
    }

    /** @return array{0: float, 1: float, 2: float} hue 0–360, saturation 0–1, lightness 0–1 */
    public static function hsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    public static function fromHsl(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return self::hex((int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }
}
