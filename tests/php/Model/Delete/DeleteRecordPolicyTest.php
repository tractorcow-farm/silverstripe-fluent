<?php

namespace TractorCow\Fluent\Tests\Model\Delete;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Validation\ValidationException;
use TractorCow\Fluent\Model\Delete\DeletePolicy;
use TractorCow\Fluent\Model\Delete\DeleteRecordPolicy;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Model\Delete\Fixtures\FilteredRecord;
use TractorCow\Fluent\Tests\Model\Delete\Fixtures\LocalisedRecord;
use TractorCow\Fluent\Tests\Model\Delete\Fixtures\Record;

class DeleteRecordPolicyTest extends SapphireTest
{
    protected static $fixture_file = '../LocaleTest.yml';

    protected static $extra_dataobjects = [
        Record::class,
        LocalisedRecord::class,
        FilteredRecord::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();

        // Set default
        FluentState::singleton()
            ->setLocale('es_US')
            ->setIsDomainMode(false);
    }

    /**
     * @throws ValidationException
     */
    public function testDelete()
    {
        $record = new Record();
        $record->Title = 'OK';
        $record->write();

        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_Record"')->value()
        );

        /** @var DeleteRecordPolicy $policy */
        $policy = Injector::inst()->create(DeletePolicy::class, $record);
        $this->assertInstanceOf(DeleteRecordPolicy::class, $policy);
        $this->assertEmpty($policy->getDependantPolicies());

        // Delete
        $blocked = $policy->delete($record);

        // Item is deleted
        $this->assertEquals(
            0,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_Record"')->value()
        );
        $this->assertFalse($blocked);
    }

    /**
     * @throws ValidationException
     */
    public function testDeleteLocalisedRecords()
    {
        // Write in en-US
        $record = new LocalisedRecord();
        $record->Title = 'us spanish content';
        $record->write();
        $recordID = $record->ID;

        // Write in en-nz
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $record = LocalisedRecord::get()->byID($recordID);
            $record->Title = 'nz content';
            $record->write();
        });

        // We should have 1 base record, 2 localised records
        $this->assertEquals(
            2,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord_Localised"')->value()
        );
        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord"')->value()
        );

        // Delete in base locale should reduce a _Localised count
        $record->delete();
        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord_Localised"')->value()
        );
        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord"')->value()
        );

        // Delete in en-nz should remove all _Localised and base
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $record = LocalisedRecord::get()->byID($recordID);
            $record->delete();
        });

        $this->assertEquals(
            0,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord_Localised"')->value()
        );
        $this->assertEquals(
            0,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_LocalisedRecord"')->value()
        );
    }

    /**
     * @throws ValidationException
     */
    public function testDeleteFilteredRecords()
    {
        // Add to 2 locales
        $record = new FilteredRecord();
        $record->Title = 'Content';
        $record->write();
        $record->FilteredLocales()->add(Locale::get()->find('Locale', 'en_NZ'));
        $record->FilteredLocales()->add(Locale::get()->find('Locale', 'es_US'));
        $recordID = $record->ID;

        // mapping table has 2 records
        $this->assertEquals(
            2,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_FilteredRecord_FilteredLocales"')->value()
        );

        // Deleting in current locale (es_US) should reduce mapping table to 1
        $record->delete();

        // We should have 1 base record, 2 localised records
        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_FilteredRecord_FilteredLocales"')->value()
        );
        $this->assertEquals(
            1,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_FilteredRecord"')->value()
        );

        // Delete in en_nz should remove the last filteredlocale mapping table, and also the base record
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $record = FilteredRecord::get()->byID($recordID);
            $record->delete();
        });
        $this->assertEquals(
            0,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_FilteredRecord_FilteredLocales"')->value()
        );
        $this->assertEquals(
            0,
            DB::query('SELECT COUNT("ID") FROM "FluentDeleteTest_FilteredRecord"')->value()
        );
    }
}
