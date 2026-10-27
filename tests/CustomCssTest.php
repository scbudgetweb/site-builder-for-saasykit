<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Support\CustomCss;

class CustomCssTest extends TestCase
{
    public function test_ordinary_css_is_allowed(): void
    {
        $css = '.promo { color: #123456; background: url(/images/bg.png); } .x { background-image: url("data:image/png;base64,AAAA"); }';

        $this->assertNull(CustomCss::problem($css));
        $this->assertSame($css, CustomCss::safe($css));
    }

    public function test_comments_strings_fragments_and_image_sets_on_this_site_are_allowed(): void
    {
        foreach ([
            '.a { content: "/* not a comment */"; color: red; }',
            '/* a comment with url(https://example.com) in it */ .a { color: red; }',
            '.a { background-image: image-set("/a.png" 1x, "/a@2x.png" 2x); }',
            '.a { background-image: -webkit-image-set(url(/a.png) 1x, url("/a@2x.png") 2x); }',
            '.a { background-image: image-set("/a.avif" type("image/avif"), "/a.png" type("image/png")); }',
            '.a { fill: url(#gradient); }',
        ] as $css) {
            $this->assertNull(CustomCss::problem($css), $css);
        }
    }

    public function test_unsafe_css_is_rejected_and_not_output(): void
    {
        foreach ([
            '</style><script>alert(1)</script>',
            '@import url(/x.css);',
            '.a { background: url(https://evil.example/track.png) }',
            '.a { background: url(//evil.example/x.png) }',
            '.a { width: expression(alert(1)) }',
            '.a { background: u\72l(https://evil.example/x.png) }',
            '.a { -moz-binding: url(/x.xml) }',
            str_repeat('a', CustomCss::MAX_BYTES + 1),
        ] as $css) {
            $this->assertNotNull(CustomCss::problem($css), $css);
            $this->assertSame('', CustomCss::safe($css));
        }
    }

    public function test_remote_files_cant_be_hidden_the_ways_a_regex_would_miss(): void
    {
        foreach ([
            // A url() between a "/*" and a "*/" that are inside strings, so the browser keeps it.
            '.a{content:"/*"} .b{background:url(https://evil.example/x.png)} .c{content:"*/"}',
            // A url( that's never closed runs to the end of the CSS.
            '.a{background:url(https://evil.example/x.png',
            '.a{background:url("https://evil.example/x.png',
            // Every image-set() candidate counts, not just the first.
            '.a{background-image:image-set("/ok.png" type("image/x-none"), "https://evil.example/x.png" 1x)}',
            '.a{background-image:-webkit-image-set("/ok.png" 1x, "//evil.example/x.png" 2x)}',
            // src() in @font-face.
            '@font-face{font-family:x;src:src("https://evil.example/font.woff2")}',
        ] as $css) {
            $this->assertNotNull(CustomCss::problem($css), $css);
            $this->assertSame('', CustomCss::safe($css), $css);
        }
    }

    public function test_css_that_cant_be_checked_is_rejected(): void
    {
        $jit = ini_get('pcre.jit');
        $backtrackLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '0');

        try {
            $css = '.a { content: "\75"; } @import url(https://evil.example/x.css);';

            $this->assertSame(__('Custom CSS couldn\'t be checked, so it wasn\'t saved. Try simplifying it.'), CustomCss::problem($css));
            $this->assertSame('', CustomCss::safe($css));
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
        }
    }

    public function test_adversarial_css_is_checked_quickly(): void
    {
        $started = microtime(true);

        foreach ([
            str_repeat('/*a', 17000),
            str_repeat('url(', 12000).')',
            str_repeat('image-set(', 5000),
            str_repeat('"/*', 17000),
        ] as $css) {
            CustomCss::problem(substr($css, 0, CustomCss::MAX_BYTES));
        }

        $this->assertLessThan(2.0, microtime(true) - $started);
    }

    public function test_mistakes_are_listed_by_line_but_dont_stop_the_css_being_saved(): void
    {
        $css = ".testing a {\n  colour: #fff;}\n}";

        $this->assertNull(CustomCss::problem($css));
        $this->assertSame([
            'Line 2: "colour" isn\'t a CSS property, so the browser ignores it.',
            'Line 3: there\'s a "}" with no matching "{" before it.',
        ], CustomCss::warnings($css));
    }

    public function test_valid_css_gives_no_warnings(): void
    {
        $css = <<<'CSS'
            /* Brand tweaks */
            @import-free { }
            :root { --brand: #0a3d2e; }
            .promo { color: var(--brand); background: url(data:image/png;base64,AAAA) no-repeat; -webkit-line-clamp: 2; }
            .promo:hover, .promo:focus-visible { text-decoration: underline; }
            .card { container-type: inline-size; & .title { inset: 0; } }
            @media (min-width: 40rem) { .promo { grid-template-columns: repeat(2, 1fr) } }
            @font-face { font-family: "Hall"; src: url("/fonts/hall.woff2") format("woff2"); font-display: swap; }
            @keyframes fade { from { opacity: 0 } 50% { opacity: .5 } to { opacity: 1 } }
            .a::before { content: "{ ; :"; }
            CSS;

        $this->assertSame([], CustomCss::warnings($css));
    }

    public function test_unclosed_brackets_strings_and_comments_and_stray_declarations_are_warned_about(): void
    {
        $this->assertSame(['A "{" is never closed.'], CustomCss::warnings('.a { color: red;'));
        // The unclosed string swallows the "}", so the "{" is left open too, as a browser would read it.
        $this->assertSame(['Line 1: a string (text in quotes) isn\'t closed.', 'A "{" is never closed.'], CustomCss::warnings(".a { content: \"hi; }\n.b { color: red }"));
        $this->assertSame(['Line 2: a comment (/* … */) isn\'t closed, so everything after it is ignored.'], CustomCss::warnings(".a { color: red }\n/* note"));
        $this->assertSame(['Line 1: "color: red" isn\'t inside a rule ({ }), so it does nothing.'], CustomCss::warnings('color: red; .a { color: blue }'));
        $this->assertCount(CustomCss::MAX_WARNINGS, CustomCss::warnings(str_repeat('.a { colr: red } ', 20)));
    }
}
