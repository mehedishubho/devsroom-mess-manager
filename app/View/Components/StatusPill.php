<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StatusPill extends Component
{
    public function __construct(
        public string $variant,
        public ?string $label = null,
    ) {}

    public function render(): View
    {
        return view('components.status-pill');
    }
}
