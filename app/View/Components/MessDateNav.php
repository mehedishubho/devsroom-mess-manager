<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class MessDateNav extends Component
{
    public function __construct(
        public string $date,
        public string $route = 'mess.meals.index',
    ) {}

    public function render(): View
    {
        return view('components.mess-date-nav');
    }
}
