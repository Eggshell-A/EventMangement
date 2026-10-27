<?php
declare(strict_types=1);

function renderEventQr(string $url): string
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('QR dependency is not installed. Run composer install.');
    }
    require_once $autoload;
    $options = new \chillerlan\QRCode\QROptions();
    $options->outputBase64 = false;
    $svg = (new \chillerlan\QRCode\QRCode($options))->render($url);
    return ltrim((string) preg_replace('/^<\?xml[^>]+>\s*/', '', $svg));
}
