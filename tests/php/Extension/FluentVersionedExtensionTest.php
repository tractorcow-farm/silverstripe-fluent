<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentVersionedExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentVersionedExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
    }

    public function testIsDraftedInLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page $page */
            $page = $this->objFromFixture(Page::class, 'home');

            $this->assertTrue($page->isDraftedInLocale());
        });
    }

    public function testIsPublishedInLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page $page */
            $page = $this->objFromFixture(Page::class, 'home');

            $this->assertTrue($page->isPublishedInLocale());
        });
    }

    public function testExistsInLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page $page */
            $page = $this->objFromFixture(Page::class, 'home');

            $this->assertTrue($page->existsInLocale());
        });
    }

    /** @group wip */
    public function testSourceLocaleIsCurrentWhenPageExistsInIt()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            // Read from the locale that the page exists in already
            /** @var Page $page */
            $page = $this->objFromFixture(Page::class, 'home');

            $this->assertEquals('en_NZ', $page->getSourceLocale()->Locale);
        });
    }
}
