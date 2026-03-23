<?php
/**
 * DevCore Storage Driver — S3Storage.php
 * Uploads files to AWS S3 using raw HTTP (no SDK required).
 *
 * Config keys used:
 *   storage.s3.key        AWS access key ID
 *   storage.s3.secret     AWS secret access key
 *   storage.s3.bucket     S3 bucket name
 *   storage.s3.region     e.g. us-east-1
 *   storage.s3.base_url   Optional CDN URL. Falls back to S3 public URL.
 *   storage.s3.acl        e.g. public-read (default)
 */
class S3Storage implements StorageInterface {
    private string $key;
    private string $secret;
    private string $bucket;
    private string $region;
    private string $baseUrl;
    private string $acl;
    private string $endpoint;

    public function __construct(array $config) {
        $s3 = $config['storage']['s3'];
        $this->key      = $s3['key'];
        $this->secret   = $s3['secret'];
        $this->bucket   = $s3['bucket'];
        $this->region   = $s3['region'] ?? 'us-east-1';
        $this->acl      = $s3['acl']    ?? 'public-read';
        $this->endpoint = $s3['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com";
        $this->baseUrl  = rtrim($s3['base_url'] ?? "{$this->endpoint}/{$this->bucket}", '/');
    }

    public function upload(string $localPath, string $destination): string {
        $destination = ltrim($destination, '/');
        $content     = file_get_contents($localPath);
        $contentType = mime_content_type($localPath) ?: 'application/octet-stream';
        $contentMd5  = base64_encode(md5($content, true));
        $date        = gmdate('D, d M Y H:i:s T');

        $stringToSign = implode("\n", [
            'PUT',
            $contentMd5,
            $contentType,
            $date,
            "x-amz-acl:{$this->acl}",
            "/{$this->bucket}/{$destination}",
        ]);

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secret, true));

        $headers = [
            "Date: $date",
            "Content-Type: $contentType",
            "Content-MD5: $contentMd5",
            "x-amz-acl: {$this->acl}",
            "Authorization: AWS {$this->key}:$signature",
        ];

        $ch = curl_init("{$this->endpoint}/{$this->bucket}/{$destination}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("S3Storage: upload failed (HTTP $httpCode): $response");
        }

        return $this->url($destination);
    }

    public function delete(string $path): bool {
        $key  = $this->pathFromUrl($path);
        $date = gmdate('D, d M Y H:i:s T');
        $stringToSign = "DELETE\n\n\n$date\n/{$this->bucket}/{$key}";
        $signature    = base64_encode(hash_hmac('sha1', $stringToSign, $this->secret, true));

        $ch = curl_init("{$this->endpoint}/{$this->bucket}/{$key}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ["Date: $date", "Authorization: AWS {$this->key}:$signature"],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $httpCode = curl_getinfo(curl_exec($ch) ? $ch : $ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 204;
    }

    public function url(string $path): string {
        return $this->baseUrl . '/' . ltrim($this->pathFromUrl($path), '/');
    }

    public function exists(string $path): bool {
        $key  = $this->pathFromUrl($path);
        $date = gmdate('D, d M Y H:i:s T');
        $stringToSign = "HEAD\n\n\n$date\n/{$this->bucket}/{$key}";
        $signature    = base64_encode(hash_hmac('sha1', $stringToSign, $this->secret, true));

        $ch = curl_init("{$this->endpoint}/{$this->bucket}/{$key}");
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_HTTPHEADER     => ["Date: $date", "Authorization: AWS {$this->key}:$signature"],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private function pathFromUrl(string $path): string {
        if (str_starts_with($path, 'http')) {
            $path = parse_url($path, PHP_URL_PATH);
            $path = ltrim(str_replace('/' . $this->bucket, '', $path), '/');
        }
        return ltrim($path, '/');
    }
}
