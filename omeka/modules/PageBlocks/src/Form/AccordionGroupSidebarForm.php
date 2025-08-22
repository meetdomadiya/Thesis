<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class AccordionGroupSidebarForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][topics][__attachmentIndex__][title]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Accordion title' // @translate
            ],
            'attributes' => [
                'data-sidebar-id' => 'accordion-data-title'
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][html]',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Accordion content' // @translate
            ],
            'attributes' => [
                'class' => 'block-html full wysiwyg',
                'data-sidebar-id' => 'accordion-data-html'
            ]
        ]);
    }
}
?>