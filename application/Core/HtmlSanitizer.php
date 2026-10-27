<?php
declare(strict_types=1);

namespace AP\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans HTML written in the admin editor before it is saved, so content can be printed without escaping.
 * Allow-list based: unknown tags are unwrapped (their text stays), dangerous ones (script, style, iframe...) are
 * removed with their content, every attribute not on the list is dropped, and links may only use safe schemes.
 *
 *   $clean = HtmlSanitizer::clean($html);
 */
final class HtmlSanitizer
{
    /** tag => allowed attributes (besides the global ones). */
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sub' => [], 'sup' => [], 'code' => [], 'pre' => [],
        'blockquote' => ['cite'], 'ul' => [], 'ol' => ['start', 'reversed'], 'li' => [],
        'a' => ['href', 'target', 'rel', 'title'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'title'],
        'figure' => [], 'figcaption' => [], 'span' => [], 'div' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
        'dl' => [], 'dt' => [], 'dd' => [],
    ];
    /** Removed together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'frame', 'frameset', 'applet', 'head', 'title'];
    private const GLOBAL_ATTRS = ['class', 'id', 'lang', 'dir'];
    private const TEXT_ALIGN = ['left', 'right', 'center', 'justify'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ap-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('ap-root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /** Plain text of some HTML, for excerpts and search. */
    public static function text(string $html, int $limit = 0): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h2>', '</h3>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($limit > 0 && mb_strlen($text) > $limit) {
            $cut = mb_substr($text, 0, $limit);
            $space = mb_strrpos($cut, ' ');
            $text = rtrim($space > $limit * 0.6 ? mb_substr($cut, 0, (int) $space) : $cut, " ,.;:") . '…';
        }
        return $text;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset(self::TAGS[$tag])) {
                // Keep the text, lose the tag.
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::attributes($child, $tag);
            self::walk($child);
        }
    }

    private static function attributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAGS[$tag]);
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            if ($name === 'style') {
                // Only text alignment survives (the editor's align buttons).
                $el->removeAttribute($attr->name);
                if (preg_match('/text-align\s*:\s*(\w+)/i', $value, $m) && in_array(strtolower($m[1]), self::TEXT_ALIGN, true)) {
                    $el->setAttribute('style', 'text-align: ' . strtolower($m[1]));
                }
                continue;
            }
            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->name);
                continue;
            }
            if (in_array($name, ['href', 'src', 'cite'], true) && !self::safeUrl($value, $name === 'src')) {
                $el->removeAttribute($attr->name);
                continue;
            }
            if ($name === 'class') {
                $el->setAttribute('class', trim((string) preg_replace('/[^A-Za-z0-9_\- ]/', '', $value)));
            }
            if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) {
                $el->removeAttribute('target');
            }
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img' && !$el->hasAttribute('loading')) {
            $el->setAttribute('loading', 'lazy');
        }
    }

    public static function safeUrl(string $url, bool $image = false): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, '?')) {
            return true;
        }
        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
            return true; // relative path such as "about" or "images/x.jpg"
        }
        $scheme = strtolower($m[1]);
        return $image ? in_array($scheme, ['http', 'https'], true) : in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
