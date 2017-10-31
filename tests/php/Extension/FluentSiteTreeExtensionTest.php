<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;

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
            ->set(Director::class, 'alternate_base_url', 'http://mocked');
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
        $this->assertCount(2, $result);
        $this->assertListEquals([
            ['Locale' => 'en_NZ'],
            ['Locale' => 'de_DE'],
        ], $result);
    }
}
