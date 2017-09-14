<?php

namespace TractorCow\Fluent\Tests\State;

use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\View\FluentTemplateGlobalProvider;

class FluentStateTest extends SapphireTest
{
    public function testWithState()
    {
        $original = new FluentState;

        $original->withState(function ($newState) use ($original) {
            $this->assertInstanceOf(FluentState::class, $newState);
            $this->assertNotSame($original, $newState);

            // Tests that the new state is injected
            $newState->setLocale('foo');
            $this->assertSame('foo', FluentTemplateGlobalProvider::getCurrentLocale());
        });

        // Tests that the original state is restored
        $this->assertSame($original, FluentState::singleton());
    }
}
