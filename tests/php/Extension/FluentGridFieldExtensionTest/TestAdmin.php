<?php

namespace TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Dev\TestOnly;

class TestAdmin extends ModelAdmin implements TestOnly
{
    private static $url_segment = 'fluent-gridfield-test';

    private static $managed_models = [
        ParentRecord::class,
        ChildRecord::class,
    ];
}
