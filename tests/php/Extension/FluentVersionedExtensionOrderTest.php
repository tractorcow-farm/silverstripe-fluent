<?php

namespace TractorCow\Fluent\Tests\Extension;

use LogicException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Tests\Extension\Stub\FluentDataObject;
use TractorCow\Fluent\Tests\Extension\Stub\FluentMisorderedDataObject;

/**
 * FluentVersionedExtension answers some methods of Versioned itself, such as isLiveVersion() (see
 * LocalisedVersionPredicatesTest). When two extensions define a method, the one that was added last answers, so this
 * only works when FluentVersionedExtension is added after Versioned. The database build makes sure that it is,
 * instead of quietly going back to the answers of Versioned.
 */
class FluentVersionedExtensionOrderTest extends SapphireTest
{
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        FluentDataObject::class,
    ];

    protected static $required_extensions = [
        FluentDataObject::class => [
            Versioned::class,
            FluentVersionedExtension::class,
        ],
        // Not an extra data object: it has no table, building it is what the test does
        FluentMisorderedDataObject::class => [
            FluentVersionedExtension::class,
            Versioned::class,
        ],
    ];

    public function testAddedAfterVersionedIsBuilt(): void
    {
        $this->requireTable(FluentDataObject::class);

        // Nothing was thrown, and the test below does not fail for some other reason
        $this->addToAssertionCount(1);
    }

    public function testAddedBeforeVersionedIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('FluentVersionedExtension must be added AFTER Versioned extension');

        $this->requireTable(FluentMisorderedDataObject::class);
    }

    /**
     * Build the table of a data object the way dev/build does
     *
     * @param string $class
     */
    private function requireTable(string $class): void
    {
        DB::get_schema()->schemaUpdate(function () use ($class) {
            singleton($class)->requireTable();
        });
    }
}
