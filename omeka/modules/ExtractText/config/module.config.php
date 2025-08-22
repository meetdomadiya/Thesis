<?php
return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'ExtractText\ExtractorManager' => ExtractText\Service\Extractor\ManagerFactory::class,
        ],
    ],
    'extract_text_extractors' => [
        'factories' => [
            'catdoc' => ExtractText\Service\Extractor\CatdocFactory::class,
            'docx2txt' => ExtractText\Service\Extractor\Docx2txtFactory::class,
            'lynx' => ExtractText\Service\Extractor\LynxFactory::class,
            'odt2txt' => ExtractText\Service\Extractor\Odt2txtFactory::class,
            'pdftotext' => ExtractText\Service\Extractor\PdftotextFactory::class,
            'tesseract' => ExtractText\Service\Extractor\TesseractFactory::class,
        ],
        'invokables' => [
            'filegetcontents' => ExtractText\Extractor\Filegetcontents::class,
            'domdocument' => ExtractText\Extractor\Domdocument::class,
        ],
        'aliases' => [
            'application/msword' => 'catdoc',
            'application/rtf' => 'catdoc',
            'application/pdf' => 'pdftotext',
            'application/vnd.oasis.opendocument.text' => 'odt2txt',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx2txt',
            'text/html' => 'lynx',
            'text/plain' => 'filegetcontents',
            'text/xml' => 'domdocument',
            'application/xml' => 'domdocument',
            'application/tei+xml' => 'domdocument',
            'image/png' => 'tesseract',
            'image/jpeg' => 'tesseract',
            'image/tiff' => 'tesseract',
            'image/jp2' => 'tesseract',
            'image/gif' => 'tesseract',
            'image/webp' => 'tesseract',
            'image/bmp' => 'tesseract',
        ],
    ],
    'extract_text' => [
        'background_only' => [
            'tesseract',
        ],
        'options' => [
            'filegetcontents' => [
                'offset' => 0, // The offset where the reading starts
                'maxlen' => null, // Maximum length of data read
            ],
            'pdftotext' => [
                'f' => null, // First page to convert
                'l' => null, // Last page to convert
            ],
            'tesseract' => [
                'l' => 'deu', // Language/script
                'psm' => 3, // Page segmentation mode
                'oem' => 3, // OCR Engine mode
            ],
        ],
    ],
];
