<?php

/**
 * @var \Kirby\Cms\App $kirby
 * @var string|null $text Text of the notice
 * @var string|null $reload Text of the reload button
 * @var string|null $class CSS class of the notice
 */

$kirby ??= kirby();
$text ??= t('owebstudio.pwa.update', 'A new version of this site is available.');
$reload ??= t('owebstudio.pwa.update.reload', 'Reload');
$class ??= 'pwa-update';
?>
<div class="<?= esc($class) ?>" role="status" data-pwa-update hidden>
  <span><?= esc($text) ?></span>
  <button type="button" data-pwa-reload><?= esc($reload) ?></button>
</div>
<script nonce="<?= esc($kirby->nonce()) ?>">
  (function () {
    var pwa = window.kirbyPwa;

    if (!pwa || pwa.updateNotice) {
      return;
    }

    pwa.updateNotice = true;

    window.addEventListener('pwa:update', function () {
      document.querySelectorAll('[data-pwa-update]').forEach(function (notice) {
        notice.hidden = false;
      });
    });

    document.addEventListener('click', function (event) {
      if (event.target.closest('[data-pwa-reload]')) {
        location.reload();
      }
    });
  })();
</script>
