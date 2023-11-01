<?php

namespace Genstack\Zyte\Extractors;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownExtractor extends CleanHtmlExtractor
{
    public function extractMarkdown(): string
    {
        $html = $this->cleanHtml();
        $readability = new Readability(new Configuration());
        $readability->parse($html);

        $content = $readability->getContent();
        $title = $readability->getTitle();

        $converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
        ]);

        $markdown = $converter->convert($content);

        // Trim each line and then replace 3 or more newlines with just two.
        $markdown = implode("\n", array_map('trim', explode("\n", $markdown)));
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);

        return '# '.$title."\n\n".$markdown;
    }
}
