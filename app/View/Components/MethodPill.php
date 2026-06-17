<?php

namespace App\View\Components;

use App\Support\PaymentMethod;
use Illuminate\View\Component;
use Illuminate\View\View;

class MethodPill extends Component
{
    public function __construct(public string $method) {}

    public function render(): View
    {
        $color = PaymentMethod::COLORS[$this->method] ?? 'slate';

        return view('components.method-pill', ['color' => $color]);
    }
}
