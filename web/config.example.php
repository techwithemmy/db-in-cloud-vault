<?php
/**
 * Reference only. Prefer the web setup wizard (writes data/config.php).
 * Never commit a real config.php with live keys.
 */
declare(strict_types=1);

return [
    'product_name' => 'DB IN CLOUD Vault',
    'portal_password' => 'change-me-to-a-long-password',
    'storage_provider' => 'cloudflare',
    'storage_endpoint' => 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com',
    'storage_region' => 'auto',
    'storage_bucket' => 'your-bucket-name',
    'storage_public_url' => '',
    'storage_access_key' => 'YOUR_ACCESS_KEY_ID',
    'storage_secret_key' => 'YOUR_SECRET_ACCESS_KEY',
    'storage_path_style' => true,
    'encryption_keys' => [
        'paste-site-encryption-key-here',
    ],
    'setup_complete' => false,
];
