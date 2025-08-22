<?php declare(strict_types=1);

namespace Deduplicate;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\DeduplicateAutoForm::class => Form\DeduplicateAutoForm::class,
            Form\DeduplicateForm::class => Form\DeduplicateForm::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Deduplicate\Controller\Index' => Controller\IndexController::class,
        ],
    ],
    // TODO Remove these routes and use main admin/default.
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'deduplicate' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/deduplicate[/:action]',
                            'constraints' => [
                                'action' => 'index|auto|manual',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Deduplicate\Controller',
                                '__ADMIN__' => true,
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'deduplicate' => [
                'label' => 'Deduplicate', // @translate
                'route' => 'admin/deduplicate',
                'resource' => 'Omeka\Controller\Admin\Item',
                'privilege' => 'batch-delete',
                'class' => 'o-icon- fa-clone',
            ],
        ],
        'Deduplicate' => [
            [
                'label' => 'Automatic', // @translate
                'route' => 'admin/deduplicate',
                'action' => 'index',
                'resource' => 'Omeka\Controller\Admin\Item',
                'privilege' => 'batch-delete',
                'pages' => [
                    [
                        'route' => 'admin/deduplicate',
                        'action' => 'auto',
                        'visible' => false,
                    ],
                ],
            ],
            [
                'label' => 'Manual', // @translate
                'route' => 'admin/deduplicate',
                'action' => 'manual',
                'resource' => 'Omeka\Controller\Admin\Item',
                'privilege' => 'batch-delete',
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Deduplicate all resources', // @translate
        'Deduplicate all resources automatically', // @translate
        'Deduplicate selected resources', // @translate
        'Deduplicate selected resources automatically', // @translate
        'Go', // @translate
    ],
    'deduplicate' => [
    ],
];
