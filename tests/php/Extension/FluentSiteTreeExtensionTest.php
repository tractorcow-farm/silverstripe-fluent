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
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubObject;

class FluentSiteTreeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentSiteTreeExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [FluentSiteTreeExtension::class],
        FluentStubObject::class => [FluentSiteTreeExtension::class],
    ];

    protected function setUp()
    {
        parent::setUp();

        Config::inst()->update(Director::class, 'alternate_base_url', 'http://mocked');
    }

    public function testGetLocaleInformation()
    {
        $result = $this->objFromFixture(Page::class, 'nz-page')->LocaleInformation('en_NZ');

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
        $result = $this->objFromFixture(Page::class, 'nz-page')->Locales();

        $this->assertInstanceOf(ArrayList::class, $result);
        $this->assertCount(2, $result);
        $this->assertDOSEquals([
            ['Locale' => 'en_NZ'],
            ['Locale' => 'de_DE'],
        ], $result);
    }

    public function testGetLinkingMode()
    {
        // Does not have a canViewInLocale method, locale is not current
        $this->assertSame('link', (new FluentStubObject)->getLinkingMode('foo'));

        // Does not have a canViewInLocale method, locale is current
        FluentState::singleton()->setLocale('foo');
        $this->assertSame('current', (new FluentStubObject)->getLinkingMode('foo'));
    }
}
