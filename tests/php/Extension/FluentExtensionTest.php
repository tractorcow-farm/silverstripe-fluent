<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubObject;

class FluentExtensionTest extends SapphireTest
{
    protected static $required_extensions = [
        SiteTree::class => [FluentExtension::class],
        FluentStubObject::class => [FluentExtension::class],
    ];

    public function testFluentLocaleAndFrontendAreAddedToDataQuery()
    {
        FluentState::singleton()
            ->setLocale('test')
            ->setIsFrontend(true);

        /** @var \SilverStripe\ORM\DataQuery $query */
        $query = SiteTree::get()->dataQuery();
        $this->assertSame('test', $query->getQueryParam('Fluent.Locale'));
        $this->assertTrue($query->getQueryParam('Fluent.IsFrontend'));
    }

    public function testGetLocalisedTable()
    {
        $this->assertSame('SiteTree_Localised', (new SiteTree)->getLocalisedTable('SiteTree'));
        $this->assertSame('SiteTree_Localised_FR', (new SiteTree)->getLocalisedTable('SiteTree', 'FR'));
    }

    public function testLocaleLinkReturnsEmptyWhenNoLinkMethodExists()
    {
        $this->assertSame('', (new FluentStubObject)->LocaleLink('foo'));
    }
}
