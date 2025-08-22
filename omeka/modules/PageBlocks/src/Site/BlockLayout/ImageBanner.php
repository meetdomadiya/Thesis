<?php
namespace PageBlocks\Site\BlockLayout;

use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use PageBlocks\Form\ImageBannerForm;

class ImageBanner extends AbstractBlockLayout
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
        return 'Image banner'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $form = $this->formElementManager->get(ImageBannerForm::class);
            
        if ($block && $block->data()) {
            $form->populateValues([
                'o:block[__blockIndex__][o:data][header]' => $block->dataValue('header'),
                'o:block[__blockIndex__][o:data][subheader]' => $block->dataValue('subheader'),
                'o:block[__blockIndex__][o:data][image]' => $block->dataValue('image'),
                'o:block[__blockIndex__][o:data][tint]' => $block->dataValue('tint'),
            ]);
        }
        
        $html = $view->formCollection($form);
        
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/image-banner', [
            // 'html' => $block->dataValue('html'),
            // 'attachments' => $block->attachments()
            'header' => $block->dataValue('header'),
            'subheader' => $block->dataValue('subheader'),
            'image' => $block->dataValue('image'),
            'tint' => $block->dataValue('tint'),
        ]);
    }
}
?>