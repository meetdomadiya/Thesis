<?php
return [
    'service_manager' => [
        'factories' => [
            \NotifyOnItemChange\Service\FileNotifier::class =>
                \NotifyOnItemChange\Service\Factory\FileNotifierFactory::class,
        ],
    ],
    'notify_on_item_change' => [
        'directory' => '/var/www/omeka/notifications',
    ],
];
