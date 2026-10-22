<?php
declare(strict_types=1);

require_once __DIR__ . '/src/StorageProviders.php';
require_once __DIR__ . '/src/ObjectStorageClient.php';
require_once __DIR__ . '/src/VaultConfig.php';
require_once __DIR__ . '/src/VaultClient.php';

function vault_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function vault_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function vault_csrf_ok(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals((string) $_SESSION['csrf'], $token);
}

function vault_flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** @return array{type:string,msg:string}|null */
function vault_flash_take(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function vault_relative_time(string $iso): string
{
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return (int) floor($diff / 86400) . 'd ago';
    }
    return gmdate('M j, Y', $ts);
}

function vault_redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function vault_asset(string $file): string
{
    $path = __DIR__ . '/assets/' . ltrim($file, '/');
    $ver = is_file($path) ? (string) filemtime($path) : '1';
    return 'assets/' . ltrim($file, '/') . '?v=' . rawurlencode($ver);
}
