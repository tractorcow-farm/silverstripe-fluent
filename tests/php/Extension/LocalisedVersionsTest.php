<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentDataObject;

/**
 * Class LocalisedVersionsTest
 *
 * The main trick to test localised version is to switch between locales when creating content (versions)
 * Correctly localised versions will not be affected by frequent locale switching
 *
 * @package TractorCow\Fluent\Tests\Extension
 */
class LocalisedVersionsTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'LocalisedVersionsTest.yml';

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
    ];

    /**
     * @var int
     */
    private $objectId;

    protected function setUp(): void
    {
        parent::setUp();

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_NZ');

            // version 1
            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = FluentDataObject::create();
            $object->Title = 'EN Title';
            $object->write();

            // version 2
            $object->Description = 'EN Description';
            $object->write();

            $this->objectId = (int) $object->ID;
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_US');

            // version 3
            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = FluentDataObject::get()->byID($this->objectId);
            $object->Title = 'US Title';
            $object->write();

            // version 4
            $object->Description = 'US Description';
            $object->write();
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('jp_JP');

            // version 5
            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = FluentDataObject::get()->byID($this->objectId);
            $object->Title = 'JP Title';
            $object->write();

            // version 6
            $object->Description = 'JP Description';
            $object->write();
        });
    }

    /**
     * @param string|null $locale
     * @param int $expected
     * @dataProvider latestVersionsProvider
     */
    public function testGetLatestVersion(?string $locale, int $expected): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $expected): void {
            $state->setLocale($locale);

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = Versioned::get_latest_version(FluentDataObject::class, $this->objectId);

            $this->assertNotNull($object);
            $this->assertTrue($object->exists());
            $this->assertFalse($object->isArchived());
            $this->assertEquals($expected, $object->Version);
        });
    }

    /**
     * @param string|null $locale
     * @param int $expected
     * @dataProvider latestVersionsProvider
     */
    public function testGetVersionNumberByStage(?string $locale, int $expected): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $expected): void {
            $state->setLocale($locale);

            $version = Versioned::get_versionnumber_by_stage(
                FluentDataObject::class,
                Versioned::DRAFT,
                $this->objectId
            );

            $this->assertEquals($expected, (int) $version);
        });
    }

    /**
     * @param string|null $locale
     * @param int $expected
     * @dataProvider listVersionsProvider
     */
    public function testGetAllVersions(?string $locale, array $expected): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $expected): void {
            $state->setLocale($locale);

            $versions = Versioned::get_all_versions(FluentDataObject::class, $this->objectId)
                ->sort('Version', 'ASC')
                ->columnUnique('Version');

            $this->assertSame($expected, $versions);
        });
    }

    public function testArchive(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_US');

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = FluentDataObject::get()->byID($this->objectId);
            $object->doArchive();
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('jp_JP');

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = FluentDataObject::get()->byID($this->objectId);
            $object->doArchive();
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_US');

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = Versioned::get_latest_version(FluentDataObject::class, $this->objectId);

            $this->assertNotNull($object);
            $this->assertTrue($object->isArchived());
            $this->assertTrue($object->hasArchiveInLocale());

            // Restore
            $object->writeToStage(Versioned::DRAFT);

            $object = FluentDataObject::get()->byID($this->objectId);
            $this->assertEquals('US Description', $object->Description);
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('jp_JP');

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = Versioned::get_latest_version(FluentDataObject::class, $this->objectId);
            $this->assertNotNull($object);

            $this->assertNotNull($object);
            $this->assertTrue($object->isArchived());
            $this->assertTrue($object->hasArchiveInLocale());

            // Restore
            $object->writeToStage(Versioned::DRAFT);

            $object = FluentDataObject::get()->byID($this->objectId);
            $this->assertEquals('JP Description', $object->Description);
        });

        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_CA');

            /** @var FluentDataObject|Versioned|FluentVersionedExtension $object */
            $object = Versioned::get_latest_version(FluentDataObject::class, $this->objectId);
            $this->assertNull($object);

            $object = FluentDataObject::get()->byID($this->objectId);

            $this->assertNotNull($object);
            $this->assertFalse($object->isArchived());
            $this->assertFalse($object->hasArchiveInLocale());
        });
    }

    public static function latestVersionsProvider(): array
    {
        return [
            [null, 6],
            ['en_NZ', 2],
            ['en_US', 4],
            ['jp_JP', 6],
        ];
    }

    public static function listVersionsProvider(): array
    {
        return [
            [
                null,
                [1, 2, 3, 4, 5, 6],
            ],
            [
                'en_NZ',
                [1, 2],
            ],
            [
                'en_US',
                [3, 4],
            ],
            [
                'jp_JP',
                [5, 6],
            ],
        ];
    }
}
