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
}
