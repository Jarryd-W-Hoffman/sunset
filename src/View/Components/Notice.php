<?php

namespace Jarryd\Sunset\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\SunsetNotice;

/**
 * <x-sunset::notice />: a banner on sunset pages, and nothing anywhere else.
 */
class Notice extends Component
{
    public ?SunsetNotice $notice;

    public function __construct()
    {
        $this->notice = Sunset::current();
    }

    public function shouldRender(): bool
    {
        return $this->notice !== null;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = config('sunset.notice_view') ?: 'sunset::notice';

        return view($view);
    }
}
