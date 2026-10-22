<?php
/**
 * Offline DB backup fetcher (works when any site is down).
 *
 * Shared object-storage layout (multi-site):
 *   backups/db/sites/{site}.json          ← index / label
 *   backups/db/{site}/{token}/latest.sql.{gz|zip}.enc
 *
 * Legacy single-site path (still tried as fallback):
 *   backups/db/{token}/latest.sql.{gz|zip}.enc
 *
 * Usage:
 *   php fetch-backup.php --list
 *   php fetch-backup.php --site=example-site --format=zip --out=./example-site.sql.zip
 *   php fetch-backup.php --secrets=/path/to/vault-secrets.php --site=example-site
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "CLI only. From a terminal:\n";
    echo "  php fetch-backup.php --list\n";
    echo "  php fetch-backup.php --site=YOUR_SITE --format=zip --out=./backup.sql.zip\n";
    exit(1);
}

if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'wb'));
}

$opts = [
    'format' => 'zip',
    'out' => '',
    'secrets' => '',
    'site' => '',
    'list' => false,
];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--format=')) {
        $opts['format'] = strtolower(substr($arg, 9));
    } elseif (str_starts_with($arg, '--out=')) {
        $opts['out'] = substr($arg, 6);
    } elseif (str_starts_with($arg, '--secrets=')) {
        $opts['secrets'] = substr($arg, 10);
    } elseif (str_starts_with($arg, '--site=')) {
        $opts['site'] = sanitize_slug(substr($arg, 7));
    } elseif ($arg === '--list') {
        $opts['list'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage:\n";
        echo "  php vault/cli/fetch-backup.php --list\n";
        echo "  php vault/cli/fetch-backup.php --site=SLUG [--format=gz|zip] [--out=file] [--secrets=path]\n";
        exit(0);
    }
}

if (!$opts['list'] && !in_array($opts['format'], ['gz', 'zip'], true)) {
    fwrite(STDERR, "format must be gz or zip\n");
    exit(1);
}

$secretsFile = $opts['secrets'] !== ''
    ? $opts['secrets']
    : __DIR__ . '/vault-secrets.php';

if (!is_file($secretsFile)) {
    fwrite(STDERR, "Missing secrets file: {$secretsFile}\n");
    fwrite(STDERR, "Copy vault/cli/vault-secrets.example.php → vault/cli/vault-secrets.php and fill it in.\n");
    exit(1);
}

/** @var array<string,mixed> $secrets */
$secrets = require $secretsFile;
if (!is_array($secrets)) {
    fwrite(STDERR, "Secrets file must return an array.\n");
    exit(1);
}

$publicUrl = rtrim(trim((string) (
    $secrets['storage_public_url']
    ?? $secrets['r2_url']
    ?? ''
)), '/');
$hasApi = secret_value($secrets, ['storage_endpoint', 'r2_endpoint']) !== ''
    && secret_value($secrets, ['storage_bucket', 'r2_bucket']) !== ''
    && secret_value($secrets, ['storage_access_key', 'r2_access_key_id']) !== ''
    && secret_value($secrets, ['storage_secret_key', 'r2_secret_access_key']) !== '';
if (($publicUrl === '' || str_contains($publicUrl, 'xxxxxxxx')) && !$hasApi) {
    fwrite(STDERR, "Set storage_public_url and/or storage_endpoint + API keys in the secrets file.\n");
    exit(1);
}

$sites = normalize_sites($secrets);
if ($sites === []) {
    fwrite(STDERR, "No sites configured. Add encryption_key / cron_key or a sites[] entry.\n");
    exit(1);
}

if ($opts['list']) {
    echo "Configured sites (" . count($sites) . "):\n";
    foreach ($sites as $slug => $site) {
        $label = (string) ($site['label'] ?? $slug);
        $remote = fetch_site_manifest($secrets, $publicUrl, $slug);
        $remoteBit = '';
        if (is_array($remote)) {
            $updated = (string) ($remote['updated_at'] ?? $remote['created_at'] ?? '');
            $db = (string) ($remote['db_name'] ?? '');
            $remoteBit = ' | storage ok' . ($db !== '' ? " db={$db}" : '') . ($updated !== '' ? " updated={$updated}" : '');
        } else {
            $remoteBit = ' | (no site.json yet, or not reachable)';
        }
        echo "  - {$slug}" . ($label !== $slug ? " ({$label})" : '') . $remoteBit . "\n";
    }
    echo "\nDownload: php vault/cli/fetch-backup.php --site=SLUG --format=zip --out=./SLUG.sql.zip\n";
    exit(0);
}

