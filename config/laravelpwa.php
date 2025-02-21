<?php

return [
    'name' => 'meSync',
    'manifest' => [
        'name' => env('APP_NAME', 'meSync'),
        'short_name' => 'meSync',
        'start_url' => '/',
        'background_color' => '#ffffff',
        'theme_color' => '#278F5A',
        'display' => 'standalone',
        'orientation'=> 'any',
        'status_bar'=> 'black',
        'icons' => [
            '72x72' => [
                'path' => '/images/icons/icon-72x72.png',
                'size' => '72x72',
                'purpose' => 'any'
            ],
            '96x96' => [
                'path' => '/images/icons/icon-96x96.png',
                'size' => '96x96',
                'purpose' => 'any'
            ],
            '128x128' => [
                'path' => '/images/icons/icon-128x128.png',
                'size' => '128x128',
                'purpose' => 'any'
            ],
            '144x144' => [
                'path' => '/images/icons/icon-144x144.png',
                'size' => '144x144',
                'purpose' => 'any'
            ],
            '152x152' => [
                'path' => '/images/icons/icon-152x152.png',
                'size' => '152x152',
                'purpose' => 'any'
            ],
            '192x192' => [
                'path' => '/images/icons/icon-192x192.png',
                'size' => '192x192',
                'purpose' => 'any'
            ],
            '384x384' => [
                'path' => '/images/icons/icon-384x384.png',
                'size' => '384x384',
                'purpose' => 'any'
            ],
            '512x512' => [
                'path' => '/images/icons/icon-512x512.png',
                'size' => '512x512',
                'purpose' => 'any'
            ],
        ],
        'splash' => [
            '640x1136' => '/images/icons/splash-640x1136.png',
            '750x1334' => '/images/icons/splash-750x1334.png',
            '828x1792' => '/images/icons/splash-828x1792.png',
            '1125x2436' => '/images/icons/splash-1125x2436.png',
            '1242x2208' => '/images/icons/splash-1242x2208.png',
            '1242x2688' => '/images/icons/splash-1242x2688.png',
            '1536x2048' => '/images/icons/splash-1536x2048.png',
            '1668x2224' => '/images/icons/splash-1668x2224.png',
            '1668x2388' => '/images/icons/splash-1668x2388.png',
            '2048x2732' => '/images/icons/splash-2048x2732.png',
        ],
        'shortcuts' => [
            // [
            //     'name' => 'Shortcut Link 1',
            //     'description' => 'Shortcut Link 1 Description',
            //     'url' => '/shortcutlink1',
            //     'icons' => [
            //         "src" => "/images/icons/icon-72x72.png",
            //         "purpose" => "any"
            //     ]
            // ],
        ],
        'custom' => [],
        'screenshots' => [
            [
                'src' => '/images/icons/splash-1242x2208.png',
                'sizes' => '1242x2208',
                'type' => 'image/png',
                'form_factor' => 'wide'
            ],
            [
                'src' => '/images/icons/splash-1242x2208.png',
                'sizes' => '1242x2208',
                'type' => 'image/png'
            ]
        ],

    ]
];
