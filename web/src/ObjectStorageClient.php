<?php
declare(strict_types=1);

/**
 * Lightweight S3-compatible GET client (SigV4). No AWS SDK required.
 */
final class ObjectStorageClient
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
    }

    public function get(string $key): ?string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        if ($key === '' || str_contains($key, '..')) {
            return null;
        }

        $public = rtrim(trim((string) (
            $this->config['storage_public_url']
            ?? $this->config['s3_public_url']
            ?? $this->config['r2_url']
            ?? ''
        )), '/');
        if ($public !== '' && !str_contains($public, 'YOUR_') && !str_contains($public, 'xxxxxxxx')) {
            $body = self::httpGet($public . '/' . $key);
            if ($body !== null && $body !== '') {
                return $body;
            }
        }

        return $this->signedGet($key);
    }

    private function signedGet(string $key): ?string
    {
        $endpoint = rtrim(trim((string) (
            $this->config['storage_endpoint']
            ?? $this->config['s3_endpoint']
            ?? $this->config['r2_endpoint']
            ?? ''
        )), '/');
        $bucket = trim((string) (
            $this->config['storage_bucket']
            ?? $this->config['s3_bucket']
            ?? $this->config['r2_bucket']
            ?? ''
        ));
        $access = trim((string) (
            $this->config['storage_access_key']
            ?? $this->config['s3_access_key_id']
            ?? $this->config['r2_access_key_id']
            ?? ''
        ));
        $secret = trim((string) (
            $this->config['storage_secret_key']
            ?? $this->config['s3_secret_access_key']
            ?? $this->config['r2_secret_access_key']
            ?? ''
        ));
        $region = trim((string) ($this->config['storage_region'] ?? $this->config['s3_region'] ?? 'auto'));
        if ($region === '') {
            $region = 'auto';
        }
        $pathStyle = true;
        if (array_key_exists('storage_path_style', $this->config)) {
            $pathStyle = (bool) $this->config['storage_path_style'];
        } elseif (array_key_exists('s3_path_style', $this->config)) {
            $pathStyle = (bool) $this->config['s3_path_style'];
        }

        if ($endpoint === '' || $bucket === '' || $access === '' || $secret === '') {
            return null;
        }

        $parsed = parse_url($endpoint);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $baseHost = (string) $parsed['host'];
        $scheme = (string) ($parsed['scheme'] ?? 'https');
        $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

        if ($pathStyle) {
            $host = $baseHost;
            $path = '/' . rawurlencode($bucket) . '/' . $encodedKey;
        } else {
            $host = $bucket . '.' . $baseHost;
            $path = '/' . $encodedKey;
        }

        $url = $scheme . '://' . $host . $port . $path;
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', '');
        $headers = [
            'host' => $host . $port,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        ksort($headers);
        $canonicalHeaders = '';
        $signedNames = [];
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
            $signedNames[] = $name;
        }
        $signedHeaders = implode(';', $signedNames);
        $canonical = "GET\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $scope = $date . '/' . $region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonical);
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $auth = 'AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        return self::httpGet($url, [
            'Authorization: ' . $auth,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $now,
        ]);
    }

    /** @param list<string> $headers */
    public static function httpGet(string $url, array $headers = []): ?string
    {
        $headers[] = 'Accept: application/octet-stream, application/json';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300 && $body !== '') {
                return $body;
            }
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 120,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body !== false && $body !== '') ? $body : null;
    }
}
