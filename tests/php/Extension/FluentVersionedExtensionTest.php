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
use TractorCow\Fluent\Tests\Extension\FluentVersionedExtensionTest\TestVersionedModel;

class FluentVersionedExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentVersionedExtensionTest.yml';

    protected static $extra_dataobjects = [
        TestVersionedModel::class
    ];

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
            /** @var Page|FluentSiteTreeExtension $page */
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

    /**
     * Tests various Versioned methods correctly identify versions in different locales
     */
    public function testCorrectVersionIdentified(): void
    {
        $recordID = null;
        // Create a new record in the base record only, no localisation
        FluentState::singleton()->withState(function (FluentState $newState) use (&$recordID) {
            $newState->setLocale(null);
            $record = new TestVersionedModel(['Title' => 'Initial value']);
            $recordID = $record->write();

            // True for anything about being on draft and not published
            $this->assertTrue($record->isOnDraft());
            $this->assertFalse($record->isPublished());
            $this->assertTrue($record->isLatestDraftVersion());
            $this->assertTrue($record->isLatestVersion());
            $this->assertFalse($record->isLiveVersion());
            $this->assertTrue($record->isModifiedOnDraft());
            $this->assertFalse($record->isOnLiveOnly());
        });

        // Check all false - it's not in this locale at all yet.
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $this->assertFalse($localisedRecord->isOnDraft());
            $this->assertFalse($localisedRecord->isPublished());
            $this->assertFalse($localisedRecord->isLatestDraftVersion());
            $this->assertFalse($localisedRecord->isLatestVersion());
            $this->assertFalse($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });

        // Publish in the base record only, no localisation
        FluentState::singleton()->withState(function (FluentState $newState) use (&$recordID) {
            $newState->setLocale(null);
            $record = TestVersionedModel::get()->byID($recordID);
            $record->publishSingle();

            // True for anything about being published and "on draft" (i.e. not deleted from draft)
            $this->assertTrue($record->isOnDraft());
            $this->assertTrue($record->isPublished());
            $this->assertTrue($record->isLatestDraftVersion());
            $this->assertTrue($record->isLatestVersion());
            $this->assertTrue($record->isLiveVersion());
            $this->assertFalse($record->isModifiedOnDraft());
            $this->assertFalse($record->isOnLiveOnly());
        });

        // Check still all false - it's not in this locale at all yet.
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $this->assertFalse($localisedRecord->isOnDraft());
            $this->assertFalse($localisedRecord->isPublished());
            $this->assertFalse($localisedRecord->isLatestDraftVersion());
            $this->assertFalse($localisedRecord->isLatestVersion());
            $this->assertFalse($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });

        // Save the record in first locale
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $localisedRecord->write();

            // True for anything about being on draft and not published
            $this->assertTrue($localisedRecord->isOnDraft());
            $this->assertFalse($localisedRecord->isPublished());
            $this->assertTrue($localisedRecord->isLatestDraftVersion());
            $this->assertTrue($localisedRecord->isLatestVersion());
            $this->assertFalse($localisedRecord->isLiveVersion());
            $this->assertTrue($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });

        // Check all false in *alternate* locale - it's not in this locale at all yet.
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_US');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $this->assertFalse($localisedRecord->isOnDraft());
            $this->assertFalse($localisedRecord->isPublished());
            $this->assertFalse($localisedRecord->isLatestDraftVersion());
            $this->assertFalse($localisedRecord->isLatestVersion());
            $this->assertFalse($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());

            // Then save it in this locale
            $localisedRecord->write();
        });

        // Publish the record in first locale
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $localisedRecord->publishSingle();

            // True for anything about being published and "on draft" (i.e. not deleted from draft)
            $this->assertTrue($localisedRecord->isOnDraft());
            $this->assertTrue($localisedRecord->isPublished());
            $this->assertTrue($localisedRecord->isLatestDraftVersion());
            $this->assertTrue($localisedRecord->isLatestVersion());
            $this->assertTrue($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });

        // Check still only true for draft stuff in *alternate* locale
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_US');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $this->assertTrue($localisedRecord->isOnDraft());
            $this->assertFalse($localisedRecord->isPublished());
            $this->assertTrue($localisedRecord->isLatestDraftVersion());
            $this->assertTrue($localisedRecord->isLatestVersion());
            $this->assertFalse($localisedRecord->isLiveVersion());
            $this->assertTrue($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());

            // Then publish it in this locale
            $localisedRecord->publishSingle();
        });

        // Update, save, and publish the record in first locale
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_NZ');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $localisedRecord->Title = 'New value';
            $localisedRecord->write();
            $localisedRecord->publishSingle();

            // True for anything about being published and "on draft" (i.e. not deleted from draft)
            $this->assertTrue($localisedRecord->isOnDraft());
            $this->assertTrue($localisedRecord->isPublished());
            $this->assertTrue($localisedRecord->isLatestDraftVersion());
            $this->assertTrue($localisedRecord->isLatestVersion());
            $this->assertTrue($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });

        // Check *alternate* locale still thinks it's published - and on the latest published version
        FluentState::singleton()->withState(function (FluentState $newState) use ($recordID) {
            $newState->setLocale('en_US');
            $localisedRecord = TestVersionedModel::get()->byID($recordID);
            $this->assertTrue($localisedRecord->isOnDraft());
            $this->assertTrue($localisedRecord->isPublished());
            $this->assertTrue($localisedRecord->isLatestDraftVersion());
            $this->assertTrue($localisedRecord->isLatestVersion());
            $this->assertTrue($localisedRecord->isLiveVersion());
            $this->assertFalse($localisedRecord->isModifiedOnDraft());
            $this->assertFalse($localisedRecord->isOnLiveOnly());
        });
    }
}
