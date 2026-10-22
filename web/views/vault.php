<?php
declare(strict_types=1);
?>
<section class="panel">
  <div class="panel-head">
    <div>
      <p class="eyebrow">Connected sites</p>
      <h1>Recovery library</h1>
      <p class="lede">Sites appear automatically from <code>backups/db/index.json</code> after each upload.</p>
    </div>
    <span class="count-pill"><?= count($sites) ?></span>
  </div>

  <?php if ($sites === []): ?>
    <div class="empty">
      <strong>No backups found yet</strong>
      <p>Run a backup on a WordPress site (or kit install), then refresh. Check Settings if the index stays empty.</p>
    </div>
  <?php else: ?>
    <div class="site-list">
      <?php foreach ($sites as $slug => $site): ?>
        <?php
          $m = is_array($site['manifest'] ?? null) ? $site['manifest'] : null;
          $label = (string) ($site['label'] ?? $slug);
          $updated = is_array($m) ? (string) ($m['updated_at'] ?? $m['created_at'] ?? '') : '';
          $dbName = is_array($m) ? (string) ($m['db_name'] ?? '') : '';
        ?>
        <a class="site-row" href="index.php?site=<?= vault_h((string) $slug) ?>">
          <div>
            <h2><?= vault_h($label) ?></h2>
            <code><?= vault_h((string) $slug) ?></code>
            <p>
              <?php if ($dbName !== ''): ?><?= vault_h($dbName) ?><?php endif; ?>
              <?php if ($updated !== ''): ?><?= $dbName !== '' ? ' · ' : '' ?><?= vault_h(vault_relative_time($updated)) ?><?php endif; ?>
              <?php if ($dbName === '' && $updated === ''): ?>Open details<?php endif; ?>
            </p>
          </div>
          <span class="chev" aria-hidden="true">›</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
