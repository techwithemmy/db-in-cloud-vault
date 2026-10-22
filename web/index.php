<?php
/**
 * Backup Vault — offline recovery portal (host separately from WordPress / app sites).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

$config = VaultConfig::exists() ? VaultConfig::load() : [];
$setupNeeded = !VaultConfig::isConfigured($config);
$client = $setupNeeded ? null : new VaultClient($config);
$authed = !empty($_SESSION['portal_ok']);
$error = '';
$flash = vault_flash_take();
$view = 'login';
$pageTitle = VaultClient::productName($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!vault_csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } elseif ($action === 'setup_save') {
        try {
            $incoming = [
                'product_name' => (string) ($_POST['product_name'] ?? 'Backup Vault'),
                'portal_password' => (string) ($_POST['portal_password'] ?? ''),
                'storage_provider' => (string) ($_POST['storage_provider'] ?? 'cloudflare'),
                'storage_region' => (string) ($_POST['storage_region'] ?? 'auto'),
                'storage_endpoint' => (string) ($_POST['storage_endpoint'] ?? ''),
                'storage_bucket' => (string) ($_POST['storage_bucket'] ?? ''),
                'storage_public_url' => (string) ($_POST['storage_public_url'] ?? ''),
                'storage_access_key' => (string) ($_POST['storage_access_key'] ?? ''),
                'storage_secret_key' => (string) ($_POST['storage_secret_key'] ?? ''),
                'storage_path_style' => !empty($_POST['storage_path_style']),
                'encryption_keys_text' => (string) ($_POST['encryption_keys_text'] ?? ''),
                'setup_complete' => true,
            ];
            if (strlen($incoming['portal_password']) < 10) {
                throw new RuntimeException('Portal password must be at least 10 characters.');
            }
            if ($incoming['storage_endpoint'] === '' || $incoming['storage_bucket'] === '') {
                throw new RuntimeException('Endpoint and bucket are required.');
            }
            if ($incoming['storage_access_key'] === '' || $incoming['storage_secret_key'] === '') {
                throw new RuntimeException('Access key and secret are required.');
            }
            $normalized = VaultConfig::normalize($incoming);
            if (empty($normalized['encryption_keys'])) {
                throw new RuntimeException('Add at least one site encryption (cron) key.');
            }
            VaultConfig::save($normalized);
            $probe = new VaultClient($normalized);
            $test = $probe->testConnection();
            vault_flash_set(
                $test['ok'] ? 'ok' : 'err',
                $test['ok']
                    ? 'Vault ready. ' . $test['message']
                    : 'Saved, but connection check failed: ' . $test['message']
            );
            vault_redirect('index.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $setupNeeded = true;
            $config = array_merge($config, [
                'product_name' => (string) ($_POST['product_name'] ?? 'Backup Vault'),
                'storage_provider' => (string) ($_POST['storage_provider'] ?? 'cloudflare'),
                'storage_region' => (string) ($_POST['storage_region'] ?? ''),
                'storage_endpoint' => (string) ($_POST['storage_endpoint'] ?? ''),
                'storage_bucket' => (string) ($_POST['storage_bucket'] ?? ''),
                'storage_public_url' => (string) ($_POST['storage_public_url'] ?? ''),
                'storage_path_style' => !empty($_POST['storage_path_style']),
            ]);
        }
    } elseif ($action === 'settings_save' && $authed) {
        try {
            $current = VaultConfig::load();
            $incoming = [
                'product_name' => (string) ($_POST['product_name'] ?? $current['product_name'] ?? 'Backup Vault'),
                'storage_provider' => (string) ($_POST['storage_provider'] ?? $current['storage_provider']),
                'storage_region' => (string) ($_POST['storage_region'] ?? $current['storage_region']),
                'storage_endpoint' => (string) ($_POST['storage_endpoint'] ?? $current['storage_endpoint']),
                'storage_bucket' => (string) ($_POST['storage_bucket'] ?? $current['storage_bucket']),
                'storage_public_url' => (string) ($_POST['storage_public_url'] ?? $current['storage_public_url']),
                'storage_path_style' => !empty($_POST['storage_path_style']),
                'encryption_keys_text' => (string) ($_POST['encryption_keys_text'] ?? implode("\n", $current['encryption_keys'] ?? [])),
                'storage_access_key' => (string) ($_POST['storage_access_key'] ?? ''),
                'storage_secret_key' => (string) ($_POST['storage_secret_key'] ?? ''),
                'portal_password' => (string) ($_POST['portal_password'] ?? ''),
                'portal_password_hash' => (string) ($current['portal_password_hash'] ?? ''),
                'setup_complete' => true,
                'sites' => $current['sites'] ?? [],
            ];
            if ($incoming['storage_access_key'] === '') {
                $incoming['storage_access_key'] = (string) ($current['storage_access_key'] ?? '');
            }
            if ($incoming['storage_secret_key'] === '') {
                $incoming['storage_secret_key'] = (string) ($current['storage_secret_key'] ?? '');
            }
            if ($incoming['portal_password'] === '') {
                $incoming['portal_password'] = '';
                // keep existing hash
            } else {
                $incoming['portal_password_hash'] = '';
            }
            $normalized = VaultConfig::normalize($incoming);
            if (empty($normalized['encryption_keys'])) {
                throw new RuntimeException('Keep at least one encryption key.');
            }
            VaultConfig::save($normalized);
            vault_flash_set('ok', 'Settings saved.');
            vault_redirect('index.php?view=settings');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'login' && !$setupNeeded) {
        $fails = (int) ($_SESSION['login_fails'] ?? 0);
        if ($fails >= 8) {
            $error = 'Too many failed attempts. Try again later.';
        } elseif (VaultClient::verifyPortalPassword($config, (string) ($_POST['password'] ?? ''))) {
            $_SESSION['portal_ok'] = true;
            $_SESSION['login_fails'] = 0;
            session_regenerate_id(true);
            vault_redirect('index.php');
        } else {
            $_SESSION['login_fails'] = $fails + 1;
            $error = 'Wrong password.';
        }
    } elseif ($action === 'logout' && $authed) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        vault_redirect('index.php');
    } elseif ($action === 'download' && $authed && $client instanceof VaultClient) {
        $site = VaultClient::sanitizeSlug((string) ($_POST['site'] ?? ''));
        $format = strtolower((string) ($_POST['format'] ?? 'zip')) === 'gz' ? 'gz' : 'zip';
        try {
            $plain = $client->fetchDecrypted($site, $format);
            $filename = $site . '-db-backup-latest.sql.' . $format;
            $mime = $format === 'zip' ? 'application/zip' : 'application/gzip';
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) strlen($plain));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            echo $plain;
            exit;
        } catch (Throwable $e) {
            vault_flash_set('err', $e->getMessage());
            $back = VaultClient::sanitizeSlug((string) ($_POST['site'] ?? ''));
            vault_redirect($back !== '' ? ('index.php?site=' . rawurlencode($back)) : 'index.php');
        }
    }
}

$authed = !empty($_SESSION['portal_ok']);
$config = VaultConfig::exists() ? VaultConfig::load() : $config;
$setupNeeded = !VaultConfig::isConfigured($config);
$client = $setupNeeded ? null : new VaultClient($config);
$productName = VaultClient::productName($config);
$sites = ($authed && $client instanceof VaultClient) ? $client->listedSites() : [];
$detailSlug = $authed ? VaultClient::sanitizeSlug((string) ($_GET['site'] ?? '')) : '';
$detailSite = ($detailSlug !== '' && isset($sites[$detailSlug])) ? $sites[$detailSlug] : null;
$wantSettings = $authed && (string) ($_GET['view'] ?? '') === 'settings';

if ($detailSlug !== '' && $detailSite === null && $authed && !$wantSettings) {
    vault_flash_set('err', 'Site not found in the storage index.');
    vault_redirect('index.php');
}

if ($setupNeeded) {
    $view = 'setup';
    $pageTitle = 'Setup · ' . $productName;
} elseif (!$authed) {
    $view = 'login';
    $pageTitle = 'Unlock · ' . $productName;
} elseif ($wantSettings) {
    $view = 'settings';
    $pageTitle = 'Settings · ' . $productName;
} elseif ($detailSite !== null) {
    $view = 'detail';
    $pageTitle = ((string) ($detailSite['label'] ?? $detailSlug)) . ' · ' . $productName;
} else {
    $view = 'vault';
    $pageTitle = $productName;
}

$defaultPw = !$setupNeeded && VaultClient::usingDefaultPassword($config);
$providers = StorageProviders::all();

require __DIR__ . '/views/layout.php';
