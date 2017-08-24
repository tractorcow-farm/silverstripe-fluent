<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
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

    /**
     * @var SiteTree
     */
    protected $siteTree;

    /**
     * Set up a mocked SiteTree that will return a fixed locale link (tested elsewhere). Note that this
     * takes advantage of the fact that `Director::absoluteURL` will return the input if it already
     * contains a protocol
     */
    protected function setUp()
    {
        parent::setUp();

        $this->siteTree = $this->getMockBuilder(SiteTree::class)
            ->setMethods(['LocaleLink'])
            ->getMock();

        $this->siteTree->method('LocaleLink')->willReturn('http://mocked');
    }

    public function testGetLocaleInformation()
    {
        $this->siteTree->expects($this->once())->method('LocaleLink');

        $result = $this->siteTree->LocaleInformation('en_NZ');

        $this->assertInstanceOf(ArrayData::class, $result);
        $this->assertEquals([
            'Locale' => 'en_NZ',
            'LocaleRFC1766' => 'en-NZ',
            'Title' => 'English (New Zealand)',
            'LanguageNative' => 'English',
            'Language' => 'en',
            'Link' => 'http://mocked',
            'AbsoluteLink' => 'http://mocked',
            'LinkingMode' => 'link',
            'URLSegment' => 'newzealand'
        ], $result->toMap());
    }

    public function testGetLocales()
    {
        $this->siteTree->expects($this->exactly(2))->method('LocaleLink');

        $result = $this->siteTree->Locales();

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
