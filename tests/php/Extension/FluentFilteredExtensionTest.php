<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Forms\LocaleToggleColumn;
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

    protected function setUp(): void
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
        SiteTree::config()->set('apply_filtered_locales_to_stage', true);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::DRAFT);

        FluentState::singleton()->withState(function (FluentState $state) {
            $state
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
        SiteTree::config()->set('apply_filtered_locales_to_stage', true);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::LIVE);

        FluentState::singleton()->withState(function (FluentState $state) {
            $state
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
        SiteTree::config()->set('apply_filtered_locales_to_stage', false);

        $currentStage = Versioned::get_stage();

        Versioned::set_stage(Versioned::DRAFT);

        // Run test
        FluentState::singleton()->withState(function (FluentState $state) {
            $state
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

            /** @var GridField $localesField */
            $localesField = $fields->dataFieldByName('RecordLocales');
            $this->assertInstanceOf(GridField::class, $localesField);

            $config = $localesField->getConfig();
            $this->assertInstanceOf(
                LocaleToggleColumn::class,
                $config->getComponentByType(LocaleToggleColumn::class)
            );
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
            $page = $this->objFromFixture(Page::class, 'about');
            $flags = $page->getStatusFlags();

            $this->assertArrayHasKey('fluentfiltered', $flags);
            $this->assertSame(
                _t(
                    'TractorCow\\Fluent\\Extension\\FluentFilteredExtension.LOCALEFILTEREDHELP',
                    'This page is not visible in this locale'
                ),
                $flags['fluentfiltered']['title']
            );
        });
    }
}
