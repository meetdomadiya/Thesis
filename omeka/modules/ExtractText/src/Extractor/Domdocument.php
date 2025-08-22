<?php
namespace ExtractText\Extractor;

/**
 * Use DOMDocument to extract text from XML.
 *
 * @see https://www.php.net/manual/en/class.domdocument.php
 */
class Domdocument implements ExtractorInterface
{
    public function getName()
    {
        return 'domdocument';
    }

    public function isAvailable()
    {
        return true;
    }

    public function extract($filePath, array $options = [])
    {
        $xml = file_get_contents($filePath);
        $document = new \DOMDocument();
        $document->loadXml($xml);
        return $document->documentElement->textContent;
    }
}
