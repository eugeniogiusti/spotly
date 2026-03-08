<?php

return [

    'food' => [
        'label' => 'Food',
        'icon' => '🍽️',
        'color' => '#F59E0B',
        'tags' => [
            ['amenity' => 'restaurant'],
            ['amenity' => 'fast_food'],
            ['amenity' => 'bar'],
            ['amenity' => 'pub'],
            ['amenity' => 'food_court'],
            ['amenity' => 'biergarten'],
        ],
    ],

    'coffee' => [
        'label' => 'Coffee',
        'icon' => '☕',
        'color' => '#92400E',
        'tags' => [
            ['amenity' => 'cafe'],
        ],
    ],

    'supermarket' => [
        'label' => 'Supermarket',
        'icon' => '🛒',
        'color' => '#8B5CF6',
        'tags' => [
            ['shop' => 'supermarket'],
            ['shop' => 'convenience'],
            ['shop' => 'greengrocer'],
            ['amenity' => 'marketplace'],
        ],
    ],

    'parks' => [
        'label' => 'Parks',
        'icon' => '🌳',
        'color' => '#10B981',
        'tags' => [
            ['leisure' => 'park'],
            ['leisure' => 'garden'],
        ],
    ],

    'transit' => [
        'label' => 'Transit',
        'icon' => '🚇',
        'color' => '#3B82F6',
        'tags' => [
            ['railway' => 'subway_entrance'],
            ['railway' => 'tram_stop'],
            ['railway' => 'station'],
            ['highway' => 'bus_stop'],
        ],
    ],

    'coworking' => [
        'label' => 'Coworking',
        'icon' => '🧑‍💻',
        'color' => '#6366F1',
        'tags' => [
            ['office' => 'coworking'],
            ['amenity' => 'coworking_space'],
        ],
    ],

    'pharmacy' => [
        'label' => 'Pharmacy',
        'icon' => '💊',
        'color' => '#EC4899',
        'tags' => [
            ['amenity' => 'pharmacy'],
        ],
    ],

    'laundry' => [
        'label' => 'Laundry',
        'icon' => '🧺',
        'color' => '#06B6D4',
        'tags' => [
            ['shop' => 'laundry'],
            ['amenity' => 'laundry'],
        ],
    ],

    'atm' => [
        'label' => 'ATM',
        'icon' => '💵',
        'color' => '#64748B',
        'tags' => [
            ['amenity' => 'atm'],
            ['amenity' => 'bank'],
        ],
    ],

    'gym' => [
        'label' => 'Gym',
        'icon' => '💪',
        'color' => '#EF4444',
        'tags' => [
            ['leisure' => 'fitness_centre'],
            ['leisure' => 'sports_centre'],
        ],
    ],

    'wellness' => [
        'label' => 'Wellness',
        'icon' => '💆',
        'color' => '#D946EF',
        'tags' => [
            ['leisure' => 'spa'],
            ['shop' => 'massage'],
            ['amenity' => 'spa'],
        ],
    ],

];
