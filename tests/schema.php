<?php
declare(strict_types=1);

return [
    'articles' => [
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'title' => [
                'type' => 'string',
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    'users' => [
        'columns' => [
            'id' => [
                'type' => 'integer',
            ],
            'email' => [
                'type' => 'string',
            ],
            'first_name' => [
                'type' => 'string',
            ],
            'last_name' => [
                'type' => 'string',
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
];
