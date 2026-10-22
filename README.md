# DB IN CLOUD Vault

Offline recovery kit for [DB IN CLOUD](https://wordpress.org/plugins/db-in-cloud).

Use this when WordPress is down. It lists and decrypts encrypted database dumps from your S3-compatible bucket (Cloudflare R2, Amazon S3, Wasabi, Backblaze B2, DigitalOcean Spaces, or any S3 API).

## What you get

- **web/** – passworded browser vault (host on a separate server)
- **cli/** – terminal list / download / decrypt

## Before you start

You need from the WordPress plugin (Setup → Recovery or Settings):

1. Site ID  
2. Encryption key  
3. Storage endpoint, bucket, region, access key, and secret key  

Keep those values private. Do not put real keys in this repository.

## Web vault

1. Upload the `web` folder to a server that is **not** your live WordPress site.  
2. Make `web/data` writable by PHP.  
3. Open the site over HTTPS.  
4. Complete the setup wizard and paste your storage credentials plus each site encryption key.

Local test:

```bash
cd web
php -S 127.0.0.1:8090
```

Then open http://127.0.0.1:8090

## CLI

```bash
cd cli
cp vault-secrets.example.php vault-secrets.php
```

Edit `vault-secrets.php` with your storage settings and site encryption keys. Then:

```bash
php fetch-backup.php --list
php fetch-backup.php --site=your-site-id --format=zip --out=./backup.sql.zip
php fetch-backup.php --site=your-site-id --format=gz --out=./backup.sql.gz
```

`vault-secrets.php` is ignored by git. Never commit it.

## Object layout

The plugin writes:

```
backups/db/index.json
backups/db/{site}/{token}/latest.sql.gz.enc
backups/db/{site}/{token}/latest.sql.zip.enc
```

The vault expects the same paths and the same encryption key used when the backup was created.

## Notes

- Prefer a private bucket and API access.  
- Host the web vault separately from production WordPress.  
- Regenerating the encryption key in WordPress means old dumps need the old key to decrypt.

## Author

Emmanuel (Techwithemmy)  
https://icreatorstech.com  

License: GPL-2.0-or-later (see LICENSE.txt)
