<?php
namespace ExtractText;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Entity\Property;
use Omeka\Entity\Resource;
use Omeka\Entity\Value;
use Omeka\File\Store\Local;
use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Element;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * Text property cache
     *
     * @var Omeka\Entity\Property|false
     */
    protected $textProperty;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        // Import the ExtractText vocabulary if it doesn't already exist.
        $api = $services->get('Omeka\ApiManager');
        $response = $api->search('vocabularies', [
            'namespace_uri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
            'limit' => 0,
        ]);
        if (0 === $response->getTotalResults()) {
            $importer = $services->get('Omeka\RdfImporter');
            $importer->import(
                'file',
                [
                    'o:namespace_uri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
                    'o:prefix' => 'extracttext',
                    'o:label' => 'Extract Text',
                    'o:comment' => null,
                ],
                [
                    'file' => __DIR__ . '/vocabs/extracttext.n3',
                    'format' => 'turtle',
                ]
            );
        }
    }

    public function getConfigForm(PhpRenderer $view)
    {
        $services = $this->getServiceLocator();
        $extractors = $services->get('ExtractText\ExtractorManager');
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');

        $disabledExtractors = $settings->get('extract_text_disabled_extractors', []);
        $backgroundExtractors = $settings->get('extract_text_background_extractors', []);
        $backgroundOnlyConfig = $config['extract_text']['background_only'];

        $html = '
        <table class="tablesaw tablesaw-stack">
            <thead>
            <tr>
                <th>' . $view->translate('Extractor') . '</th>
                <th>' . $view->translate('Available') . '</th>
                <th>' . $view->translate('Disabled') . '</th>
                <th>' . $view->translate('Background only') . '</th>
            </tr>
            </thead>
            <tbody>';
        foreach ($extractors->getRegisteredNames() as $extractorName) {
            $extractor = $extractors->get($extractorName);
            $isAvailable = $extractor->isAvailable()
                ? sprintf('<span style="color: green;">%s</span>', $view->translate('Yes'))
                : sprintf('<span style="color: red;">%s</span>', $view->translate('No'));
            $disableCheckbox = new Element\Checkbox(sprintf('extract_text_disabled_extractors[%s]', $extractorName));
            $disableCheckbox->setValue(isset($disabledExtractors[$extractorName]) && $disabledExtractors[$extractorName] ? '1' : '0');
            $backgroundCheckbox = new Element\Checkbox(sprintf('extract_text_background_extractors[%s]', $extractorName));
            if (in_array($extractorName, $backgroundOnlyConfig)) {
                $backgroundCheckbox->setValue('1');
                $backgroundCheckbox->setAttribute('disabled', true);
            } else {
                $backgroundCheckbox->setValue(isset($backgroundExtractors[$extractorName]) && $backgroundExtractors[$extractorName] ? '1' : '0');
            }
            $html .= sprintf('
                <tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                $extractorName,
                $isAvailable,
                $view->formElement($disableCheckbox),
                $view->formElement($backgroundCheckbox),
            );
        }
        $html .= '
            </tbody>
        </table>';
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $postData = $controller->params()->fromPost();
        $settings->set('extract_text_disabled_extractors', $postData['extract_text_disabled_extractors']);
        $settings->set('extract_text_background_extractors', $postData['extract_text_background_extractors']);
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        /*
         * Before ingesting a media file, extract its text and set it to the
         * media. This will only happen when creating the media.
         */
        $sharedEventManager->attach(
            '*',
            'media.ingest_file.pre',
            function (Event $event) {
                $textProperty = $this->getTextProperty();
                if (false === $textProperty) {
                    return; // The text property doesn't exist. Do nothing.
                }
                $tempFile = $event->getParam('tempFile');
                $this->setTextToMedia(
                    $tempFile->getTempPath(),
                    $event->getTarget(),
                    $textProperty,
                    $tempFile->getMediaType()
                );
            }
        );
        /*
         * Perform text extraxt actions after hydrating an item. This happens
         * when creating and updating the item.
         */
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.post',
            function (Event $event) {
                $textProperty = $this->getTextProperty();
                if (false === $textProperty) {
                    return; // The text property doesn't exist. Do nothing.
                }
                $item = $event->getParam('entity');
                $data = $event->getParam('request')->getContent();
                $action = $data['extract_text_action'] ?? 'default';
                $this->extractTextItem($item, $textProperty, $action);
            }
        );
        /*
         * Perform text extraxt actions after hydrating a media. This happens
         * when creating and updating the item.
         */
        // $sharedEventManager->attach(
        //     'Omeka\Api\Adapter\MediaAdapter',
        //     'api.hydrate.post',
        //     function (Event $event) {
        //         $textProperty = $this->getTextProperty();
        //         if (false === $textProperty) {
        //             return;
        //         }
        //         $media = $event->getParam('entity');
        //         $data = $event->getParam('request')->getContent();
        //         $action = $data['extract_text_action'] ?? 'default';
        //         $this->extractTextMedia($media, $textProperty, $action);
        //     }
        // );
        $sharedEventManager->attach(
    'Omeka\Api\Adapter\MediaAdapter',
    'api.hydrate.post',
    function (Event $event) {
        $textProperty = $this->getTextProperty();
        if ($textProperty === false) {
            return;
        }
        $media = $event->getParam('entity');
        // **Only run on actual Media and only for PDFs**
        if (! $media instanceof \Omeka\Entity\Media
            || $media->getMediaType() !== 'application/pdf'
        ) {
            return;
        }
        $data = $event->getParam('request')->getContent();
        $action = $data['extract_text_action'] ?? 'default';
        $this->extractTextMedia($media, $textProperty, $action);
    }
);
        /*
         * Add the ExtractText select menu to the resource batch update form.
         */
        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            function (Event $event) {
                $form = $event->getTarget();
                $resourceType = $form->getOption('resource_type');
                if ('item' !== $resourceType) {
                    // This is not an item batch update form.
                    return;
                }
                $valueOptions = ['clear' => 'Clear text']; // @translate;
                $store = $this->getServiceLocator()->get('Omeka\File\Store');
                if ($store instanceof Local) {
                    // Files must be stored locally to refresh extracted text.
                    $valueOptions['refresh'] = 'Refresh text'; // @translate
                }
                $form->add([
                    'name' => 'extract_text_action',
                    'type' => Element\Select::class,
                    'options' => [
                        'label' => 'Extract text', // @translate
                        'value_options' => $valueOptions,
                        'empty_option' => '[No action]', // @translate
                    ],
                    'attributes' => [
                        'value' => '',
                        'data-collection-action' => 'replace',
                    ],
                ]);
            }
        );
        /*
         * Don't require the ExtractText select menu in the resource batch
         * update form.
         */
        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_input_filters',
            function (Event $event) {
                $inputFilter = $event->getParam('inputFilter');
                $inputFilter->add([
                    'name' => 'extract_text_action',
                    'required' => false,
                ]);
            }
        );
        /*
         * When preprocessing the batch update data, authorize the "extract_text
         * _action" key. This will signal the process to refresh or clear the
         * text while updating each item in the batch.
         */
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.preprocess_batch_update',
            function (Event $event) {
                $adapter = $event->getTarget();
                $data = $event->getParam('data');
                $rawData = $event->getParam('request')->getContent();
                if (isset($rawData['extract_text_action'])
                    && in_array($rawData['extract_text_action'], ['refresh', 'clear'])
                ) {
                    $data['extract_text_action'] = $rawData['extract_text_action'];
                }
                $event->setParam('data', $data);
            }
        );
        /*
         * Add an "Extract text" tab to the item and media edit pages.
         */
        $adapters = ['Omeka\Controller\Admin\Item', 'Omeka\Controller\Admin\Media'];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'view.edit.section_nav',
                function (Event $event) {
                    $view = $event->getTarget();
                    $sectionNavs = $event->getParam('section_nav');
                    $sectionNavs['extract-text'] = $view->translate('Extract text');
                    $event->setParam('section_nav', $sectionNavs);
                }
            );
        }

        /*
         * Add an "Extract text" section to the item and media edit pages.
         */
        $controllers = ['Omeka\Controller\Admin\Item', 'Omeka\Controller\Admin\Media'];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.edit.form.after',
                function (Event $event) {
                    $view = $event->getTarget();
                    $store = $this->getServiceLocator()->get('Omeka\File\Store');
                    $select = new Element\Select('extract_text_action');
                    $valueOptions = ['clear' => 'Clear text']; // @translate
                    if ($store instanceof Local) {
                        $valueOptions['refresh'] = 'Refresh text'; // @translate
                        $valueOptions['refresh_background'] = 'Refresh text (background)'; // @translate
                    }
                    $select->setLabel('Extract text'); // @translate
                    $select->setValueOptions($valueOptions);
                    $select->setEmptyOption('[No action]'); // @translate
                    echo sprintf('<div id="extract-text" class="section">%s</div>', $view->formRow($select));
                }
            );
        }
    }

    /**
     * Primary method to extract text from one item and its media.
     *
     * There are three actions this method can perform:
     *
     * - default: aggregates text from child media and sets it to the item.
     * - refresh: same as default but (re)extracts text from files first.
     * - refresh_background: runs the refresh action in a background job.
     * - clear: same as default but clears all extracted text from item and child media first.
     *
     * @param Item $item
     * @param Property $textProperty
     * @param string $action default|refresh|refresh_background|clear
     */
    public function extractTextItem(Item $item, Property $textProperty, $action = 'default')
    {
        $services = $this->getServiceLocator();
        $store = $services->get('Omeka\File\Store');
        if ('refresh_background' === $action) {
            // Note that we only dispatch the job when a) not already in the
            // background and b) when files are stored locally.
            if (('cli' !== PHP_SAPI) && ($store instanceof Local)) {
                $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
                $jobDispatcher->dispatch('ExtractText\Job\RefreshItemText', [
                    'item_id' => $item->getId(),
                ]);
            }
            return;
        }
        $itemTexts = [];
        $itemMedia = $item->getMedia();
        // Order by position in case the position was changed on this request.
        $criteria = Criteria::create()->orderBy(['position' => Criteria::ASC]);
        foreach ($itemMedia->matching($criteria) as $media) {
            // Files must be stored locally to refresh extracted text.
            if (('refresh' === $action) && ($store instanceof Local)) {
                $filePath = $store->getLocalPath(sprintf('original/%s', $media->getFilename()));
                $this->setTextToMedia($filePath, $media, $textProperty);
            }
            $mediaValues = $media->getValues();
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('property', $textProperty))
                ->andWhere(Criteria::expr()->eq('type', 'literal'));
            foreach ($mediaValues->matching($criteria) as $mediaValueTextProperty) {
                if ('clear' === $action) {
                    $mediaValues->removeElement($mediaValueTextProperty);
                } else {
                    $itemTexts[] = $mediaValueTextProperty->getValue();
                }
            }
        }
        $itemText = trim(implode(PHP_EOL, $itemTexts));
        $this->setTextToTextProperty($item, $textProperty, ('' === $itemText) ? null : $itemText);
    }

    /**
     * Primary method to extract text from one media.
     *
     * There are three actions this method can perform:
     *
     * - default: aggregates text from the parent item's media and sets it to the item.
     * - refresh: same as default but (re)extracts text from the file first.
     * - refresh_background: runs the refresh action in a background job.
     * - clear: same as default but clears the extracted text from the media first.
     *
     * @param Media $media
     * @param Property $textProperty
     * @param string $action default|refresh|refresh_background|clear
     */
    public function extractTextMedia(Media $media, Property $textProperty, $action = 'default')
    {
        $services = $this->getServiceLocator();
        $store = $services->get('Omeka\File\Store');
        if ('refresh_background' === $action) {
            // Note that we only dispatch the job when a) not already in the
            // background and b) when files are stored locally.
            if (('cli' !== PHP_SAPI) && ($store instanceof Local)) {
                $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
                $jobDispatcher->dispatch('ExtractText\Job\RefreshMediaText', [
                    'media_id' => $media->getId(),
                ]);
            }
            return;
        }
        if (('refresh' === $action) && ($store instanceof Local)) {
            $filePath = $store->getLocalPath(sprintf('original/%s', $media->getFilename()));
            $this->setTextToMedia($filePath, $media, $textProperty);
        }
        if ('clear' === $action) {
            $mediaValues = $media->getValues();
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('property', $textProperty))
                ->andWhere(Criteria::expr()->eq('type', 'literal'));
            foreach ($mediaValues->matching($criteria) as $mediaValueTextProperty) {
                $mediaValues->removeElement($mediaValueTextProperty);
            }
        }
        $this->extractTextItem($media->getItem(), $textProperty, 'default');
    }

    /**
     * Get the text property, caching on first pass.
     *
     * @return Omeka\Entity\Property|false
     */
    public function getTextProperty()
    {
        if (isset($this->textProperty)) {
            return $this->textProperty;
        }
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $textProperty = $entityManager->createQuery('
            SELECT p FROM Omeka\Entity\Property p
            JOIN p.vocabulary v
            WHERE p.localName = :localName
            AND v.namespaceUri = :namespaceUri
        ')->setParameters([
            'localName' => 'extracted_text',
            'namespaceUri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
        ])->getOneOrNullResult();
        $this->textProperty = (null === $textProperty) ? false : $textProperty;
        return $this->textProperty;
    }

    /**
     * Set extracted text to a media.
     *
     * @param string $filePath
     * @param Media $media
     * @param Property $textProperty
     * @param string $mediaType
     * @return null|false
     */
    public function setTextToMedia($filePath, Media $media, Property $textProperty, $mediaType = null)
    {
        if (null === $mediaType) {
            // Fall back on the media type set to the media.
            $mediaType = $media->getMediaType();
        }
        $text = $this->extractText($filePath, $mediaType);
        if (false === $text) {
            // Could not extract text from the file.
            return false;
        }
        $text = trim($text);
        $this->setTextToTextProperty($media, $textProperty, ('' === $text) ? null : $text);
    }

    /**
     * Extract text from a file.
     *
     * @param string $filePath
     * @param string $mediaType
     * @return string|false
     */
    public function extractText($filePath, $mediaType = null)
    {
        if (!@is_file($filePath)) {
            // The file doesn't exist.
            return false;
        }
        if (null === $mediaType) {
            // Fall back on PHP's magic.mime file.
            $mediaType = mime_content_type($filePath);
        }
        $services = $this->getServiceLocator();
        $extractors = $services->get('ExtractText\ExtractorManager');
        try {
            $extractor = $extractors->get($mediaType);
        } catch (ServiceNotFoundException $e) {
            // No extractor assigned to the media type.
            return false;
        }
        $extractorName = $extractor->getName();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');
        $disabledExtractors = $settings->get('extract_text_disabled_extractors', []);
        if (isset($disabledExtractors[$extractorName]) && $disabledExtractors[$extractorName]) {
            // The extractor is disabled in config settings.
            return false;
        }
        $backgroundExtractors = $settings->get('extract_text_background_extractors', []);
        $backgroundOnlyConfig = $config['extract_text']['background_only'];
        if ('cli' !== PHP_SAPI) {
            // This extractor is currently being run in the foreground.
            if (isset($backgroundExtractors[$extractorName]) && $backgroundExtractors[$extractorName]) {
                // An administrator set this extractor to not run in the foreground.
                return false;
            } elseif (in_array($extractorName, $backgroundOnlyConfig)) {
                // The module config set this extractor to never run in the foreground.
                return false;
            }
        }
        if (!$extractor->isAvailable()) {
            // The extractor is unavailable.
            return false;
        }
        $options = $config['extract_text']['options'][$extractorName] ?? [];
        // extract() should return false if it cannot extract text.
        return $extractor->extract($filePath, $options);
    }

    /**
     * Set text as a text property value of a resource.
     *
     * Clears all existing text property values from the resource before setting
     * the value. Pass anything but a string to $text to just clear the values.
     *
     * @param Resource $resource
     * @param Property $textProperty
     * @param string $text
     */
    public function setTextToTextProperty(Resource $resource, Property $textProperty, $text)
    {
        $isPublic = true;
        // Clear values.
        $criteria = Criteria::create()->where(Criteria::expr()->eq('property', $textProperty));
        $resourceValues = $resource->getValues();
        foreach ($resourceValues->matching($criteria) as $resourceValueTextProperty) {
            $isPublic = $resourceValueTextProperty->getIsPublic();
            $resourceValues->removeElement($resourceValueTextProperty);
        }
        // Use a property reference to avoid Doctrine's "A new entity was found"
        // error during batch operations.
        $textProperty = $this->getServiceLocator()
            ->get('Omeka\EntityManager')
            ->getReference('Omeka\Entity\Property', $textProperty->getId());
        // Create and add the value.
        if (is_string($text)) {
            $value = new Value;
            $value->setResource($resource);
            $value->setType('literal');
            $value->setProperty($textProperty);
            $value->setValue($text);
            $value->setIsPublic($isPublic);
            $resourceValues->add($value);
        }
    }
}
