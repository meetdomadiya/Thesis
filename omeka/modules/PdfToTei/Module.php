<?php

declare(strict_types=1);

namespace PdfToTei;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\Event;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Manager as ApiManager;

class Module extends AbstractModule
{
    protected ?ApiManager $api = null;
    protected array $localConfig = [];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        $this->localConfig = include __DIR__ . '/config/module.config.php';
        $services = $event->getApplication()->getServiceManager();
        $sharedEventManager = $services->get('SharedEventManager');

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'logItemCreate']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'logItemUpdate']
        );
    }

    public function logItemCreate(Event $event): void
    {
        $services = $event->getTarget()->getServiceLocator();
        $this->api ??= $services->get('Omeka\ApiManager');

        $response = $event->getParam('response');
        if (!$response) {
            $this->writeLog("No response in logItemCreate.");
            return;
        }

        $itemEntity = $response->getContent();
        $item = $this->api->read('items', $itemEntity->getId())->getContent();
        $this->logItemData($item);
    }

    public function logItemUpdate(Event $event): void
    {
        $services = $event->getTarget()->getServiceLocator();
        $this->api ??= $services->get('Omeka\ApiManager');

        $response = $event->getParam('response');
        if (!$response) {
            $this->writeLog("No response in logItemUpdate.");
            return;
        }

        $itemEntity = $response->getContent();
        $item = $this->api->read('items', $itemEntity->getId())->getContent();
        $this->logItemData($item);
    }

    protected function logItemData(ItemRepresentation $item): void
    {
        $xmlString = $this->generateTeiXml($item);

        // 1. Save to eXist-db
        $this->saveTeiToExistDb((string)$item->id(), $xmlString);

        // 2. Attach as Omeka S media
        $this->attachTeiXmlViaCurl((string) $item->id(), $xmlString);
    }

    protected function generateTeiXml(ItemRepresentation $item): string
    {
        $properties = [
            'dcterms:title'              => 'title',
            'dcterms:publisher'          => 'publisher',
            'dcterms:spatial'            => 'publicationPlace',
            'dcterms:issued'             => 'dateIssued',
            'dcterms:valid'              => 'effectiveDate',
            'dcterms:type'               => 'category',
            'dcterms:subject'            => 'jobTitle',
            'dcterms:abstract'           => 'keyWords',
            'dcterms:audience'           => 'audience',
            'bibo:suffixName'            => 'titleTemplate',
            'bibo:identifier'            => 'textType',
            'bibo:editor'                => 'editor',
            'bibo:numPages'              => 'numberOfPages',
            'bibo:shortDescription'      => 'learningObjective',
            'extracttext:extracted_text' => 'extractedText',
        ];

        // Get metadata values
        $metadata = [];
        foreach ($properties as $term => $tag) {
            $values = $item->value($term, ['all' => true]);
            if (!empty($values)) {
                $texts = array_map(function ($v) use ($tag) {
                    $raw = $v->value();
                    if ($tag === 'extractedText') {
                        return $this->cleanGermanText($raw);
                    }
                    return $raw;
                }, $values);
                $metadata[$tag] = $texts;
            }
        }

        // Build TEI XML
        $tei = [];
        $tei[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $tei[] = '<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:id="item_' . $item->id() . '">';
        
        // TEI Header
        $tei[] = '  <teiHeader>';
        $tei[] = '    <fileDesc>';
        
        // Title Statement
        $tei[] = '      <titleStmt>';
        $title = $this->getFirstValue($metadata, 'title') ?? 'Item ID: ' . $item->id();
        $tei[] = '        <title>' . $this->escapeXml($title) . '</title>';
        
        if (isset($metadata['editor'])) {
            foreach ($metadata['editor'] as $editor) {
                $tei[] = '        <editor>' . $this->escapeXml($editor) . '</editor>';
            }
        }
        $tei[] = '      </titleStmt>';
        
        // Publication Statement
        $tei[] = '      <publicationStmt>';
        if (isset($metadata['publisher'])) {
            foreach ($metadata['publisher'] as $publisher) {
                $tei[] = '        <publisher>' . $this->escapeXml($publisher) . '</publisher>';
            }
        }
        if (isset($metadata['publicationPlace'])) {
            foreach ($metadata['publicationPlace'] as $place) {
                $tei[] = '        <pubPlace>' . $this->escapeXml($place) . '</pubPlace>';
            }
        }
        if (isset($metadata['dateIssued'])) {
            $date = $this->getFirstValue($metadata, 'dateIssued');
            $tei[] = '        <date>' . $this->escapeXml($date) . '</date>';
        }
        $tei[] = '        <availability>';
        $tei[] = '          <p>Generated from Omeka S Item</p>';
        $tei[] = '        </availability>';
        $tei[] = '      </publicationStmt>';
        
        // Source Description
        $tei[] = '      <sourceDesc>';
        $tei[] = '        <p>Digital item from Omeka S with ID: ' . $item->id() . '</p>';
        if (isset($metadata['numberOfPages'])) {
            $pages = $this->getFirstValue($metadata, 'numberOfPages');
            $tei[] = '        <p>Number of pages: ' . $this->escapeXml($pages) . '</p>';
        }
        $tei[] = '      </sourceDesc>';
        $tei[] = '    </fileDesc>';
        
        // Profile Description (for metadata)
        $tei[] = '    <profileDesc>';
        
        // Text Class
        if (isset($metadata['category']) || isset($metadata['keyWords']) || isset($metadata['jobTitle'])) {
            $tei[] = '      <textClass>';
            
            if (isset($metadata['keyWords'])) {
                $tei[] = '        <keywords>';
                foreach ($metadata['keyWords'] as $keyword) {
                    $terms = explode(';', $keyword);
                    foreach ($terms as $term) {
                        $term = trim($term);
                        if (!empty($term)) {
                            $tei[] = '          <term>' . $this->escapeXml($term) . '</term>';
                        }
                    }
                }
                $tei[] = '        </keywords>';
            }
            
            if (isset($metadata['category'])) {
                foreach ($metadata['category'] as $category) {
                    $tei[] = '        <classCode>' . $this->escapeXml($category) . '</classCode>';
                }
            }
            
            $tei[] = '      </textClass>';
        }
        
        $tei[] = '    </profileDesc>';
        
        // Encoding Description
        $tei[] = '    <encodingDesc>';
        $tei[] = '      <projectDesc>';
        $tei[] = '        <p>TEI document generated from Omeka S metadata by PdfToTei module</p>';
        $tei[] = '      </projectDesc>';
        $tei[] = '    </encodingDesc>';
        
        // Revision Description
        $tei[] = '    <revisionDesc>';
        $tei[] = '      <change when="' . date('Y-m-d') . '">Generated from Omeka S item</change>';
        $tei[] = '    </revisionDesc>';
        
        $tei[] = '  </teiHeader>';
        
        // Text Body
        $tei[] = '  <text>';
        $tei[] = '    <body>';
        
        // Front matter with metadata
        $tei[] = '      <front>';
        $tei[] = '        <div type="metadata">';
        $tei[] = '          <head>Item Metadata</head>';
        
        // Add metadata as structured content
        foreach (['textType', 'audience', 'learningObjective', 'titleTemplate'] as $field) {
            if (isset($metadata[$field])) {
                $label = ucwords(str_replace('_', ' ', $field));
                foreach ($metadata[$field] as $value) {
                    $tei[] = '          <p><label>' . $label . ':</label> ' . $this->escapeXml($value) . '</p>';
                }
            }
        }
        
        $tei[] = '        </div>';
        $tei[] = '      </front>';
        
        // Main content
        $tei[] = '      <div type="main">';
        $tei[] = '        <head>' . $this->escapeXml($title) . '</head>';
        
        if (isset($metadata['extractedText']) && !empty($metadata['extractedText'])) {
            $extractedText = $this->getFirstValue($metadata, 'extractedText');
            
            // Process the extracted text into paragraphs
            $paragraphs = $this->processTextIntoParagraphs($extractedText);
            
            foreach ($paragraphs as $paragraph) {
                if (!empty(trim($paragraph))) {
                    $tei[] = '        <p>' . $this->escapeXml(trim($paragraph)) . '</p>';
                }
            }
        } else {
            $tei[] = '        <p>[No extracted text available]</p>';
        }
        
        $tei[] = '      </div>';
        $tei[] = '    </body>';
        
        // Back matter for additional information
        if (isset($metadata['effectiveDate'])) {
            $tei[] = '    <back>';
            $tei[] = '      <div type="notes">';
            $effectiveDate = $this->getFirstValue($metadata, 'effectiveDate');
            $tei[] = '        <p>Effective Date: ' . $this->escapeXml($effectiveDate) . '</p>';
            $tei[] = '      </div>';
            $tei[] = '    </back>';
        }
        
        $tei[] = '  </text>';
        $tei[] = '</TEI>';

        return implode("\n", $tei);
    }

    protected function processTextIntoParagraphs(string $text): array
    {
        // Split by double newlines to get paragraphs
        $paragraphs = explode("\n\n", $text);
        
        // Clean up each paragraph
        $cleaned = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Replace single newlines with spaces within paragraphs
                $paragraph = str_replace("\n", " ", $paragraph);
                // Clean up multiple spaces
                $paragraph = preg_replace('/\s+/', ' ', $paragraph);
                $cleaned[] = $paragraph;
            }
        }
        
        return $cleaned;
    }

    protected function getFirstValue(array $metadata, string $key): ?string
    {
        return isset($metadata[$key]) && !empty($metadata[$key]) ? $metadata[$key][0] : null;
    }

    protected function saveTeiToExistDb(string $itemId, string $xmlString): void
    {
        if (empty($this->localConfig['PdfToTei'])
            || !is_array($this->localConfig['PdfToTei'])
        ) {
            $this->writeLog("PdfToTei config missing or invalid.");
            return;
        }

        $dbConfig = $this->localConfig['PdfToTei'];
        $baseUrl  = rtrim($dbConfig['exist_db_url'], '/');
        $username = $dbConfig['exist_db_user'] ?? '';
        $password = $dbConfig['exist_db_pass'] ?? '';

        $url = "{$baseUrl}/item_{$itemId}.xml";
        $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'auto');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/xml',
            'Content-Length: ' . strlen($xmlString),
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->writeLog("TEI XML successfully saved to eXist-db for item {$itemId}.");
        } else {
            $this->writeLog("Failed to save TEI XML for item {$itemId} to eXist-db.");
        }
    }

    protected function attachTeiXmlViaCurl(string $itemId, string $xmlString): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "tei_{$itemId}_");
        file_put_contents($tmpFile, $xmlString);

        $config = $this->localConfig['PdfToTei'];
        $url = sprintf(
            '%s/api/media?key_identity=%s&key_credential=%s',
            rtrim($config['omeka_base_url'], '/'),
            urlencode($config['omeka_key_id']),
            urlencode($config['omeka_key_credential'])
        );

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_HTTPHEADER => ['Expect:'], // disables chunked
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            'file[0]' => new \CurlFile($tmpFile, 'application/tei+xml', "item_{$itemId}.xml"),
            'data'    => json_encode([
                'o:ingester' => 'upload',
                'file_index' => 0,
                'o:item'     => ['o:id' => (int) $itemId],
            ]),
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $code  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        @unlink($tmpFile);

        if ($response === false || $code < 200 || $code >= 300) {
            $this->writeLog("❌ curl attach failed for item {$itemId}: HTTP {$code}, error: {$error}, response: {$response}");
        } else {
            $this->writeLog("✅ TEI XML attached via curl to item {$itemId}: HTTP {$code}");
        }
    }

    protected function cleanGermanText(string $text): string
    {
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // Handle ligatures
        $ligatures = [
            'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬀ' => 'ff',
            'ﬃ' => 'ffi', 'ﬄ' => 'ffl',
        ];
        $text = strtr($text, $ligatures);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Fix hyphenated words that are broken across lines
        // This handles cases like "Tehr-\nberuf" -> "Tehrberuf"
        $text = preg_replace('/([a-zA-ZäöüÄÖÜß])-\s*\n\s*([a-zA-ZäöüÄÖÜß])/u', '$1$2', $text);
        
        // Fix words broken across lines without hyphen
        // This handles cases like "Schul\nmwefen" -> "Schulmwefen"
        $text = preg_replace('/([a-zA-ZäöüÄÖÜß])\s*\n\s*([a-z])/u', '$1$2', $text);
        
        // Fix broken compound words and technical terms
        // Handle specific patterns common in German OCR
        $text = preg_replace('/([a-zA-ZäöüÄÖÜß])\s+([a-z]{1,3})\s+([a-zA-ZäöüÄÖÜß])/u', '$1$2$3', $text);
        
        // Clean up specific OCR artifacts
        $text = str_replace([
            'Tehr beruf' => 'Lehrberuf',
            'Schul mwefen' => 'Schulwesen',
            'Induftrie' => 'Industrie',
            'Deutfchen' => 'Deutschen',
            'Arbeits front' => 'Arbeitsfront',
            'Reichs gruppe' => 'Reichsgruppe',
            'Handels kammern' => 'Handelskammern',
            'Reichs wirtfchafts kammer' => 'Reichswirtschaftskammer',
            'Berufs eignungs anforderungen' => 'Berufseignungsanforderungen',
            'Volks fchul bildung' => 'Volksschulbildung',
            'Hilfs schulbildung' => 'Hilfsschulbildung',
            'Seh schwachen' => 'Sehschwachen',
            'Blinden fchul bildung' => 'Blindenschulbildung',
            'Taub stumme' => 'Taubstumme'
        ], array_keys(str_replace([
            'Tehr beruf' => 'Lehrberuf',
            'Schul mwefen' => 'Schulwesen',
            'Induftrie' => 'Industrie',
            'Deutfchen' => 'Deutschen',
            'Arbeits front' => 'Arbeitsfront',
            'Reichs gruppe' => 'Reichsgruppe',
            'Handels kammern' => 'Handelskammern',
            'Reichs wirtfchafts kammer' => 'Reichswirtschaftskammer',
            'Berufs eignungs anforderungen' => 'Berufseignungsanforderungen',
            'Volks fchul bildung' => 'Volksschulbildung',
            'Hilfs schulbildung' => 'Hilfsschulbildung',
            'Seh schwachen' => 'Sehschwachen',
            'Blinden fchul bildung' => 'Blindenschulbildung',
            'Taub stumme' => 'Taubstumme'
        ], '', [])), $text);
        
        // Fix common OCR character mistakes in old German text
        $characterFixes = [
            'mw' => 'nw',  // common OCR mistake
            'rn' => 'm',   // when context suggests it
            'fi' => 'si',  // in some contexts
            'ift' => 'ist',
            'find' => 'sind',
            'fich' => 'sich',
            'forte' => 'sowie',
            'befonderen' => 'besonderen',
            'Facharbeiter' => 'Facharbeiter',
            'abgefchloffene' => 'abgeschlossene',
            'Möglichft' => 'Möglichst',
            'Ermwünjcht' => 'Erwünscht',
            'Ubgängern' => 'Abgängern',
            'fißende' => 'sitzende',
            'ausüben' => 'ausüben'
        ];
        
        foreach ($characterFixes as $wrong => $correct) {
            $text = str_replace($wrong, $correct, $text);
        }
        
        // Join lines that seem to be part of the same sentence
        // But preserve intentional paragraph breaks
        $lines = explode("\n", $text);
        $cleaned_lines = [];
        $current_line = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                // Empty line - end current paragraph
                if (!empty($current_line)) {
                    $cleaned_lines[] = trim($current_line);
                    $current_line = '';
                }
                $cleaned_lines[] = '';
            } elseif (
                // Line seems to continue previous line
                !empty($current_line) && 
                !preg_match('/^[A-ZÄÖÜ]/', $line) && // doesn't start with capital
                !preg_match('/[.!?:]\s*$/', $current_line) && // previous doesn't end with punctuation
                strlen($line) > 3 // not just a short fragment
            ) {
                $current_line .= ' ' . $line;
            } else {
                // Start new line/paragraph
                if (!empty($current_line)) {
                    $cleaned_lines[] = trim($current_line);
                }
                $current_line = $line;
            }
        }
        
        // Don't forget the last line
        if (!empty($current_line)) {
            $cleaned_lines[] = trim($current_line);
        }
        
        $text = implode("\n", $cleaned_lines);
        
        // Final cleanup
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        return trim($text);
    }

    protected function escapeXml(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function writeLog(string $message): void
    {
        $logPath = OMEKA_PATH . '/logs/item_log.txt';
        $time = date('Y-m-d H:i:s');
        file_put_contents(
            $logPath,
            "[{$time}]\n{$message}\n--------------------------\n",
            FILE_APPEND
        );
    }
}