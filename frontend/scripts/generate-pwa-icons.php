<?php

declare(strict_types=1);

/**
 * Generate global PWA / install icons from the official Rafeea app icon.
 *
 * Source: frontend/src/assets/brand/rafeea-app-icon.png
 * Output: frontend/public/icons/*
 *
 * Does NOT touch the in-app UI logo (rafeea-logo.png).
 */

$srcPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'brand'.DIRECTORY_SEPARATOR.'rafeea-app-icon.png';
$outDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'icons';

if (! is_file($srcPath)) {
    fwrite(STDERR, "Missing app icon source: {$srcPath}\n");
    exit(1);
}

if (! function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "PHP GD extension is required to generate PWA icons.\n");
    exit(1);
}

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Unable to create {$outDir}\n");
    exit(1);
}

$source = imagecreatefrompng($srcPath);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$srcPath}\n");
    exit(1);
}

imagealphablending($source, true);
imagesavealpha($source, true);

$srcW = imagesx($source);
$srcH = imagesy($source);

/** Brand-green fill matching the designed app-icon field (covers export corner AA). */
const FILL_R = 1;
const FILL_G = 52;
const FILL_B = 39;

/**
 * @param  resource|\GdImage  $source
 */
function writePngIcon($source, int $srcW, int $srcH, string $path, int $size, float $insetRatio, bool $opaque): void
{
    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) {
        throw new RuntimeException("Unable to create canvas {$size}x{$size}");
    }

    if ($opaque) {
        imagealphablending($canvas, true);
        $background = imagecolorallocate($canvas, FILL_R, FILL_G, FILL_B);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
    } else {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
        imagealphablending($canvas, true);
        // Designed icon already has a green field — fill with that green so white
        // export-corner anti-aliasing does not show on Android/iOS home screens.
        $background = imagecolorallocate($canvas, FILL_R, FILL_G, FILL_B);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
    }

    $inset = (int) round($size * $insetRatio);
    $box = max(1, $size - ($inset * 2));
    $scale = min($box / $srcW, $box / $srcH);
    $destW = max(1, (int) round($srcW * $scale));
    $destH = max(1, (int) round($srcH * $scale));
    $destX = (int) round(($size - $destW) / 2);
    $destY = (int) round(($size - $destH) / 2);

    imagecopyresampled($canvas, $source, $destX, $destY, 0, 0, $destW, $destH, $srcW, $srcH);

    if ($opaque) {
        // Flatten to opaque PNG (Apple prefers non-transparent home-screen icons).
        $flat = imagecreatetruecolor($size, $size);
        if ($flat === false) {
            throw new RuntimeException("Unable to flatten canvas {$size}x{$size}");
        }
        $background = imagecolorallocate($flat, FILL_R, FILL_G, FILL_B);
        imagefilledrectangle($flat, 0, 0, $size, $size, $background);
        imagealphablending($flat, true);
        imagecopy($flat, $canvas, 0, 0, 0, 0, $size, $size);
        imagepng($flat, $path, 9);
        imagedestroy($flat);
    } else {
        imagesavealpha($canvas, true);
        imagepng($canvas, $path, 9);
    }

    imagedestroy($canvas);
}

/*
 * any: near full-bleed so the designed icon reads as-is.
 * maskable: extra padding (~20%) so the central emblem stays in the safe zone
 * under circle / rounded-square / squircle crops — do not crop the artwork.
 */
$icons = [
    // name, size, insetRatio, opaque
    ['icon-32.png', 32, 0.04, true],
    ['apple-touch-icon.png', 180, 0.04, true],
    ['icon-192.png', 192, 0.04, false],
    ['icon-512.png', 512, 0.04, false],
    ['icon-192-maskable.png', 192, 0.20, true],
    ['icon-512-maskable.png', 512, 0.20, true],
];

foreach ($icons as [$name, $size, $inset, $opaque]) {
    $path = $outDir.DIRECTORY_SEPARATOR.$name;
    writePngIcon($source, $srcW, $srcH, $path, $size, $inset, $opaque);
    echo "Wrote {$name} ({$size}x{$size})\n";
}

imagedestroy($source);

echo "Generated PWA icons in {$outDir}\n";
