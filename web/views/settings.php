<?php
declare(strict_types=1);
$provider = StorageProviders::sanitize((string) ($config['storage_provider'] ?? 'cloudflare'));
$help = (string) ($providers[$provider]['help'] ?? '');
$cronText = implode("\n", is_array($config['encryption_keys'] ?? null) ? $config['encryption_keys'] : []);
$clientLabel = $client instanceof VaultClient ? $client->providerLabel() : '';
?>
<section class="panel">
  <div class="panel-head">
    <div>
      <p class="eyebrow">Vault settings</p>
      <h1>Storage &amp; keys</h1>
      <p class="lede">Update destination, encryption keys, or portal password. Leave secret fields blank to keep current values.<?= $clientLabel !== '' ? ' Current provider: <strong>' . vault_h($clientLabel) . '</strong>.' : '' ?></p>
    </div>
  </div>

  <form method="post" class="setup-form" id="vault-setup-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
    <input type="hidden" name="action" value="settings_save">

    <fieldset>
      <legend>Portal</legend>
      <label>
        Product name
        <input type="text" name="product_name" value="<?= vault_h((string) ($config['product_name'] ?? 'Backup Vault')) ?>" required>
      </label>
      <label>
        New admin password <em>(optional)</em>
        <input type="password" name="portal_password" minlength="10" autocomplete="new-password" placeholder="Leave blank to keep current">
      </label>
    </fieldset>

    <fieldset>
      <legend>Storage destination</legend>
      <label>
        Provider
        <select name="storage_provider" id="storage_provider">
          <?php foreach ($providers as $id => $meta): ?>
            <option value="<?= vault_h($id) ?>" <?= $provider === $id ? 'selected' : '' ?>><?= vault_h((string) $meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="field-help" id="provider_help"><?= vault_h($help) ?></p>
      <div class="grid-2">
        <label>
          Region
          <input type="text" name="storage_region" id="storage_region" value="<?= vault_h((string) ($config['storage_region'] ?? 'auto')) ?>">
        </label>
        <label>
          Bucket
          <input type="text" name="storage_bucket" value="<?= vault_h((string) ($config['storage_bucket'] ?? '')) ?>" required>
        </label>
      </div>
      <label>
        Endpoint
        <input type="url" name="storage_endpoint" id="storage_endpoint" value="<?= vault_h((string) ($config['storage_endpoint'] ?? '')) ?>" required>
      </label>
      <label>
        Public base URL <em>(optional)</em>
        <input type="url" name="storage_public_url" value="<?= vault_h((string) ($config['storage_public_url'] ?? '')) ?>">
      </label>
      <div class="grid-2">
        <label>
          Access key ID
          <input type="password" name="storage_access_key" autocomplete="off" placeholder="Leave blank to keep">
        </label>
        <label>
          Secret access key
          <input type="password" name="storage_secret_key" autocomplete="off" placeholder="Leave blank to keep">
        </label>
      </div>
      <label class="check">
        <input type="checkbox" name="storage_path_style" id="storage_path_style" value="1" <?= !empty($config['storage_path_style']) ? 'checked' : '' ?>>
        Path-style URLs
      </label>
    </fieldset>

    <fieldset>
      <legend>Encryption keys</legend>
      <label>
        Encryption keys <em>(one per line)</em>
        <textarea name="encryption_keys_text" rows="6" required><?= vault_h($cronText) ?></textarea>
      </label>
    </fieldset>

    <button type="submit" class="btn primary">Save settings</button>
  </form>
</section>
