<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentDataObject;

class FluentBadgeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentBadgeExtensionTest.yml';

    protected static $extra_dataobjects = [
        FluentDataObject::class,
    ];

    protected static $required_extensions = [
        FluentDataObject::class => [
            FluentExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');
            $record = $this->objFromFixture(FluentDataObject::class, 'test_record');
            // Ensure the record is written in this locale
            $record->write();
        });
    }

    /**
     * Tests status flag added for the locale this is saved in
     */
    public function testDefaultLocaleBadgeAdded()
    {
        // Publish the page in the default locale
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');
            $record = $this->objFromFixture(FluentDataObject::class, 'test_record');
            $flags = $record->getStatusFlags();

            $this->assertArrayHasKey('fluent fluent-badge fluent-badge--default', $flags);
            $this->assertSame(
                ['title' => 'Localised in English (NZ)', 'text' => 'en_NZ'],
                $flags['fluent fluent-badge fluent-badge--default']
            );
        });
    }

    /**
     * Tests status flag added for the fallback locale
     */
    public function testLocalisedLocaleBadgeAdded()
    {
        // Publish the page in the default locale
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('pt_PT');
            $record = $this->objFromFixture(FluentDataObject::class, 'test_record');
            $flags = $record->getStatusFlags();

            $this->assertArrayHasKey('fluent fluent-badge fluent-badge--localised', $flags);
            $this->assertSame(
                ['title' => 'Localised in English (NZ)', 'text' => 'en_NZ'],
                $flags['fluent fluent-badge fluent-badge--localised']
            );
        });
    }

    /**
     * Tests status flag added to indicate this record is NOT saved in this locale
     */
    public function testInvisibleLocaleBadgeWasAdded()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            // Don't write the page in the non-default locale, then it shouldn't exist
            $newState->setLocale('de_DE');
            $record = $this->objFromFixture(FluentDataObject::class, 'test_record');
            $flags = $record->getStatusFlags();

            $this->assertArrayHasKey('fluent fluent-badge fluent-badge--invisible', $flags);
            $this->assertSame(
                [
                    'title' => 'Fluent Data Object has no available content in German, localise the Fluent Data Object or provide a locale fallback',
                    'text' => 'de_DE',
                ],
                $flags['fluent fluent-badge fluent-badge--invisible']
            );
        });
    }
}
