<div class="block-text">{!! \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::html($block['content'] ?? '') !!}</div>
@include('site-builder::blocks.partials.buttons', ['buttons' => \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::validButtons($block['buttons'] ?? [])])
