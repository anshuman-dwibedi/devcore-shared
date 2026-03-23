<?php
/**
 * DevCore Storage Driver — R2Storage.php
 * Cloudflare R2 is S3-compatible, but has its own endpoint format
 * and uses AWS Signature Version 4 (not V2 like classic S3).
 *
 * Config keys used:
 *   storage.r2.account_id   Cloudflare account ID
 *   storage.r2.key          R2 Access Key ID
 *   storage.r2.secret       R2 Secret Access Key
 *   storage.r2.bucket       R2 bucket name
 *   storage.r2.base_url     Public bucket URL (custom domain or r2.dev URL)
 *                           e.g. https://pub-xxxx.r2.dev  OR  https://cdn.mysite.com
 */
class R2Storage implements StorageInterface {
    private string $accountId;
    private string $key;
    private string $secret;
    private string $bucket;
    private string $baseUrl;
    private string $endpoint;
    private string $region = 'auto';

    public function __construct(array $config) {
        $r2 = $config['storage']['r2'];
        $this->accountId = $r2['account_id'];
        $this->key       = $r2['key'];
        $this->secret    = $r2['secret'];
        $this->bucket    = $r2['bucket'];
        $this->baseUrl   = rtrim($r2['base_url'], '/');
        $this->endpoint  = "https://{$this->accountId}.r2.cloudflarestorage.com";
    }

    public function upload(string $localPath, string $destination): string {
        $destination = ltrim($destination, '/');
        $content     = file_get_contents($localPath);
        $contentType = mime_content_type($localPath) ?: 'application/octet-stream';

        $this->request('PUT', $destination, $content, $contentType);
        return $this->url($destination);
    }

    public function delete(string $path): bool {
        $key = $this->pathFromUrl($path);
        try {
            $this->request('DELETE', $key);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function url(string $path): string {
        return $this->baseUrl . '/' . ltrim($this->pathFromUrl($path), '/');
    }

    public function exists(string $path): bool {
        $key = $this->pathFromUrl($path);
        try {
            $this->request('HEAD', $key);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    // ── AWS Signature Version 4 ────────────────────────────────
    private function request(string $method, string $key, string $body = '', string $contentType = ''): string {
        $host        = parse_url($this->endpoint, PHP_URL_HOST) . '/' . $this->bucket;
        $uri         = '/' . $this->bucket . '/' . $key;
        $now         = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $amzDate     = $now->format('Ymd\THis\Z');
        $dateStamp   = $now->format('Ymd');
        $payloadHash = hash('sha256', $body);

        $canonicalHeaders = implode("\n", [
            "content-type:$contentType",
            "host:$host",
            "x-amz-content-sha256:$payloadHash",
            "x-amz-date:$amzDate",
            '',
        ]);
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $uri,
            '', // query string
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "$dateStamp/{$this->region}/s3/aws4_request";
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($dateStamp);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->key}/$credentialScope, "
            . "SignedHeaders=$signedHeaders, Signature=$signature";

        $headers = [
            "Authorization: $authorization",
            "Content-Type: $contentType",
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $amzDate",
        ];

        $url = $this->endpoint . $uri;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if ($method === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException("R2Storage: $method $key failed (HTTP $httpCode): $response");
        }

        return $response;
    }

    private function signingKey(string $dateStamp): string {
        $kDate    = hash_hmac('sha256', $dateStamp,      'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region,   $kDate,    true);
        $kService = hash_hmac('sha256', 's3',            $kRegion,  true);
        return      hash_hmac('sha256', 'aws4_request',  $kService, true);
    }

    private function pathFromUrl(string $path): string {
        if (str_starts_with($path, 'http')) {
            $path = parse_url($path, PHP_URL_PATH) ?? $path;
            // strip leading /bucket/ if present
            $prefix = '/' . $this->bucket . '/';
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }
        return ltrim($path, '/');
    }
}
