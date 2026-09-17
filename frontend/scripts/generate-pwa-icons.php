<?php

declare(strict_types=1);

$srcPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'brand'.DIRECTORY_SEPARATOR.'rafeea-logo.png';
$outDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'icons';

if (! is_file($srcPath)) {
    fwrite(STDERR, "Missing brand logo: {$srcPath}\n");
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

/**
 * @param  resource|\GdImage  $source
 */
function writePngIcon($source, int $srcW, int $srcH, string $path, int $size, float $insetRatio): void
{
    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) {
        throw new RuntimeException("Unable to create canvas {$size}x{$size}");
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $background = imagecolorallocate($canvas, 6, 78, 59);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
    imagealphablending($canvas, true);

    $inset = (int) round($size * $insetRatio);
    $box = max(1, $size - ($inset * 2));
    $scale = min($box / $srcW, $box / $srcH);
    $destW = max(1, (int) round($srcW * $scale));
    $destH = max(1, (int) round($srcH * $scale));
    $destX = (int) round(($size - $destW) / 2);
    $destY = (int) round(($size - $destH) / 2);

    imagecopyresampled($canvas, $source, $destX, $destY, 0, 0, $destW, $destH, $srcW, $srcH);
    imagepng($canvas, $path, 9);
    imagedestroy($canvas);
}

$icons = [
    ['icon-32.png', 32, 0.12],
    ['apple-touch-icon.png', 180, 0.12],
    ['icon-192.png', 192, 0.12],
    ['icon-512.png', 512, 0.12],
    ['icon-192-maskable.png', 192, 0.22],
    ['icon-512-maskable.png', 512, 0.22],
];

foreach ($icons as [$name, $size, $inset]) {
    writePngIcon($source, $srcW, $srcH, $outDir.DIRECTORY_SEPARATOR.$name, $size, $inset);
}

imagedestroy($source);

echo "Generated PWA icons in {$outDir}\n";
