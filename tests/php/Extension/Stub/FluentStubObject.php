<?php

namespace TractorCow\Fluent\Tests\Extension\Stub;

use SilverStripe\Core\Extensible;
use SilverStripe\Dev\TestOnly;

class FluentStubObject implements TestOnly
{
    use Extensible;

    public function __construct()
    {
        $this->constructExtensions();
    }
}
