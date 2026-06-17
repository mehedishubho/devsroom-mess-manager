<?php

namespace App\View\Components;

use App\Models\Member;
use Illuminate\View\Component;
use Illuminate\View\View;

class MemberCard extends Component
{
    public function __construct(
        public Member $member,
        public bool $showStatus = true,
        public string $size = 'md',
    ) {}

    public function render(): View
    {
        return view('components.member-card');
    }
}
