<?php if (option('owebstudio.pwa.enable', false) === true): ?>
    <?php $manifestOptions = $manifestOptions ?? optionsKirbyPwa(); ?>
    <!-- Start PWA Settings -->

    <!-- Web Application Manifest -->
    <link rel="manifest" href="<?= url('manifest.json') ?>">

    <!-- Chrome for Android theme color -->
    <meta name="theme-color" content="<?= $manifestOptions['theme_color'] ?>">

    <!-- Add to homescreen for Chrome on Android -->
    <meta name="mobile-web-app-capable" content="<?= $manifestOptions['display'] === 'standalone' ? 'yes' : 'no' ?>">
    <meta name="application-name" content="<?= $manifestOptions['short_name'] ?>">
    <?php if (empty($manifestOptions['icons']) === false): ?>
    <link rel="icon"
          sizes="<?= getArrayLastItem($manifestOptions['icons'], 'sizes') ?>"
          href="<?= getArrayLastItem($manifestOptions['icons'], 'src') ?>">
    <?php endif; ?>

    <!-- Add to homescreen for Safari on iOS -->
    <meta name="apple-mobile-web-app-capable" content="<?= $manifestOptions['display'] === 'standalone' ? 'yes' : 'no' ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="<?= $manifestOptions['status_bar'] ?>">
    <meta name="apple-mobile-web-app-title" content="<?= $manifestOptions['short_name'] ?>">
    <?php if (empty($manifestOptions['icons']) === false): ?>
    <link rel="apple-touch-icon" href="<?= getArrayLastItem($manifestOptions['icons'], 'src') ?>">
    <?php endif; ?>

    <?php foreach ($manifestOptions['splash'] ?? [] as $splash): ?>
        <link href="<?= url($splash['src']) ?>"
            <?php if (empty($splash['media'])): ?>
                media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)"
            <?php endif; ?>
              rel="apple-touch-startup-image"/>
    <?php endforeach; ?>

    <!-- Tile for Win8 -->
    <meta name="msapplication-TileColor" content="<?= $manifestOptions['background_color'] ?>">
    <?php if (empty($manifestOptions['icons']) === false): ?>
    <meta name="msapplication-TileImage" content="<?= getArrayLastItem($manifestOptions['icons'], 'src') ?>">
    <?php endif; ?>

    <!-- Register service worker  -->
    <script type="text/javascript">
      // Initialize the service worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= url('serviceworker.js') ?>', {
          // resolve the scope like the browser resolves the manifest scope,
          // so that every page registers the same scope
          scope: new URL('<?= $manifestOptions['scope'] ?>', new URL('<?= url('manifest.json') ?>', location.href)).href
        }).then(function (registration) {
            <?php if(option('debug') === true): ?>
          // Registration was successful
          console.log('Kirby PWA: ServiceWorker registration successful with scope: ', registration.scope);
            <?php endif; ?>

          // remove the registrations that earlier versions created per page
          var current = registration.installing || registration.waiting || registration.active;

          return navigator.serviceWorker.getRegistrations().then(function (registrations) {
            registrations.forEach(function (item) {
              var worker = item.active || item.waiting || item.installing;

              if (item.scope !== registration.scope && worker && worker.scriptURL === current.scriptURL) {
                item.unregister();
              }
            });
          });
        }, function (err) {
            <?php if(option('debug') === true): ?>
          // registration failed :(
          console.log('Kirby PWA: ServiceWorker registration failed: ', err);
            <?php endif; ?>
        });
      }
    </script>
    <!-- End PWA Settings -->
<?php endif; ?>
