<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class TeamMembersSidebarForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][topics][__attachmentIndex__][name]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Member name' // @translate
            ],
            'attributes' => [
                'data-sidebar-id' => 'member-data-name'
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][topics][__attachmentIndex__][description]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Member description' // @translate
            ],
            'attributes' => [
                'data-sidebar-id' => 'member-data-description'
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][topics][__attachmentIndex__][avatar]',
            'type' => OmekaElement\Asset::class,
            'options' => [
                'label' => 'Member avatar' // @translate
            ],
            'attributes' => [
                'data-sidebar-id' => 'member-data-avatar'
            ]
        ]);
    }
}
?>