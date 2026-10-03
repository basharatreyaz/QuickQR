<?php

namespace App\QR;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;

class QrGenerator implements QrGeneratorInterface
{
    private function parseEcc(string $ecc): int
    {
        return match (strtoupper($ecc)) {
            'L' => EccLevel::L,
            'Q' => EccLevel::Q,
            'H' => EccLevel::H,
            default => EccLevel::M,
        };
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public function renderSvg(string $content, array $config = []): string
    {
        $fgColor = $config['fg_color'] ?? '#0f172a';
        $bgColor = $config['bg_color'] ?? '#ffffff';
        $margin  = max(0, min(10, (int)($config['margin'] ?? 2)));
        $size    = max(100, min(1000, (int)($config['size'] ?? 300)));
        $scale   = max(4, (int)round($size / 30));
        $ecc     = $this->parseEcc($config['ecc'] ?? 'M');
        $style   = $config['style'] ?? 'dots'; // 'dots', 'rounded', 'square'

        $logoPath = $this->resolveLogoPath($config['logo_path'] ?? null);
        $hasLogo = !empty($logoPath);

        $options = new QROptions([
            'outputInterface'     => QRMarkupSVG::class,
            'outputBase64'        => false,
            'svgAddXmlHeader'     => true,
            'eccLevel'            => $hasLogo ? EccLevel::H : $ecc,
            'scale'               => $scale,
            'quietzoneSize'       => $margin,
            'bgColor'             => $bgColor,
            'drawCircularModules' => in_array($style, ['rounded', 'dots'], true),
            'circleRadius'        => ($style === 'dots') ? 0.45 : 0.25,
            'keepAsSquare'        => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT, QRMatrix::M_ALIGNMENT_DARK],
            'moduleValues'        => $this->getSvgModuleValues($fgColor, $bgColor),
        ]);

        if ($hasLogo) {
            $options->addLogoSpace = true;
            $options->logoSpaceWidth = 9;
            $options->logoSpaceHeight = 9;
        }

        $svg = (new QRCode($options))->render($content);

        // If logo exists, embed into SVG center
        if ($hasLogo) {
            $svg = $this->embedLogoInSvg($svg, $logoPath);
        }

        return $svg;
    }

    public function renderPng(string $content, array $config = []): string
    {
        $fgHex = $config['fg_color'] ?? '#0f172a';
        $bgHex = $config['bg_color'] ?? '#ffffff';
        $margin = max(0, min(10, (int)($config['margin'] ?? 2)));
        $size   = max(100, min(1000, (int)($config['size'] ?? 300)));
        $scale  = max(4, (int)round($size / 30));
        $ecc    = $this->parseEcc($config['ecc'] ?? 'M');
        $style  = $config['style'] ?? 'dots';

        $logoPath = $this->resolveLogoPath($config['logo_path'] ?? null);
        $hasLogo = !empty($logoPath);

        $fgRgb = $this->hexToRgb($fgHex);
        $bgRgb = $this->hexToRgb($bgHex);

        $options = new QROptions([
            'outputInterface'     => QRGdImagePNG::class,
            'outputBase64'        => false,
            'eccLevel'            => $hasLogo ? EccLevel::H : $ecc,
            'scale'               => $scale,
            'quietzoneSize'       => $margin,
            'bgColor'             => $bgRgb,
            'drawCircularModules' => in_array($style, ['rounded', 'dots'], true),
            'circleRadius'        => ($style === 'dots') ? 0.45 : 0.25,
            'keepAsSquare'        => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT, QRMatrix::M_ALIGNMENT_DARK],
            'moduleValues'        => $this->getPngModuleValues($fgRgb, $bgRgb),
        ]);

        if ($hasLogo) {
            $options->addLogoSpace = true;
            $options->logoSpaceWidth = 9;
            $options->logoSpaceHeight = 9;
        }

        $pngData = (new QRCode($options))->render($content);

        if ($hasLogo) {
            $pngData = $this->overlayLogoOnPng($pngData, $logoPath);
        }

