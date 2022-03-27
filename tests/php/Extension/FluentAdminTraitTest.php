<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentAdminTraitTest\AdminHandler;
use TractorCow\Fluent\Tests\Extension\FluentAdminTraitTest\GridObjectVersioned;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedParent;

class FluentAdminTraitTest extends SapphireTest
{
    protected static $fixture_file = 'FluentAdminTraitTest.yml';

    protected static $extra_dataobjects = [
        // Versioned
        GridObjectVersioned::class,
        // Non-versioned
        LocalisedParent::class,
    ];

    /**
     * @var int
     */
    protected $versionedRecordId = 0;

    /**
     * @var int
     */
    protected $nonVersionedRecordId = 0;

    /**
     * @throws ValidationException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestModels();
        $this->reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->reset();
    }

    /**
     * Generate test models during runtime as writing the fixture is way too complicated
     *
     * @throws ValidationException
     */
    protected function setUpTestModels(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_US');

            // draft only object
            $object = GridObjectVersioned::create();
            $object->Title = 'A record';
            $object->Description = 'Not very interesting';
            $object->write();

            $this->versionedRecordId = (int) $object->ID;

            // non-versioned object
            $object = LocalisedParent::create();
            $object->Title = 'A record';
            $object->Description = 'Not very interesting';
            $object->write();

            $this->nonVersionedRecordId = (int) $object->ID;
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('de_DE');

            // published object with different stages
            /** @var GridObjectVersioned $object */
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);
            $object->Title = 'Eine Akte';
            $object->Description = 'Live live de hahaha';
            $object->write();
            $object->publishRecursive();

            $object->Description = 'Nicht sehr interessant';
            $object->write();

            /** @var LocalisedParent $object */
            $object = LocalisedParent::get()->byID($this->versionedRecordId);
            $object->Title = 'Eine Akte';
            $object->Description = 'Nicht sehr interessant';
            $object->write();
        });
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
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);

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
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);

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
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);

            $baseRecordPublished = FluentState::singleton()->withState(function (FluentState $state) use ($object): bool {
                $state->setLocale(null);

                return $object->isPublished();
            });

            $this->assertTrue($baseRecordPublished);
            $this->assertFalse($object->isPublished());

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
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);
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
            $object = GridObjectVersioned::get()->byID($this->versionedRecordId);

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
            $object = LocalisedParent::get()->byID($this->nonVersionedRecordId);

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
