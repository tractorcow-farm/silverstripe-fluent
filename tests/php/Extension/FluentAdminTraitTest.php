<?php

namespace TractorCow\Fluent\Tests\php\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedAnother;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedChild;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedParent;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\MixedLocalisedSortObject;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\UnlocalisedChild;
use TractorCow\Fluent\Tests\php\Extension\FluentAdminTraitTest\AdminHandler;
use TractorCow\Fluent\Tests\php\Extension\FluentAdminTraitTest\GridObjectVersioned;

class FluentAdminTraitTest extends SapphireTest
{
    protected static $fixture_file = [
        'FluentAdminTraitTest.yml',
        'FluentExtensionTest.yml',
    ];

    protected static $extra_dataobjects = [
        // Versioned
        GridObjectVersioned::class,
        // Non-versioned
        LocalisedAnother::class,
        LocalisedChild::class,
        LocalisedParent::class,
        MixedLocalisedSortObject::class,
        UnlocalisedChild::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->reset();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->reset();
    }

    protected function reset()
    {
        Locale::clearCached();
        Versioned::set_stage(Versioned::DRAFT);
        FluentVersionedExtension::reset();
        Versioned::reset();
    }

    // Versioned tests

    public function testClearFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var GridObjectVersioned $object */
            $object = $this->objFromFixture(GridObjectVersioned::class, 'record_a');

            // In 2 locales before
            $this->assertTrue($object->existsInLocale('en_US'));
            $this->assertTrue($object->existsInLocale('de_DE'));
            $this->assertFalse($object->existsInLocale('es_ES'));

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->clearFluent([], $form);
            $this->assertEquals('All localisations have been cleared for \'A record\'.', $message);

            // In 1 locale after (only current)
            $this->assertTrue($object->existsInLocale('en_US'));
            $this->assertFalse($object->existsInLocale('de_DE'));
            $this->assertFalse($object->existsInLocale('es_ES'));
        });
    }

    public function testCopyFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var GridObjectVersioned $object */
            $object = $this->objFromFixture(GridObjectVersioned::class, 'record_a');

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->copyFluent([], $form);
            $this->assertEquals('Copied \'A record\' to all other locales.', $message);

            // Check values in each locale now match en_US version
            $data = DB::prepared_query(
                <<<'SQL'
SELECT "Locale", "Description"
FROM "FluentTest_GridObjectVersioned_Localised"
WHERE "RecordID" = ?
ORDER BY "Locale"
SQL
                ,
                [$object->ID]
            )->map();
            $this->assertEquals([
                'de_DE' => 'Not very interesting',
                'en_US' => 'Not very interesting',
                'es_ES' => 'Not very interesting',
            ], $data);
        });
    }

    public function testUnpublishFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var GridObjectVersioned $object */
            $object = $this->objFromFixture(GridObjectVersioned::class, 'record_a');
            $this->assertTrue($object->isPublished());

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->unpublishFluent([], $form);
            $this->assertEquals("Unpublished 'A record' from all locales.", $message);

            $this->assertFalse($object->isPublished());
            $this->assertFalse($object->isPublishedInLocale('de_DE'));
            $this->assertFalse($object->isPublishedInLocale('en_US'));
            $this->assertFalse($object->isPublishedInLocale('es_ES'));
        });
    }

    public function testArchiveFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var GridObjectVersioned $object */
            $object = $this->objFromFixture(GridObjectVersioned::class, 'record_a');
            $objectID = $object->ID;

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->archiveFluent([], $form);
            $this->assertEquals("Archived 'A record' and all of its localisations.", $message);

            // Empty tables
            $localisations = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentTest_GridObjectVersioned_Localised" WHERE "RecordID" = ?',
                [$objectID]
            )->value();
            $this->assertEquals(0, $localisations);
            $liveLocalisations = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentTest_GridObjectVersioned_Localised_Live" WHERE "RecordID" = ?',
                [$objectID]
            )->value();
            $this->assertEquals(0, $liveLocalisations);
            $published = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentTest_GridObjectVersioned_Live" WHERE "ID" = ?',
                [$objectID]
            )->value();
            $this->assertEquals(0, $published);
            $records = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentTest_GridObjectVersioned" WHERE "ID" = ?',
                [$objectID]
            )->value();
            $this->assertEquals(0, $records);
        });
    }

    public function testPublishFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var GridObjectVersioned $object */
            $object = $this->objFromFixture(GridObjectVersioned::class, 'record_a');

            $this->assertTrue($object->isPublishedInLocale('de_DE'));
            $this->assertFalse($object->isPublishedInLocale('en_US'));
            $this->assertFalse($object->isPublishedInLocale('es_ES'));

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->publishFluent([], $form);
            $this->assertEquals("Published 'A record' across all locales.", $message);

            $this->assertTrue($object->isPublished());
            $this->assertTrue($object->isPublishedInLocale('de_DE'));
            $this->assertTrue($object->isPublishedInLocale('en_US'));
            $this->assertTrue($object->isPublishedInLocale('es_ES'));
        });
    }

    // Unversioned tests

    public function testDeleteFluent()
    {
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            /** @var LocalisedParent $object */
            $object = $this->objFromFixture(LocalisedParent::class, 'record_a');

            /** @var Form $form */
            $form = Form::create();
            $form->loadDataFrom($object);
            $message = AdminHandler::singleton()->deleteFluent([], $form);
            $this->assertEquals("Deleted 'A record' and all of its localisations.", $message);

            // Empty tables
            $localisations = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentExtensionTest_LocalisedParent_Localised" WHERE "RecordID" = ?',
                [$object->ID]
            )->value();
            $this->assertEquals(0, $localisations);
            $records = DB::prepared_query(
                'SELECT COUNT(*) FROM "FluentExtensionTest_LocalisedParent" WHERE "ID" = ?',
                [$object->ID]
            )->value();
            $this->assertEquals(0, $records);
        });
    }
}
