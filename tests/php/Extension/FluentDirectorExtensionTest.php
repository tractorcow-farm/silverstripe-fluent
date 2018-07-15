<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Dev\FunctionalTest;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;

/**
 * Class FluentDirectorExtensionTest
 *
 * @package TractorCow\Fluent\Tests\Extension
 */
class FluentDirectorExtensionTest extends FunctionalTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'FluentDirectorExtensionTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
            FluentFilteredExtension::class,
        ],
        Director::class => [
            FluentDirectorExtension::class,
        ],
    ];

    public function setUp() // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        parent::setUp();

        $this->logInWithPermission('ADMIN');
    }

    public function testVisitUrlByLocaleWithMultiByteCharacter()
    {
        $locale = 'en_NZ';
        FluentState::singleton()->withState(function (FluentState $state) use ($locale) {
            $state->setLocale($locale);

            $expectedTitle = sprintf('Page1 (%s)', $locale);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = Page::get()->filter(['Title' => $expectedTitle])->first();
            $this->assertNotEmpty($page);
            $page->publishRecursive();

            $this->get($page->AbsoluteLink());

            $this->assertContains(sprintf('<title>%s', $expectedTitle), $this->content());
        });
    }
}
