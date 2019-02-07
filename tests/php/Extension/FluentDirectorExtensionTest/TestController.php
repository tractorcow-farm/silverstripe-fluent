<?php

namespace TractorCow\Fluent\Tests\Extension\FluentDirectorExtensionTest;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class TestController extends Controller implements TestOnly
{
    private static $url_segment = 'TestController';

    public function index()
    {
        return 'Test Controller! ' . FluentState::singleton()->getLocale();
    }

    public function Link($action = null)
    {
        return Controller::join_links('nouvelle-zélande', parent::Link($action));
    }
}
