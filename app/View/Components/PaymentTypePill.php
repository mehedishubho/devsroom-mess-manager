<?php

namespace App\View\Components;

use App\Support\PaymentType;
use Illuminate\View\Component;
use Illuminate\View\View;

class PaymentTypePill extends Component
{
    public function __construct(public string $type) {}

    public function render(): View
    {
        $color = $this->type === PaymentType::ADVANCE_DEPOSIT ? 'sky' : 'slate';

        return view('components.payment-type-pill', ['color' => $color]);
    }
}
