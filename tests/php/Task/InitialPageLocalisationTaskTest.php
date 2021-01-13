<?php

namespace TractorCow\Fluent\Tests\Task;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Task\InitialPageLocalisationTask;

class InitialPageLocalisationTaskTest extends SapphireTest
{
    protected static $fixture_file = 'InitialPageLocalisationTaskTest.yml';

    protected function setUp(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            // We don't want to localise pages yet - create only base records
            $state->setLocale(null);

            parent::setUp();
        });
    }

    /**
     * @param bool $publish
     * @param int $limit
     * @param array $localised
     * @param array $published
     * @dataProvider publishStateProvider
     */
    public function testInitialPageLocalisation(bool $publish, int $limit, array $localised, array $published): void
    {
        // Check base records
        $pages = FluentState::singleton()->withState(function (FluentState $state): array {
            $state->setLocale(null);

            // Publish some base records (too much effort to set up via fixture)
            $pagesToPublish = [
                'page1',
                'page2',
            ];

            foreach ($pagesToPublish as $identifier) {
                /** @var SiteTree $page */
                $page = $this->objFromFixture(SiteTree::class, $identifier);
                $page->publishRecursive();
            }

            return SiteTree::get()
                ->sort('Title', 'ASC')
                ->column('Title');
        });

        $allPages = [
            'Page1',
            'Page2',
            'Page3',
            'Page4',
        ];

        $this->assertEquals($allPages, $pages);

        // Check localised records (should be empty)
        $pages = $this->getLocalisedPages();
        $this->assertCount(0, $pages);

        $getParams = [
            'publish' => $publish,
            'limit' => $limit,
        ];

        // Localise pages
        InitialPageLocalisationTask::singleton()->run(new HTTPRequest('GET', '/', $getParams));

        // Check localised records (should have all pages now)
        $pages = $this->getLocalisedPages();
        $this->assertEquals($localised, $pages);

        // Check published state
        $pages = FluentState::singleton()->withState(function (FluentState $state) use ($publish): array {
            $state->setLocale('en_NZ');
            $pages = SiteTree::get()->sort('Title', 'ASC');
            $publishedPages = [];

            /** @var SiteTree|FluentSiteTreeExtension $page */
            foreach ($pages as $page) {
                if (!$page->isDraftedInLocale()) {
                    continue;
                }

                if (!$page->isPublishedInLocale()) {
                    continue;
                }

                $publishedPages[] = $page->Title;
            }

            return $publishedPages;
        });

        $this->assertEquals($published, $pages);
    }

    public function publishStateProvider(): array
    {
        return [
            [
                false,
                0,
                [
                    'Page1',
                    'Page2',
                    'Page3',
                    'Page4',
                ],
                [],
            ],
            [
                true,
                0,
                [
                    'Page1',
                    'Page2',
                    'Page3',
                    'Page4',
                ],
                [
                    'Page1',
                    'Page2',
                ],
            ],
            [
                false,
                1,
                [
                    'Page1',
                ],
                [],
            ],
            [
                true,
                1,
                [
                    'Page1',
                ],
                [
                    'Page1',
                ],
            ],
            [
                false,
                2,
                [
                    'Page1',
                    'Page2',
                ],
                [],
            ],
            [
                true,
                2,
                [
                    'Page1',
                    'Page2',
                ],
                [
                    'Page1',
                    'Page2',
                ],
            ],
        ];
    }

    private function getLocalisedPages(): array
    {
        return FluentState::singleton()->withState(static function (FluentState $state): array {
            $state->setLocale('en_NZ');

            $pages = SiteTree::get()->sort('Title', 'ASC');
            $titles = [];

            /** @var SiteTree|FluentSiteTreeExtension $page */
            foreach ($pages as $page) {
                if (!$page->isDraftedInLocale()) {
                    continue;
                }

                $titles[] = $page->Title;
            }

            return $titles;
        });
    }
}