$siteSlug = $opts['site'];
if ($siteSlug === '') {
    if (count($sites) === 1) {
        $siteSlug = (string) array_key_first($sites);
    } else {
        fwrite(STDERR, "Multiple sites configured. Pass --site=SLUG or use --list.\n");
        fwrite(STDERR, 'Sites: ' . implode(', ', array_keys($sites)) . "\n");
        exit(1);
    }
}

if (!isset($sites[$siteSlug])) {
    fwrite(STDERR, "Unknown site \"{$siteSlug}\". Known: " . implode(', ', array_keys($sites)) . "\n");
    exit(1);
}

$site = $sites[$siteSlug];
$cronKey = trim((string) ($site['encryption_key'] ?? $site['cron_key'] ?? ''));
if (
    $cronKey === ''
    || str_starts_with($cronKey, 'paste-')
    || str_contains($cronKey, 'YOUR_')
) {
    fwrite(STDERR, "Set encryption_key (or cron_key) for site \"{$siteSlug}\" in the secrets file.\n");
    exit(1);
}

$token = substr(hash('sha256', 'db-backup|' . $cronKey), 0, 32);
$objectCandidates = [
    // Current multi-site layout
    'backups/db/' . $siteSlug . '/' . $token . '/latest.sql.' . $opts['format'] . '.enc',
    // Legacy path (pre site-slug)
    'backups/db/' . $token . '/latest.sql.' . $opts['format'] . '.enc',
];

$encBlob = null;
$usedObject = '';
foreach ($objectCandidates as $object) {
    echo "Trying {$object}\n";
    if ($publicUrl !== '') {
        $encBlob = http_get_bytes($publicUrl . '/' . $object);
    }
    if ($encBlob === null) {
        $encBlob = storage_get_object_bytes($secrets, $object);
    }
    if ($encBlob !== null && $encBlob !== '') {
        $usedObject = $object;
        break;
    }
}

if ($encBlob === null || $encBlob === '') {
    fwrite(STDERR, "Could not download backup for site \"{$siteSlug}\".\n");
    fwrite(STDERR, "Tried:\n");
    foreach ($objectCandidates as $object) {
        fwrite(STDERR, "  - {$object}\n");
    }
    fwrite(STDERR, "Check site id, cron_key, storage credentials, and that a backup has been uploaded.\n");
    exit(1);
}

