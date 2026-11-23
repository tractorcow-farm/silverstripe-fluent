<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;
use TractorCow\Fluent\State\FluentState;
use PHPUnit\Framework\Attributes\DataProvider;

// Skip if pages module not installed
if (!class_exists(SiteTree::class)) {
    return;
}

class FluentSiteTreeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentSiteTreeExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()
            ->set(Director::class, 'alternate_base_url', 'http://mocked')
            ->set(FluentDirectorExtension::class, 'disable_default_prefix', false);

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentVersionedExtension::reset();

        FluentState::singleton()
            ->setLocale('de_DE')
            ->setIsDomainMode(false);
    }

    public function testGetLocaleInformation()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture(Page::class, 'nz-page');
            $result = $page->LocaleInformation('en_NZ');

            $this->assertInstanceOf(RecordLocale::class, $result);
            $this->assertEquals('en_NZ', $result->getLocale());
            $this->assertEquals('en-nz', $result->getLocaleRFC1766());
            $this->assertEquals('en-nz', $result->getHrefLang());
            $this->assertEquals('English (New Zealand)', $result->getTitle());
            $this->assertEquals('English', $result->getLanguageNative());
            $this->assertEquals('en', $result->getLanguage());
            $this->assertEquals($this->normaliseTrailingSlash('/newzealand/a-page/'), $result->getLink());
            $this->assertEquals($this->normaliseTrailingSlash('http://mocked/newzealand/a-page/'), $result->getAbsoluteLink());
            $this->assertEquals('link', $result->getLinkingMode());
            $this->assertEquals('newzealand', $result->getURLSegment());
        });
    }

    public function testGetLocales()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture(Page::class, 'nz-page');
            $result = $page->Locales();

            $this->assertInstanceOf(ArrayList::class, $result);
            $this->assertCount(6, $result);
            $this->assertListEquals([
                ['Locale' => 'en_NZ'],
                ['Locale' => 'de_DE'],
                ['Locale' => 'en_US'],
                ['Locale' => 'en_GB'],
                ['Locale' => 'es_ES'],
                ['Locale' => 'zh_CN'],
            ], $result);
        });
    }

    /**
     * Tests for url generation
     *
     * @return array list of tests with values:
     *  - domain (or false for non-domain mode)
     *  - locale
     *  - disable_default_prefix flag
     *  - page id
     *  - expected link
     */
    public static function provideURLTests()
    {
        return [
            // Non-domain tests
            [null, 'de_DE', false, 'home', '/german/'],
            [null, 'de_DE', false, 'about', '/german/about-us/'],
            [null, 'de_DE', false, 'staff', '/german/about-us/my-staff/'],
            // Since de_DE is the only locale on the www.example.de domain, ensure that the locale
            // isn't unnecessarily added to the link.
            // In this case disable_default_prefix is ignored
            // See https://github.com/tractorcow/silverstripe-fluent/issues/75
            ['www.example.de', 'de_DE', false, 'home', '/'],
            ['www.example.de', 'de_DE', false, 'about', '/about-us/'],
            ['www.example.de', 'de_DE', false, 'staff', '/about-us/my-staff/'],

            // Test domains with multiple locales
            //  - es_ES non default locale
            ['www.example.com', 'es_ES', false, 'home', '/es_ES/'],
            ['www.example.com', 'es_ES', false, 'about', '/es_ES/about-us/'],
            ['www.example.com', 'es_ES', false, 'staff', '/es_ES/about-us/my-staff/'],
            //  - en_US default locale
            ['www.example.com', 'en_US', false, 'home', '/usa/'],
            ['www.example.com', 'en_US', false, 'about', '/usa/about-us/'],
            ['www.example.com', 'en_US', false, 'staff', '/usa/about-us/my-staff/'],
            //  - en_US default locale, but with disable_default_prefix on
            ['www.example.com', 'en_US', true, 'home', '/'],
            ['www.example.com', 'en_US', true, 'about', '/about-us/'],
            ['www.example.com', 'en_US', true, 'staff', '/about-us/my-staff/'],

            // Test cross-domain links include the opposing domain
            // - to default locale
            ['www.example.de', 'en_US', true, 'home', 'http://www.example.com/'],
            ['www.example.de', 'en_US', true, 'staff', 'http://www.example.com/about-us/my-staff/'],
            // - to non defalut locale
            ['www.example.de', 'es_ES', true, 'home', 'http://www.example.com/es_ES/'],
            ['www.example.de', 'es_ES', true, 'staff', 'http://www.example.com/es_ES/about-us/my-staff/'],
        ];
    }

    /**
     * Test that URLS for pages are generated correctly
     *
     * @param string $domain
     * @param string $locale
     * @param bool $prefixDisabled
     * @param string $pageName
     * @param string $url
     */
    #[DataProvider('provideURLTests')]
    public function testFluentURLs(?string $domain, string $locale, bool $prefixDisabled, string $pageName, string $url)
    {
        FluentState::singleton()->withState(
            function (FluentState $newState) use ($domain, $locale, $prefixDisabled, $pageName, $url) {
                $newState
                    ->setLocale($locale)
                    ->setDomain($domain)
                    ->setIsDomainMode(!empty($domain));

                // Set url generation option
                Config::modify()
                    ->set(FluentDirectorExtension::class, 'disable_default_prefix', $prefixDisabled);

                /** @var Page|FluentSiteTreeExtension $page */
                $page = $this->objFromFixture(Page::class, $pageName);
                $this->assertEquals($this->normaliseTrailingSlash($url), $page->Link());
            }
        );
    }

    public function testUpdateStatusFlagsFluentInvisible()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture(Page::class, 'home');
        $flags = $page->getStatusFlags();

        $this->assertArrayHasKey('fluentinvisible', $flags);
    }

    public function testStatusMessageNotVisible()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture(Page::class, 'staff');
            $page->config()
                ->set('locale_published_status_message', true)
                ->set('frontend_publish_required', FluentExtension::INHERITANCE_MODE_EXACT);

            $fields = $page->getCMSFields();

            /** @var LiteralField $statusMessage */
            $statusMessage = $fields->fieldByName('LocaleStatusMessage');

            $this->assertNotNull($statusMessage, 'Locale message was not added');
            $this->assertStringContainsString('This page will not be visible', $statusMessage->getContent());
        });
    }

    public function testStatusMessageUnknown()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture(Page::class, 'home');
            $page->config()
                ->set('locale_published_status_message', true)
                ->set('frontend_publish_required', FluentExtension::INHERITANCE_MODE_ANY);

            $fields = $page->getCMSFields();

            /** @var LiteralField $statusMessage */
            $statusMessage = $fields->fieldByName('LocaleStatusMessage');

            $this->assertNotNull($fields->fieldByName('LocaleStatusMessage'));
            $this->assertStringContainsString('No content is available for this page', $statusMessage->getContent());
        });
    }

    public function testStatusMessageDrafted()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = $this->objFromFixture(Page::class, 'home');
            $page->config()
                ->set('locale_published_status_message', true)
                ->set('frontend_publish_required', FluentExtension::INHERITANCE_MODE_ANY);
            $page->write();

            $fields = $page->getCMSFields();

            /** @var LiteralField $statusMessage */
            $statusMessage = $fields->fieldByName('LocaleStatusMessage');

            $this->assertNotNull($fields->fieldByName('LocaleStatusMessage'));
            $this->assertStringContainsString('A draft has been created for this locale', $statusMessage->getContent());
        });
    }

    public function testUpdateCMSActionsInherited()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture(Page::class, 'home');
        $actions = $page->getCMSActions();

        /** @var CompositeField $majorActions */
        $majorActions = $actions->fieldByName('MajorActions');

        $this->assertNotNull($majorActions);

        $actionSave = $majorActions->getChildren()->fieldByName('action_save_localised_copy');
        $actionPublish = $majorActions->getChildren()->fieldByName('action_publish_localised_copy');

        $this->assertNotNull($actionSave);
        $this->assertNotNull($actionPublish);

        $this->assertEquals('Copy to draft', $actionSave->Title());
        $this->assertEquals('Copy & publish', $actionPublish->Title());
    }

    public function testUpdateCMSActionsDrafted()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture(Page::class, 'about');
        // Make sure page is properly localised (including version records)
        $page->writeToStage(Versioned::DRAFT);
        $actions = $page->getCMSActions();

        /** @var CompositeField $majorActions */
        $majorActions = $actions->fieldByName('MajorActions');

        $this->assertNotNull($majorActions);

        $actionSave = $majorActions->getChildren()->fieldByName('action_save');
        $actionPublish = $majorActions->getChildren()->fieldByName('action_publish');

        $this->assertNotNull($actionSave);
        $this->assertNotNull($actionPublish);

        $this->assertEquals('Saved', $actionSave->Title());
        // The default value changed between SS 4.0 and 4.1 - assert it contains Publish instead of exact matching
        $this->assertStringContainsString('publish', strtolower($actionPublish->Title()));
    }

    /**
     * @param string $localeCode
     * @param string $fixture
     * @param string $expected
     */
    #[DataProvider('localePrefixUrlProvider')]
    public function testAddLocalePrefixToUrlSegment(string $localeCode, string $fixture, string $expected)
    {
        FluentState::singleton()->withState(
            function (FluentState $newState) use ($localeCode, $fixture, $expected) {
                $newState
                    ->setLocale($localeCode)
                    ->setIsDomainMode(true);

                $fields = $this->objFromFixture(Page::class, $fixture)->getCMSFields();

                /** @var SiteTreeURLSegmentField $segmentField */
                $segmentField = $fields->fieldByName('Root.Main.URLSegment');
                $this->assertInstanceOf(SiteTreeURLSegmentField::class, $segmentField);

                $this->assertSame($expected, $segmentField->getURLPrefix());
            }
        );
    }

    /**
     * @param string $cmsInheritanceMode
     * @param string $frontendInheritanceMode
     * @param bool $frontendContext
     * @param bool $expected
     * @return void
     */
    #[DataProvider('provideLocaleInheritanceMode')]
    public function testLocaleInheritanceMode(
        string $cmsInheritanceMode,
        string $frontendInheritanceMode,
        bool $frontendContext,
        bool $expected
    ): void {
        Page::config()
            ->set('cms_localisation_required', $cmsInheritanceMode)
            ->set('frontend_publish_required', $frontendInheritanceMode);

        FluentState::singleton()->withState(function (FluentState $state) use ($frontendContext, $expected) {
            $state
                ->setLocale('de_DE')
                ->setIsDomainMode(false)
                ->setIsFrontend($frontendContext);

            $page = Page::get()->find('URLSegment', 'home');

            if ($expected) {
                $this->assertNotNull($page, 'We expect the page model to have content available');

                return;
            }

            $this->assertNull($page, 'We expect the page model to not have any content available');
        });
    }

    public static function provideLocaleInheritanceMode(): array
    {
        return [
            'cms with any mode, frontend with any mode, frontend context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendContext' => true,
                'expected' => true,
            ],
            'cms with exact mode, frontend with any mode, frontend context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendContext' => true,
                'expected' => true,
            ],
            'cms with exact mode, frontend with exact mode, frontend context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendContext' => true,
                'expected' => false,
            ],
            'cms with any mode, frontend with exact mode, frontend context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendContext' => true,
                'expected' => false,
            ],
            'cms with any mode, frontend with any mode, cms context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendContext' => false,
                'expected' => true,
            ],
            'cms with any mode, frontend with exact mode, cms context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendContext' => false,
                'expected' => true,
            ],
            'cms with exact mode, frontend with exact mode, cms context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendContext' => false,
                'expected' => false,
            ],
            'cms with exact mode, frontend with any mode, cms context' => [
                'cmsInheritanceMode' => FluentExtension::INHERITANCE_MODE_EXACT,
                'frontendInheritanceMode' => FluentExtension::INHERITANCE_MODE_ANY,
                'frontendContext' => false,
                'expected' => false,
            ],
        ];
    }

    /**
     * @return array[]
     */
    public static function localePrefixUrlProvider()
    {
        return [
            'locale_with_domain'            => ['en_US', 'about', 'http://www.example.com/usa/'],
            'locale_without_domain'         => ['zh_CN', 'about', 'http://mocked/zh_CN/'],
            'locale_alone_on_domain_nested' => ['de_DE', 'staff', 'http://www.example.de/about-us/'],
        ];
    }

    /**
     * @param string|bool $cmsMode
     * @param string|bool $frontendMode
     * @param bool $isFrontend
     * @param int $expected
     * @throws ValidationException
     */
    #[DataProvider('localeFallbackProvider')]
    public function testPageVisibilityWithFallback(
        string|bool $cmsMode,
        string|bool $frontendMode,
        bool $isFrontend,
        int $expected
    ): void {
        Page::config()
            ->set('cms_localisation_required', $cmsMode)
            ->set('frontend_publish_required', $frontendMode);

        $pageId = FluentState::singleton()->withState(function (FluentState $state): int {
            $state
                ->setLocale('en_NZ')
                ->setIsDomainMode(false);

            // Intentionally avoiding fixtures as this is easier to maintain
            $page = Page::create();
            $page->Title = 'fallback-test';
            $page->URLSegment = 'fallback-test';
            $page->write();
            $page->publishRecursive();

            return $page->ID;
        });

        FluentState::singleton()->withState(function (FluentState $state) use ($isFrontend, $pageId, $expected) {
            $state
                ->setLocale('en_GB')
                ->setIsDomainMode(false)
                ->setIsFrontend($isFrontend);

            $this->assertCount($expected, Page::get()->byIDs([$pageId]));
        });
    }

    public static function localeFallbackProvider(): array
    {
        return [
            'Frontend / no inheritance' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_EXACT,
                true,
                0,
            ],
            'Frontend / fallback inheritance' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_FALLBACK,
                true,
                1,
            ],
            'Frontend / no inheritance (legacy)' => [
                true,
                true,
                true,
                0,
            ],
            'CMS / no inheritance' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_EXACT,
                false,
                0,
            ],
            'CMS / fallback inheritance' => [
                FluentExtension::INHERITANCE_MODE_FALLBACK,
                FluentExtension::INHERITANCE_MODE_EXACT,
                false,
                1,
            ],
            'CMS / no inheritance (legacy)' => [
                true,
                true,
                false,
                0,
            ],
        ];
    }

    /**
     * Regression test for #252: duplicating a page must produce a unique
     * URLSegment in every localised row, not just the base table. Covers
     * multiple locales — each locale's URLSegment is uniquified
     * independently, preserving translated slugs.
     */
    public function testDuplicateProducesUniqueLocalisedURLSegment(): void
    {
        // Set up the original page with different URLSegments per locale
        $originalID = FluentState::singleton()->withState(function (FluentState $state): int {
            $state
                ->setLocale('de_DE')
                ->setIsDomainMode(false);

            $page = Page::create();
            $page->Title = 'Innovation';
            $page->URLSegment = 'innovation';
            $page->write();

            return (int) $page->ID;
        });

        // Localise the page into en_US with a different slug
        FluentState::singleton()->withState(function (FluentState $state) use ($originalID): void {
            $state
                ->setLocale('en_US')
                ->setIsDomainMode(false);

            $page = Page::get()->byID($originalID);
            $page->URLSegment = 'innovation-en';
            $page->write();
        });

        // Duplicate while in the default locale
        FluentState::singleton()->withState(function (FluentState $state) use ($originalID): void {
            $state
                ->setLocale('de_DE')
                ->setIsDomainMode(false);

            $duplicate = Page::get()->byID($originalID)->duplicate();

            // Base table is already de-duplicated by SiteTree::validURLSegment
            $this->assertNotSame(
                'innovation',
                $duplicate->URLSegment,
                'Base URLSegment should be unique after duplicate'
            );

            // de_DE locale row matches the de-duplicated base value
            $deSegment = DB::prepared_query(
                'SELECT "URLSegment" FROM "SiteTree_Localised" WHERE "RecordID" = ? AND "Locale" = ?',
                [$duplicate->ID, 'de_DE']
            )->value();
            $this->assertNotSame(
                'innovation',
                $deSegment,
                'de_DE localised URLSegment must not collide with the original (issue #252)'
            );
            $this->assertSame(
                $duplicate->URLSegment,
                $deSegment,
                'de_DE localised URLSegment should match the de-duplicated base value'
            );

            // en_US locale row is uniquified independently from its own original
            // value, not from the de_DE slug. Expectation: 'innovation-en' -> 'innovation-en-2'.
            $usSegment = DB::prepared_query(
                'SELECT "URLSegment" FROM "SiteTree_Localised" WHERE "RecordID" = ? AND "Locale" = ?',
                [$duplicate->ID, 'en_US']
            )->value();
            $this->assertNotSame(
                'innovation-en',
                $usSegment,
                'en_US localised URLSegment must not collide with the original'
            );
            $this->assertSame(
                'innovation-en-2',
                $usSegment,
                'en_US localised URLSegment should be derived from the en_US slug, not the default locale'
            );

            // _Versions rows mirror the live localised values per locale
            $deVersion = DB::prepared_query(
                'SELECT "URLSegment" FROM "SiteTree_Localised_Versions"'
                . ' WHERE "RecordID" = ? AND "Locale" = ? ORDER BY "Version" DESC LIMIT 1',
                [$duplicate->ID, 'de_DE']
            )->value();
            $this->assertSame(
                $deSegment,
                $deVersion,
                'de_DE _Versions URLSegment should match the live row'
            );
            $usVersion = DB::prepared_query(
                'SELECT "URLSegment" FROM "SiteTree_Localised_Versions"'
                . ' WHERE "RecordID" = ? AND "Locale" = ? ORDER BY "Version" DESC LIMIT 1',
                [$duplicate->ID, 'en_US']
            )->value();
            $this->assertSame(
                $usSegment,
                $usVersion,
                'en_US _Versions URLSegment should match the live row'
            );
        });
    }

    /**
     * Regression test for #252 / #1031 review: ensure parent scoping is
     * respected — duplicating a page must only bump its URLSegment based on
     * siblings under the same parent, not unrelated pages elsewhere in the
     * tree. With nested_urls = true (the SiteTree default), /foo/bar and
     * /baz/bar are valid distinct URLs.
     */
    public function testDuplicateRespectsParentScope(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state
                ->setLocale('de_DE')
                ->setIsDomainMode(false);

            // /foo
            $foo = Page::create();
            $foo->Title = 'Foo';
            $foo->URLSegment = 'foo';
            $foo->write();

            // /bar
            $bar = Page::create();
            $bar->Title = 'Bar';
            $bar->URLSegment = 'bar';
            $bar->write();

            // /foo/innovation-2 (unrelated sibling that would falsely bump
            // a globally-scoped uniqueness check to "-3")
            $fooChild = Page::create();
            $fooChild->Title = 'Innovation';
            $fooChild->URLSegment = 'innovation-2';
            $fooChild->ParentID = $foo->ID;
            $fooChild->write();

            // /bar/innovation
            $barChild = Page::create();
            $barChild->Title = 'Innovation';
            $barChild->URLSegment = 'innovation';
            $barChild->ParentID = $bar->ID;
            $barChild->write();

            $duplicate = $barChild->duplicate();

            // Base URLSegment is parent-scoped by SiteTree itself, so it lands on innovation-2
            $this->assertSame(
                'innovation-2',
                $duplicate->URLSegment,
                'Base URLSegment should be uniquified within the same parent'
            );

            $localisedSegment = DB::prepared_query(
                'SELECT "URLSegment" FROM "SiteTree_Localised" WHERE "RecordID" = ? AND "Locale" = ?',
                [$duplicate->ID, 'de_DE']
            )->value();

            // With parent scoping in the Fluent fix, the localised value also lands on innovation-2
            // (the /foo/innovation-2 sibling lives under a different parent and is irrelevant).
            $this->assertSame(
                'innovation-2',
                $localisedSegment,
                'Localised URLSegment must be parent-scoped, not bumped by an unrelated /foo/innovation-2'
            );
            $this->assertSame(
                $duplicate->URLSegment,
                $localisedSegment,
                'Localised and base URLSegments must stay in sync'
            );
        });
    }

    /**
     * Normalises a test URL's trailing slash, but ignores complexities
     * such as whether the domain host in the UR matches Director::host()
     */
    private function normaliseTrailingSlash(string $testURL): string
    {
        if ($testURL === '/' || $testURL === '') {
            return '/';
        }
        $slash = Controller::config()->get('add_trailing_slash') ? '/' : '';
        return (rtrim($testURL, '/')) . $slash;
    }
}
