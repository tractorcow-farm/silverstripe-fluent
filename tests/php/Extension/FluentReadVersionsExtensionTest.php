<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentReadVersionsExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;
use ReflectionMethod;

class FluentReadVersionsExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    public function testUpdateListSetsCurrentLocaleIntoHavingInQuery()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');
            $list = SiteTree::get();

            $extension = new FluentReadVersionsExtension();
            $method = new ReflectionMethod(FluentReadVersionsExtension::class, 'updateList');
            $method->setAccessible(true);
            // Note this MUST be passed by reference, it cannot be changed not be passed by reference
            // in the extension method as the reference to the list is updated in the method
            $method->invokeArgs($extension, [&$list]);

            $this->assertContains(
                ['"SourceLocale" = ?' => ['en_NZ']],
                $list->dataQuery()->getFinalisedQuery()->getHaving(),
                'Fluent adds the current locale to the underlying list\'s data query'
            );
        });
    }
}
