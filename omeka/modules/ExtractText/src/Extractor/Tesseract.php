<?php
namespace ExtractText\Extractor;

use Omeka\Stdlib\Cli;

/**
 * Use tesseract to extract text.
 *
 * @see https://github.com/tesseract-ocr/tesseract/blob/main/doc/tesseract.1.asc
 */
class Tesseract implements ExtractorInterface
{
    protected $cli;

    public function __construct(Cli $cli)
    {
        $this->cli = $cli;
    }

    public function getName()
    {
        return 'tesseract';
    }

    public function isAvailable()
    {
        return (bool) $this->cli->getCommandPath('tesseract');
    }

    public function extract($filePath, array $options = [])
    {
        $commandPath = $this->cli->getCommandPath('tesseract');
        if (false === $commandPath) {
            return false;
        }
        $commandArgs = [
            $commandPath,
            escapeshellarg($filePath), // imagename
            '-', // outputbase (stdout)
            isset($options['l']) ? sprintf('-l %s', escapeshellarg($options['l'])) : '-l eng', // language
            isset($options['psm']) ? sprintf('--psm %s', escapeshellarg($options['psm'])) : '--psm 3', // page segmentation mode
            isset($options['oem']) ? sprintf('--oem %s', escapeshellarg($options['oem'])) : '--oem 3', // OCR Engine mode
            'quiet', // suppress tesseract info line
        ];
        $command = implode(' ', $commandArgs);
        return $this->cli->execute($command);
    }
}
