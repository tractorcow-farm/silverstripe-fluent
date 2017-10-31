<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentSiteTreeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentSiteTreeExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();
        Config::modify()
            ->set(Director::class, 'alternate_base_url', 'http://mocked')
            ->set(FluentDirectorExtension::class, 'disable_default_prefix', false);

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentState::singleton()
            ->setLocale('de_DE')
            ->setIsDomainMode(false);
    }

    public function testGetLocaleInformation()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture(Page::class, 'nz-page');
        $result = $page->LocaleInformation('en_NZ');

        $this->assertInstanceOf(ArrayData::class, $result);
        $this->assertEquals([
            'Locale' => 'en_NZ',
            'LocaleRFC1766' => 'en-NZ',
            'Title' => 'English (New Zealand)',
            'LanguageNative' => 'English',
            'Language' => 'en',
            'Link' => '/newzealand/a-page/',
            'AbsoluteLink' => 'http://mocked/newzealand/a-page/',
            'LinkingMode' => 'link',
            'URLSegment' => 'newzealand'
        ], $result->toMap());
    }

    public function testGetLocales()
    {
        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture(Page::class, 'nz-page');
        $result = $page->Locales();

        $this->assertInstanceOf(ArrayList::class, $result);
        $this->assertCount(4, $result);
        $this->assertListEquals([
            ['Locale' => 'en_NZ'],
            ['Locale' => 'de_DE'],
            ['Locale' => 'en_US'],
            ['Locale' => 'es_ES'],
        ], $result);
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
    public function provideURLTests()
    {
        return [
            // Non-domain tests
            [null, 'de_DE', false, 'home', '/'],
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
            ['www.example.com', 'en_US', false, 'home', '/'],
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
     * @dataProvider provideURLTests
     * @param string $domain
     * @param string $locale
     * @param bool $prefixDisabled
     * @param string $pageName
     * @param string $url
     */
    public function testFluentURLs($domain, $locale, $prefixDisabled, $pageName, $url)
    {
        // Set state
        FluentState::singleton()
            ->setLocale($locale)
            ->setDomain($domain)
            ->setIsDomainMode(!empty($domain));
        // Set url generation option
        Config::modify()
            ->set(FluentDirectorExtension::class, 'disable_default_prefix', $prefixDisabled);

        /** @var Page|FluentSiteTreeExtension $page */
        $page = $this->objFromFixture('Page', $pageName);
        $this->assertEquals($url, $page->Link());
    }
}
