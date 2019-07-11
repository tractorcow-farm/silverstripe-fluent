<?php


namespace TractorCow\Fluent\Tests\Task;


use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Dev\TranslatedDataObject;
use TractorCow\Fluent\Task\FluentMigrationTask;

class FluentMigrationTaskTest extends SapphireTest
{
    protected static $fixture_file = 'FluentMigrationTaskTest.yml';

    protected static $extra_dataobjects = [
        TranslatedDataObject::class
    ];

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
}
