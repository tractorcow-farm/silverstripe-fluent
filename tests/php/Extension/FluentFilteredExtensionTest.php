<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
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
    }

    public function testAugmentSQLFrontend()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(true)
                ->setIsDomainMode(false);

            $this->assertEquals(1, SiteTree::get()->count());
        });
    }

    public function testAugmentSQLCMS()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(false)
                ->setIsDomainMode(false);

            $this->assertEquals(2, SiteTree::get()->count());
        });
    }

    public function testUpdateCMSFields()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = SiteTree::get()->filter('URLSegment', 'home')->first();
            $fields = $page->getCMSFields();

            $this->assertNotNull($fields->dataFieldByName('FilteredLocales'));
        });
    }

    public function testUpdateStatusFlags()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US')
                ->setIsFrontend(false)
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture('Page', 'about');
            $flags = $page->getStatusFlags();

            $this->assertTrue(array_key_exists('fluentfiltered', $flags));

            if (!array_key_exists('fluentfiltered', $flags)) {
                return;
            }

            $this->assertEquals('Filtered', $flags['fluentfiltered']['text']);
        });
    }
}
