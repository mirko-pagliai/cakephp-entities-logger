<?php
declare(strict_types=1);

return [
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
