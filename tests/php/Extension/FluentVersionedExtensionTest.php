<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        (new FluentVersionedExtension)->flushCache();

        FluentState::singleton()
            ->setLocale('en_NZ')
            ->setIsDomainMode(false);
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

    public function testExistsInLocaleReturnsTheRightValueFromCache()
    {
        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'home');

        //warm up cache
        $this->assertTrue($page->existsInLocale());
        $this->assertTrue($page->existsInLocale('en_NZ'));
        $this->assertFalse($page->existsInLocale('de_AT'), 'Homepage does not exist in de_AT');

        //get results from cache
        $this->assertTrue($page->existsInLocale());
        $this->assertTrue($page->existsInLocale('en_NZ'));
        $this->assertFalse($page->existsInLocale('de_AT'), 'Homepage does not exist in de_AT, cache does not return false');
    }

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

    public function testLocalisedStageCacheIsUsedForIsLocalisedInLocale()
    {
        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'home');

        /** @var FluentVersionedExtension $extension */
        $extension = $this->getMockBuilder(FluentVersionedExtension::class)
            ->setMethods(['findRecordInLocale'])
            ->getMock();
        $extension->setOwner($page);

        // We only expect one call to this method, because subsequent calls should be cached
        $extension->expects($this->once())->method('findRecordInLocale')->willReturn(true);

        // Initial request
        $result = $extension->isPublishedInLocale('en_NZ');
        $this->assertSame(true, $result, 'Original method result is returned');

        // Checking the cache
        $result2 = $extension->isPublishedInLocale('en_NZ');
        $this->assertSame(true, $result2, 'Cached result is returned');
    }

    public function testIdsInLocaleCacheIsUsedForIsLocalisedInLocale()
    {
        // Optimistically generate the cache
        FluentVersionedExtension::prepoulateIdsInLocale('en_NZ', Page::class, true, true);

        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'home');

        /** @var FluentVersionedExtension $extension */
        $extension = $this->getMockBuilder(FluentVersionedExtension::class)
            ->setMethods(['findRecordInLocale'])
            ->getMock();
        $extension->setOwner($page);

        // We expect the lookup method to never get called, because the results are optimistically cached
        $extension->expects($this->never())->method('findRecordInLocale');
        $this->assertTrue($extension->isPublishedInLocale('en_NZ'), 'Fixtured page is published');
    }

    /**
     * @throws ValidationException
     */
    public function testStagesDifferInLocale(): void
    {
        $pageId = FluentState::singleton()->withState(function (FluentState $state): int {
            $state->setLocale(null);

            $page = Page::create();
            $page->Title = 'Test page stages differ';
            $page->URLSegment = 'test-page-stages-differ';

            // Not in DB
            $this->assertFalse($page->stagesDifferInLocale());

            return (int) $page->write();
        });

        /** @var Page $page */
        $page = Page::get()->byID($pageId);

        // Not Localised in Draft
        $this->assertFalse($page->stagesDifferInLocale());

        // Localise to Draft
        $page->write();

        // Not Localised in Live (draft only)
        $this->assertTrue($page->stagesDifferInLocale());

        // Publish
        $page->publishRecursive();

        // Localised in both Draft and Live (same content)
        $this->assertFalse($page->stagesDifferInLocale());

        // Update draft content
        $page->MetaDescription = 'New description';
        $page->write();

        // Draft has newer content
        $this->assertTrue($page->stagesDifferInLocale());

        // Publish
        $page->publishRecursive();

        // Same content on Draft and Live
        $this->assertFalse($page->stagesDifferInLocale());

        // Unpublish
        $page->doUnpublish();

        // No Live version
        $this->assertTrue($page->stagesDifferInLocale());
    }
}
