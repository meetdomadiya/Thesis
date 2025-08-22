<?php
namespace PageBlocks\Site\BlockLayout;

use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use PageBlocks\Form\CallToActionForm;

class CallToAction extends AbstractBlockLayout
{
    /**
     * @var FormElementManager
     */
    protected $formElementManager;
    
    /**
     * @param FormElementManager $formElementManager
     */
    public function __construct(FormElementManager $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }
    
    public function getLabel()
    {
        return 'Call to action'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $form = $this->formElementManager->get(CallToActionForm::class);
            
        if ($block && $block->data()) {
            $form->populateValues([
                'o:block[__blockIndex__][o:data][header]' => $block->dataValue('header'),
                'o:block[__blockIndex__][o:data][subheader]' => $block->dataValue('subheader'),
                'o:block[__blockIndex__][o:data][button_text]' => $block->dataValue('button_text'),
                'o:block[__blockIndex__][o:data][button_link]' => $block->dataValue('button_link'),
                'o:block[__blockIndex__][o:data][color_scheme]' => $block->dataValue('color_scheme'),
                'o:block[__blockIndex__][o:data][background_color]' => $block->dataValue('background_color')
            ]);
        }
        
        $html = $view->formCollection($form);
        
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/call-to-action', [
            'header' => $block->dataValue('header'),
            'subheader' => $block->dataValue('subheader'),
            'buttonText' => $block->dataValue('button_text'),
            'buttonLink' => $block->dataValue('button_link'),
            'colorScheme' => $block->dataValue('color_scheme'),
            'backgroundColor' => $block->dataValue('background_color')
        ]);
    }
}
?>