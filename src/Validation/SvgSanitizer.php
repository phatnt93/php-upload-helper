<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use DOMDocument;
use DOMElement;
use DOMNode;
use UploadHelper\Exceptions\SecurityException;

class SvgSanitizer
{
    /** @var array<int, string> */
    public const BLOCKED_TAGS = [
        'script', 'foreignobject', 'iframe', 'object', 'embed', 'applet', 'meta', 'link',
    ];

    /**
     * Quét và làm sạch tệp SVG, loại bỏ các đoạn mã độc Stored XSS
     *
     * @throws SecurityException Nếu phát hiện payload độc hại không thể làm sạch an toàn
     */
    public static function sanitize(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return;
        }

        // Kiểm tra nhanh sự xuất hiện của các từ khóa độc hại nguy hiểm
        if (preg_match('/<\s*(?:script|foreignobject|iframe|object|embed)/i', $content)) {
            // Cố gắng làm sạch bằng DOMDocument
            $cleaned = self::cleanSvgXml($content);
            file_put_contents($filePath, $cleaned);
        } else {
            // Quét thêm các thuộc tính sự kiện inline on* và javascript:
            if (preg_match('/\bon[a-z]+\s*=/i', $content) || preg_match('/(?:href|xlink:href)\s*=\s*["\']\s*javascript:/i', $content)) {
                $cleaned = self::cleanSvgXml($content);
                file_put_contents($filePath, $cleaned);
            }
        }
    }

    public static function cleanSvgXml(string $svgXml): string
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;

        // Block every external entity / DTD fetch (XXE) while parsing, then restore the caller's loader.
        $previousEntityLoader = function_exists('libxml_get_external_entity_loader')
            ? libxml_get_external_entity_loader()
            : null;
        libxml_set_external_entity_loader(static fn() => null);
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($svgXml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_set_external_entity_loader($previousEntityLoader);
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        if (!$loaded) {
            throw SecurityException::forCode('ERR_INVALID_SVG_XML');
        }

        self::traverseAndClean($dom->documentElement);

        $result = $dom->saveXML();
        return $result !== false ? $result : $svgXml;
    }

    private static function traverseAndClean(?DOMNode $node): void
    {
        if ($node === null) {
            return;
        }

        if ($node instanceof DOMElement) {
            $tagName = strtolower($node->tagName);

            // 1. Xóa bỏ thẻ bị chặn
            if (in_array($tagName, self::BLOCKED_TAGS, true)) {
                $node->parentNode?->removeChild($node);
                return;
            }

            // 2. Xóa các thuộc tính nguy hiểm
            if ($node->hasAttributes()) {
                $attrsToRemove = [];
                foreach ($node->attributes as $attr) {
                    $attrName = strtolower($attr->name);
                    $attrValue = strtolower(trim($attr->value));

                    // Sự kiện onload, onerror, onclick,...
                    if (str_starts_with($attrName, 'on')) {
                        $attrsToRemove[] = $attr->name;
                        continue;
                    }

                    // href="javascript:..."
                    if (($attrName === 'href' || $attrName === 'xlink:href') && str_starts_with($attrValue, 'javascript:')) {
                        $attrsToRemove[] = $attr->name;
                        continue;
                    }
                }

                foreach ($attrsToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Duyệt các con
        $child = $node->firstChild;
        while ($child !== null) {
            $next = $child->nextSibling;
            self::traverseAndClean($child);
            $child = $next;
        }
    }
}
