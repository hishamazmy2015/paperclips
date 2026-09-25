<?php

declare(strict_types=1);

use App\Media\ImageProcessor;

function pngOf(int $w, int $h, array $rgb, ?array $stripe = null): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));
    if ($stripe !== null) {
        imagefilledrectangle($img, 0, 0, intdiv($w, 3), $h, imagecolorallocate($img, ...$stripe));
    }
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

it('crops to a square WebP and strips everything but pixels', function (): void {
    $webp = app(ImageProcessor::class)->squareWebp(pngOf(800, 400, [200, 30, 30]), 256);
    $info = getimagesizefromstring($webp);

    expect($info[0])->toBe(256)->and($info[1])->toBe(256)->and($info['mime'])->toBe('image/webp');
});

it('fits a logo inside a box without cropping', function (): void {
    $webp = app(ImageProcessor::class)->fitWebp(pngOf(1000, 250, [20, 60, 200]), 500);
    $info = getimagesizefromstring($webp);

    expect($info[0])->toBe(500)->and($info[1])->toBe(125);
});

it('finds the dominant saturated colour, ignoring white and grey', function (): void {
    $processor = app(ImageProcessor::class);
    // mostly white with a navy stripe → navy wins; a grey image has no colour
    $navy = $processor->dominantColor(pngOf(300, 300, [255, 255, 255], [30, 58, 138]));
    expect($navy)->toMatch('/^#[0-9a-f]{6}$/');
    [$h] = ImageProcessor::hsl(...ImageProcessor::rgb((string) $navy));
    expect($h)->toBeGreaterThan(200)->toBeLessThan(250)
        ->and($processor->dominantColor(pngOf(50, 50, [128, 128, 128])))->toBeNull();

    $palette = ImageProcessor::paletteFrom('#c8963e');
    expect($palette)->toHaveKeys(['primary', 'secondary', 'accent']);
    foreach ($palette as $hex) {
        expect($hex)->toMatch('/^#[0-9a-f]{6}$/');
    }
    [, , $l] = ImageProcessor::hsl(...ImageProcessor::rgb($palette['primary']));
    expect($l)->toBeLessThanOrEqual(0.43); // dark enough for white text on the primary
});

it('rejects files that are not images', function (): void {
    expect(fn () => app(ImageProcessor::class)->squareWebp('not an image'))->toThrow(InvalidArgumentException::class);
});
