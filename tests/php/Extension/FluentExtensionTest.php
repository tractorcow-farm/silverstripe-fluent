<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubObject;

class FluentExtensionTest extends SapphireTest
{
    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    public function testFluentLocaleAndFrontendAreAddedToDataQuery()
    {
        FluentState::singleton()
            ->setLocale('test')
            ->setIsFrontend(true);

        $query = SiteTree::get()->dataQuery();
        $this->assertSame('test', $query->getQueryParam('Fluent.Locale'));
        $this->assertTrue($query->getQueryParam('Fluent.IsFrontend'));
    }

    public function testGetLocalisedTable()
    {
        /** @var SiteTree|FluentSiteTreeExtension $page */
        $page = new SiteTree;
        $this->assertSame('SiteTree_Localised', $page->getLocalisedTable('SiteTree'));
        $this->assertSame('SiteTree_Localised_FR', $page->getLocalisedTable('SiteTree', 'FR'));
    }

    public function testGetLinkingMode()
    {
        // Does not have a canViewInLocale method, locale is not current
        $stub = new FluentStubObject();
        $this->assertSame('link', $stub->getLinkingMode('foo'));

        // Does not have a canViewInLocale method, locale is current
        FluentState::singleton()->setLocale('foo');
        $this->assertSame('current', $stub->getLinkingMode('foo'));
    }
}
