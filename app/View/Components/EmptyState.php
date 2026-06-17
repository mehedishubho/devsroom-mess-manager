<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class EmptyState extends Component
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $icon = null,
        public ?string $actionLabel = null,
        public ?string $actionRoute = null,
    ) {}

    public function render(): View
    {
        return view('components.empty-state');
    }
}
