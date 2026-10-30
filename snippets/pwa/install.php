<?php

/**
 * @var \Kirby\Cms\App $kirby
 * @var string|null $label Text of the install button
 * @var string|false|null $hint Install instructions for iOS, `false` hides them
 * @var string|null $class CSS class of the button
 */

$kirby ??= kirby();
$label ??= t('owebstudio.pwa.install', 'Install app');
$hint ??= t('owebstudio.pwa.install.ios', 'To install this app, tap the Share button and then “Add to Home Screen”.');
$class ??= 'pwa-install';
?>
<button type="button" class="<?= esc($class) ?>" data-pwa-install hidden><?= esc($label) ?></button>
<?php if ($hint !== false): ?>
<p class="<?= esc($class) ?>-hint" data-pwa-install-hint hidden><?= esc($hint) ?></p>
<?php endif ?>
<script nonce="<?= esc($kirby->nonce()) ?>">
  (function () {
    var pwa = window.kirbyPwa;

    if (!pwa) {
      return;
    }

    function update() {
      // iOS has no install prompt, but the Share menu can add the site to the home screen
      var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

      document.querySelectorAll('[data-pwa-install]').forEach(function (button) {
        button.hidden = !pwa.installPrompt;
      });

      document.querySelectorAll('[data-pwa-install-hint]').forEach(function (hint) {
        hint.hidden = !ios || standalone;
      });
    }

    update();

    if (pwa.installButtons) {
      return;
    }

    pwa.installButtons = true;

    document.addEventListener('click', function (event) {
      if (event.target.closest('[data-pwa-install]')) {
        pwa.install().then(update);
      }
    });

    window.addEventListener('pwa:installable', update);
    window.addEventListener('pwa:installed', update);
  })();
</script>
