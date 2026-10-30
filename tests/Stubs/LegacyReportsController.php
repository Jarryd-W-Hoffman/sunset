<?php

namespace Jarryd\Sunset\Tests\Stubs;

class LegacyReportsController
{
    /**
     * @note [deprecated] Use reports.index, which pages its results
     * @note Owned by the finance team
     */
    public function index(): string
    {
        return 'Legacy';
    }

    public function show(): string
    {
        return 'Legacy report';
    }
}
