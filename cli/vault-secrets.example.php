<?php
/**
 * DB IN CLOUD · Offline kit secrets (CLI).
 *
 * 1. Copy to vault-secrets.php (same folder)
 * 2. Fill storage API + one encryption key per site
 * 3. Keep vault-secrets.php OUT of git and off public web servers
 *
 *   php fetch-backup.php --list
 *   php fetch-backup.php --site=example-site --format=zip --out=./example-site.sql.zip
 */

return [
    'storage_public_url' => getenv('STORAGE_PUBLIC_URL') ?: '',

    'storage_endpoint' => getenv('STORAGE_ENDPOINT') ?: 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com',
    'storage_bucket' => getenv('STORAGE_BUCKET') ?: 'your-bucket-name',
    'storage_access_key' => getenv('STORAGE_ACCESS_KEY') ?: 'YOUR_ACCESS_KEY_ID',
    'storage_secret_key' => getenv('STORAGE_SECRET_KEY') ?: 'YOUR_SECRET_ACCESS_KEY',
    'storage_region' => getenv('STORAGE_REGION') ?: 'auto',
    'storage_path_style' => true,

    // site id must match the WordPress / php-kit BACKUP_SITE_ID (or plugin site id)
    'sites' => [
        'example-site' => [
            'label' => 'Example Site',
            // Same value as the plugin encryption key for that site
            'encryption_key' => 'paste-unique-encryption-key-here',
            // Legacy alias still accepted by the CLI:
            // 'cron_key' => 'paste-unique-encryption-key-here',
        ],
    ],
];
