# Backup Vault · Web

Passworded recovery UI. Host separately from live sites.

## Features

- Web setup wizard (writes `data/config.php`)
- Multi-provider storage: Cloudflare R2, Amazon S3, Wasabi, B2, Spaces, custom
- Native SigV4 client (no AWS SDK)
- Site list, decrypt download, settings

## Deploy

1. Point document root at this folder
2. `chmod 750 data/` (writable by PHP)
3. HTTPS only
4. Complete setup in the browser

## Source map

```
src/
  VaultConfig.php           # load/save settings
  StorageProviders.php      # provider presets
  ObjectStorageClient.php    # SigV4 GET
  VaultClient.php           # list / decrypt / download
views/
  setup.php | login.php | vault.php | detail.php | settings.php
```
