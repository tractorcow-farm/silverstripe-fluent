<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
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

    /**
     * Test that we only get 1 visible Page when browsing the frontend with:
     * stage=Stage
     * apply_filtered_locales_to_stage=true
     */
    public function testAugmentSQLFrontend()
    {
        // Specifically set this config value so that filtered locales ARE required in stage=Stage.
        Config::modify()->set(DataObject::class, 'apply_filtered_locales_to_stage', true);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::DRAFT);

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(true)
                ->setIsDomainMode(false);

            $this->assertCount(1, SiteTree::get());
        });

        if ($currentStage) {
            Versioned::set_stage($currentStage);
        }
    }

    /**
     * Test that we don't get any visible Pages when browsing the frontend with:
     * stage=Live
     * apply_filtered_locales_to_stage=true
     */
    public function testAugmentSQLFrontendLive()
    {
        // Specifically set this config value so that filtered locales ARE required in stage=Stage.
        Config::modify()->set(DataObject::class, 'apply_filtered_locales_to_stage', true);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::LIVE);

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(true);

            $this->assertCount(0, SiteTree::get());
        });

        if ($currentStage) {
            Versioned::set_stage($currentStage);
        }
    }

    /**
     * Test that we get 2 visible Pages when browsing the frontend with:
     * stage=Stage
     * apply_filtered_locales_to_stage=true
     */
    public function testAugmentSQLStage()
    {
        // Specifically set this config value so that filtered locales are NOT required in stage=Stage.
        Config::modify()->set(DataObject::class, 'apply_filtered_locales_to_stage', false);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::DRAFT);

        // Run test
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(true);

            $this->assertCount(2, SiteTree::get());
        });

        if ($currentStage) {
            Versioned::set_stage($currentStage);
        }
    }

    public function testAugmentSQLCMS()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsFrontend(false)
                ->setIsDomainMode(false);

            $this->assertCount(2, SiteTree::get());
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
            $this->assertEquals('Filtered', $flags['fluentfiltered']['text']);
        });
    }
}
