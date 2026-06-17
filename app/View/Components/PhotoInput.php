<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PhotoInput extends Component
{
    public function __construct(
        public string $name = 'photo',
        public ?string $currentPath = null,
        public ?string $currentUrl = null,
        public int $size = 96,
        public bool $capture = true,
    ) {}

    public function render(): View
    {
        return view('components.photo-input');
    }
}
