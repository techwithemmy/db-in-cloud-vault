<?php
declare(strict_types=1);
/** @var string $view @var string $pageTitle @var string $productName @var string $error @var array|null $flash @var bool $authed @var bool $defaultPw @var bool $setupNeeded @var array $config @var array $providers @var array $sites @var string $detailSlug @var array|null $detailSite @var VaultClient|null $client */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#0b1220">
  <title><?= vault_h($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@450;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= vault_h(vault_asset('app.css')) ?>">
</head>
<body class="view-<?= vault_h($view) ?>">
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text">
          <strong><?= vault_h($productName) ?></strong>
          <small>Offline DB recovery</small>
        </span>
      </a>
      <?php if ($authed && !$setupNeeded): ?>
        <nav class="topnav">
          <a href="index.php" class="<?= $view === 'vault' || $view === 'detail' ? 'is-active' : '' ?>">Sites</a>
          <a href="index.php?view=settings" class="<?= $view === 'settings' ? 'is-active' : '' ?>">Settings</a>
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="linkish">Log out</button>
          </form>
        </nav>
      <?php endif; ?>
    </header>

    <?php if ($error !== ''): ?>
      <div class="banner is-err"><?= vault_h($error) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="banner <?= ($flash['type'] ?? '') === 'err' ? 'is-err' : 'is-ok' ?>"><?= vault_h((string) ($flash['msg'] ?? '')) ?></div>
    <?php endif; ?>
    <?php if ($defaultPw && $authed): ?>
      <div class="banner is-warn">You’re still on a weak/default password. Change it under Settings.</div>
    <?php endif; ?>

    <main class="main">
      <?php
      $file = __DIR__ . '/' . $view . '.php';
      if (is_file($file)) {
          require $file;
      }
      ?>
    </main>

    <footer class="foot">
      Host on a separate server from your live sites. Keep <code>data/config.php</code> private.
    </footer>
  </div>
  <script src="<?= vault_h(vault_asset('app.js')) ?>" defer></script>
</body>
</html>
