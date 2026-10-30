<?php

/**
 * Shown for pages that are neither online nor in the cache,
 * when the site has no offline page
 *
 * @var \Kirby\Cms\App $kirby
 * @var \Kirby\Cms\Site $site
 * @var \Owebstudio\Pwa\Pwa $pwa
 */

$kirby ??= kirby();
$site ??= $kirby->site();
$pwa ??= Owebstudio\Pwa\Pwa::instance($kirby);
$manifest = $pwa->manifest();
$background = $pwa->color($manifest['background_color'] ?? null) ?? '#ffffff';
$accent = $pwa->color($manifest['theme_color'] ?? null) ?? 'currentColor';
$title = t('owebstudio.pwa.offline.title', 'You are offline');
?>
<!DOCTYPE html>
<html lang="<?= esc($kirby->language()?->code() ?? 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= esc($title) ?> | <?= esc($site->title()->value()) ?></title>
  <style nonce="<?= esc($kirby->nonce()) ?>">
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 1.5rem;
      box-sizing: border-box;
      font-family: system-ui, sans-serif;
      line-height: 1.5;
      text-align: center;
      background: <?= $background ?>;
      color: <?= $pwa->contrast($background) ?>;
    }
    main {
      max-width: 30rem;
    }
    a {
      color: <?= $accent === $background ? 'inherit' : $accent ?>;
    }
  </style>
</head>
<body>
  <main>
    <h1><?= esc($title) ?></h1>
    <p><?= esc(t('owebstudio.pwa.offline.text', 'This page is not available offline. Please check your connection and try again.')) ?></p>
    <p><a href=""><?= esc(t('owebstudio.pwa.offline.retry', 'Try again')) ?></a></p>
  </main>
</body>
</html>