try {
    $plain = decrypt_dbb1($encBlob, $cronKey);
} catch (Throwable $e) {
    fwrite(STDERR, 'Decrypt failed: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Usually means cron_key does not match the key used when the backup was created.\n");
    exit(1);
}

$out = $opts['out'] !== ''
    ? $opts['out']
    : (getcwd() . '/' . $siteSlug . '-db-backup-latest.sql.' . $opts['format']);

if (file_put_contents($out, $plain) === false) {
    fwrite(STDERR, "Could not write {$out}\n");
    exit(1);
}

$magic = bin2hex(substr($plain, 0, 2));
echo "Site {$siteSlug} ← {$usedObject}\n";
echo "Wrote {$out} (" . number_format(strlen($plain)) . " bytes, magic={$magic})\n";
if ($opts['format'] === 'gz' && $magic !== '1f8b') {
    fwrite(STDERR, "Warning: file does not look like gzip.\n");
    exit(2);
}
if ($opts['format'] === 'zip' && $magic !== '504b') {
    fwrite(STDERR, "Warning: file does not look like zip.\n");
    exit(2);
}
echo "OK. Live site was not required for this download.\n";
exit(0);

function sanitize_slug(string $raw): string
{
    $s = strtolower(trim($raw));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * @param array<string,mixed> $secrets
 * @return array<string,array<string,string>>
 */
function normalize_sites(array $secrets): array
{
    $out = [];
    $rawSites = $secrets['sites'] ?? null;
    if (is_array($rawSites)) {
        foreach ($rawSites as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = sanitize_slug((string) ($row['site'] ?? $row['id'] ?? $key));
            if ($slug === '') {
                continue;
            }
            $out[$slug] = [
                'encryption_key' => trim((string) ($row['encryption_key'] ?? $row['cron_key'] ?? '')),
                'cron_key' => trim((string) ($row['encryption_key'] ?? $row['cron_key'] ?? '')),
                'label' => trim((string) ($row['label'] ?? $slug)),
            ];
        }
    }

    // Single-site shorthand still works
    $topKey = trim((string) ($secrets['encryption_key'] ?? $secrets['cron_key'] ?? ''));
    if ($topKey !== '' && !str_starts_with($topKey, 'paste-') && !str_contains($topKey, 'YOUR_')) {
        $slug = sanitize_slug((string) ($secrets['site'] ?? $secrets['backup_site_id'] ?? 'default'));
        if ($slug === '') {
            $slug = 'default';
        }
        if (!isset($out[$slug])) {
            $out[$slug] = [
                'encryption_key' => $topKey,
                'cron_key' => $topKey,
                'label' => trim((string) ($secrets['label'] ?? $slug)),
            ];
        }
    }

    return $out;
}

/** @param array<string,mixed> $secrets */
function secret_value(array $secrets, array $keys): string
{
    foreach ($keys as $key) {
        $v = trim((string) ($secrets[$key] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

/** @param array<string,mixed> $secrets */
function fetch_site_manifest(array $secrets, string $publicUrl, string $slug): ?array
{
    $keys = [
        'backups/db/sites/' . $slug . '.json',
        'backups/db/' . $slug . '/site.json',
    ];
    foreach ($keys as $key) {
        $body = null;
        if ($publicUrl !== '') {
            $body = http_get_bytes($publicUrl . '/' . $key);
        }
        if ($body === null) {
            $body = storage_get_object_bytes($secrets, $key);
        }
        if ($body === null || $body === '') {
            continue;
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

function http_get_bytes(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Accept: application/octet-stream, application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body !== false && $code >= 200 && $code < 300 && $body !== '') {
            return $body;
        }
        return null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body !== false && $body !== '') ? $body : null;
}

/** @param array<string,mixed> $secrets */
function storage_get_object_bytes(array $secrets, string $key): ?string
{
    $endpoint = secret_value($secrets, ['storage_endpoint', 'r2_endpoint']);
    $bucket = secret_value($secrets, ['storage_bucket', 'r2_bucket']);
    $ak = secret_value($secrets, ['storage_access_key', 'r2_access_key_id']);
    $sk = secret_value($secrets, ['storage_secret_key', 'r2_secret_access_key']);
    $region = secret_value($secrets, ['storage_region', 'r2_region']) ?: 'auto';
    if ($endpoint === '' || $bucket === '' || $ak === '' || $sk === '') {
        return null;
    }

    $root = dirname(__DIR__);
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "Private storage fetch needs vendor/aws-sdk (composer) or a public storage_public_url.\n");
        return null;
    }
    require_once $autoload;
    try {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $ak, 'secret' => $sk],
        ]);
        $result = $client->getObject(['Bucket' => $bucket, 'Key' => $key]);
        $body = $result['Body']->getContents();
        return is_string($body) && $body !== '' ? $body : null;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Storage API fetch failed: ' . $e->getMessage() . "\n");
        return null;
    }
}

function decrypt_dbb1(string $blob, string $cronKey): string
{
    if (strlen($blob) < 4 + 12 + 16) {
        throw new RuntimeException('Encrypted backup is truncated.');
    }
    if (substr($blob, 0, 4) !== 'DBB1') {
        throw new RuntimeException('Unknown backup format (expected DBB1).');
    }
    $key = hash('sha256', 'db-backup-enc|' . $cronKey, true);
    $iv = substr($blob, 4, 12);
    $tag = substr($blob, 16, 16);
    $cipher = substr($blob, 32);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('AES decrypt failed.');
    }
    return $plain;
}
