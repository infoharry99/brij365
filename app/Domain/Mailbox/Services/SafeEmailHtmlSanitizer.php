<?php

namespace App\Domain\Mailbox\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SafeEmailHtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'blockquote'];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<div id="mail-body">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('mail-body');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (! in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if ($child->tagName !== 'a' || ! in_array($name, ['href', 'title'], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if (strtolower($child->tagName) === 'a') {
                $href = trim($child->getAttribute('href'));
                if (! preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
                    $child->removeAttribute('href');
                } else {
                    $child->setAttribute('rel', 'noopener noreferrer');
                    $child->setAttribute('target', '_blank');
                }
            }

            $this->cleanChildren($child);
        }
    }
}
