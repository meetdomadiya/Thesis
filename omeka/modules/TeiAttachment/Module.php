<?php
declare(strict_types=1);

namespace TeiAttachment;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\Event;
use Omeka\Api\Manager as ApiManager;

class Module extends AbstractModule
{
    protected ?ApiManager $api = null;
    protected $services;
    protected array $localConfig = [];

    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        $this->services    = $event->getApplication()->getServiceManager();
        $this->localConfig = $this->getConfig()['TeiAttachment'] ?? [];
        $this->writeLog("Loaded local config:\n" . var_export($this->localConfig, true));

        $shared = $this->services->get('SharedEventManager');
        $shared->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'attachTeiMedia']
        );
        $shared->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'attachTeiMedia']
        );
    }

    public function attachTeiMedia(Event $event): void
    {
        if (!$this->api) {
            $this->api = $this->services->get('Omeka\ApiManager');
        }

        $response = $event->getParam('response');
        if (!$response) {
            $this->writeLog("No response in attachTeiMedia.");
            return;
        }

        $itemId = $response->getContent()->getId();
        $this->writeLog("Processing item {$itemId} for TEI attachment.");

        $xml = $this->fetchTeiFromExist($itemId);
        if (empty($xml)) {
            $this->writeLog("No TEI XML found for item {$itemId} in eXist‑db.");
            return;
        }

        $this->uploadXmlAsMedia($itemId, $xml);
    }

    protected function fetchTeiFromExist(int $itemId): ?string
    {
        if (empty($this->localConfig['exist_db_url'])) {
            return null;
        }

        $url = rtrim($this->localConfig['exist_db_url'], '/') . "/item_{$itemId}.xml";
        $this->writeLog("Fetching TEI from eXist‑db URL: {$url}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->localConfig['exist_db_user']}:{$this->localConfig['exist_db_pass']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/xml'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $xml  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->writeLog("eXist‑db responded HTTP {$code} for item {$itemId}");
        return ($code >= 200 && $code < 300) ? $xml : null;
    }

protected function uploadXmlAsMedia(int $itemId, string $xml): void
{
    $this->writeLog("Uploading TEI XML as media to item {$itemId}.");

    // Write to a real temporary file
    $tmpFilePath = tempnam(sys_get_temp_dir(), 'tei_');
    file_put_contents($tmpFilePath, $xml);

    try {
        $this->api->create('media', [
            'o:item' => ['o:id' => $itemId],
            'o:ingester' => 'upload',
            'file' => $tmpFilePath,
            'dcterms:title' => "TEI XML for item {$itemId}",
            'o:alt_text' => 'TEI XML attached from eXist-db',
        ]);

        $this->writeLog("Successfully attached TEI media to item {$itemId}.");
    } catch (\Throwable $e) {
        $message = $e->getMessage();
        if (empty($message)) {
            $message = 'Unknown error; likely a validation or ingester mismatch. Enable Omeka display_errors for full details.';
        }
        $this->writeLog("Error attaching TEI media to item {$itemId}: "
            . $message
            . "\n" . $e->getTraceAsString()
        );
    } finally {
        unlink($tmpFilePath);
    }
}

    protected function writeLog(string $message): void
    {
        $logPath = OMEKA_PATH . '/logs/tei_attachment_log.txt';
        $time    = date('Y-m-d H:i:s');
        file_put_contents(
            $logPath,
            "[{$time}]\n{$message}\n--------------------------\n",
            FILE_APPEND
        );
    }
}
