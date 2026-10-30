<?php

namespace Owebstudio\Pwa;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Data\Json;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Http\Url;
use Kirby\Toolkit\I18n;

/**
 * Builds the web app manifest, the service worker
 * and the data for the head tags
 */
class Pwa
{
    /**
     * Caching strategies that can be used in the `strategies` option
     */
    public const STRATEGIES = [
        'cache-first',
        'network-first',
        'network-only',
        'stale-while-revalidate'
    ];

    public function __construct(
        protected App $kirby
    ) {
    }

    public static function instance(App|null $kirby = null): static
    {
        return new static($kirby ?? App::instance());
    }

    /**
     * Returns a plugin option
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->kirby->option('owebstudio.pwa.' . $key, $default);
    }

    public function isEnabled(): bool
    {
        return $this->option('enable', false) === true;
    }

    /**
     * Returns the color if it is safe to use in CSS
     */
    public function color(string|null $color): string|null
    {
        if ($color === null) {
            return null;
        }

        $color = trim($color);

        if (preg_match('/^(#[0-9a-f]{3,8}|[a-z]+|(rgb|hsl)a?\([0-9.,%\s\/]+\))$/i', $color) !== 1) {
            return null;
        }

        return $color;
    }

    /**
     * Black or white, whichever is easier to read
     * on a hex background color
     */
    public function contrast(string $background): string
    {
        $hex = ltrim($background, '#');

        if (preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})([0-9a-f]{1,2})?$/i', $hex, $matches) !== 1) {
            return '#000000';
        }

        $hex = strlen($matches[1]) === 3 ? preg_replace('/(.)/', '$1$1', $matches[1]) : $matches[1];
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 128 ? '#000000' : '#ffffff';
    }

    /**
     * Data for the `pwa` snippet
     */
    public function head(): array
    {
        $manifest = $this->manifest();
        $options  = $this->option('manifest', []);
        $icons    = $manifest['icons'] ?? [];
        $display  = $manifest['display'] ?? 'browser';

        // maskable icons have a safe zone and look cropped outside of masks
        $anyIcons = array_filter($icons, fn ($icon) => str_contains($icon['purpose'], 'any') === true);

        return [
            'manifest'   => $this->url('manifest.json'),
            'name'       => $manifest['short_name'] ?? $manifest['name'] ?? null,
            'themeColor' => $manifest['theme_color'] ?? null,
            'capable'    => in_array($display, ['standalone', 'fullscreen'], true),
            'statusBar'  => $options['status_bar'] ?? 'black',
            'icon'       => end($anyIcons) ?: end($icons) ?: null,
            'splash'     => $this->splash(),
            'register'   => [
                'script'   => $this->url('serviceworker.js'),
                'scope'    => $manifest['scope'] ?? '.',
                'manifest' => $this->url('manifest.json'),
            ]
        ];
    }

    /**
     * Icons for the `icons` member of the manifest
     */
    public function icons(): array
    {
        return $this->iconList($this->option('icons', []));
    }

    /**
     * Web app manifest as array
     */
    public function manifest(): array
    {
        $options  = $this->option('manifest', []);
        $language = $this->kirby->language();
        $name     = $options['name'] ?? $this->kirby->site()->title()->value();

        $manifest = [
            'id'               => $options['id'] ?? null,
            'name'             => $name,
            'short_name'       => $options['short_name'] ?? $name,
            'description'      => $options['description'] ?? null,
            'lang'             => $options['lang'] ?? $language?->code(),
            'dir'              => $options['dir'] ?? $language?->direction(),
            'start_url'        => $options['start_url'] ?? '.',
            'scope'            => $options['scope'] ?? '.',
            'display'          => $options['display'] ?? 'standalone',
            'orientation'      => $options['orientation'] ?? 'any',
            'theme_color'      => $options['theme_color'] ?? '#000000',
            'background_color' => $options['background_color'] ?? '#ffffff',
            'icons'            => $this->icons(),
            'shortcuts'        => $this->shortcuts(),
        ];

        foreach ($this->option('custom', []) as $key => $value) {
            $manifest[$key] = $value;
        }

        return array_filter($manifest, fn ($value) => $value !== null && $value !== []);
    }

    /**
     * Response for the `manifest.json` route
     */
    public function manifestResponse(): mixed
    {
        $custom = $this->customResponse('manifest.json');

        if ($custom !== null || $this->isEnabled() === false) {
            return $custom;
        }

        return new Response(
            Json::encode($this->manifest(), pretty: true),
            'application/manifest+json'
        );
    }

    /**
     * Offline page from the `offline` option
     */
    public function offlinePage(): Page|null
    {
        $id = $this->option('offline', 'offline');

        if (is_string($id) === false) {
            return null;
        }

        return $this->kirby->page($id);
    }

    /**
     * Response for the `pwa/offline` route,
     * used when the site has no offline page
     */
    public function offlineResponse(): Response|null
    {
        if ($this->isEnabled() === false || $this->option('offline', 'offline') === false) {
            return null;
        }

        return new Response(
            $this->kirby->snippet('pwa/offline', ['pwa' => $this], true),
            'text/html',
            200,
            ['Cache-Control' => 'no-cache']
        );
    }

    /**
     * URL of the page that is shown for pages
     * that are neither online nor in the cache
     */
    public function offlineUrl(): string|null
    {
        if ($this->option('offline', 'offline') === false) {
            return null;
        }

        return $this->offlinePage()?->url() ?? $this->url('pwa/offline');
    }

    /**
     * URLs that the service worker caches on install
     */
    public function precache(): array
    {
        $urls = [];

        foreach ($this->option('precache', []) as $item) {
            $urls[] = $this->kirby->page($item)?->url() ?? $this->url($item);
        }

        return array_values(array_unique($urls));
    }

    /**
     * Options for the service worker script
     */
    public function serviceWorker(): array
    {
        $manifest = $this->manifest();
        $limits   = $this->option('limits', []);

        $config = [
            'start'      => $manifest['start_url'] ?? '.',
            'manifest'   => $this->url('manifest.json'),
            'offline'    => $this->offlineUrl(),
            'precache'   => $this->precache(),
            'exclude'    => $this->exclude(),
            'strategies' => $this->strategies(),
            'media'      => '^' . $this->escape($this->path('media')) . '/(?:pages|site|users)/',
            'panel'      => $this->pattern($this->path('panel')),
            'limits'     => [
                'pages'  => (int)($limits['pages'] ?? 50),
                'assets' => (int)($limits['assets'] ?? 200),
            ],
            'debug'      => $this->kirby->option('debug') === true,
        ];

        // every change creates a new service worker
        // that replaces the caches of the old one
        $config['version'] = substr(md5(Json::encode([
            $this->kirby->plugin('owebstudio/pwa')?->version(),
            md5_file(dirname(__DIR__) . '/js/serviceworker.js'),
            $this->option('version'),
            $this->offlinePage()?->modified(),
            $config,
        ])), 0, 12);

        return $config;
    }

    /**
     * Response for the `serviceworker.js` route
     */
    public function serviceWorkerResponse(): mixed
    {
        $custom = $this->customResponse('serviceworker.js');

        if ($custom !== null || $this->isEnabled() === false) {
            return $custom;
        }

        $config = json_encode($this->serviceWorker(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $script = F::read(dirname(__DIR__) . '/js/serviceworker.js');

        return new Response(
            'const config = ' . $config . ';' . PHP_EOL . PHP_EOL . $script,
            'text/javascript',
            200,
            ['Cache-Control' => 'no-cache']
        );
    }

    /**
     * Shortcuts for the `shortcuts` member of the manifest
     */
    public function shortcuts(): array
    {
        $shortcuts = [];

        foreach ($this->option('shortcuts', []) as $shortcut) {
            $icons = $shortcut['icons'] ?? [];

            // a single icon or a list of icons
            if (isset($icons['src']) === true) {
                $icons = [$icons];
            }

            $shortcuts[] = array_filter([
                'name'        => $this->translate($shortcut['name']),
                'short_name'  => $this->translate($shortcut['short_name'] ?? null),
                'description' => $this->translate($shortcut['description'] ?? null),
                'url'         => $this->url($shortcut['url']),
                'icons'       => $this->iconList(array_map(
                    fn ($icon) => ['path' => $icon['src']] + $icon,
                    $icons
                )),
            ], fn ($value) => $value !== null && $value !== []);
        }

        return $shortcuts;
    }

    /**
     * Startup images for iOS
     */
    public function splash(): array
    {
        $splash = [];

        foreach ($this->option('splash', []) as $file) {
            $splash[] = [
                'href'  => $this->url($file['path'] ?? $file),
                'media' => $file['media'] ?? '(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)',
            ];
        }

        return $splash;
    }

    /**
     * Rules for paths that the service worker never handles
     */
    public function exclude(): array
    {
        $exclude = [
            $this->pattern($this->path('panel')),
            $this->pattern($this->path('api')),
            $this->pattern($this->path('media') . '/panel'),
            $this->pattern($this->path('index') . '/manifest.json'),
            $this->pattern($this->path('index') . '/serviceworker.js'),
        ];

        foreach ($this->option('exclude', []) as $pattern) {
            $exclude[] = $this->pattern($this->path('index') . '/' . trim($pattern, '/'));
        }

        return $exclude;
    }

    /**
     * Caching strategies for custom paths
     */
    public function strategies(): array
    {
        $strategies = [];

        foreach ($this->option('strategies', []) as $pattern => $strategy) {
            if (in_array($strategy, static::STRATEGIES, true) === true) {
                $strategies[] = [
                    $this->pattern($this->path('index') . '/' . trim($pattern, '/')),
                    $strategy
                ];
            }
        }

        return $strategies;
    }

    /**
     * Absolute URL for a path of the site
     */
    public function url(string $path): string
    {
        return Url::to($path);
    }

    /**
     * Serves the files and closures that can replace
     * the generated manifest and service worker
     */
    protected function customResponse(string $name): mixed
    {
        $root = $this->kirby->root('index') . '/' . $name;

        if ($this->isEnabled() === true) {
            $custom = $this->option($name);

            if ($custom instanceof Closure) {
                return $custom();
            }

            if (is_string($custom) === true) {
                $file = $this->file($custom);
                return $file !== null ? Response::file($file) : null;
            }
        }

        if (F::exists($root) === true) {
            return Response::file($root);
        }

        return null;
    }

    /**
     * Escapes a string for a JavaScript regular expression
     */
    protected function escape(string $string): string
    {
        return preg_replace('/[.*+?^${}()|[\]\\\\\/]/', '\\\\$0', $string);
    }

    /**
     * Finds a file by its absolute path or
     * its path relative to the index root
     */
    protected function file(string $path): string|null
    {
        $file = $this->kirby->root('index') . '/' . ltrim($path, '/');

        if (F::exists($file) === true) {
            return $file;
        }

        if (F::exists($path) === true) {
            return $path;
        }

        return null;
    }

    protected function iconList(array $icons): array
    {
        $list = [];

        foreach ($icons as $sizes => $icon) {
            $icon = is_string($icon) === true ? ['path' => $icon] : $icon;

            $list[] = array_filter([
                'src'     => $this->url($icon['path']),
                'type'    => $icon['type'] ?? $this->mime($icon['path']),
                'sizes'   => $icon['sizes'] ?? (is_string($sizes) === true ? $sizes : null),
                'purpose' => $icon['purpose'] ?? 'any',
            ]);
        }

        return $list;
    }

    protected function mime(string $path): string
    {
        $extension = strtolower(F::extension($path));

        return F::extensionToMime($extension) ?? 'image/' . $extension;
    }

    /**
     * URL path of a Kirby URL (`index`, `panel`, `api`, `media` …)
     * without trailing slash
     */
    protected function path(string $type): string
    {
        return rtrim(parse_url($this->kirby->url($type), PHP_URL_PATH) ?? '', '/');
    }

    /**
     * Regular expression for a path and everything below it,
     * `*` matches any characters
     */
    protected function pattern(string $path): string
    {
        $parts = array_map(fn ($part) => $this->escape($part), explode('*', $path));

        return '^' . implode('.*', $parts) . '(?:/.*)?$';
    }

    protected function translate(string|array|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $value = I18n::translate($value, is_string($value) === true ? $value : null);

        return is_string($value) === true ? $value : null;
    }
}
