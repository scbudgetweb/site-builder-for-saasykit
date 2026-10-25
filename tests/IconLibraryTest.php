<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Support\IconLibrary;

class IconLibraryTest extends TestCase
{
    public function test_icons_render_inline_in_the_text_colour(): void
    {
        $svg = IconLibrary::render('bx:regular/bx-calendar', 'h-4 w-4')->toHtml();

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true" focusable="false">', $svg);
    }

    public function test_only_files_from_the_icon_folder_can_be_output(): void
    {
        foreach ([null, '', 'bx:regular/bx-no-such-icon', 'bx:regular/../../../composer', 'bx:other/bx-calendar', '<svg onload=x>'] as $value) {
            $this->assertSame('', IconLibrary::render($value)->toHtml());
        }

        $this->assertTrue(IconLibrary::isValid(null));
        $this->assertTrue(IconLibrary::isValid('bx:solid/bxs-star'));
        $this->assertFalse(IconLibrary::isValid('bx:regular/../secrets'));
    }

    public function test_search_understands_everyday_words(): void
    {
        $this->assertArrayHasKey('bx:regular/bx-pound', IconLibrary::search('money'));
        $this->assertArrayHasKey('bx:regular/bx-calendar-check', IconLibrary::search('booking'));
        $this->assertArrayHasKey('bx:regular/bx-trophy', IconLibrary::search('trop'));
        $this->assertArrayHasKey('bx:solid/bxs-star', IconLibrary::search('star filled'));
        $this->assertArrayNotHasKey('bx:regular/bx-card', IconLibrary::search('travel'), 'Synonyms match whole words, so "car" doesn\'t find card.');
    }

    public function test_the_picker_starts_with_popular_icons_and_a_hint(): void
    {
        $options = IconLibrary::initialOptions();

        $this->assertSame(IconLibrary::SEARCH_HINT, array_key_first($options));
        $this->assertCount(count(IconLibrary::POPULAR) + 1, $options, 'Every popular icon exists.');
        $this->assertGreaterThan(1500, count(IconLibrary::all()));
    }
}
