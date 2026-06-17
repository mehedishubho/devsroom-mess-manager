<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class TabNav extends Component
{
    public function __construct(
        public array $tabs,
        public string $activeKey = '',
    ) {}

    public function render(): View
    {
        return view('components.tab-nav');
    }
}
