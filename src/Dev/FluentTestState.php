<?php
namespace TractorCow\Fluent\Dev;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use TractorCow\Fluent\Model\Locale;

class FluentTestState implements TestState
{
    /**
     * Called on setup
     *
     * @param SapphireTest $test
     */
    public function setUp(SapphireTest $test)
    {
        // Clear locale static caching between tests.
        Locale::clearCached();
    }

    /**
     * Called on tear down
     *
     * @param SapphireTest $test
     */
    public function tearDown(SapphireTest $test)
    {
    }

    /**
     * Called once on setup
     *
     * @param string $class Class being setup
     */
    public function setUpOnce($class)
    {
    }

    /**
     * Called once on tear down
     *
     * @param string $class Class being torn down
     */
    public function tearDownOnce($class)
    {
    }
}
