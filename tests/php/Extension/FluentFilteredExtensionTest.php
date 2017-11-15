<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\FixtureFactory;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentFilteredExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentFilteredExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
            FluentFilteredExtension::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentState::singleton()
            ->setLocale('en_NZ')
            ->setIsDomainMode(false);

        /** @var FixtureFactory $factory */
        $factory = $this->getFixtureFactory();

        $factory->clear();

        $factory->createObject('TractorCow\Fluent\Model\Locale', 'nz', [
            'Locale' => 'en_NZ',
            'Title' => 'English (New Zealand)',
            'URLSegment' => 'newzealand',
        ]);

        $factory->createObject('SilverStripe\CMS\Model\SiteTree', 'home', [
            'Title' => 'Home',
            'URLSegment' => 'home',
            'FilteredLocales' => '=>TractorCow\Fluent\Model\Locale.nz',
        ]);

        $factory->createObject('SilverStripe\CMS\Model\SiteTree', 'about', [
            'Title' => 'About',
            'URLSegment' => 'about',
        ]);
    }

    public function testAugmentSQLFrontend()
    {
        FluentState::singleton()
            ->setLocale('en_NZ')
            ->setIsFrontend(true);

        $this->assertEquals(1, SiteTree::get()->count());
    }

    public function testAugmentSQLCMS()
    {
        FluentState::singleton()
            ->setLocale('en_NZ')
            ->setIsFrontend(false);

        $this->assertEquals(2, SiteTree::get()->count());
    }

    public function testUpdateCMSFields()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = SiteTree::get()->filter('URLSegment', 'home')->first();
        $fields = $page->getCMSFields();

        $this->assertNotNull($fields->dataFieldByName('FilteredLocales'));
    }
}
