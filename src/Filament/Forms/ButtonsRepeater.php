<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use Filament\Forms\Components\Repeater;

/**
 * A block's buttons (PageBlock::buttonsRepeater()). A block with one anywhere in its settings gets Design › Buttons in
 * its panel without declaring it (PageBlock::hasButtons()), however many buttons it holds.
 */
class ButtonsRepeater extends Repeater {}
