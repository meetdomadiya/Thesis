# Extract Text

Extract text from files to make them searchable and machine readable.

Once installed and active, this module has the following features:

- The module adds an "extracted text" property where it sets extracted text to
  media and items.
- When adding a media, the module will automatically extract text from the file
  and set the text to the media.
- When adding or editing an item, the module will automatically aggregate the
  media text (in order) and set the text to the item.
- When editing an item or batch editing items, the user can choose to refresh or
  clear the extracted text.
- When editing a media, the user can choose to refresh or clear the extracted
  text.
- On the module configuration page, the user can:
  - See which extractors are available on their system.
  - Disable individual extractors.
  - Set individual extractors to only run in the background.

## Supported file formats:

- DOC (application/msword)
- DOCX (application/vnd.openxmlformats-officedocument.wordprocessingml.document)
- HTML (text/html)
- ODT (application/vnd.oasis.opendocument.text)
- PDF (application/pdf)
- RTF (application/rtf)
- TXT (text/plain)
- XML (application/xml)

Images files are supported by tesseract if compiled with the [required libraries](https://tesseract-ocr.github.io/tessdoc/InputFormats.html):

- BMP (image/bmp)
- GIF (image/gif)
- JP2 (image/jp2)
- JPG (image/jpeg)
- PNG (image/png)
- TIFF (image/tiff)
- WEBP (image/webp)

Note that some file extensions or media types may be disallowed in your global
settings.

## Extractors:

### catdoc

Used to extract text from DOC and RTF files. Requires [catdoc](https://linux.die.net/man/1/catdoc).

### docx2txt

Used to extract text from DOCX files. Requires [docx2txt](http://docx2txt.sourceforge.net/).

### domdocument

Used to extract text from XML files. No requirements.

### filegetcontents

Used to extract text from TXT files. No requirements.

### lynx

Used to extract text from HTML files. Requires [lynx](https://linux.die.net/man/1/lynx).

### odt2txt

Used to extract text from ODT files. Requires [odt2txt](https://linux.die.net/man/1/odt2txt).

### pdftotext

Used to extract text from PDF files. Requires [pdftotext](https://linux.die.net/man/1/pdftotext),
a part of the poppler-utils package.

### tesseract

Used to extract text from image files (OCR). Requires [tesseract](https://tesseract-ocr.github.io/tessdoc/Command-Line-Usage.html).

## Disabling text extraction

You can disable text extraction for individual extractors in the module config
page.

You can disable text extraction for a specific media type by setting the media
type alias to `null` in the "extract_text_extractors" service config in your
local configuration file (config/local.config.php). For example, if you want to
disable extraction for TXT (text/plain) files, add the following:

```php
'extract_text_extractors' => [
    'aliases' => [
        'text/plain' => null,
    ],
],
```

## Extracting text in the background

Extractors may take a long time to process. Running them in the browser could result
in long wait times and server/brower timeouts, especially if done in synchronous
batches. For processes that take too long, users have two options to extract text:

- In the item edit page, by selecting "Refresh text (background)".
- In the item browse page, by selecting the "Edit all" batch action (perhaps after making a search).

Both of these options will run text extraction in a background job.

You can set individual extractors to only run in the background on the module config
page. Note that extractors can be set to never run in the foreground (see below).
These extractors cannot be toggled on/off on the module config page. They are always
set to "background only."

You can set an extractor to never run in the foreground using the "extract_text/
background_only" config in your local configuration file (config/local.config.php).
For example, if you want to set the pdftotext extractor as background only, add
the following:

```php
  'extract_text' => [
      'background_only' => [
          'pdftotext',
      ],
  ],
```

Note that extractors set as background only will not automatically extract text
when adding a media. You will need to extract text using the two options above.

## Configuring extractors

Some extractors allow you to configure how they extract text. You can configure
them using the "extract_text/options" config in your local configuration file
(config/local.config.php). For example, if you want to always skip the first page
of PDFs, add the following:

```php
'extract_text' => [
    'options' => [
        'pdftotext' => [
            'f' => 2,
        ],
    ],
],
```

Another example: if you want to use English and German together for OCR, add the
following:

```php
'extract_text' => [
    'options' => [
        'tesseract' => [
            'l' => 'eng+deu',
        ],
    ],
],
```

The following extractors have configuration options:

### filegetcontents

- offset: The offset where the reading starts (default 0)
- maxlen: Maximum length of data read (default null)

### pdftotext

- f: First page to convert (default null)
- l: Last page to convert (default null)

### tesseract

- l: Language/script (default 'eng')
- psm: Page segmentation mode (default 3)
- oem: OCR Engine mode (default 3)

See the [tesseract manual](https://github.com/tesseract-ocr/tesseract/blob/main/doc/tesseract.1.asc) for more info.

# Copyright

ExtractText is Copyright © 2019-present Corporation for Digital Scholarship, Vienna, Virginia, USA http://digitalscholar.org

The Corporation for Digital Scholarship distributes the Omeka source code
under the GNU General Public License, version 3 (GPLv3). The full text
of this license is given in the license file.

The Omeka name is a registered trademark of the Corporation for Digital Scholarship.

Third-party copyright in this distribution is noted where applicable.

All rights not expressly granted are reserved.
