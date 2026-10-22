<?php
/**
 * Object-storage backup fetch + decrypt (no live site required).
 * Discovers sites from backups/db/index.json so renames/new uploads show up automatically.
 */

declare(strict_types=1);

final class VaultClient
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $indexCache = null;

    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
    }

    public function publicUrl(): string
    {
        return rtrim(trim((string) (
            $this->config['storage_public_url']
            ?? $this->config['s3_public_url']
            ?? $this->config['r2_url']
            ?? ''
        )), '/');
    }

    /**
     * Cron keys available for decrypt (shared key and/or per-site overrides).
     *
     * @return list<string>
     */
    public function cronKeys(): array
    {
        $keys = [];
        $top = trim((string) ($this->config['cron_key'] ?? ''));
        if ($top !== '' && !str_contains($top, 'paste-')) {
            $keys[] = $top;
        }
        $list = $this->config['cron_keys'] ?? null;
        if (is_array($list)) {
            foreach ($list as $k) {
                $k = trim((string) $k);
                if ($k !== '' && !str_contains($k, 'paste-')) {
                    $keys[] = $k;
                }
            }
        }
        foreach ($this->configuredSites() as $site) {
            $k = trim((string) ($site['cron_key'] ?? ''));
            if ($k !== '' && !str_contains($k, 'paste-')) {
                $keys[] = $k;
            }
        }
        return array_values(array_unique($keys));
    }

    public function providerLabel(): string
    {
        $id = StorageProviders::sanitize((string) ($this->config['storage_provider'] ?? 'cloudflare'));
        return StorageProviders::all()[$id]['label'] ?? $id;
    }

    /**
     * Sites from config only (optional overrides for labels / per-site keys).
     *
     * @return array<string,array{cron_key:string,label:string}>
     */
    public function configuredSites(): array
    {
        $out = [];
        $rawSites = $this->config['sites'] ?? null;
        if (is_array($rawSites)) {
            foreach ($rawSites as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $slug = self::sanitizeSlug((string) ($row['site'] ?? $row['id'] ?? $key));
                if ($slug === '') {
                    continue;
                }
                $out[$slug] = [
                    'cron_key' => trim((string) ($row['cron_key'] ?? '')),
                    'label' => trim((string) ($row['label'] ?? $slug)),
                ];
            }
        }

        $topKey = trim((string) ($this->config['cron_key'] ?? ''));
        if ($topKey !== '' && !str_contains($topKey, 'paste-')) {
            $slug = self::sanitizeSlug((string) ($this->config['site'] ?? $this->config['backup_site_id'] ?? ''));
            if ($slug !== '' && !isset($out[$slug])) {
                $out[$slug] = [
                    'cron_key' => $topKey,
                    'label' => trim((string) ($this->config['label'] ?? $slug)),
                ];
            }
        }

        return $out;
    }

    /**
     * Bucket registry written by each site on backup.
     *
     * @return array<string,array<string,mixed>>
     */
    public function bucketIndex(): array
    {
        if ($this->indexCache !== null) {
            return $this->indexCache;
        }
        $body = $this->getObject('backups/db/index.json');
        if ($body === null || $body === '') {
            return $this->indexCache = [];
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $this->indexCache = [];
        }
        $sites = $decoded['sites'] ?? [];
        if (!is_array($sites)) {
            return $this->indexCache = [];
        }
        $out = [];
        foreach ($sites as $slug => $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = self::sanitizeSlug((string) ($row['site_slug'] ?? $slug));
            if ($clean === '') {
                continue;
            }
            $out[$clean] = $row;
        }
        return $this->indexCache = $out;
    }

    /**
     * All sites the portal should show: storage index first, then config-only entries.
     *
     * @return array<string,array{label:string,cron_key:string,manifest:?array<string,mixed>,source:string}>
     */
    public function listedSites(): array
    {
        $configured = $this->configuredSites();
        $indexed = $this->bucketIndex();
        $out = [];

        foreach ($indexed as $slug => $manifest) {
            $label = trim((string) ($manifest['label'] ?? ''));
            if ($label === '') {
                $label = (string) ($configured[$slug]['label'] ?? $slug);
            }
            $out[$slug] = [
                'label' => $label,
                'cron_key' => (string) ($configured[$slug]['cron_key'] ?? ''),
                'manifest' => $manifest,
                'source' => 'storage',
            ];
        }

        foreach ($configured as $slug => $site) {
            if (isset($out[$slug])) {
                if ($site['label'] !== '' && $site['label'] !== $slug) {
                    $out[$slug]['label'] = $site['label'];
                }
                if ($site['cron_key'] !== '') {
                    $out[$slug]['cron_key'] = $site['cron_key'];
                }
                continue;
            }
            $manifest = $this->siteManifest($slug);
            $out[$slug] = [
                'label' => $site['label'] !== '' ? $site['label'] : $slug,
                'cron_key' => $site['cron_key'],
                'manifest' => $manifest,
                'source' => 'config',
            ];
        }

        return $this->collapseSameTokenSites($out);
    }

    /**
     * One vault row per encryption identity (cron key / token_prefix).
     * Slug renames must not appear as duplicate sites when the token prefix matches.
     *
     * @param array<string,array{label:string,cron_key:string,manifest:?array<string,mixed>,source:string}> $sites
     * @return array<string,array{label:string,cron_key:string,manifest:?array<string,mixed>,source:string,aliases:list<string>}>
     */
    private function collapseSameTokenSites(array $sites): array
    {
        $groups = [];
        foreach ($sites as $slug => $row) {
            $groupKey = $this->siteGroupKey($slug, $row);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            $groups[$groupKey][$slug] = $row;
        }

        $out = [];
        foreach ($groups as $members) {
            uasort($members, static function (array $a, array $b): int {
                $ta = strtotime((string) (($a['manifest']['updated_at'] ?? '') ?: '1970-01-01')) ?: 0;
                $tb = strtotime((string) (($b['manifest']['updated_at'] ?? '') ?: '1970-01-01')) ?: 0;
                return $tb <=> $ta;
            });
            $primarySlug = (string) array_key_first($members);
            $primary = $members[$primarySlug];
            $aliases = [];
            foreach (array_keys($members) as $s) {
                if ((string) $s !== $primarySlug) {
                    $aliases[] = (string) $s;
                }
            }
            $primary['aliases'] = $aliases;
            $out[$primarySlug] = $primary;
        }

        uasort($out, static function (array $a, array $b): int {
            $ta = strtotime((string) (($a['manifest']['updated_at'] ?? '') ?: '1970-01-01')) ?: 0;
            $tb = strtotime((string) (($b['manifest']['updated_at'] ?? '') ?: '1970-01-01')) ?: 0;
            return $tb <=> $ta;
        });

        return $out;
    }

    /** @param array{label:string,cron_key:string,manifest:?array<string,mixed>,source:string} $row */
    private function siteGroupKey(string $slug, array $row): string
    {
        $manifest = is_array($row['manifest'] ?? null) ? $row['manifest'] : [];
        $prefix = trim((string) ($manifest['token_prefix'] ?? ''));
        if ($prefix !== '') {
            return 't:' . $prefix;
        }

        $path = (string) (($manifest['latest_zip'] ?? '') ?: ($manifest['latest_gz'] ?? ''));
        foreach ($this->cronKeys() as $cronKey) {
            $token = substr(hash('sha256', 'db-backup|' . $cronKey), 0, 32);
            if ($path !== '' && str_contains($path, '/' . $token . '/')) {
                return 't:' . substr($token, 0, 8);
            }
        }

        $cronKey = trim((string) ($row['cron_key'] ?? ''));
        if ($cronKey !== '') {
            return 't:' . substr(hash('sha256', 'db-backup|' . $cronKey), 0, 8);
        }

        return 's:' . $slug;
    }

    /** @deprecated use listedSites() */
    public function sites(): array
    {
        $out = [];
        foreach ($this->listedSites() as $slug => $row) {
            $out[$slug] = [
                'cron_key' => $row['cron_key'],
                'label' => $row['label'],
            ];
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function siteManifest(string $slug): ?array
    {
        $slug = self::sanitizeSlug($slug);
        $indexed = $this->bucketIndex();
        if (isset($indexed[$slug]) && is_array($indexed[$slug])) {
            return $indexed[$slug];
        }
        foreach ([
            'backups/db/sites/' . $slug . '.json',
            'backups/db/' . $slug . '/site.json',
        ] as $key) {
            $body = $this->getObject($key);
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

    /**
     * Download + decrypt latest backup using storage manifests and configured encryption keys.
     *
     * @throws RuntimeException
     */
    public function fetchDecrypted(string $siteSlug, string $format = 'zip'): string
    {
        $format = strtolower($format) === 'gz' ? 'gz' : 'zip';
        $slug = self::sanitizeSlug($siteSlug);
        if ($slug === '') {
            throw new RuntimeException('Unknown site.');
        }

        $manifest = $this->siteManifest($slug);
        $objectKey = '';
        if (is_array($manifest)) {
            $objectKey = $format === 'zip'
                ? (string) ($manifest['latest_zip'] ?? '')
                : (string) ($manifest['latest_gz'] ?? '');
        }

        $keys = $this->cronKeys();
        $configured = $this->configuredSites();
        if (!empty($configured[$slug]['cron_key'])) {
            array_unshift($keys, $configured[$slug]['cron_key']);
            $keys = array_values(array_unique($keys));
        }
        if ($keys === []) {
            throw new RuntimeException('No encryption keys configured. Add cron keys in Vault settings.');
        }

        $candidates = [];
        if ($objectKey !== '') {
            $candidates[] = ltrim($objectKey, '/');
        }
        foreach ($keys as $cronKey) {
            $token = substr(hash('sha256', 'db-backup|' . $cronKey), 0, 32);
            $candidates[] = 'backups/db/' . $slug . '/' . $token . '/latest.sql.' . $format . '.enc';
            $candidates[] = 'backups/db/' . $token . '/latest.sql.' . $format . '.enc';
        }
        $candidates = array_values(array_unique($candidates));

        $blob = null;
        $usedObject = '';
        foreach ($candidates as $object) {
            $blob = $this->getObject($object);
            if ($blob !== null && $blob !== '') {
                $usedObject = $object;
                break;
            }
        }
        if ($blob === null || $blob === '') {
            throw new RuntimeException('Could not download encrypted backup for "' . $slug . '".');
        }

        // Prefer key whose path token matches the object key
        $orderedKeys = $keys;
        foreach ($keys as $cronKey) {
            $token = substr(hash('sha256', 'db-backup|' . $cronKey), 0, 32);
            if ($usedObject !== '' && str_contains($usedObject, '/' . $token . '/')) {
                $orderedKeys = array_values(array_unique(array_merge([$cronKey], $keys)));
                break;
            }
        }

        $lastErr = null;
        foreach ($orderedKeys as $cronKey) {
            try {
                return self::decryptDbb1($blob, $cronKey);
            } catch (Throwable $e) {
                $lastErr = $e;
            }
        }
        throw new RuntimeException(
            'Decrypt failed for "' . $slug . '". '
            . ($lastErr ? $lastErr->getMessage() : 'No matching cron_key.')
        );
    }

    public function getObject(string $key): ?string
    {
        return (new ObjectStorageClient($this->config))->get($key);
    }

    public function testConnection(): array
    {
        $body = $this->getObject('backups/db/index.json');
        if ($body === null || $body === '') {
            return [
                'ok' => false,
                'message' => 'Could not read backups/db/index.json. Check endpoint, bucket, keys, and that at least one site has uploaded a backup.',
            ];
        }
        $decoded = json_decode($body, true);
        $count = is_array($decoded['sites'] ?? null) ? count($decoded['sites']) : 0;
        return [
            'ok' => true,
            'message' => 'Connected. Index lists ' . $count . ' site(s).',
            'sites' => $count,
        ];
    }

    public static function sanitizeSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    public static function decryptDbb1(string $blob, string $cronKey): string
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
            throw new RuntimeException('Decrypt failed (wrong cron_key or corrupt file).');
        }
        return $plain;
    }

    public static function httpGet(string $url): ?string
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

    public static function verifyPortalPassword(array $config, string $password): bool
    {
        $hash = trim((string) ($config['portal_password_hash'] ?? ''));
        if ($hash !== '') {
            return password_verify($password, $hash);
        }
        $plain = (string) ($config['portal_password'] ?? '');
        if ($plain === '') {
            return false;
        }
        return hash_equals($plain, $password);
    }

    public static function usingDefaultPassword(array $config): bool
    {
        if (trim((string) ($config['portal_password_hash'] ?? '')) !== '') {
            return false;
        }
        $plain = (string) ($config['portal_password'] ?? '');
        return $plain === 'change-me-now' || $plain === '';
    }

    public static function productName(array $config): string
    {
        $name = trim((string) ($config['product_name'] ?? 'Backup Vault'));
        return $name !== '' ? $name : 'Backup Vault';
    }
}
