<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedAnother;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedChild;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedParent;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\MixedLocalisedSortObject;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\UnlocalisedChild;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubObject;

class FluentExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentExtensionTest.yml';

    protected static $extra_dataobjects = [
        LocalisedAnother::class,
        LocalisedChild::class,
        LocalisedParent::class,
        MixedLocalisedSortObject::class,
        UnlocalisedChild::class,
    ];

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        Locale::clearCached();
    }

    public function testFluentLocaleAndFrontendAreAddedToDataQuery()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('test')
                ->setIsFrontend(true);

            $query = SiteTree::get()->dataQuery();
            $this->assertSame('test', $query->getQueryParam('Fluent.Locale'));
            $this->assertTrue($query->getQueryParam('Fluent.IsFrontend'));
        });
    }

    public function testGetLocalisedTable()
    {
        /** @var SiteTree|FluentSiteTreeExtension $page */
        $page = new SiteTree;
        $this->assertSame('SiteTree_Localised', $page->getLocalisedTable('SiteTree'));
        $this->assertSame(
            'SiteTree_Localised_FR',
            $page->getLocalisedTable('SiteTree', 'FR'),
            'Table aliases can be generated with getLocalisedTable()'
        );
    }

    public function testGetLinkingMode()
    {
        // Does not have a canViewInLocale method, locale is not current
        $stub = new FluentStubObject();
        $this->assertSame('link', $stub->getLinkingMode('foo'));

        // Does not have a canViewInLocale method, locale is current
        FluentState::singleton()->withState(function (FluentState $newState) use ($stub) {
            $newState->setLocale('foo');

            $this->assertSame('current', $stub->getLinkingMode('foo'));
        });
    }

    public function testGetLocalisedFields()
    {
        // test data_include / data_exclude
        // note: These parent fields should be all accessible from the child records as well
        $parent = new LocalisedParent();
        $parentLocalised = [
            'Title' => 'Varchar',
            'Details' => 'Varchar(200)',
        ];
        $this->assertEquals(
            $parentLocalised,
            $parent->getLocalisedFields()
        );

        // test field_include / field_exclude
        $another = new LocalisedAnother();
        $this->assertEquals(
            [
                'Bastion' => 'Varchar',
                'Data' => 'Varchar(100)',
            ],
            $another->getLocalisedFields()
        );
        $this->assertEquals(
            $parentLocalised,
            $another->getLocalisedFields(LocalisedParent::class)
        );

        // Test translate directly
        $child = new LocalisedChild();
        $this->assertEquals(
            [ 'Record' => 'Text' ],
            $child->getLocalisedFields()
        );
        $this->assertEquals(
            $parentLocalised,
            $child->getLocalisedFields(LocalisedParent::class)
        );

        // Test 'none'
        $unlocalised = new UnlocalisedChild();
        $this->assertEmpty($unlocalised->getLocalisedFields());
        $this->assertEquals(
            $parentLocalised,
            $unlocalised->getLocalisedFields(LocalisedParent::class)
        );
    }

    public function testWritesToCurrentLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_US');

            $record = $this->objFromFixture(LocalisedParent::class, 'record_a');
            $this->assertTrue(
                $this->hasLocalisedRecord($record, 'en_US'),
                'Record can be read from default locale'
            );
        });

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('de_DE');

            $record2 = $this->objFromFixture(LocalisedParent::class, 'record_a');
            $this->assertTrue(
                $this->hasLocalisedRecord($record2, 'de_DE'),
                'Existing record can be read from German locale'
            );

            $newState->setLocale('es_ES');

            $record2->Title = 'Un archivo';
            $record2->write();

            $record3 = $this->objFromFixture(LocalisedParent::class, 'record_a');
            $this->assertTrue(
                $this->hasLocalisedRecord($record3, 'es_ES'),
                'Record Locale is set to current locale when writing new records'
            );
        });
    }

    public function testLocalisedMixSorting()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_US');

            // Sort by the NonLocalisedSort field first then the LocalisedField second both in ascending order
            // so the result will be opposite if the order of the columns is not maintained
            $objects=MixedLocalisedSortObject::get()->sort(
                '"FluentExtensionTest_MixedLocalisedSortObject"."LocalizedSort", '.
                '"FluentExtensionTest_MixedLocalisedSortObject"."NonLocalizedSort", '.
                '"FluentExtensionTest_MixedLocalisedSortObject"."Title"'
            );

            // Make sure Item A is first
            $this->assertEquals(
                'Item A',
                $objects->offsetGet(0)->Title
            );

            // Make sure Item B is second
            $this->assertEquals(
                'Item B',
                $objects->offsetGet(1)->Title
            );

            // Make sure Item C is third
            $this->assertEquals(
                'Item C',
                $objects->offsetGet(2)->Title
            );
        });
    }

    /**
     * Ensure that records can be sorted in their locales
     *
     * @dataProvider sortRecordProvider
     * @param string $locale
     * @param string[] $sortArgs
     * @param string[] $expected
     * @group exclude-from-travis
     */
    public function testLocalisedFieldsCanBeSorted($locale, array $sortArgs, $expected)
    {
        FluentState::singleton()->withState(function (FluentState $newState) use ($locale, $sortArgs, $expected) {
            $newState->setLocale($locale);

            $records = LocalisedParent::get()->sort(...$sortArgs);
            $titles = $records->column('Title');
            $this->assertEquals($expected, $titles);
        });
    }

    /**
     * @return array[] Keys: Locale, sorting arguments, expected titles in result
     */
    public function sortRecordProvider()
    {
        return [
            /**
             * Single field (non-composite) sorting syntax (either string or array syntax)
             *
             * E.g. `->sort('"foo"')`, `->sort('Title', 'DESC')` etc
             */
            'german ascending single sort' => [
                'de_DE',
                ['Title', 'ASC'],
                ['Eine Akte', 'Lesen Sie mehr', 'Rennen'],
            ],
            'german descending single sort' => [
                'de_DE',
                ['"Title" DESC'],
                ['Rennen', 'Lesen Sie mehr', 'Eine Akte'],
            ],
            'english ascending single sort' => [
                'en_US',
                ['"Title" ASC'],
                ['A record', 'Go for a run', 'Read about things'],
            ],
            'english descending single sort' => [
                'en_US',
                ['Title', 'DESC'],
                ['Read about things', 'Go for a run', 'A record'],
            ],
            'english ascending on unlocalised field' => [
                'en_US',
                ['"Description"'],
                ['Read about things', 'Go for a run', 'A record'],
            ],
            'english descending on unlocalised field' => [
                'en_US',
                ['"Description" DESC'],
                ['A record', 'Read about things', 'Go for a run'],
            ],
            'german ascending on unlocalised field' => [
                'de_DE',
                ['"Description"'],
                ['Lesen Sie mehr', 'Rennen', 'Eine Akte'],
            ],
            'german descending on unlocalised field' => [
                'de_DE',
                ['"Description" DESC'],
                ['Eine Akte', 'Lesen Sie mehr', 'Rennen'],
            ],
            /**
             * Composite sorting tests (either string syntax or array syntax)
             *
             * E.g. `->sort(['foo' => 'ASC', 'bar' => 'DESC'])`
             */
            'english composite sort, string' => [
                'en_US',
                ['"Details" ASC, "Title" ASC'],
                ['Go for a run', 'A record', 'Read about things']
            ],
            'german composite sort, string' => [
                'de_DE',
                ['"Details" ASC, "Title" ASC'],
                ['Rennen', 'Eine Akte', 'Lesen Sie mehr'],
            ],
            'english, composite sort, array' => [
                'en_US',
                [[
                    'Details' => 'ASC',
                    'Title' => 'ASC'
                ]],
                ['Go for a run', 'A record', 'Read about things'],
            ],
            'german, composite sort, array' => [
                'de_DE',
                [[
                    'Details' => 'ASC',
                    'Title' => 'ASC'
                ]],
                ['Rennen', 'Eine Akte', 'Lesen Sie mehr'],
            ],
            'german, composite sort, array (flipped)' => [
                'de_DE',
                [[
                    'Details' => 'ASC',
                    'Title' => 'DESC'
                ]],
                ['Rennen', 'Lesen Sie mehr', 'Eine Akte'],
            ],
            'english, composite sort, array (flipped)' => [
                'en_US',
                [[
                    'Details' => 'DESC',
                    'Title' => 'DESC'
                ]],
                ['Read about things', 'A record', 'Go for a run'],
            ],
            'german, composite sort, no directions' => [
                'de_DE',
                ['"Details", "Title"'],
                ['Rennen', 'Eine Akte', 'Lesen Sie mehr'],
            ],
            /**
             * Ignored types of sorting, e.g. subqueries. Ignored sorting should use the ORM default
             * and sort on whatever is in the base table.
             */
            'english, subquery sort' => [
                'en_US',
                ['CONCAT((SELECT COUNT(*) FROM "FluentExtensionTest_LocalisedParent_Localised"), "FluentExtensionTest_LocalisedParent"."ID")'],
                ['A record', 'Read about things', 'Go for a run'],
            ]
        ];
    }

    /**
     * Get a Locale field value directly from a record's localised database table, skipping the ORM
     *
     * @param DataObject $record
     * @param string $locale
     * @return boolean
     */
    protected function hasLocalisedRecord(DataObject $record, $locale)
    {
        $result = SQLSelect::create()
            ->setFrom($record->config()->get('table_name') . '_Localised')
            ->setWhere([
                'RecordID' => $record->ID,
                'Locale' => $locale,
            ])
            ->execute()
            ->first();

        return !empty($result);
    }
}
