<?php
/**
 * DevCore Shared Library — QrCode.php
 * Generates QR code URLs via free API — no library needed
 * Uses goqr.me API (free, no key required)
 */
class QrCode {
    private static string $baseUrl = 'https://api.qrserver.com/v1/create-qr-code/';

    /**
     * Get a QR code image URL for any string/URL
     * Usage: <img src="<?= QrCode::url('https://mysite.com/table/5') ?>">
     */
    public static function url(string $data, int $size = 200, string $color = '000000', string $bg = 'ffffff'): string {
        return self::$baseUrl . '?' . http_build_query([
            'size'    => "{$size}x{$size}",
            'data'    => $data,
            'color'   => $color,
            'bgcolor' => $bg,
            'format'  => 'png',
            'margin'  => 10,
        ]);
    }

    /**
     * Render a full QR card HTML block (ready to embed anywhere)
     */
    public static function card(string $data, string $label = '', int $size = 200): string {
        $src       = htmlspecialchars(self::url($data, $size));
        $label     = htmlspecialchars($label);
        $labelHtml = $label ? "<p class='dc-qr-label'>{$label}</p>" : '';
        return <<<HTML
        <div class="dc-qr-card">
            <img src="{$src}" alt="QR Code" width="{$size}" height="{$size}" loading="lazy" />
            {$labelHtml}
        </div>
        HTML;
    }

    /**
     * Generate a printable QR page (open in new tab → print)
     */
    public static function printPage(array $codes): string {
        $items = '';
        foreach ($codes as $item) {
            $src   = htmlspecialchars(self::url($item['data'], 250));
            $label = htmlspecialchars($item['label'] ?? '');
            $items .= "<div class='qr-print-item'><img src='{$src}'/><p>{$label}</p></div>";
        }
        return <<<HTML
        <!DOCTYPE html><html><head>
        <style>
            body { font-family: sans-serif; display:flex; flex-wrap:wrap; gap:20px; padding:20px; }
            .qr-print-item { text-align:center; border:1px solid #eee; padding:16px; border-radius:8px; }
            .qr-print-item p { margin:8px 0 0; font-size:13px; font-weight:600; }
            @media print { body { gap:10px; padding:10px; } }
        </style>
        </head><body>{$items}</body></html>
        HTML;
    }
}