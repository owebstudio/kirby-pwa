<?php

/**
 * @var \Kirby\Cms\App $kirby
 */

use Owebstudio\Pwa\Pwa;

$kirby ??= kirby();
$pwa = Pwa::instance($kirby);

if ($pwa->isEnabled() === false) {
    return;
}

$head = $pwa->head();
$register = $head['register'] + ['debug' => $kirby->option('debug') === true];
?>
<link rel="manifest" href="<?= esc($head['manifest']) ?>">
<?php if ($head['themeColor'] !== null): ?>
<meta name="theme-color" content="<?= esc($head['themeColor']) ?>">
<?php endif ?>
<meta name="mobile-web-app-capable" content="<?= $head['capable'] === true ? 'yes' : 'no' ?>">
<meta name="application-name" content="<?= esc((string)$head['name']) ?>">
<?php if ($head['icon'] !== null): ?>
<link<?= attr(['rel' => 'icon', 'href' => $head['icon']['src'], 'type' => $head['icon']['type'] ?? null, 'sizes' => $head['icon']['sizes'] ?? null], ' ') ?>>
<?php endif ?>
<meta name="apple-mobile-web-app-capable" content="<?= $head['capable'] === true ? 'yes' : 'no' ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="<?= esc($head['statusBar']) ?>">
<meta name="apple-mobile-web-app-title" content="<?= esc((string)$head['name']) ?>">
<?php if ($head['icon'] !== null): ?>
<link rel="apple-touch-icon" href="<?= esc($head['icon']['src']) ?>">
<?php endif ?>
<?php foreach ($head['splash'] as $splash): ?>
<link rel="apple-touch-startup-image" href="<?= esc($splash['href']) ?>" media="<?= esc($splash['media']) ?>">
<?php endforeach ?>
<script nonce="<?= esc($kirby->nonce()) ?>">
  (function (options) {
    var pwa = window.kirbyPwa = window.kirbyPwa || {};

    function emit(name) {
      window.dispatchEvent(new CustomEvent('pwa:' + name));
    }

    // keep the install prompt for an install button
    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      pwa.installPrompt = event;
      emit('installable');
    });

    window.addEventListener('appinstalled', function () {
      pwa.installPrompt = null;
      emit('installed');
    });

    pwa.install = function () {
      var prompt = pwa.installPrompt;

      if (!prompt) {
        return Promise.resolve('unavailable');
      }

      pwa.installPrompt = null;
      prompt.prompt();

      return prompt.userChoice.then(function (choice) {
        return choice.outcome;
      });
    };

    if (!('serviceWorker' in navigator)) {
      return;
    }

    // tell the page when a new service worker takes over
    var controlled = !!navigator.serviceWorker.controller;

    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (controlled) {
        emit('update');
      }

      controlled = true;
    });

    navigator.serviceWorker.register(options.script, {
      // resolve the scope like the browser resolves the manifest scope,
      // so that every page registers the same scope
      scope: new URL(options.scope, new URL(options.manifest, location.href)).href,
      updateViaCache: 'none'
    }).then(function (registration) {
      pwa.registration = registration;

      if (options.debug) {
        console.log('Kirby PWA: ServiceWorker registration successful with scope: ', registration.scope);
      }

      // remove the registrations that versions before 1.0.3 created per page
      var current = registration.installing || registration.waiting || registration.active;

      return navigator.serviceWorker.getRegistrations().then(function (registrations) {
        registrations.forEach(function (item) {
          var worker = item.active || item.waiting || item.installing;

          if (item.scope !== registration.scope && worker && worker.scriptURL === current.scriptURL) {
            item.unregister();
          }
        });
      });
    }).catch(function (error) {
      if (options.debug) {
        console.log('Kirby PWA: ServiceWorker registration failed: ', error);
      }
    });
  })(<?= json_encode($register, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
</script>
