<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use TractorCow\Fluent\Extension\FluentLeftAndMainExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentStubController;

class FluentBadgeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentBadgeExtensionTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    /**
     * @var SiteTree
     */
    protected $mockPage;

    /**
     * @var Controller
     */
    protected $mockController;

    /**
     * @var FluentLeftAndMainExtension
     */
    protected $extension;

    protected function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');

            $this->mockPage = $this->objFromFixture(SiteTree::class, 'test_page');
            $this->mockController = new FluentStubController($this->mockPage->ID);
            $this->extension = new FluentLeftAndMainExtension();
            $this->extension->setOwner($this->mockController);
        });
    }

    public function testDefaultLocaleBadgeAdded()
    {
        // Publish the page in the default locale
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');
            $this->mockPage->publishRecursive();

            $result = $this->extension->getBadge($this->mockPage);
            $this->assertInstanceOf(DBHTMLText::class, $result);
            $this->assertContains('fluent-badge--default', $result->getValue());
            $this->assertContains('Localised in', $result->getValue());
            $this->assertContains('NZ', $result->getValue(), 'Badge shows owner locale');
        });
    }

    public function testInvisibleLocaleBadgeWasAdded()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            // Don't write the page in the non-default locale, then it shouldn't exist
            $newState->setLocale('de_DE');

            $result = $this->extension->getBadge($this->mockPage);
            $this->assertInstanceOf(DBHTMLText::class, $result);
            $this->assertContains('fluent-badge--invisible', $result->getValue());
            $this->assertContains('Page has no available content in', $result->getValue());
            $this->assertContains('de_DE', $result->getValue(), 'Badge shows owner locale');
        });
    }
}
