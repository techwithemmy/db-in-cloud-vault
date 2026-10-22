# Security

Do not commit live storage keys, encryption keys, or production bucket names.

Ignored by git:

- `cli/vault-secrets.php`
- `web/data/config.php`
- `web/config.php`

If a secret was ever committed, rotate it at your provider and in the WordPress plugin.
