<?php
/**
 * Daily Deals: stored sticker attachments and image compositing for branded downloads.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists Media Library attachment IDs per corner.
 */
final class CarsDailyDealsStickerStore
{
    private const OPTION = 'bricks_child_daily_deals_stickers';

    /**
     * @return array{top_left:int,top_right:int,bottom_left:int,bottom_right:int}
     */
    public static function getAttachmentIds(): array
    {
        $raw = get_option(self::OPTION, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        return array(
            'top_left'     => isset($raw['top_left']) ? absint($raw['top_left']) : 0,
            'top_right'    => isset($raw['top_right']) ? absint($raw['top_right']) : 0,
            'bottom_left'  => isset($raw['bottom_left']) ? absint($raw['bottom_left']) : 0,
            'bottom_right' => isset($raw['bottom_right']) ? absint($raw['bottom_right']) : 0,
        );
    }

    /**
     * @param array{top_left?:int,top_right?:int,bottom_left?:int,bottom_right?:int} $ids
     */
    public static function saveAttachmentIds(array $ids): void
    {
        $clean = array(
            'top_left'     => isset($ids['top_left']) ? absint($ids['top_left']) : 0,
            'top_right'    => isset($ids['top_right']) ? absint($ids['top_right']) : 0,
            'bottom_left'  => isset($ids['bottom_left']) ? absint($ids['bottom_left']) : 0,
            'bottom_right' => isset($ids['bottom_right']) ? absint($ids['bottom_right']) : 0,
        );
        update_option(self::OPTION, $clean, false);
    }

    /**
     * @return array<int, array{path:string, position:string}>
     */
    public static function getStickerLayersForComposite(): array
    {
        $ids = self::getAttachmentIds();
        $map = array(
            'top_left'     => 'top_left',
            'top_right'    => 'top_right',
            'bottom_left'  => 'bottom_left',
            'bottom_right' => 'bottom_right',
        );
        $out = array();
        foreach ($map as $key => $position) {
            $aid = (int) ($ids[$key] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $path = get_attached_file($aid);
            if ($path === false || $path === '' || !is_readable($path)) {
                continue;
            }
            $out[] = array(
                'path'     => $path,
                'position' => $position,
            );
        }

        return $out;
    }

    public static function hasAnySticker(): bool
    {
        return self::getStickerLayersForComposite() !== array();
    }
}

/**
 * Composites corner stickers; full-frame branded JPEG and Instagram 1080×1080 (letterboxed).
 */
final class CarsDailyDealsImageCompositor
{
    private const MAX_WIDTH_RATIO = 0.28;

    private const MARGIN_RATIO = 0.02;

    /** Instagram feed square (px); photo is scaled to fit inside (nothing cropped). */
    private const INSTAGRAM_SQUARE = 1080;

    private const INSTAGRAM_BG_HEX = '#111111';

    public static function canComposite(): bool
    {
        if (class_exists('Imagick')) {
            return true;
        }

        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromstring');
    }

    /**
     * @param string $sourcePath Server path to listing image.
     * @return string|false Absolute path to temp JPEG, or false if no stickers / failure.
     */
    public static function compositeToTempJpeg(string $sourcePath)
    {
        $layers = CarsDailyDealsStickerStore::getStickerLayersForComposite();
        if ($layers === array() || !is_readable($sourcePath)) {
            return false;
        }

        if (class_exists('Imagick')) {
            return self::compositeFullFrameImagick($sourcePath, $layers);
        }

        return self::compositeFullFrameGd($sourcePath, $layers);
    }

    /**
     * 1080×1080 JPEG: full photo visible (letterboxed), optional corner stickers on top.
     *
     * @param string $sourcePath Server path to listing image.
     * @return string|false
     */
    public static function instagramSquareToTempJpeg(string $sourcePath)
    {
        if (!is_readable($sourcePath) || !self::canComposite()) {
            return false;
        }

        $layers = CarsDailyDealsStickerStore::getStickerLayersForComposite();

        if (class_exists('Imagick')) {
            return self::instagramSquareImagick($sourcePath, $layers);
        }

        return self::instagramSquareGd($sourcePath, $layers);
    }

    /**
     * @param array<int, array{path:string, position:string}> $layers
     * @return string|false
     */
    private static function compositeFullFrameImagick(string $sourcePath, array $layers)
    {
        try {
            $base = new Imagick($sourcePath);
            $base->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $bw = max(1, (int) $base->getImageWidth());
            $bh = max(1, (int) $base->getImageHeight());
            self::applyStickerLayersImagick($base, $bw, $bh, $layers);
            return self::writeImagickJpegTemp($base);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param \Imagick                                        $base
     * @param array<int, array{path:string, position:string}> $layers
     */
    private static function applyStickerLayersImagick($base, int $bw, int $bh, array $layers): void
    {
        $margin = (int) max(8, round(min($bw, $bh) * self::MARGIN_RATIO));
        $maxW   = max(40, (int) round($bw * self::MAX_WIDTH_RATIO));

        foreach ($layers as $layer) {
            $ov = new Imagick($layer['path']);
            $ov->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $ow = max(1, (int) $ov->getImageWidth());
            $scale_h = (int) round(($maxW / $ow) * (int) $ov->getImageHeight());
            $ov->resizeImage($maxW, $scale_h, Imagick::FILTER_LANCZOS, 1);

            $sw = (int) $ov->getImageWidth();
            $sh = (int) $ov->getImageHeight();
            $x  = $margin;
            $y  = $margin;
            switch ($layer['position']) {
                case 'top_right':
                    $x = $bw - $sw - $margin;
                    $y = $margin;
                    break;
                case 'bottom_left':
                    $x = $margin;
                    $y = $bh - $sh - $margin;
                    break;
                case 'bottom_right':
                    $x = $bw - $sw - $margin;
                    $y = $bh - $sh - $margin;
                    break;
                case 'top_left':
                default:
                    $x = $margin;
                    $y = $margin;
                    break;
            }

            $base->compositeImage($ov, Imagick::COMPOSITE_OVER, $x, $y);
            $ov->clear();
            $ov->destroy();
        }
    }

    /**
     * @param \Imagick $base
     * @return string|false
     */
    private static function writeImagickJpegTemp($base)
    {
        try {
            $base->setImageFormat('jpeg');
            $base->setImageCompressionQuality(90);
            $tmp = wp_tempnam('dd-branded-');
            if (! $tmp) {
                $base->clear();
                $base->destroy();
                return false;
            }
            if (! $base->writeImage($tmp)) {
                $base->clear();
                $base->destroy();
                @unlink($tmp);
                return false;
            }
            $base->clear();
            $base->destroy();
            return $tmp;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int, array{path:string, position:string}> $layers
     * @return string|false
     */
    private static function compositeFullFrameGd(string $sourcePath, array $layers)
    {
        $binary = @file_get_contents($sourcePath);
        if ($binary === false) {
            return false;
        }

        $base = @imagecreatefromstring($binary);
        if ($base === false) {
            return false;
        }

        $bw = imagesx($base);
        $bh = imagesy($base);
        if ($bw < 1 || $bh < 1) {
            imagedestroy($base);
            return false;
        }

        imagealphablending($base, true);
        self::applyStickerLayersGd($base, $bw, $bh, $layers);

        $tmp = wp_tempnam('dd-branded-');
        if (! $tmp) {
            imagedestroy($base);
            return false;
        }

        $ok = imagejpeg($base, $tmp, 90);
        imagedestroy($base);
        if (! $ok) {
            @unlink($tmp);
            return false;
        }

        return $tmp;
    }

    /**
     * @param resource|\GdImage $base
     * @param array<int, array{path:string, position:string}> $layers
     */
    private static function applyStickerLayersGd($base, int $bw, int $bh, array $layers): void
    {
        $margin = (int) max(8, round(min($bw, $bh) * self::MARGIN_RATIO));
        $maxW   = max(40, (int) round($bw * self::MAX_WIDTH_RATIO));

        foreach ($layers as $layer) {
            $ov_bin = @file_get_contents($layer['path']);
            if ($ov_bin === false) {
                continue;
            }
            $ov = @imagecreatefromstring($ov_bin);
            if ($ov === false) {
                continue;
            }

            imagealphablending($ov, true);
            imagesavealpha($ov, true);

            $ow = imagesx($ov);
            $oh = imagesy($ov);
            if ($ow < 1 || $oh < 1) {
                imagedestroy($ov);
                continue;
            }

            $nw     = $maxW;
            $nh     = (int) round(($maxW / $ow) * $oh);
            $scaled = imagescale($ov, $nw, $nh, IMG_BILINEAR_FIXED);
            imagedestroy($ov);
            if ($scaled === false) {
                continue;
            }

            $sw = imagesx($scaled);
            $sh = imagesy($scaled);
            $x  = $margin;
            $y  = $margin;
            switch ($layer['position']) {
                case 'top_right':
                    $x = $bw - $sw - $margin;
                    $y = $margin;
                    break;
                case 'bottom_left':
                    $x = $margin;
                    $y = $bh - $sh - $margin;
                    break;
                case 'bottom_right':
                    $x = $bw - $sw - $margin;
                    $y = $bh - $sh - $margin;
                    break;
                case 'top_left':
                default:
                    $x = $margin;
                    $y = $margin;
                    break;
            }

            imagecopy($base, $scaled, $x, $y, 0, 0, $sw, $sh);
            imagedestroy($scaled);
        }
    }

    /**
     * @param array<int, array{path:string, position:string}> $layers
     * @return string|false
     */
    private static function instagramSquareImagick(string $sourcePath, array $layers)
    {
        try {
            $size = (int) apply_filters('bricks_child_daily_deals_instagram_square_size', self::INSTAGRAM_SQUARE);
            if ($size < 600 || $size > 4096) {
                $size = self::INSTAGRAM_SQUARE;
            }
            $bgHex = (string) apply_filters('bricks_child_daily_deals_instagram_bg_hex', self::INSTAGRAM_BG_HEX);

            $canvas = new Imagick();
            $canvas->newImage($size, $size, new ImagickPixel($bgHex));
            $canvas->setImageColorspace(Imagick::COLORSPACE_SRGB);

            $src = new Imagick($sourcePath);
            $src->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $sw = max(1, (int) $src->getImageWidth());
            $sh = max(1, (int) $src->getImageHeight());
            $scale = min($size / $sw, $size / $sh);
            $newW  = (int) max(1, round($sw * $scale));
            $newH  = (int) max(1, round($sh * $scale));
            $src->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1);
            $x = (int) floor(($size - $newW) / 2);
            $y = (int) floor(($size - $newH) / 2);
            $canvas->compositeImage($src, Imagick::COMPOSITE_OVER, $x, $y);
            $src->clear();
            $src->destroy();

            if ($layers !== array()) {
                self::applyStickerLayersImagick($canvas, $size, $size, $layers);
            }

            return self::writeImagickJpegTemp($canvas);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int, array{path:string, position:string}> $layers
     * @return string|false
     */
    private static function instagramSquareGd(string $sourcePath, array $layers)
    {
        $size = (int) apply_filters('bricks_child_daily_deals_instagram_square_size', self::INSTAGRAM_SQUARE);
        if ($size < 600 || $size > 4096) {
            $size = self::INSTAGRAM_SQUARE;
        }

        $hex = strtoupper(ltrim((string) apply_filters('bricks_child_daily_deals_instagram_bg_hex', self::INSTAGRAM_BG_HEX), '#'));
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '111111';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $binary = @file_get_contents($sourcePath);
        if ($binary === false) {
            return false;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return false;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            imagedestroy($src);
            return false;
        }

        $scale = min($size / $sw, $size / $sh);
        $newW  = max(1, (int) round($sw * $scale));
        $newH  = max(1, (int) round($sh * $scale));
        $scaled = imagescale($src, $newW, $newH, IMG_BILINEAR_FIXED);
        imagedestroy($src);
        if ($scaled === false) {
            return false;
        }

        $canvas = imagecreatetruecolor($size, $size);
        if ($canvas === false) {
            imagedestroy($scaled);
            return false;
        }

        $bg = imagecolorallocate($canvas, $r, $g, $b);
        imagefill($canvas, 0, 0, $bg);
        imagealphablending($canvas, true);

        $x = (int) floor(($size - $newW) / 2);
        $y = (int) floor(($size - $newH) / 2);
        imagecopy($canvas, $scaled, $x, $y, 0, 0, $newW, $newH);
        imagedestroy($scaled);

        if ($layers !== array()) {
            self::applyStickerLayersGd($canvas, $size, $size, $layers);
        }

        $tmp = wp_tempnam('dd-ig-');
        if (! $tmp) {
            imagedestroy($canvas);
            return false;
        }

        $ok = imagejpeg($canvas, $tmp, 92);
        imagedestroy($canvas);
        if (! $ok) {
            @unlink($tmp);
            return false;
        }

        return $tmp;
    }
}
