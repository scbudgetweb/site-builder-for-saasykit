<?php

namespace ScBudgetWeb\SiteBuilder\Support;

/**
 * Checks the admin-written CSS from Admin › Website › Branding › Custom CSS before it goes into the public
 * <head>. Admins are trusted to style the site, not to run code or load things from elsewhere, so this
 * rejects anything that could:
 *
 *  - break out of the <style> tag (any "<", which valid CSS never needs);
 *  - load files from other sites (@import, url(), src() or image-set() pointing at another host), which would
 *    leak visitors' data or what they type (CSS "keyloggers" use attribute selectors with remote images);
 *  - run script through old browser features (expression(), behavior, -moz-binding, javascript: URLs).
 *
 * The CSS is read the way a browser reads it: comments are removed (but not "/*" inside a quoted string), CSS
 * escapes (\75 for "u") are decoded, and every url( or src( target is checked, even one never closed, as is every
 * candidate in image-set(). url() may point at this site ("/images/…"), an inline data:image or a #fragment.
 * Anything the check can't read is rejected (it fails closed).
 */
class CustomCss
{
    public const MAX_BYTES = 51200;

    public const MAX_WARNINGS = 10;

    /** @var array<string, int>|null */
    private static ?array $knownProperties = null;

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

        if ($normalised === null) {
            return __('Custom CSS couldn\'t be checked, so it wasn\'t saved. Try simplifying it.');
        }

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

        foreach (self::urlTargets($normalised) as $target) {
            if (! self::isAllowedUrl($target)) {
                return __('url() can only point to a file on this site (starting with /) or an inline data:image. ":url" isn\'t allowed.', ['url' => mb_strimwidth($target, 0, 60, '…')]);
            }
        }

