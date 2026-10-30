<?php

use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;

/**
 * @param array $array
 * @param string $key
 * @param null $default
 * @return array|mixed
 */
function getArrayLastItem(array $array, string $key, $default = null)
{
    $item = end($array);

    if (is_array($item) === false) {
        return $default;
    }

    return A::get($item, $key, $default);
}

/**
 * @param array $data
 * @param false $return
 * @return string|null
 */
function snippetKirbyPwa(array $data = [], bool $return = false): ?string
{
    return snippet('pwa', $data, $return);
}

/**
 * @param string $path
 * @return string|null
 */
function fileKirbyPwa(string $path): ?string
{
    $file = kirby()->root('index') . '/' . ltrim($path, '/');

    if (F::exists($file) === true) {
        return $file;
    }

    if (F::exists($path) === true) {
        return $path;
    }

    return null;
}

/**
 * @param string $path
 * @return string
 */
function mimeKirbyPwa(string $path): string
{
    $extension = strtolower(F::extension($path));

    return F::extensionToMime($extension) ?? 'image/' . $extension;
}

/**
 * @return array
 */
function optionsKirbyPwa(): array
{
    // get options
    $manifest = option('owebstudio.pwa.manifest');
    $icons = option('owebstudio.pwa.icons', []);
    $splash = option('owebstudio.pwa.splash', []);
    $shortcuts = option('owebstudio.pwa.shortcuts', []);
    $custom = option('owebstudio.pwa.custom', []);

    $data = [
        'name' => $manifest['name'],
        'short_name' => $manifest['short_name'] ?? $manifest['name'],
        'description' => $manifest['description'] ?? $manifest['name'],
        'scope' => $manifest['scope'] ?? '.',
        'start_url' => $manifest['start_url'] ?? '.',
        'display' => $manifest['display'] ?? 'standalone',
        'theme_color' => $manifest['theme_color'] ?? '#000000',
        'background_color' => $manifest['background_color'] ?? '#ffffff',
        'orientation' => $manifest['orientation'] ?? 'any',
        'status_bar' => $manifest['status_bar'] ?? 'black'
    ];

    // prepare icons
    $data['icons'] = [];
    foreach ($icons as $size => $file) {
        if (is_string($file) === true) {
            $path = $file;
        } else {
            $path = $file['path'];
        }

        $data['icons'][] = [
            'src' => url($path),
            'type' => mimeKirbyPwa($path),
            'sizes' => $size,
            'purpose' => $file['purpose'] ?? 'any'
        ];
    }

    // prepare splash
    $data['splash'] = [];
    foreach ($splash as $size => $file) {
        $path = $file['path'] ?? $file;

        $data['splash'][] = [
            'src' => url($path),
            'sizes' => $size,
            'media' => $file['media'] ?? null
        ];
    }

    // prepare shortcuts
    $data['shortcuts'] = [];
    foreach ($shortcuts as $shortcut) {
        if (array_key_exists('icons', $shortcut) === true) {
            $shortcutIcons = [
                [
                    'src' => url($shortcut['icons']['src']),
                    'type' => mimeKirbyPwa($shortcut['icons']['src']),
                    'purpose' => $shortcut['icons']['purpose'] ?? 'any'
                ]
            ];
        } else {
            $shortcutIcons = [];
        }

        $data['shortcuts'][] = array_filter([
            'name' => I18n::translate($shortcut['name'], $shortcut['name']),
            'description' => isset($shortcut['description']) === true ? I18n::translate($shortcut['description'], $shortcut['description']) : null,
            'url' => url($shortcut['url']),
            'icons' => $shortcutIcons
        ], function ($value) {
            return $value !== null;
        });
    }

    // custom options
    foreach ($custom as $tag => $value) {
        $data[$tag] = $value;
    }

    return $data;
}
