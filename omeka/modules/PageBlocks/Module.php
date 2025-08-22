<?php declare(strict_types=1);

namespace PageBlocks;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\SharedEventManagerInterface;
use PageBlocks\Form\TopicsListSidebarForm;
use PageBlocks\Form\TeamMembersSidebarForm;
use PageBlocks\Form\AccordionGroupSidebarForm;

class Module extends AbstractModule
{
    /** Module body **/

    /**
     * Get this module's configuration array.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    public function attachListeners(SharedEventManagerInterface $sharedEventManager) 
    {
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Page',
            'view.edit.before',
            [$this, 'addSidebar']
        );
    }
    
    function addSidebar($event)
    {
        $view = $event->getTarget();
        echo $view->sidebar('topic-sidebar', TopicsListSidebarForm::class);
        echo $view->sidebar('member-sidebar', TeamMembersSidebarForm::class);
        echo $view->sidebar('accordion-sidebar', AccordionGroupSidebarForm::class);
    }
}

?>