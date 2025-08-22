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

        $tei = [];
        $tei[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $tei[] = '<TEI xmlns="http://www.tei-c.org/ns/1.0">';
        $tei[] = '  <teiHeader>';
        $tei[] = '    <fileDesc>';
        $tei[] = "      <titleStmt><title>Item ID: {$item->id()}</title></titleStmt>";
        $tei[] = '      <publicationStmt><p>Logged by PdfToTei Module</p></publicationStmt>';
        $tei[] = '      <sourceDesc><p>Omeka S Item Metadata</p></sourceDesc>';
        $tei[] = '    </fileDesc>';
        $tei[] = '  </teiHeader>';
        $tei[] = '  <text>';
        $tei[] = '    <body>';

        foreach ($properties as $term => $tag) {
            $values = $item->value($term, ['all' => true]);
            if (!empty($values)) {
                $texts = array_map(function ($v) use ($tag) {
                    $raw = $v->value();
                    if ($tag === 'extractedText') {
                        $cleaned = $this->cleanGermanText($raw);
                    } else {
                        $cleaned = $raw;
                    }
                    return $this->escapeXml($cleaned);
                }, $values);
                $valueString = implode('; ', $texts);
            } else {
                $valueString = 'N/A';
            }
            $tei[] = "      <{$tag}>{$valueString}</{$tag}>";
        }

        $tei[] = '    </body>';
        $tei[] = '  </text>';
        $tei[] = '</TEI>';

        $xmlString = implode("\n", $tei);

        // 1. Save to eXist-db
        $this->saveTeiToExistDb((string)$item->id(), $xmlString);

        // 2. Attach as Omeka S media
        $this->attachTeiXmlViaCurl((string) $item->id(), $xmlString);

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
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $ligatures = [
            'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬀ' => 'ff',
            'ﬃ' => 'ffi', 'ﬄ' => 'ffl',
        ];
        $text = strtr($text, $ligatures);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
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
