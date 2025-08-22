<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class ImageBannerForm extends Fieldset
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
            'name' => 'o:block[__blockIndex__][o:data][image]',
            'type' => OmekaElement\Asset::class,
            'options' => [
                'label' => 'Image asset', // @translate
            ],
            'attributes' => [
                'required' => true
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][tint]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Overlay tint', // @translate
                'value_options' => [
                    'dark' => 'Dark', // @translate
                    'light' => 'Light' // @translate
                ]
            ]
        ]);
    }
}
?>