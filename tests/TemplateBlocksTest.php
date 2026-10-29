<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteAccountLinksBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteCopyrightBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteFooterLinksBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteLogoBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteMenuBlock;

/**
 * The Theme Builder's own blocks: Logo, Menu, Account links, Footer links and Copyright. Offered only in templates,
 * and the ones that depend on the page or visitor (Menu, Account links) aren't cached.
 */
class TemplateBlocksTest extends PageTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/privacy-policy', fn () => 'Privacy')->name('privacy-policy');
        $router->get('/terms-of-service', fn () => 'Terms')->name('terms-of-service');
    }

    private const TEMPLATE_BLOCKS = ['site_logo', 'site_menu', 'site_account_links', 'site_footer_links', 'site_copyright'];

    public function test_template_blocks_are_offered_only_in_templates_they_suit(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertSame([], array_intersect(self::TEMPLATE_BLOCKS, array_keys($registry->offered())), 'Never on a page.');

        $registry->editingTemplate('header');
        $this->assertSame(['site_logo', 'site_menu', 'site_account_links', 'site_copyright'], array_values(array_intersect(self::TEMPLATE_BLOCKS, array_keys($registry->offered()))));
        $this->assertArrayHasKey('text', $registry->offered(), 'Ordinary blocks too.');

        $registry->editingTemplate('footer');
        $this->assertSame(self::TEMPLATE_BLOCKS, array_values(array_intersect(self::TEMPLATE_BLOCKS, array_keys($registry->offered()))));

        $this->assertFalse(SiteMenuBlock::isCacheable([]), 'The current page\'s link is marked.');
        $this->assertFalse(SiteAccountLinksBlock::isCacheable([]), 'It depends on who\'s looking.');
        $this->assertTrue(SiteLogoBlock::isCacheable([]));
        $this->assertTrue(SiteFooterLinksBlock::isCacheable([]));
        $this->assertTrue(SiteCopyrightBlock::isCacheable([]));
    }

    public function test_the_logo_links_home_at_its_height_and_line_up(): void
    {
        $this->page(['blocks' => [
            self::block('site_logo', ['logo' => 'footer', 'height' => 48, 'align' => 'center']),
            self::block('site_logo', ['height' => 9999, 'align' => 'diagonal']),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<div class="flex justify-center">\s*<a href="http:\/\/localhost"[^>]*aria-label="[^"]* home">\s*<span class="[^"]*" style="height: 48px; width: auto;">Example Site/', $html, 'The site\'s name when no logo is uploaded.');
        $this->assertStringContainsString('height: 200px; width: auto;', $html, 'Too tall is clamped.');
        $this->assertSame(1, substr_count($html, 'class="flex justify-center"'), 'An unknown line-up is left.');
    }

    public function test_the_menu_shows_the_navigation_and_a_mobile_menu_of_its_own(): void
    {
        $page = $this->page(['blocks' => [
            self::block('site_menu', ['align' => 'center', 'breakpoint' => 'md', 'link_color' => '#0a3d2e', 'mobile_background' => 'red; x: y']),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();
        $uid = $page->blocks[0]['uid'];

        $this->assertStringContainsString('style="--color-sb-header-link: #0a3d2e;"', $html);
        $this->assertStringNotContainsString('red; x: y', $html, 'Only hex colours.');
        $this->assertMatchesRegularExpression('/<nav class="hidden items-center gap-8 md:flex" aria-label="Main">.*Pricing.*Blog/s', $html);
        $this->assertStringContainsString('aria-controls="site-menu-'.$uid.'"', $html);
        $this->assertStringContainsString('id="site-menu-'.$uid.'"', $html);
        $this->assertMatchesRegularExpression('/id="site-menu-'.$uid.'"[^>]*class="[^"]*md:hidden"/s', $html);
        $this->assertStringNotContainsString('id="site-mobile-menu"', $html, 'Not the standard header\'s.');
    }

    public function test_account_links_for_visitors_and_their_settings(): void
    {
        $this->page(['blocks' => [
            self::block('site_account_links', ['login_label' => 'Sign in', 'button_label' => 'Book now', 'button_url' => 'javascript:alert(1)']),
            self::block('site_account_links', ['show_button' => false, 'align' => 'left']),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertStringContainsString('>Sign in</a>', $html);
        $this->assertMatchesRegularExpression('/<a href="http:\/\/localhost#pricing"[^>]*>Book now<\/a>/', $html, 'An unsafe link falls back to the pricing.');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(1, substr_count($html, '>Book now</a>'));
        $this->assertStringNotContainsString('Get started', $html, 'The second shows no button.');
        $this->assertStringContainsString('>Log in</a>', $html);
    }

    public function test_footer_links_and_copyright_come_from_the_navigation(): void
    {
        $this->page(['blocks' => [
            self::block('site_footer_links', ['columns' => 3]),
            self::block('site_copyright', ['align' => 'center']),
            self::block('site_copyright', ['source' => 'own', 'text' => '© :year Village Hall <b>bold</b>', 'align' => 'right']),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<div class="grid grid-cols-1 gap-8 sm:grid-cols-3">.*Blog.*Privacy Policy.*Terms of Service/s', $html);
        $this->assertStringContainsString('© '.date('Y').' Example Site. All rights reserved.', $html);
        $this->assertStringContainsString('© '.date('Y').' Village Hall &lt;b&gt;bold&lt;/b&gt;', $html, 'Its own text is escaped.');
        $this->assertMatchesRegularExpression('/<p class="text-xs block-text text-right">/', $html);
    }
}
