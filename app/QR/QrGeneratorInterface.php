<?php

namespace App\QR;

interface QrGeneratorInterface
{
    /**
     * Render QR code as SVG markup.
     */
    public function renderSvg(string $content, array $config = []): string;

    /**
     * Render QR code as PNG binary data.
     */
    public function renderPng(string $content, array $config = []): string;
}