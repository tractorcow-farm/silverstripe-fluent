<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedAnother;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedChild;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\LocalisedParent;
use TractorCow\Fluent\Tests\Extension\FluentExtensionTest\UnlocalisedChild;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubObject;

class FluentExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentExtensionTest.yml';

    protected static $extra_dataobjects = [
        LocalisedAnother::class,
        LocalisedChild::class,
        LocalisedParent::class,
        UnlocalisedChild::class,
    ];

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    public function testFluentLocaleAndFrontendAreAddedToDataQuery()
    {
        FluentState::singleton()
            ->setLocale('test')
            ->setIsFrontend(true);

        $query = SiteTree::get()->dataQuery();
        $this->assertSame('test', $query->getQueryParam('Fluent.Locale'));
        $this->assertTrue($query->getQueryParam('Fluent.IsFrontend'));
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
        FluentState::singleton()->setLocale('foo');
        $this->assertSame('current', $stub->getLinkingMode('foo'));
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
        FluentState::singleton()->setLocale('en_US');
        $record = $this->objFromFixture(LocalisedParent::class, 'record_a');
        $this->assertSame(
            'en_US',
            $this->getLocalisedLocaleFromDb($record),
            'Record can be read from default locale'
        );

        FluentState::singleton()->setLocale('de_DE');
        $record->write();

        $record2 = $this->objFromFixture(LocalisedParent::class, 'record_a');
        $this->assertSame(
            'de_DE',
            $this->getLocalisedLocaleFromDb($record2),
            'Record Locale is set to current locale'
        );
    }

    /**
     * Get a Locale field value directly from a record's localised database table, skipping the ORM
     *
     * @param DataObject $record
     * @return string|null
     */
    protected function getLocalisedLocaleFromDb(DataObject $record)
    {
        $result = SQLSelect::create()
            ->setFrom($record->config()->get('table_name') . '_Localised')
            ->setWhere(['RecordID' => $record->ID])
            ->execute()
            ->first();

        return isset($result['Locale']) ? $result['Locale'] : null;
    }
}
