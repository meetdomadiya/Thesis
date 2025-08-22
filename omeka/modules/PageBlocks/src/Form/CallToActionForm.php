<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class CallToActionForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][header]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Header text', // @translate
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][subheader]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Subheader text', // @translate
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][button_text]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Button text', // @translate
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][button_link]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Button link', // @translate
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][color_scheme]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Color scheme', // @translate
                'value_options' => [
                    'light' => 'Light', // @translate
                    'dark' => 'Dark' // @translate
                ]
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][background_color]',
            'type' => OmekaElement\ColorPicker::class,
            'options' => [
                'label' => 'Background color', // @translate
            ],
        ]);
    }
}
?>