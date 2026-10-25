<?php

namespace ScBudgetWeb\SiteBuilder\Support;

/**
 * Checks the admin-written CSS from Admin › Website › Branding › Custom CSS before it goes into the public
 * <head>. Admins are trusted to style the site, not to run code or load things from elsewhere, so this
 * rejects anything that could:
 *
 *  - break out of the <style> tag (any "<", which valid CSS never needs);
 *  - load files from other sites (@import, url() to another host), which would leak visitors' data or what
 *    they type (CSS "keyloggers" use attribute selectors with remote background images);
 *  - run script through old browser features (expression(), behavior, -moz-binding, javascript: URLs).
 *
 * CSS escapes (\75 for "u") and comments are removed before checking, so the rules can't be dodged by
 * writing "u\72l(" or "ur/ * * /l(". url() may point at this site ("/images/…") or an inline data:image.
 */
class CustomCss
{
    public const MAX_BYTES = 51200;

    /**
     * Why the CSS can't be used, or null when it's safe.
     */
    public static function problem(?string $css): ?string
    {
        if ($css === null || trim($css) === '') {
            return null;
        }

        if (strlen($css) > self::MAX_BYTES) {
            return __('Custom CSS can be up to :size KB.', ['size' => self::MAX_BYTES / 1024]);
        }

        if (str_contains($css, '<')) {
            return __('Custom CSS can\'t contain "<". It isn\'t needed in CSS, and could be used to add HTML or scripts to the page.');
        }

        $normalised = self::normalise($css);

        foreach ([
            '@import' => __('@import isn\'t allowed. Paste the CSS itself instead.'),
            'expression(' => __('expression() isn\'t allowed.'),
            'behavior' => __('behavior isn\'t allowed.'),
            '-moz-binding' => __('-moz-binding isn\'t allowed.'),
            'javascript:' => __('javascript: isn\'t allowed.'),
            'vbscript:' => __('vbscript: isn\'t allowed.'),
        ] as $needle => $message) {
            if (str_contains($normalised, $needle)) {
                return $message;
            }
        }

        // Every url(…) and image-set("…") target must be on this site or an inline image.
        preg_match_all('/url\(\s*([^)]*)\)|image-set\(\s*["\']([^"\']*)["\']/', $normalised, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $target = trim($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            $target = trim($target, " \t\n\r\"'");

            if (! self::isAllowedUrl($target)) {
                return __('url() can only point to a file on this site (starting with /) or an inline data:image. ":url" isn\'t allowed.', ['url' => mb_strimwidth($target, 0, 60, '…')]);
            }
        }

        return null;
    }

    /**
     * The CSS to output, or '' when it fails the checks (e.g. a value changed directly in the database).
     */
    public static function safe(?string $css): string
    {
        return $css !== null && trim($css) !== '' && self::problem($css) === null ? trim($css) : '';
    }

    private static function isAllowedUrl(string $url): bool
    {
        // A path on this site, but not a protocol-relative //other-host URL (or its \\ / /\ variants).
        if (preg_match('#^/(?![/\\\\])#', $url) === 1) {
            return true;
        }

        return preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml)[;,]#', $url) === 1;
    }

    /**
     * Lower-cased CSS with comments removed and escapes decoded, so obfuscated keywords are caught.
     */
    private static function normalise(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';

        // \75 or \000075 (hex code point, optionally followed by one space), then \x (any other escaped character).
        $css = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', fn (array $match): string => mb_chr((int) hexdec($match[1])) ?: '', $css) ?? '';
        $css = preg_replace('/\\\\(.)/s', '$1', $css) ?? '';

        // Whitespace inside a keyword (e.g. "url (" or "java\tscript:") doesn't hide it either.
        return strtolower(preg_replace('/\s+/', '', $css) ?? '');
    }
}
