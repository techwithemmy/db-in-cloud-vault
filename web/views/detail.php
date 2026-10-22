<?php
declare(strict_types=1);
/** @var array $detailSite @var string $detailSlug */
$m = is_array($detailSite['manifest'] ?? null) ? $detailSite['manifest'] : null;
$label = (string) ($detailSite['label'] ?? $detailSlug);
$updated = is_array($m) ? (string) ($m['updated_at'] ?? $m['created_at'] ?? '') : '';
$updatedAbs = '';
if ($updated !== '') {
    $ts = strtotime($updated);
    $updatedAbs = $ts !== false ? gmdate('M j, Y · H:i', $ts) . ' UTC' : $updated;
}
$dbName = is_array($m) ? (string) ($m['db_name'] ?? '') : '';
$appUrl = is_array($m) ? (string) ($m['app_url'] ?? '') : '';
$folder = is_array($m) ? (string) (($m['latest_zip'] ?? '') ?: ($m['latest_gz'] ?? '')) : '';
if ($folder !== '' && str_contains($folder, '/')) {
    $folder = dirname($folder);
}
$bytesZip = is_array($m) ? (int) ($m['bytes_zip'] ?? 0) : 0;
$bytesGz = is_array($m) ? (int) ($m['bytes_gz'] ?? 0) : 0;
$aliases = is_array($detailSite['aliases'] ?? null) ? $detailSite['aliases'] : [];
$fmt = static function (int $n): string {
    if ($n <= 0) {
        return '—';
    }
    if ($n < 1024) {
        return $n . ' B';
    }
    if ($n < 1048576) {
        return round($n / 1024, 1) . ' KB';
    }
    return round($n / 1048576, 2) . ' MB';
};
?>
<a class="back" href="index.php">← All sites</a>
<section class="panel">
  <div class="detail-hero">
    <div>
      <p class="eyebrow">Site backup</p>
      <h1><?= vault_h($label) ?></h1>
      <div class="pills">
        <span class="pill">Ready</span>
        <span class="pill muted"><?= vault_h($detailSlug) ?></span>
      </div>
    </div>
    <div class="when">
      <strong><?= $updated !== '' ? vault_h(vault_relative_time($updated)) : '—' ?></strong>
      <span><?= $updatedAbs !== '' ? vault_h($updatedAbs) : 'No timestamp' ?></span>
    </div>
  </div>

  <div class="download-box">
    <h2>Download latest dump</h2>
    <p>Decrypts here and saves a normal archive you can restore into MySQL.</p>
    <div class="dl-actions">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
        <input type="hidden" name="action" value="download">
        <input type="hidden" name="site" value="<?= vault_h($detailSlug) ?>">
        <input type="hidden" name="format" value="zip">
        <button class="btn primary" type="submit">Download zip<span><?= vault_h($fmt($bytesZip)) ?> · .sql inside</span></button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
        <input type="hidden" name="action" value="download">
        <input type="hidden" name="site" value="<?= vault_h($detailSlug) ?>">
        <input type="hidden" name="format" value="gz">
        <button class="btn ghost" type="submit">Download gz<span><?= vault_h($fmt($bytesGz)) ?></span></button>
      </form>
    </div>
  </div>

  <div class="metrics">
    <div><span>Database</span><strong><?= $dbName !== '' ? vault_h($dbName) : '—' ?></strong></div>
    <div><span>Zip</span><strong><?= vault_h($fmt($bytesZip)) ?></strong></div>
    <div><span>Gz</span><strong><?= vault_h($fmt($bytesGz)) ?></strong></div>
    <div><span>App</span><strong><?php if ($appUrl !== ''): ?><?= vault_h(preg_replace('#^https?://#', '', $appUrl) ?? $appUrl) ?><?php else: ?>—<?php endif; ?></strong></div>
  </div>

  <details class="tech">
    <summary>Technical details</summary>
    <dl>
      <div><dt>Site ID</dt><dd><code><?= vault_h($detailSlug) ?></code></dd></div>
      <div><dt>Object folder</dt><dd><code><?= $folder !== '' ? vault_h($folder) : '—' ?></code></dd></div>
      <?php if ($appUrl !== ''): ?>
      <div><dt>App URL</dt><dd><?= vault_h($appUrl) ?></dd></div>
      <?php endif; ?>
      <?php if ($aliases !== []): ?>
      <div><dt>Old IDs</dt><dd><code><?= vault_h(implode(', ', $aliases)) ?></code></dd></div>
      <?php endif; ?>
    </dl>
  </details>
</section>
