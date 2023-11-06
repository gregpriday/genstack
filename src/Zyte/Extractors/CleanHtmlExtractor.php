<?php

namespace Genstack\Zyte\Extractors;

use Symfony\Component\DomCrawler\Crawler;

class CleanHtmlExtractor extends Extractor
{
    public function cleanHtml(): string
    {
        $crawler = new Crawler($this->html);

        // Remove all script, style, template, iframe, and noscript tags
        $crawler->filter('script, style, template, iframe, noscript')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                $childNode->parentNode->removeChild($childNode);
            }
        });

        // Remove all image, video, svg, and audio tags
        $crawler->filter('img, video, svg, audio')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                $childNode->parentNode->removeChild($childNode);
            }
        });

        // Remove the header and footer
        $crawler->filter('header, footer')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                $childNode->parentNode->removeChild($childNode);
            }
        });

        // Remove all <link> and <meta> elements
        $crawler->filter('link, meta')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                $childNode->parentNode->removeChild($childNode);
            }
        });

        // Keep only the <title> element within <head>
        $crawler->filter('head')->each(function (Crawler $headNode) {
            foreach ($headNode->children() as $child) {
                if ($child->nodeName !== 'title') {
                    $child->parentNode->removeChild($child);
                }
            }
        });

        // For all remaining elements, remove all styles, classes, ids, and data attributes
        $crawler->filter('*')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                $childNode->removeAttribute('style');
                $childNode->removeAttribute('class');
                $childNode->removeAttribute('id');
                foreach ($childNode->attributes as $attribute) {
                    if (preg_match('/^data-/', $attribute->name) || in_array($attribute->name, ['onclick', 'onmouseover', 'onload'])) {
                        $childNode->removeAttribute($attribute->name);
                    }
                }
            }
        });

        // Remove any empty tags or tags containing only white spaces
        $crawler->filter('*')->each(function (Crawler $node) {
            foreach ($node as $childNode) {
                if (trim($childNode->textContent) === '') {
                    $childNode->parentNode->removeChild($childNode);
                }
            }
        });

        // Iterate over all `div`, `span`, `section`, and `article` nodes and remove them if they have a single child of the same type
        $crawler->filter('div, span, section, article')->each(function (Crawler $node) {
            // Check if the element has a single child and the child is of the same type
            if ($node->children()->count() == 1 && in_array($node->children()->getNode(0)->nodeName, ['div', 'span', 'section', 'article'])) {
                // Replace the element with its own content
                $node->getNode(0)->parentNode->replaceChild($node->children()->getNode(0), $node->getNode(0));
            }
            // Check if element only contains other structural elements and no meaningful content directly
            elseif ($node->filter('p, h1, h2, h3, h4, h5, h6, ul, ol')->count() == 0 && $node->filter('div, span, section, article')->count() == $node->children()->count()) {
                // Replace the element with its own children
                foreach (iterator_to_array($node->children()->getNode(0)->childNodes) as $child) {
                    $node->getNode(0)->parentNode->insertBefore($child, $node->getNode(0));
                }
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            }
        });

        // Retrieve the HTML, then remove comments and extra white spaces outside of the crawler
        $cleanedHtml = $crawler->html();

        // Replace non-breaking spaces, zero-width joiners, zero-width non-joiners,
        // and other characters you might want to remove or replace
        $searchReplaceArray = [
            '&nbsp;' => ' ',
            "\xc2\xa0" => ' ', // non-breaking space
            "\xe2\x80\x8d" => '',  // zero-width joiner
            "\xe2\x80\x8c" => '',  // zero-width non-joiner
            // Add any other replacements you need
        ];
        $cleanedHtml = str_replace(array_keys($searchReplaceArray), array_values($searchReplaceArray), $cleanedHtml);

        // Decode any HTML entities left, including those introduced by previous replacements
        $cleanedHtml = html_entity_decode($cleanedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Return the cleaned HTML
        return $cleanedHtml;
    }
}
