<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use Filament\Forms\Components\TextInput;

/**
 * A block's shared heading (PageBlock::headingFields(), drawn by the heading partial as .section-heading with its intro
 * as .block-text). A block with one gets the heading and text size settings without declaring them
 * (PageBlock::headingSettings(), hasBodyText()).
 */
class HeadingInput extends TextInput {}
