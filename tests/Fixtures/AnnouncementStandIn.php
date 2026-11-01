<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures;

use Livewire\Component;

/**
 * A stand-in for SaaSykit's announcement bar (<livewire:announcement.view />), so the theme's header can be drawn.
 */
class AnnouncementStandIn extends Component
{
    public function render(): string
    {
        return '<div data-announcement></div>';
    }
}