        return null;
    }

    /**
     * Mistakes that don't make the CSS unsafe but may stop it working, each naming its line: a bracket with no partner
     * (an extra "}" can make the browser skip the rules after it), a string or comment that's never closed, a
     * declaration outside any { }, and a property CSS doesn't have (e.g. "colour"). The CSS is still saved; these are
     * shown as a warning. Custom properties (--name) and vendor-prefixed ones (-webkit-…) are always accepted.
     *
     * @return array<int, string> at most MAX_WARNINGS
     */
    public static function warnings(?string $css): array
    {
        if ($css === null || trim($css) === '') {
            return [];
        }

        $warnings = [];
        $length = strlen($css);
        $line = 1;
        $brackets = [];
        $statement = '';
        $statementLine = 1;
        $quote = null;
        $quoteLine = 1;
        $partners = ['}' => '{', ')' => '(', ']' => '['];

        for ($index = 0; $index < $length && count($warnings) < self::MAX_WARNINGS; $index++) {
            $character = $css[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                } elseif ($character === "\n") {
                    $warnings[] = __('Line :line: a string (text in quotes) isn\'t closed.', ['line' => $quoteLine]);
                    $quote = null;
                }

                $line += $character === "\n" ? 1 : 0;

                continue;
            }

            if ($character === '/' && ($css[$index + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $index + 2);

                if ($end === false) {
                    $warnings[] = __('Line :line: a comment (/* … */) isn\'t closed, so everything after it is ignored.', ['line' => $line]);

                    break;
                }

                $line += substr_count($css, "\n", $index, $end - $index);
                $index = $end + 1;

                continue;
            }

            if (trim($statement) === '') {
                $statementLine = $line;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $quoteLine = $line;
                $statement .= '""';

                continue;
            }

            $line += $character === "\n" ? 1 : 0;
            $insideBrackets = in_array('(', $brackets, true) || in_array('[', $brackets, true);

            if (in_array($character, ['{', '(', '['], true)) {
                $brackets[] = $character;
                $statement = $character === '{' ? '' : $statement.$character;

                continue;
            }

            if (isset($partners[$character])) {
                if (end($brackets) === $partners[$character]) {
                    array_pop($brackets);
                } else {
                    $warnings[] = __('Line :line: there\'s a ":bracket" with no matching ":partner" before it.', ['line' => $line, 'bracket' => $character, 'partner' => $partners[$character]]);
                }

                if ($character !== '}') {
                    $statement .= $character;

                    continue;
                }
            }

            if (($character === ';' && ! $insideBrackets) || $character === '}') {
                if (($warning = self::declarationWarning($statement, $statementLine, $character === '}' || in_array('{', $brackets, true))) !== null) {
                    $warnings[] = $warning;
                }

                $statement = '';

                continue;
            }

            $statement .= $character;
        }

        if ($quote !== null && count($warnings) < self::MAX_WARNINGS) {
            $warnings[] = __('Line :line: a string (text in quotes) isn\'t closed.', ['line' => $quoteLine]);
        }

        foreach (array_unique($brackets) as $bracket) {
            if (count($warnings) < self::MAX_WARNINGS) {
                $warnings[] = __('A ":bracket" is never closed.', ['bracket' => $bracket]);
            }
        }

        return $warnings;
    }

    /**
     * The warning for one statement that ended with ";" or "}", if it's a declaration with a problem.
     */
    private static function declarationWarning(string $statement, int $line, bool $isInsideRule): ?string
    {
        $statement = trim($statement);

        if ($statement === '' || str_starts_with($statement, '@') || ! str_contains($statement, ':')) {
            return null;
        }

        if (! $isInsideRule) {
            return __('Line :line: ":declaration" isn\'t inside a rule ({ }), so it does nothing.', ['line' => $line, 'declaration' => mb_strimwidth($statement, 0, 40, '…')]);
        }

        $property = strtolower(trim(strstr($statement, ':', true)));

        if (str_starts_with($property, '--') || preg_match('/^-(webkit|moz|ms|o)-[a-z-]+$/', $property) === 1) {
            return null;
        }

        self::$knownProperties ??= array_flip(require dirname(__DIR__, 2).'/resources/data/css-properties.php');

        return isset(self::$knownProperties[$property])
            ? null
            : __('Line :line: ":property" isn\'t a CSS property, so the browser ignores it.', ['line' => $line, 'property' => mb_strimwidth($property, 0, 40, '…')]);
    }

    /**
     * The CSS to output, or '' when it fails the checks (e.g. a value changed directly in the database).
     */
    public static function safe(?string $css): string
    {
        return $css !== null && trim($css) !== '' && self::problem($css) === null ? trim($css) : '';
    }

    /**
     * The start of every url( and src( target, read to its closing quote or bracket or, like a browser, to the end of
     * the CSS, and of every quoted candidate in image-set() (type("…") arguments aside). Only the start decides whether
     * a target is allowed, so only that much is kept, and the time taken grows in step with the CSS.
     *
     * @return array<int, string>
     */
    private static function urlTargets(string $css): array
    {
        $targets = [];
        $length = strlen($css);
        $start = fn (int $position, int $end): string => ltrim(substr($css, $position, min($end - $position, 200)), " \t\n\r\"'");

        foreach (['url(', 'src('] as $function) {
            for ($offset = 0; ($found = strpos($css, $function, $offset)) !== false; $offset = $found + 1) {
                $position = $found + strlen($function);
                $quote = $css[$position] ?? '';
                $end = $quote === '"' || $quote === "'" ? strpos($css, $quote, $position + 1) : strpos($css, ')', $position);

                $targets[] = $start($position, $end === false ? $length : $end);

                // Never closed: it runs to the end, so anything after it is part of this target.
                if ($end === false) {
                    break;
                }
            }
        }

        for ($offset = 0; ($found = strpos($css, 'image-set(', $offset)) !== false; $offset = max($found + 1, $index)) {
            $depth = 1;

            // Up to the matching ")" (or the end). url( targets inside were checked above; type("…") isn't a file.
            for ($index = $found + strlen('image-set('); $index < $length && $depth > 0; $index++) {
                $character = $css[$index];

                if ($character === '"' || $character === "'") {
                    $end = strpos($css, $character, $index + 1);
                    $end = $end === false ? $length : $end;
                    $before = substr($css, max(0, $index - 5), min(5, $index));

                    if (! str_ends_with($before, 'type(') && ! str_ends_with($before, 'url(')) {
                        $targets[] = $start($index + 1, $end);
                    }

                    $index = $end;

                    continue;
                }

                $depth += match ($character) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
            }
        }

        return $targets;
    }

    private static function isAllowedUrl(string $url): bool
    {
        // A path on this site, but not a protocol-relative //other-host URL (or its \\ / /\ variants).
        if (preg_match('#^/(?![/\\\\])#', $url) === 1) {
            return true;
        }

        // A fragment points into the same document.
        if (str_starts_with($url, '#')) {
            return true;
        }

        return preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml)[;,]#', $url) === 1;
    }

    /**
     * Lower-cased CSS with comments removed, escapes decoded and whitespace dropped, so obfuscated keywords are caught,
     * or null when it can't be read (so the caller rejects it rather than letting it through).
     */
    private static function normalise(string $css): ?string
    {
        $css = self::withoutComments($css);

        // \75 or \000075 (hex code point, optionally followed by one space), then \x (any other escaped character).
        $css = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', fn (array $match): string => mb_chr((int) hexdec($match[1])) ?: '', $css);
        $css = $css === null ? null : preg_replace('/\\\\(.)/s', '$1', $css);

        // Whitespace inside a keyword (e.g. "url (" or "java\tscript:") doesn't hide it either.
        $css = $css === null ? null : preg_replace('/\s+/', '', $css);

        return $css === null ? null : strtolower($css);
    }

    /**
     * The CSS without its comments, read like a browser: "/*" inside a quoted string isn't a comment, and a comment
     * that's never closed runs to the end. One pass, so it takes the same time however the CSS is written.
     */
    private static function withoutComments(string $css): string
    {
        $result = '';
        $length = strlen($css);
        $quote = null;

        for ($index = 0; $index < $length; $index++) {
            $character = $css[$index];

            if ($quote !== null) {
                $result .= $character;

                if ($character === '\\' && $index + 1 < $length) {
                    $result .= $css[++$index];
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '/' && ($css[$index + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $index + 2);

                if ($end === false) {
                    break;
                }

                $index = $end + 1;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '\\' && $index + 1 < $length) {
                $result .= $character.$css[++$index];

                continue;
            }

            $result .= $character;
        }

        return $result;
    }
}
