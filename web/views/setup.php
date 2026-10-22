<?php
declare(strict_types=1);
$provider = StorageProviders::sanitize((string) ($config['storage_provider'] ?? 'cloudflare'));
$help = (string) ($providers[$provider]['help'] ?? '');
?>
<section class="panel setup-panel">
  <div class="panel-intro">
    <p class="eyebrow">First-run setup</p>
    <h1>Configure Backup Vault</h1>
    <p class="lede">No hand-edited PHP. Choose storage, set a portal password, paste encryption keys from your sites, and you’re ready.</p>
    <ol class="setup-rail">
      <li><span>1</span> Brand &amp; lock</li>
      <li><span>2</span> Object storage</li>
      <li><span>3</span> Site encryption keys</li>
    </ol>
  </div>

  <form method="post" class="setup-form" id="vault-setup-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
    <input type="hidden" name="action" value="setup_save">

    <fieldset>
      <legend>Portal</legend>
      <label>
        Product name
        <input type="text" name="product_name" value="<?= vault_h((string) ($config['product_name'] ?? 'Backup Vault')) ?>" required>
      </label>
      <label>
        Admin password
        <input type="password" name="portal_password" minlength="10" required autocomplete="new-password" placeholder="At least 10 characters">
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
          <input type="text" name="storage_region" id="storage_region" value="<?= vault_h((string) ($config['storage_region'] ?? ($providers[$provider]['region'] ?? 'auto'))) ?>">
        </label>
        <label>
          Bucket
          <input type="text" name="storage_bucket" value="<?= vault_h((string) ($config['storage_bucket'] ?? '')) ?>" required>
        </label>
      </div>
      <label>
        Endpoint
        <input type="url" name="storage_endpoint" id="storage_endpoint" value="<?= vault_h((string) ($config['storage_endpoint'] ?? ($providers[$provider]['endpoint'] ?? ''))) ?>" required placeholder="https://…">
      </label>
      <label>
        Public base URL <em>(optional)</em>
        <input type="url" name="storage_public_url" value="<?= vault_h((string) ($config['storage_public_url'] ?? '')) ?>" placeholder="https://cdn.example.com">
      </label>
      <div class="grid-2">
        <label>
          Access key ID
          <input type="password" name="storage_access_key" required autocomplete="off">
        </label>
        <label>
          Secret access key
          <input type="password" name="storage_secret_key" required autocomplete="off">
        </label>
      </div>
      <label class="check">
        <input type="checkbox" name="storage_path_style" id="storage_path_style" value="1" <?= !empty($config['storage_path_style']) || !isset($config['storage_path_style']) ? 'checked' : '' ?>>
        Path-style URLs <span>(recommended for R2, Wasabi, B2, MinIO)</span>
      </label>
    </fieldset>

    <fieldset>
      <legend>Encryption keys</legend>
      <p class="field-help">Paste each site’s encryption key (one per line). These decrypt dumps offline.</p>
      <label>
        Encryption keys
        <textarea name="encryption_keys_text" rows="5" required placeholder="wp-abc123…&#10;site-b-cron-…"></textarea>
      </label>
    </fieldset>

    <button type="submit" class="btn primary block">Save &amp; open vault</button>
  </form>
</section>
