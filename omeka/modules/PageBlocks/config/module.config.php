<?php declare(strict_types=1);

namespace PageBlocks;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ]
    ],
    'block_layouts' => [
        'factories' => [
            'accordion-group' => Service\BlockLayout\AccordionGroupFactory::class,
            'call-to-action' => Service\BlockLayout\CallToActionFactory::class,
            'image-banner' => Service\BlockLayout\ImageBannerFactory::class,
            'jumbotron-search' => Service\BlockLayout\JumbotronSearchFactory::class,
            'media-details' => Service\BlockLayout\MediaDetailsFactory::class,
            'media-single' => Service\BlockLayout\MediaSingleFactory::class,
            'team-members' => Service\BlockLayout\TeamMembersFactory::class,
            'two-column' => Service\BlockLayout\TwoColumnFactory::class,
            'three-column' => Service\BlockLayout\ThreeColumnFactory::class,
            'topics-list' => Service\BlockLayout\TopicsListFactory::class
        ],
    ],
    'resource_page_block_layouts' => [
        'factories' => [
            'smart-embeds' => Service\ResourcePageBlockLayout\SmartEmbedsFactory::class
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\AccordionGroupForm::class => Form\AccordionGroupForm::class,
            Form\CallToActionForm::class => Form\CallToActionForm::class,
            Form\ImageBannerForm::class => Form\ImageBannerForm::class,
            Form\JumbotronSearchForm::class => Form\JumbotronSearchForm::class,
            Form\MediaSingleForm::class => Form\MediaSingleForm::class,
            Form\TeamMembersForm::class => Form\TeamMembersForm::class,
            Form\TeamMembersSidebarForm::class => Form\TeamMembersSidebarForm::class,
            Form\TwoColumnForm::class => Form\TwoColumnForm::class,
            Form\ThreeColumnForm::class => Form\ThreeColumnForm::class,
            Form\TopicsListForm::class => Form\TopicsListForm::class,
            Form\TopicsListSidebarForm::class => Form\TopicsListSidebarForm::class
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'sidebar' => Service\View\Helper\SidebarViewHelperFactory::class
        ]
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
    ]
];

?>