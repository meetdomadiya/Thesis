<?php declare(strict_types=1);

namespace ExtractOcr\Job;

use DateTime;
use DOMDocument;
use Exception;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFile;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use SimpleXMLElement;
use XSLTProcessor;

class ExtractOcr extends AbstractJob
{
    const FORMAT_ALTO = 'application/alto+xml';
    const FORMAT_PDF2XML = 'application/vnd.pdf2xml+xml';
    const FORMAT_TSV = 'text/tab-separated-values';

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var \IiifSearch\View\Helper\FixUtf8|null
     */
    protected $fixUtf8;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\File\TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var bool
     */
    protected $createEmptyFile;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var \Omeka\Api\Representation\PropertyRepresentation|null
     */
    protected $property;

    /**
     * @var string
     */
    protected $targetExtension;

    /**
     * @var string
     */
    protected $targetMediaType;

    /**
     * @var array
     */
    protected $contentValue;

    /**
     * @var array
     */
    protected $dataPdf;

    /**
     * @var array
     */
    protected $store = [
        'item' => false,
        'media_pdf' => false,
        'media_xml' => false,
    ];

    /**
     * @var array
     */
    protected $stats = [];

    /**
     * @brief Attach attracted ocr data from pdf with item
     */
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $helpers = $services->get('ViewHelperManager');
        $this->api = $services->get('Omeka\ApiManager');
        $this->fixUtf8 = $helpers->has('FixUtf8') ? $helpers->get('FixUtf8') : null;
        $this->logger = $services->get('Omeka\Logger');
        $this->tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $this->cli = $services->get('Omeka\Cli');
        $this->baseUri = $this->getArg('baseUri');
        $this->basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDir($this->basePath . '/temp')) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(new Message(
                'The temporary directory "files/temp" is not writeable. Fix rights or create it manually.' // @translate
            ));
            return;
        }

        $settings = $services->get('Omeka\Settings');

        $mediaType = $settings->get('extractocr_media_type');
        $this->targetMediaType = in_array(
            $mediaType, [
                self::FORMAT_ALTO,
                self::FORMAT_PDF2XML,
                self::FORMAT_TSV
            ], true)
            ? $mediaType
            : self::FORMAT_TSV;

        if ($this->targetMediaType === self::FORMAT_ALTO && !class_exists('XSLTProcessor')) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(new Message(
                'The php extension "xml" or "xsl" is required to extract text as xml alto.' // @translate
            ));
            return;
        }

        $this->targetExtension = $this->targetMediaType === self::FORMAT_TSV ? 'tsv' : 'xml';

        $mode = $this->getArg('mode') ?: 'all';
        $itemId = (int) $this->getArg('itemId');
        $itemIds = (string) $this->getArg('item_ids');
        if ($itemId) {
            $itemIds = trim($itemId . ' ' . $itemIds);
        }

        // TODO Manage the case where there are multiple pdf by item (rare).

        $contentStore = array_filter($settings->get('extractocr_content_store') ?? []);
        if ($contentStore) {
            $prop = $settings->get('extractocr_content_property');
            if ($prop) {
                $prop = $this->api->search('properties', ['term' => $prop])->getContent();
                if ($prop) {
                    $this->property = reset($prop);
                    $this->language = $settings->get('extractocr_content_language');
                    $this->store['item'] = in_array('item', $contentStore) && !$this->getArg('manual');
                    $this->store['media_pdf'] = in_array('media_pdf', $contentStore);
                    $this->store['media_xml'] = in_array('media_xml', $contentStore);
                }
            }
            if (!$this->property) {
                $this->logger->warn(new Message(
                    'The option to store text is set, but no property is defined.' // @translate
                ));
            }
        }

        $this->createEmptyFile = (bool) $settings->get('extractocr_create_empty_file');

        // It's not possible to search multiple item ids, so use the connection.
        // SInce the job can be sent only by an admin, there is no rights issue.

        /*
        // TODO The media type can be non-standard for pdf (text/pdf…) on very old servers.
        $query = [
            'media_type' => 'application/pdf',
            'extension' => 'pdf',
        ];
        if ($itemId) {
            $query['item_id'] = $itemId;
        }
        $response = $this->api->search('media', $query, ['returnScalar' => 'id']);
        $pdfMediaIds = $response->getContent();
        $totalToProcess = count($pdfMediaIds);
        */

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        /*
        $entityManager = $services->get('Omeka\EntityManager');
        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);
        $criteria = Criteria::create();
        $expr = $criteria->expr();
        $criteria
            ->andWhere($expr->in('media_type', ['application/pdf', 'text/pdf']))
            ->andWhere($expr->eq('extension', 'pdf'))
            ->orderBy(['id' => 'ASC']);
        if ($itemIds) {
            $range = $this->exprRange('item', $itemIds);
            if ($range) {
                $criteria->andWhere($expr->orX(...$range));
            }
        }
        $collection = $mediaRepository->matching($criteria);
        $totalToProcess = $collection->count();
        */

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $sql = 'SELECT id FROM `media` WHERE `media_type`IN (:media_type) AND `extension`= :extension';
        $bind = [
            'media_type' => ['application/pdf', 'text/pdf'],
            'extension' => 'pdf',
        ];
        $types = [
            'media_type' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
            'extension' => \Doctrine\DBAL\ParameterType::STRING,
        ];
        if ($itemIds) {
            $range = $this->exprRange('item_id', $itemIds);
            if ($range) {
                $sql .= ' AND ' . implode(' AND ', $range);
            }
        }
        $sql .= ' ORDER BY `item_id` ASC';
        $pdfMediaIds = $connection->executeQuery($sql, $bind, $types)->fetchFirstColumn();
        $totalToProcess = count($pdfMediaIds);

        if (empty($totalToProcess)) {
            $message = new Message('No item with a pdf to process.'); // @translate
            $this->logger->notice($message);
            return;
        }

        $formats = [
            self::FORMAT_ALTO => 'alto',
            self::FORMAT_PDF2XML => 'pdf2xml',
            self::FORMAT_TSV => 'tsv',
        ];
        $message = new Message(sprintf(
            'Format of xml files to create: %s.', // @translate,
            $formats[$this->targetMediaType]
        ));

        if ($mode === 'existing') {
            $message = new Message(
                'Creating Extract OCR xml files for %d PDF only if they already exist.', // @translate
                $totalToProcess
            );
        } elseif ($mode === 'missing') {
            $message = new Message(
                'Creating Extract OCR xml files for %d PDF, only if they do not exist yet.', // @translate
                $totalToProcess
            );
        } elseif ($mode === 'all') {
            $message = new Message(
                'Creating Extract OCR xml files for %d PDF, xml files will be overridden or created.', // @translate
                $totalToProcess
            );
        } else {
            $message = new Message(
                'Mode of extraction "%s" is not managed.', // @translate
                $mode
            );
            return;
        }
        $this->logger->info($message);

        $countPdf = 0;
        $countSkipped = 0;
        $countFailed = 0;
        $countProcessed = 0;
        $this->stats = [
            'no_pdf' => [],
            'no_text_layer' => [],
            'issue' => [],
        ];

        foreach ($pdfMediaIds as $pdfMediaId) {
            if ($this->shouldStop()) {
                if ($mode === 'all') {
                    $this->logger->warn(new Message(
                        'The job "Extract OCR" was stopped: %1$d/%2$d resources processed, %3$d failed (%4$d without file, %5$d without text layer, %6$d with issue).', // @translate
                        $countProcessed, $totalToProcess, $countFailed, count($this->stats['no_pdf']), count($this->stats['no_text_layer']), count($this->stats['issue'])
                    ));
                } else {
                    $this->logger->warn(new Message(
                        'The job "Extract OCR" was stopped: %1$d/%2$d resources processed, %3$d skipped, %4$d failed (%5$d without file, %6$d without text layer, %7$d with issue).', // @translate
                        $countProcessed, $totalToProcess, $countSkipped, $countFailed, count($this->stats['no_pdf']), count($this->stats['no_text_layer']), count($this->stats['issue'])
                    ));
                }
                return;
            }

            $pdfMedia = $this->api->read('media', ['id' => $pdfMediaId])->getContent();
            $item = $pdfMedia->item();

            if (in_array($this->targetMediaType, [self::FORMAT_ALTO, self::FORMAT_PDF2XML])) {
                // Search if this item has already an xml file.
                $targetFilename = basename($pdfMedia->source(), '.pdf') . '.xml';
                // TODO Improve search of an existing xml, that can be imported separatly, or that can be another xml format with the same name.
                $searchXmlFile = $this->getMediaFromFilename($item->id(), $targetFilename, 'xml', $this->targetMediaType);
            } elseif ($this->targetMediaType === self::FORMAT_TSV) {
                // Search if this item has already an tsv file.
                $targetFilename = basename($pdfMedia->source(), '.pdf') . '.tsv';
                // TODO Improve search of an existing xml, that can be imported separatly, or that can be another xml format with the same name.
                $searchXmlFile = $this->getMediaFromFilename($item->id(), $targetFilename, 'tsv', $this->targetMediaType);
            } else {
                return;
            }

            ++$countPdf;
            $this->logger->info(new Message(
                'Index #%1$d/%2$d: Extracting OCR for item #%3$d, media #%4$d "%5$s".', // @translate
                $countPdf, $totalToProcess, $item->id(), $pdfMedia->id(), $pdfMedia->source())
            );

            if ($mode === 'all' || $mode === 'existing') {
                if ($searchXmlFile) {
                    try {
                        $this->api->delete('media', $searchXmlFile->id());
                    } catch (Exception $e) {
                        // There may be a doctrine issue with module Access, but media is removed.
                    }
                    $this->logger->info(new Message(
                        'The existing %1$s was removed for item #%2$d.', // @translate
                        $this->targetExtension, $item->id()
                    ));
                } elseif ($mode === 'existing') {
                    ++$countSkipped;
                    continue;
                }
            } elseif ($searchXmlFile) {
                $this->logger->info(new Message(
                    'A file %1$s (media #%2$d) already exists, so item #%3$d is skipped.',  // @translate
                    $this->targetExtension, $searchXmlFile->id(), $item->id()
                ));
                ++$countSkipped;
                continue;
            }

            $this->contentValue = null;
            $xmlMedia = $this->extractOcrForMedia($pdfMedia);
            if ($xmlMedia) {
                $this->logger->info(new Message(
                    'Media #%1$d (item #%2$d) created for %3$s file.', // @translate
                    $xmlMedia->id(), $item->id(), $this->targetExtension
                ));
                if ($this->store['item']) {
                    $this->storeContentInProperty($item);
                }
                ++$countProcessed;
            } else {
                ++$countFailed;
            }

            // Avoid memory issue.
            unset($pdfMedia);
            unset($xmlMedia);
            unset($item);
        }

        if ($this->stats['no_pdf']) {
            $message = new Message(sprintf(
                'These medias have no pdf file: #%s', // @translate
                implode(', #', $this->stats['no_pdf'])
            ));
            $this->logger->notice($message);
        }

        if ($this->stats['no_text_layer']) {
            $message = new Message(sprintf(
                'These pdf files have no text layer: #%s', // @translate
                implode(', #', $this->stats['no_text_layer'])
            ));
            $this->logger->notice($message);
        }

        if ($this->stats['issue']) {
            $message = new Message(sprintf(
                'These pdf files have issues when extracting content: #%s', // @translate
                implode(', #', $this->stats['issue'])
            ));
            $this->logger->notice($message);
        }

        if ($mode === 'all') {
            $message = new Message(
                'Processed %1$d/%2$d pdf files, %3$d files %4$s created, %5$d failed (%6$d without file, %7$d without text layer, %8$d with issue).', // @translate
                $countPdf, $totalToProcess, $countProcessed, $this->targetExtension, $countFailed, count($this->stats['no_pdf']), count($this->stats['no_text_layer']), count($this->stats['issue'])
            );
        } else {
            $message = new Message(
                'Processed %1$d/%2$d pdf files, %3$d skipped, %4$d files %5$s, created, %6$d failed (%7$d without file, %8$d without text layer, %9$d with issue).', // @translate
                $countPdf, $totalToProcess, $countSkipped, $countProcessed, $this->targetExtension, $countFailed, count($this->stats['no_pdf']), count($this->stats['no_text_layer']), count($this->stats['issue'])
            );
        }
        $this->logger->notice($message);
    }

    /**
     * Get the first media from item id, source name and extension.
     *
     * @todo Improve search of ocr pdf2xml files.
     */
    protected function getMediaFromFilename(
        int $itemId,
        string $filename,
        string $extension,
        string $mediaType
    ): ?MediaRepresentation {
        // The api search() doesn't allow to search a source, so we use read().
        try {
            return $this->api->read('media', [
                'item' => $itemId,
                'source' => $filename,
                'extension' => $extension,
                'mediaType' => $mediaType,
            ])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param MediaRepresentation $pdfMedia
     * @return MediaRepresentation|null The xml media.
     */
    protected function extractOcrForMedia(MediaRepresentation $pdfMedia): ?MediaRepresentation
    {
        $pdfFilepath = $this->basePath . '/original/' . $pdfMedia->filename();
        if (!file_exists($pdfFilepath)) {
            $this->stats['no_pdf'][] = $pdfMedia->id();
            $this->logger->err(new Message(
                'Missing pdf file (media #%1$d).', // @translate
                $pdfMedia->id()
            ));
            return null;
        }

        $this->dataPdf = [
            'source_pdf_file_url' => $pdfMedia->originalUrl(),
            'source_pdf_file_name' => $pdfMedia->filename(),
            'source_pdf_file_identifier' => (string) $pdfMedia->value('dcterms:identifier') ?: '',
            'source_pdf_document_url' => $pdfMedia->item()->apiUrl(),
            'source_pdf_document_identifier' => (string) $pdfMedia->item()->value('dcterms:identifier') ?: '',
        ];

        // Do the conversion of the pdf to xml.
        $xmlTempFile = $this->pdfToText($pdfFilepath, $pdfMedia->item());
        if (empty($xmlTempFile)) {
            $this->stats['issue'][] = $pdfMedia->id();
            $this->logger->err(new Message(
                'File %1$s was not created for media #%2$s.', // @translate
                $this->targetExtension, $pdfMedia->id()
            ));
            return null;
        }

        $tempPath = $xmlTempFile->getTempPath();

        // A check is done when option "create empty file" is not used.
        if (!$tempPath || !file_exists($tempPath)) {
            return null;
        }

        $content = file_get_contents($tempPath);

        // The content can be reextracted through pdftotext, that may return a
        // different layout with options -layout or -raw.
        // Here, the text is extracted from the extracted pdf2xml.
        if ($this->targetMediaType === self::FORMAT_ALTO) {
            $content = $this->extractTextFromAlto($content);
        } elseif ($this->targetMediaType === self::FORMAT_PDF2XML) {
            $content = trim(strip_tags($content));
        } else {
            $content = '';
        }

        if ($this->targetMediaType !== self::FORMAT_TSV
            && !$this->createEmptyFile
            && !strlen($content)
        ) {
            $xmlTempFile->delete();
            $this->stats['no_text_layer'][] = $pdfMedia->id();
            $this->logger->notice(new Message(
                'The output %1$s for pdf #%2$d has no text content and is not created.', // @translate
                $this->targetExtension, $pdfMedia->id()
            ));
            return null;
        }

        // It's not possible to save a local file via the "upload" ingester. So
        // the ingester "url" can be used, but it requires the file to be in the
        // omeka files directory. Else, use module FileSideload or inject sql.
        $xmlStoredFile = $this->makeTempFileDownloadable($xmlTempFile, '/extractocr');
        if (!$xmlStoredFile) {
            $xmlTempFile->delete();
            return null;
        }

        $currentPosition = count($pdfMedia->item()->media());

        // This data is important to get the matching pdf and xml.
        $source = basename($pdfMedia->source(), '.pdf') . '.' . $this->targetExtension;

        $data = [
            'o:item' => [
                'o:id' => $pdfMedia->item()->id(),
            ],
            'o:ingester' => 'url',
            'ingest_url' => $xmlStoredFile['url'],
            'o:source' => $source,
            'o:lang' => $this->language,
            'o:media_type' => $this->targetMediaType,
            'position' => $currentPosition,
            'values_json' => '{}',
        ];

        if ($this->property && strlen($content)) {
            $this->contentValue = [
                'type' => 'literal',
                'property_id' => $this->property->id(),
                '@value' => $content,
                '@language' => $this->language,
            ];
            if ($this->store['media_pdf']) {
                $this->storeContentInProperty($pdfMedia);
            }
            if ($this->store['media_xml']) {
                $data[$this->property->term()][] = $this->contentValue;
                $data['dcterms:isFormatOf'][] = [
                    'type' => 'resource:media',
                    // dcterms:isFormatOf.
                    'property_id' => 37,
                    'value_resource_id' => $pdfMedia->id(),
                ];
            }
        }

        try {
            $media = $this->api->create('media', $data)->getContent();
        } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
            // Generally a bad or missing pdf file.
            $this->logger->err($e->getMessage() ?: $e);
            return null;
        } catch (Exception $e) {
            $this->logger->err($e);
            return null;
        } finally {
            $xmlTempFile->delete();
            @unlink($xmlStoredFile['filepath']);
        }

        if (!$media) {
            return null;
        }

        // Move the xml file as the last media to avoid thumbnails issues.
        $this->reorderMediasAndSetType($media);
        return $media;
    }

    /**
     * Extract and store OCR Data from pdf in .xml file
     */
    protected function pdfToText(string $pdfFilepath, $item): ?TempFile
    {
        $tempFile = $this->tempFileFactory->build();

        $xmlFilepath = $tempFile->getTempPath() . '.' . $this->targetExtension;
        @unlink($tempFile->getTempPath());
        $tempFile->setTempPath($xmlFilepath);
        $tempPath = $tempFile->getTempPath();

        if ($this->targetMediaType === self::FORMAT_TSV) {
            $result = $this->extractTextToTsv($pdfFilepath, $xmlFilepath, $item);
            if (!$result) {
                if ($tempPath && file_exists($tempPath)) {
                    $tempFile->delete();
                }
                return null;
            }
            return $tempFile;
        }

        $command = sprintf('pdftohtml -i -c -hidden -nodrm -enc "UTF-8" -xml %1$s %2$s',
            escapeshellarg($pdfFilepath), escapeshellarg($xmlFilepath));

        $result = $this->cli->execute($command);
        if ($result === false) {
            if ($tempPath && file_exists($tempPath)) {
                $tempFile->delete();
            }
            return null;
        }

        // Remove control characters from bad ocr.
        /** @see https://stackoverflow.com/questions/1497885/remove-control-characters-from-php-string */
        $content = file_get_contents($xmlFilepath);
        $content = preg_replace('/[^\PCc^\PCn^\PCs]/u', '', $content);
        $xml = simplexml_load_string($content, null,
            LIBXML_BIGLINES
            | LIBXML_COMPACT
            | LIBXML_NOBLANKS
            | LIBXML_PARSEHUGE
            // | LIBXML_NOCDATA
            // | LIBXML_NOENT
            // Avoid issue when network is unavailable?
            // | LIBXML_NONET
        );

        if ($this->fixUtf8) {
            $xmlContent = $this->fixUtf8->__invoke($xmlContent);
        }

        $xmlContent = $this->fixXmlPdf2Xml($xmlContent);
        if (!$xmlContent) {
            if ($tempPath && file_exists($tempPath)) {
                $tempFile->delete();
            }
            return null;
        }

        $simpleXml = $this->fixXmlDom($xmlContent);
        if (!$simpleXml) {
            if ($tempPath && file_exists($tempPath)) {
                $tempFile->delete();
            }
            return null;
        }

        $simpleXml->saveXML($xmlFilepath);

        if ($this->targetMediaType === self::FORMAT_ALTO) {
            /** @see https://gitlab.freedesktop.org/poppler/poppler/-/raw/master/utils/pdf2xml.dtd pdf2xml */
            $modulePath = dirname(__DIR__, 2);
            $xsltPath = $modulePath . '/data/xsl/pdf2xml_to_alto.xsl';
            $args = $this->dataPdf;
            $args['datetime'] = (new DateTime('now'))->format('Y-m-d\TH:i:s');
            $dom = $this->processXslt($simpleXml, $xsltPath, $args);
            if (!$dom) {
                $tempFile->delete();
                return null;
            }
            $dom->formatOutput = true;
            $dom->strictErrorChecking = false;
            $dom->validateOnParse = false;
            $dom->recover = true;
            $dom->preserveWhiteSpace = false;
            $dom->substituteEntities = true;
            $result = $dom->save($xmlFilepath);
            if (!$result) {
                if ($tempPath && file_exists($tempPath)) {
                    $tempFile->delete();
                }
                return null;
            }
        }

        return $tempFile;
    }

    protected function extractTextToTsv($pdfFilepath, $tsvFilepath, $item) : bool
    {
        $listMedia = $this->listMedia($item);

        // Create temp file.
        $tempFile = $this->tempFileFactory->build();
        $xmlFilepath = $tempFile->getTempPath() . '.xml';
        @unlink($tempFile->getTempPath());
        $tempFile->setTempPath($xmlFilepath);
        $tempPath = $tempFile->getTempPath();

        $command = sprintf('pdftotext -bbox -layout %1$s %2$s',
            escapeshellarg($pdfFilepath), escapeshellarg($xmlFilepath));

        $result = $this->cli->execute($command);
        if ($result === false) {
            if ($tempPath && file_exists($tempPath)) {
                $tempFile->delete();
            }
            return false;
        }

        // Remove control characters from bad ocr.
        /** @see https://stackoverflow.com/questions/1497885/remove-control-characters-from-php-string */
        $content = file_get_contents($xmlFilepath);
        $content = preg_replace('/[^\PCc^\PCn^\PCs]/u', '', $content);
        $xml = simplexml_load_string($content, null,
            LIBXML_BIGLINES
            | LIBXML_COMPACT
            | LIBXML_NOBLANKS
            | LIBXML_PARSEHUGE
            // | LIBXML_NOCDATA
            // | LIBXML_NOENT
            // Avoid issue when network is unavailable.
            // | LIBXML_NONET
        );

        if ($xml === false) {
            return false;
        }

        $resultTsv = [];
        $indexXmlPage = 0;

        foreach ($xml->body->doc->page ?? [] as $xmlPage) {
            ++$indexXmlPage;

            $attributesPage = $xmlPage->attributes();
            $widthPage = $attributesPage->width;
            $heightPage = $attributesPage->height;

            $scaleX = $listMedia[$indexXmlPage - 1]['width'] / $widthPage;
            $scaleY = $listMedia[$indexXmlPage - 1]['height'] / $heightPage;

            foreach ($xmlPage->word ?? [] as $xmlword) {
                $word = (string) $xmlword;
                $word = $this->slugify($word);
                if (!strlen($word)) {
                    continue;
                }

                $attributes = $xmlword->attributes();

                $xMax = $attributes->xMax;
                $yMax = $attributes->yMax;
                $xMin = $attributes->xMin;
                $yMin = $attributes->yMin;

                $xMax = $xMax * $scaleX;
                $yMax = $yMax * $scaleY;
                $xMin = $xMin * $scaleX;
                $yMin = $yMin * $scaleY;

                $width = round($xMax - $xMin);
                $height = round($yMax - $yMin);

                if (isset($resultTsv[$word])) {
                    $resultTsv[$word][1] .= ";" . $indexXmlPage . ":" . round((float) $xMin) . "," . round((float) $yMin) . "," . $width . "," . $height;
                } else {
                    $resultTsv[$word][0] = $word;
                    $resultTsv[$word][1] = $indexXmlPage . ":" . round((float) $xMin) . "," . round((float) $yMin) . "," . $width . "," . $height;
                }
            }
        }

        if (!$resultTsv && !$this->createEmptyFile) {
            return true;
        }

        $fp = fopen($tsvFilepath, 'w');
        foreach ($resultTsv as $fields) {
            fputcsv($fp, $fields, "\t", chr(0), chr(0));
        }
        $tempFile->delete();
        return fclose($fp);
    }

    /**
     * Extract text from alto.
     */
    protected function extractTextFromAlto(string $content): string
    {
        $simpleXml = simplexml_load_string($content);
        $modulePath = dirname(__DIR__, 2);
        $xsltPath = $modulePath . '/data/xsl/alto_to_text.xsl';
        $dom = $this->processXslt($simpleXml, $xsltPath);
        if (!$dom) {
            return '';
        }
        $dom->formatOutput = false;
        $dom->strictErrorChecking = false;
        $dom->validateOnParse = false;
        $dom->recover = true;
        // $dom->preserveWhiteSpace = true;
        // $dom->substituteEntities = true;
        $result = (string) $dom->saveHTML();
        return html_entity_decode($result, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    }

    /**
     * Check if xml is valid.
     *
     * Copy in:
     * @see \ExtractOcr\Job\ExtractOcr::fixXmlDom()
     * @see \IiifSearch\View\Helper\IiifSearch::fixXmlDom()
     * @see \IiifSearch\View\Helper\XmlAltoSingle::fixXmlDom()
     * @see \IiifServer\Iiif\TraitXml::fixXmlDom()
     */
    protected function fixXmlDom(string $xmlContent): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.1', 'UTF-8');
        $dom->strictErrorChecking = false;
        $dom->validateOnParse = false;
        $dom->recover = true;
        try {
            $result = $dom->loadXML($xmlContent);
            $result = $result ? simplexml_import_dom($dom) : null;
        } catch (Exception $e) {
            $result = null;
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $result;
    }

    /**
     * Copy in:
     * @see \ExtractOcr\Job\ExtractOcr::fixXmlPdf2Xml()
     * @see \IiifSearch\View\Helper\IiifSearch::fixXmlPdf2Xml()
     * @see \IiifServer\Iiif\TraitXml::fixXmlPdf2Xml()
     */
    protected function fixXmlPdf2Xml(string $xmlContent): string
    {
        // When the content is not a valid unicode text, a null is output.
        // Replace all series of spaces by a single space.
        $xmlContent = preg_replace('~\s{2,}~S', ' ', $xmlContent) ?? $xmlContent;
        // Remove bold and italic.
        $xmlContent = preg_replace('~</?[bi]>~S', '', $xmlContent) ?? $xmlContent;
        // Remove fontspecs, useless for search and sometime incorrect with old
        // versions of pdftohtml. Exemple with pdftohtml 0.71 (debian 10):
        // <fontspec id="^C
        // <fontspec id=" " size="^P" family="PBPMTB+ArialUnicodeMS" color="#000000"/>
        /*
        if (preg_match('~<fontspec id=".*>$~S', '', $xmlContent)) {
            $xmlContent = preg_replace('~<fontspec id=".*>$~S', '', $xmlContent) ?? $xmlContent;
        }
        */
        // Keep incomplete font specs in order to keep order of font ids.
        $xmlContent = preg_replace('~<fontspec id="[^>]*$~S', '<fontspec/>*\n', $xmlContent) ?? $xmlContent;
        $xmlContent = str_replace('<!doctype pdf2xml system "pdf2xml.dtd">', '<!DOCTYPE pdf2xml SYSTEM "pdf2xml.dtd">', $xmlContent);
        return $xmlContent;
    }

    protected function processXslt(SimpleXMLElement $simpleXml, string $xsltPath, array $params = []): ?DOMDocument
    {
        try {
            $domXml = dom_import_simplexml($simpleXml);
            $domXsl = new DOMDocument('1.1', 'UTF-8');
            $domXsl->load($xsltPath);
            $proc = new XSLTProcessor();
            $proc->importStyleSheet($domXsl);
            $proc->setParameter('', $params);
            return $proc->transformToDoc($domXml) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Append the content text to a resource.
     *
     * A check is done to avoid to duplicate content.
     *
     * @param AbstractResourceEntityRepresentation $resource
     */
    protected function storeContentInProperty(AbstractResourceEntityRepresentation $resource): void
    {
        if (empty($this->contentValue)) {
            return;
        }

        foreach ($resource->value($this->property->term(), ['all' => true]) as $v) {
            if ($v->value() === $this->contentValue['@value']) {
                return;
            }
        }

        $this->api->update(
            $resource->resourceName(),
            $resource->id(),
            [$this->property->term() => [$this->contentValue]],
            [],
            ['isPartial' => true, 'collectionAction' => 'append']
        );
    }

    /**
     * Move a media at the last position of the item.
     *
     * @see \CSVImport\Job\Import::reorderMedias()
     *
     * @todo Move this process in the core.
     */
    protected function reorderMediasAndSetType(MediaRepresentation $media): void
    {
        // Note: the position is not available in representation.

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);
        $medias = $mediaRepository->findBy(['item' => $media->item()->id()]);
        if (count($medias) <= 1) {
            return;
        }

        $lastMedia = null;
        $lastMediaId = (int) $media->id();
        $key = 0;
        foreach ($medias as $itemMedia) {
            $itemMediaId = (int) $itemMedia->getId();
            if ($itemMediaId !== $lastMediaId) {
                $itemMedia->setPosition(++$key);
            } else {
                $lastMedia = $itemMedia;
            }
        }
        $lastMedia->setPosition(++$key);

        $lastMedia->setMediaType($this->targetMediaType);

        // Flush one time to use a transaction and to avoid a duplicate issue
        // with the index item_id/position.
        $entityManager->flush();
    }

    /**
     * Save a temp file into the files/temp directory.
     *
     * @see \DerivativeMedia\Module::makeTempFileDownloadable()
     * @see \Ebook\Mvc\Controller\Plugin\Ebook::saveFile()
     * @see \ExtractOcr\Job\ExtractOcr::makeTempFileDownloadable()
     */
    protected function makeTempFileDownloadable(TempFile $tempFile, string $base = ''): ?array
    {
        $baseDestination = '/temp';
        $destinationDir = $this->basePath . $baseDestination . $base;
        if (!$this->checkDir($destinationDir)) {
            return null;
        }

        $source = $tempFile->getTempPath();

        // Find a unique meaningful filename instead of a hash.
        $name = date('Ymd_His') . '_pdf2xml';
        $i = 0;
        do {
            $filename = $name . ($i ? '-' . $i : '') . '.' . $this->targetExtension;
            $destination = $destinationDir . '/' . $filename;
            if (!file_exists($destination)) {
                $result = @copy($source, $destination);
                if (!$result) {
                    $this->logger->err(new Message(
                        'File cannot be saved in temporary directory "%1$s" (temp file: "%2$s")', // @translate
                        $destination, $source
                    ));
                    return null;
                }
                $storageId = $base . $name . ($i ? '-' . $i : '');
                break;
            }
        } while (++$i);

        return [
            'filepath' => $destination,
            'filename' => $filename,
            'url' => $this->baseUri . $baseDestination . $base . '/' . $filename,
            'url_file' => $baseDestination . $base . '/' . $filename,
            'storageId' => $storageId,
        ];
    }

    /**
     * Check or create the destination folder.
     */
    protected function checkDir(string $dirPath): bool
    {
        if (!file_exists($dirPath)) {
            if (!is_writeable($this->basePath)) {
                $this->logger->err(new Message(
                    'Temporary destination for temp files can not be created : %1$s', // @translate
                    $dirPath
                ));
                return false;
            }
            @mkdir($dirPath, 0755, true);
        } elseif (!is_dir($dirPath) || !is_writeable($dirPath)) {
            return false;
        }
        return true;
    }

    /**
     * Create a list of doctrine expressions for a range.
     *
     * @param string $column
     * @param array|string $ids
     */
    protected function exprRange(string $column, $ids): array
    {
        $ranges = $this->rangeToArray($ids);
        if (empty($ranges)) {
            return [];
        }

        $conditions = [];

        foreach ($ranges as $range) {
            if (strpos($range, '-') === false) {
                $conditions[] = $column . ' = ' . (int) $range;
            } else {
                [$from, $to] = explode('-', $range);
                $from = strlen($from) ? (int) $from : null;
                $to = strlen($to) ? (int) $to : null;
                if ($from && $to) {
                    $conditions[] = "`$column` >= $from AND `$column` <= $to)";
                } elseif ($from) {
                    $conditions[] = "`$column` >= $from";
                } elseif ($to) {
                    $conditions[] = "`$column` <= $to";
                }
            }
        }

        return $conditions;
    }

    /**
     * Clean a list of ranges of ids.
     *
     * @param string|array $ids
     */
    protected function rangeToArray($ids): array
    {
        $clean = function ($str): string {
            $str = preg_replace('/[^0-9-]/', ' ', (string) $str);
            $str = preg_replace('/\s+/', ' ', $str);
            return trim($str);
        };

        $ids = is_array($ids)
            ? array_map($clean, $ids)
            : explode(' ', $clean($ids));

        // Skip empty ranges, fake ranges  and ranges with multiple "-".
        return array_values(array_filter($ids, function ($v) {
            return !empty($v) && $v !== '-' && substr_count($v, '-') <= 1;
        }));
    }

    /**
     * Transform the given string into a valid URL slug
     *
     * @param string $input
     * @return string
     */
    protected function slugify($input): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = mb_strtolower($slug, 'UTF-8');

        return $slug;
    }

    protected function listMedia($item): array
    {
        $imageSizes = [];

        foreach ($item->media() as $media) {
            $mediaId = $media->id();
            $mediaType = $media->mediaType();
            if (strtok((string) $mediaType, '/') === 'image') {
                // TODO The images sizes may be stored by xml files too, so skip size retrieving once the matching between images and text is done by page.
                $mediaData = $media->mediaData();
                // Iiif info stored by Omeka.
                if (isset($mediaData['width'])) {
                    $imageSizes[] = [
                        'id' => $mediaId,
                        'width' => $mediaData['width'],
                        'height' => $mediaData['height'],
                        'source' => $media->source(),
                    ];
                }
                // Info stored by Iiif Server.
                elseif (isset($mediaData['dimensions']['original']['width'])) {
                    $imageSizes[] = [
                        'id' => $mediaId,
                        'width' => $mediaData['dimensions']['original']['width'],
                        'height' => $mediaData['dimensions']['original']['height'],
                        'source' => $media->source(),
                    ];
                } elseif ($media->hasOriginal() && strtok($mediaType, '/') === 'image') {
                    $size = ['id' => $mediaId];
                    $size += $this->imageSizeLocal($media);
                    $size['source'] = $media->source();
                    $imageSizes[] = $size;
                }
            }
        }

        return $imageSizes;
    }

    protected function imageSizeLocal(MediaRepresentation $media): array
    {
        // Some media types don't save the file locally.
        $filepath = ($filename = $media->filename())
            ? $this->basePath . '/original/' . $filename
            : $media->originalUrl();
        $size = getimagesize($filepath);
        return $size
            ? ['width' => $size[0], 'height' => $size[1]]
            : ['width' => 0, 'height' => 0];
    }
}
