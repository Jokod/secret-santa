<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    '@fortawesome/fontawesome-free/css/fontawesome.min.css' => [
        'version' => '6.5.2',
        'type' => 'css',
    ],
    '@fortawesome/fontawesome-free/css/solid.min.css' => [
        'version' => '6.5.2',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-400.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-500.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-700.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-ext-400.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-ext-500.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/fraunces/latin-ext-700.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-400.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-600.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-700.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-ext-400.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-ext-600.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
    '@fontsource/source-sans-3/latin-ext-700.css' => [
        'version' => '5.3.1',
        'type' => 'css',
    ],
];
