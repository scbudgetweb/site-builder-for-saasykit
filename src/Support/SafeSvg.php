<?php

namespace ScBudgetWeb\SiteBuilder\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Checks an uploaded SVG (a logo, favicon or block image) before it's stored. Uploads are served from the site's own address, and
 * an SVG opened on its own is a document that can run script, so an SVG is only accepted when it's a plain drawing:
 *
 *  - no script, foreignObject (HTML inside the SVG), animation that can change links, or other active elements;
 *  - no event handler attributes (onload="…") and no javascript: anywhere;
 *  - links and url() references only to #fragments in the same file, or inline raster data:image pictures;
 *  - no entities or DOCTYPE with declarations of its own (the plain DOCTYPE line some editors add is fine: it's never
 *    loaded), no processing instructions such as <?xml-stylesheet?>, and styles that pass the same checks as Custom CSS.
 *
 * Elements in other namespaces (e.g. Inkscape's or Illustrator's metadata) are inert and allowed; anything in the
 * XHTML namespace isn't. Other file types pass straight through.
 */
class SafeSvg implements ValidationRule
{
    /** SVG elements a drawing can use. Anything else in the SVG namespace is refused. */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata', 'style',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker', 'image', 'switch',
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix',
        'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB',
        'feFuncG', 'feFuncR', 'feGaussianBlur', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset',
        'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence',
    ];

    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! self::isSvg($value)) {
            return;
        }

        $problem = self::problem((string) file_get_contents($value->getRealPath()));

        if ($problem !== null) {
            $fail($problem);
        }
    }

    /**
     * Why the SVG can't be used, or null when it's a plain drawing.
     */
    public static function problem(string $svg): ?string
    {
        $unsafe = __('This SVG can\'t be used because it contains scripts, links to other files or other active content. Export it again as a plain SVG (in most design apps: Export › SVG, without editing data or scripts), or use a PNG or WebP.');

        // A DOCTYPE with its own declarations ([…]) can define entities; a plain one only names a DTD that isn't loaded.
        if (trim($svg) === '' || stripos($svg, '<!ENTITY') !== false || preg_match('/<!DOCTYPE[^>]*\[/i', $svg) === 1) {
            return $unsafe;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $document->documentElement?->localName !== 'svg') {
            return __('This file isn\'t a valid SVG.');
        }

        $xpath = new DOMXPath($document);

        // E.g. an xml-stylesheet instruction, which would load a style sheet from elsewhere. (The XML declaration at
        // the top isn't one.)
        if (($xpath->query('//processing-instruction()')?->length ?? 0) > 0) {
            return $unsafe;
        }

        foreach ($xpath->query('//*') ?: [] as $element) {
            if (! $element instanceof DOMElement || ! self::isAllowedElement($element)) {
                return $unsafe;
            }

            foreach ($element->attributes ?? [] as $attribute) {
                if (! self::isAllowedAttribute($attribute->localName, $attribute->value)) {
                    return $unsafe;
                }
            }

            if ($element->localName === 'style' && CustomCss::problem($element->textContent) !== null) {
                return $unsafe;
            }
        }

        return null;
    }

    private static function isSvg(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true)
            || strtolower($file->getClientOriginalExtension()) === 'svg';
    }

    private static function isAllowedElement(DOMElement $element): bool
    {
        return match ($element->namespaceURI) {
            self::SVG_NAMESPACE, null, '' => in_array($element->localName, self::ALLOWED_ELEMENTS, true),
            self::XHTML_NAMESPACE => false,
            default => true,
        };
    }

    private static function isAllowedAttribute(string $name, string $value): bool
    {
        $compact = strtolower((string) preg_replace('/\s+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

        if (str_starts_with(strtolower($name), 'on') || str_contains($compact, 'javascript:') || str_contains($compact, 'vbscript:')) {
            return false;
        }

        if ($name === 'href' && ! self::isAllowedReference($compact)) {
            return false;
        }

        if ($name === 'style') {
            return CustomCss::problem($value) === null;
        }

        // Presentation attributes such as fill="url(#gradient)" may only point into the same file.
        if (str_contains($compact, 'url(')) {
            return preg_match_all('/url\(\s*["\']?(?!#)/', $compact) === 0;
        }

        return true;
    }

    private static function isAllowedReference(string $reference): bool
    {
        return str_starts_with($reference, '#')
            || preg_match('#^data:image/(png|jpe?g|gif|webp);#', $reference) === 1;
    }
}
