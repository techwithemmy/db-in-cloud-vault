<?php
declare(strict_types=1);
?>
<section class="panel auth-panel">
  <div>
    <p class="eyebrow">Secure unlock</p>
    <h1>Open the vault</h1>
    <p class="lede">Decrypt and download the latest database dump for any connected site. No live app or MySQL required.</p>
    <ul class="ticks">
      <li>Works when WordPress / apps are offline</li>
      <li>AES-256-GCM encrypted objects</li>
      <li>Cloudflare R2, S3, Wasabi, B2, Spaces, custom</li>
    </ul>
  </div>
  <form method="post" autocomplete="current-password" class="auth-form">
    <input type="hidden" name="csrf" value="<?= vault_h(vault_csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label>
      Portal password
      <input id="password" name="password" type="password" required autofocus enterkeyhint="go" autocomplete="current-password">
    </label>
    <button class="btn primary block" type="submit">Unlock</button>
  </form>
</section>
