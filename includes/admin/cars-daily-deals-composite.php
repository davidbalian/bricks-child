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
        $ids  = self::getAttachmentIds();
        $map  = array(
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
 * Composites corner PNG/WebP/JPEG stickers onto a listing photo; outputs JPEG.
 */
final class CarsDailyDealsImageCompositor
{
    private const MAX_WIDTH_RATIO = 0.28;

    private const MARGIN_RATIO = 0.02;

    public static function canComposite(): bool
    {
        if (class_exists('Imagick')) {
            return true;
        }

        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromstring');
    }

    /**
     * Writes a composited JPEG to a new temp file, or returns false to use the original.
     *
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
            return self::compositeImagick($sourcePath, $layers);
        }

        return self::compositeGd($sourcePath, $layers);
    }

    /**
     * @param array<int, array{path:string, position:string}> $layers
     * @return string|false
     */
    private static function compositeImagick(string $sourcePath, array $layers)
    {
        try {
            $base = new Imagick($sourcePath);
            $base->setImageColorspace(Imagick::COLORSPACE_SRGB);

            $bw = max(1, (int) $base->getImageWidth());
            $bh = max(1, (int) $base->getImageHeight());
            $margin = (int) max(8, round(min($bw, $bh) * self::MARGIN_RATIO));
            $maxW     = max(40, (int) round($bw * self::MAX_WIDTH_RATIO));

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
    private static function compositeGd(string $sourcePath, array $layers)
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

            $nw = $maxW;
            $nh = (int) round(($maxW / $ow) * $oh);
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
}
