<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentDirectorExtensionTest\TestController;

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

    public function testLocalizedControllerRouting()
    {
        $locale = 'en_NZ';
        FluentState::singleton()->withState(function (FluentState $state) use ($locale) {
            $state->setLocale($locale);

            $expectedTitle = sprintf('Page1 (%s)', $locale);

            /** @var Page|FluentSiteTreeExtension $page */
            $page = Page::get()->filter(['Title' => $expectedTitle])->first();
            $this->assertNotEmpty($page);
            $page->publishRecursive();
        });


        $this->get(Director::absoluteURL('nouvelle-z%C3%A9lande/TestController'));

        $this->assertContains('Test Controller! en_NZ', $this->content());
    }

    protected function setUpRoutes()
    {
        parent::setUpRoutes();

        // Add controller-name auto-routing
        $rules = Director::config()->rules;

        // Modify the rule for our test controller to include the locale parameter
        $i = array_search('admin', array_keys($rules));
        if ($i !== false) {
            $rule = [
                'nouvelle-z%C3%A9lande/TestController//$Action/$ID/$OtherID' => [
                    'Controller' => TestController::class,
                    'l' => 'en_NZ'
                ]
            ];

            $rules = array_slice($rules, 0, $i, true) + $rule + array_slice($rules, $i, null, true);
        } else {
            throw new \Exception('Could not find "admin" url rule');
        }

        // Add controller-name auto-routing
        Director::config()->set('rules', $rules);
    }
}
