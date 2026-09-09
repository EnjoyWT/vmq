<?php

namespace app\service;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrcodeServer
{
    private string $encoding = 'UTF-8';
    private int $size = 180;
    private bool $logo = false;
    private string $logoUrl = '';
    private int $logoSize = 80;
    private bool $title = false;
    private string $titleContent = '';
    private string $generate = 'display';
    private string $fileName = './static/qrcode';

    public function __construct(array $config = [])
    {
        $this->encoding = (string) ($config['encoding'] ?? $this->encoding);
        $this->size = (int) ($config['size'] ?? $this->size);
        $this->logo = (bool) ($config['logo'] ?? $this->logo);
        $this->logoUrl = (string) ($config['logo_url'] ?? $this->logoUrl);
        $this->logoSize = (int) ($config['logo_size'] ?? $this->logoSize);
        $this->title = (bool) ($config['title'] ?? $this->title);
        $this->titleContent = (string) ($config['title_content'] ?? $this->titleContent);
        $this->generate = (string) ($config['generate'] ?? $this->generate);
        $this->fileName = (string) ($config['file_name'] ?? $this->fileName);
    }

    public function createServer(string $content)
    {
        $qrCode = new QrCode(
            data: $content,
            encoding: new Encoding($this->encoding),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $this->size,
            margin: 10
        );
        $logo = $this->logo && is_file($this->logoUrl)
            ? new Logo($this->logoUrl, $this->logoSize)
            : null;
        $label = $this->title && $this->titleContent !== ''
            ? new Label($this->titleContent)
            : null;
        $result = (new PngWriter())->write($qrCode, $logo, $label);

        if ($this->generate === 'display') {
            return $result->getString();
        }

        if ($this->generate !== 'writefile') {
            return ['success' => false, 'message' => 'unsupported generate type', 'data' => ''];
        }

        if (!is_dir($this->fileName) && !mkdir($this->fileName, 0755, true) && !is_dir($this->fileName)) {
            return ['success' => false, 'message' => 'cannot create output directory', 'data' => ''];
        }

        $path = $this->fileName . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.png';
        $result->saveToFile($path);

        return ['success' => true, 'message' => 'write qr image success', 'data' => ['url' => $path, 'ext' => 'png']];
    }
}