        return $pngData;
    }

    private function resolveLogoPath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (file_exists($path)) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);
        $projectRoot = dirname(__DIR__, 2);
        $relPath = $projectRoot . '/' . ltrim($normalized, '/');
        if (file_exists($relPath)) {
            return $relPath;
        }

        $baseName = basename($normalized);
        $storageUploads = $projectRoot . '/storage/uploads/' . $baseName;
        if (file_exists($storageUploads)) {
            return $storageUploads;
        }

        return null;
    }

    private function embedLogoInSvg(string $svg, string $logoPath): string
    {
        $logoData = file_get_contents($logoPath);
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $base64 = base64_encode($logoData);

        // Find width and height from SVG
        if (preg_match('/viewBox="([^"]+)"/', $svg, $matches)) {
            $parts = explode(' ', trim($matches[1]));
            if (count($parts) === 4) {
                $width = (float)$parts[2];
                $height = (float)$parts[3];
                $logoSize = $width * 0.24;
                $x = ($width - $logoSize) / 2;
                $y = ($height - $logoSize) / 2;

                $logoElem = sprintf(
                    '<image x="%.2f" y="%.2f" width="%.2f" height="%.2f" href="data:%s;base64,%s" />',
                    $x, $y, $logoSize, $logoSize, $mime, $base64
                );

                return str_replace('</svg>', $logoElem . '</svg>', $svg);
            }
        }

        return $svg;
    }

    private function overlayLogoOnPng(string $pngData, string $logoPath): string
    {
        $qrImg = imagecreatefromstring($pngData);
        if (!$qrImg) {
            return $pngData;
        }

        $logoData = file_get_contents($logoPath);
        $logoImg = imagecreatefromstring($logoData);
        if (!$logoImg) {
            return $pngData;
        }

        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);
        $logoW = imagesx($logoImg);
        $logoH = imagesy($logoImg);

        $targetW = (int)($qrW * 0.22);
        $targetH = (int)($logoH * ($targetW / $logoW));

        $dstX = (int)(($qrW - $targetW) / 2);
        $dstY = (int)(($qrH - $targetH) / 2);

        // Enable alpha blending
        imagealphablending($qrImg, true);
        imagesavealpha($qrImg, true);

        // Optional circular white backing for logo
        $pad = 6;
        $white = imagecolorallocate($qrImg, 255, 255, 255);
        imagefilledrectangle($qrImg, $dstX - $pad, $dstY - $pad, $dstX + $targetW + $pad, $dstY + $targetH + $pad, $white);

        imagecopyresampled($qrImg, $logoImg, $dstX, $dstY, 0, 0, $targetW, $targetH, $logoW, $logoH);

        ob_start();
        imagepng($qrImg);
        $merged = ob_get_clean();

        imagedestroy($qrImg);
        imagedestroy($logoImg);

        return $merged ?: $pngData;
    }

    private function getSvgModuleValues(string $fgColor, string $bgColor): array
    {
        return [
            // Dark modules -> Custom foreground color
            QRMatrix::M_DATA_DARK        => $fgColor,
            QRMatrix::M_FINDER_DARK      => $fgColor,
            QRMatrix::M_FINDER_DOT       => $fgColor,
            QRMatrix::M_ALIGNMENT_DARK   => $fgColor,
            QRMatrix::M_TIMING_DARK      => $fgColor,
            QRMatrix::M_FORMAT_DARK      => $fgColor,
            QRMatrix::M_VERSION_DARK     => $fgColor,
            QRMatrix::M_DARKMODULE       => $fgColor,
            QRMatrix::M_SEPARATOR_DARK   => $fgColor,
            QRMatrix::M_QUIETZONE_DARK   => $fgColor,
            QRMatrix::M_LOGO_DARK        => $fgColor,

            // Light modules -> Custom background color
            QRMatrix::M_NULL             => $bgColor,
            QRMatrix::M_DARKMODULE_LIGHT => $bgColor,
            QRMatrix::M_DATA             => $bgColor,
            QRMatrix::M_FINDER           => $bgColor,
            QRMatrix::M_SEPARATOR        => $bgColor,
            QRMatrix::M_ALIGNMENT        => $bgColor,
            QRMatrix::M_TIMING           => $bgColor,
            QRMatrix::M_FORMAT           => $bgColor,
            QRMatrix::M_VERSION          => $bgColor,
            QRMatrix::M_QUIETZONE        => $bgColor,
            QRMatrix::M_LOGO             => $bgColor,
            QRMatrix::M_FINDER_DOT_LIGHT => $bgColor,
        ];
    }

    private function getPngModuleValues(array $fgRgb, array $bgRgb): array
    {
        return [
            // Dark modules -> Custom foreground color
            QRMatrix::M_DATA_DARK        => $fgRgb,
            QRMatrix::M_FINDER_DARK      => $fgRgb,
            QRMatrix::M_FINDER_DOT       => $fgRgb,
            QRMatrix::M_ALIGNMENT_DARK   => $fgRgb,
            QRMatrix::M_TIMING_DARK      => $fgRgb,
            QRMatrix::M_FORMAT_DARK      => $fgRgb,
            QRMatrix::M_VERSION_DARK     => $fgRgb,
            QRMatrix::M_DARKMODULE       => $fgRgb,
            QRMatrix::M_SEPARATOR_DARK   => $fgRgb,
            QRMatrix::M_QUIETZONE_DARK   => $fgRgb,
            QRMatrix::M_LOGO_DARK        => $fgRgb,

            // Light modules -> Custom background color
            QRMatrix::M_NULL             => $bgRgb,
            QRMatrix::M_DARKMODULE_LIGHT => $bgRgb,
            QRMatrix::M_DATA             => $bgRgb,
            QRMatrix::M_FINDER           => $bgRgb,
            QRMatrix::M_SEPARATOR        => $bgRgb,
            QRMatrix::M_ALIGNMENT        => $bgRgb,
            QRMatrix::M_TIMING           => $bgRgb,
            QRMatrix::M_FORMAT           => $bgRgb,
            QRMatrix::M_VERSION          => $bgRgb,
            QRMatrix::M_QUIETZONE        => $bgRgb,
            QRMatrix::M_LOGO             => $bgRgb,
            QRMatrix::M_FINDER_DOT_LIGHT => $bgRgb,
        ];
    }
}
