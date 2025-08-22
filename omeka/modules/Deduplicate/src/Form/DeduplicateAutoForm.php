<?php declare(strict_types=1);

namespace Deduplicate\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class DeduplicateAutoForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-deduplicate-auto')
            ->add([
                'name' => 'deduplicate_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Property to deduplicate on', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'used_terms' => true,
                ],
                'attributes' => [
                    'id' => 'deduplicate-property',
                    'class' => 'chosen-select',
                    'required' => true,
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'method',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Heuristic to find similar values', // @translate
                    'value_options' => [
                        'equal' => 'Equal (exactly)', // @translate
                        'equal_insensitive' => 'Equal (case insensitive)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'deduplicate-method',
                    'required' => false,
                    'value' => 'equal',
                ],
            ])
            ->add([
                'name' => 'process',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Process', // @translate
                ],
                'attributes' => [
                    'id' => 'deduplicate-process',
                    'required' => true,
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'resource_type',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => 'items',
                ],
            ])
            ->add([
                'name' => 'query',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => '[]',
                ],
            ])
        ;
    }
}
