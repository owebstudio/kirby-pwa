<?php

use Kirby\Cms\App as Kirby;
use Owebstudio\Pwa\Pwa;

load([
    'Owebstudio\\Pwa\\Pwa' => 'src/Pwa.php'
], __DIR__);

Kirby::plugin('owebstudio/pwa', [
    'options' => [
        'enable' => false,
        'manifest' => [],
        'icons' => [],
        'splash' => [],
        'shortcuts' => [],
        'custom' => [],
        'offline' => 'offline',
        'precache' => [],
        'exclude' => [],
        'strategies' => [],
        'limits' => [
            'pages' => 50,
            'assets' => 200
        ],
        'version' => null,
        'manifest.json' => null,
        'serviceworker.js' => null
    ],
    'snippets' => [
        'pwa' => __DIR__ . '/snippets/pwa.php',
        'pwa/install' => __DIR__ . '/snippets/pwa/install.php',
        'pwa/offline' => __DIR__ . '/snippets/pwa/offline.php',
        'pwa/update' => __DIR__ . '/snippets/pwa/update.php'
    ],
    'translations' => [
        'de' => require __DIR__ . '/translations/de.php',
        'en' => require __DIR__ . '/translations/en.php',
        'tr' => require __DIR__ . '/translations/tr.php'
    ],
    'routes' => [
        [
            'pattern' => 'manifest.json',
            'action' => function () {
                return Pwa::instance()->manifestResponse();
            }
        ],
        [
            'pattern' => 'serviceworker.js',
            'action' => function () {
                return Pwa::instance()->serviceWorkerResponse();
            }
        ],
        [
            'pattern' => 'pwa/offline',
            'action' => function () {
                return Pwa::instance()->offlineResponse();
            }
        ]
    ]
]);
