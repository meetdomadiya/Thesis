<?php declare(strict_types=1);

namespace Deduplicate\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class DeduplicateForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-deduplicate')
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
                    'required' => false,
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'deduplicate_value',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Value', // @translate
                ],
                'attributes' => [
                    'id' => 'deduplicate-value',
                ],
            ])
            ->add([
                'name' => 'method',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Heuristic to find similar values', // @translate
                    'info' => 'Different algorithms can be used to detect "similar" values.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate/-/blob/master/LISEZMOI.md',
                    'value_options' => [
                        'equal' => 'Equal', // @translate
                        'similar_text' => 'Similar text', // @translate
                        'levenshtein' => 'Levenshtein distance', // @translate
                        'metaphone' => 'Metaphone', // @translate
                        'soundex' => 'Soundex', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'deduplicate-method',
                    'required' => false,
                    'value' => 'equal',
                ],
            ])
            /*
            ->add([
                'name' => 'resource_type',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => 'item',
                ],
            ])
            */
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'deduplicate_property',
                'required' => false,
            ])
            ->add([
                'name' => 'method',
                'required' => false,
            ])
        ;
    }
}
