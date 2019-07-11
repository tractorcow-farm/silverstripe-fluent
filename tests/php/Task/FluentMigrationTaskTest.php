<?php


namespace TractorCow\Fluent\Tests\Task;


use Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Dev\TranslatedDataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Task\FluentMigrationTask;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

class FluentMigrationTaskTest extends SapphireTest
{
    protected static $fixture_file = 'FluentMigrationTaskTest.yml';

    protected static $extra_dataobjects = [
        TranslatedDataObject::class
    ];

    /**
     * @useDatabase false
     */
    public function testTestDataObjectsHaveFluentExtensionApplied()
    {
        foreach (self::$extra_dataobjects as $className) {
            $instance = $className::create();
            $hasExtension = $instance->hasExtension(FluentExtension::class);
            $this->assertTrue($hasExtension, $className . ' should have FluentExtension applied');
        }

    }

    public function testFixturesAreSetupWithOldData()
    {
        $house = $this->objFromFixture(TranslatedDataObject::class, 'house');

        $id = $house->ID;

        $allFields = SQLSelect::create()
            ->setFrom(Config::inst()->get(TranslatedDataObject::class, 'table_name'))
            ->addWhere('ID = ' . $id)
            ->firstRow()
            ->execute();
        $record = $allFields->record();

        $this->assertEquals('A House', $record['Title_en_US']);
        $this->assertEquals('Something', $record['Name_en_US']);
        $this->assertEquals('Ein Haus', $record['Title_de_AT']);
        $this->assertEquals('Irgendwas', $record['Name_de_AT']);
    }

    public function testMigrationTaskMigratesDataObjectsWithoutVersioning()
    {
        $task = FluentMigrationTask::create();
        $task->run();

        $house = $this->objFromFixture(TranslatedDataObject::class, 'house');
    }

    /**
     * @useDatabase false
     */
    public function testGetLocales()
    {
        $locales = [
            'de_ch', 'en_foo'
        ];
        Config::modify()->set('Fluent', 'locales', $locales);

        $task = FluentMigrationTask::create();

        $this->assertEquals($locales, $task->getLocales(), 'getLocales() should get locales from old fluent config');

    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Fluent.locales is required
     * @useDatabase false
     */
    public function testGetLocalesThrowsExceptionWhenNoConfigIsFound()
    {
        Config::modify()->set('Fluent', 'locales', []);
        $task = FluentMigrationTask::create();
        $task->getLocales();
    }
}
