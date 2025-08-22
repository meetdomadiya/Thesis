<?php
namespace PageBlocks\Site\BlockLayout;

use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use PageBlocks\Form\AccordionGroupForm;
use PageBlocks\Form\AccordionGroupSidebarForm;

class AccordionGroup extends AbstractBlockLayout
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
        return 'Accordion group'; // @translate
    }
    
    public function prepareForm(PhpRenderer $view)
    {
        $view->headScript()->appendFile($view->assetUrl('js/accordion-group.js', 'PageBlocks'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/admin.css', 'PageBlocks'));
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $form = $this->formElementManager->get(AccordionGroupForm::class);
            
        if ($block && $block->data()) {
            $form->populateValues([
                'o:block[__blockIndex__][o:data][header]' => $block->dataValue('header'),
            ]);
        }
        
        return $view->formCollection($form) . $view->partial('common/admin/accordion-group', [
            'accordions' => ($block) ? $block->dataValue('accordions') : []
        ]);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/accordion-group', [
            'header' => $block->dataValue('header'),
            'accordions' => $block->dataValue('accordions')
        ]);
    }
}
?>