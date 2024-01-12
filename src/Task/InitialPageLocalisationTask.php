<?php

namespace TractorCow\Fluent\Task;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class InitialPageLocalisationTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'initial-page-localisation-task';

    /**
     * @var string
     */
    protected $title = 'Initial page localisation';

    /**
     * @var string
     */
    protected $description = 'Intended for projects which already have some pages when Fluent module is added.' .
    ' This dev task will localise / publish all pages in the default locale. Locale setup has to be done before running this task.' .
    ' Pages which are not published will not be published, only localised. Pages which are already localised will be skipped.';

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $publish = (bool) $request->getVar('publish');
        $limit = (int) $request->getVar('limit');

        $globalLocale = Locale::get()
            ->filter(['IsGlobalDefault' => 1])
            ->sort('ID', 'ASC')
            ->first();

        if (!$globalLocale) {
            echo 'Please set global locale first!' . PHP_EOL;

            return;
        }

        $pageIds = FluentState::singleton()->withState(static function (FluentState $state) use ($limit): array {
            $state->setLocale(null);
            $pages = SiteTree::get()->sort('ID', 'ASC');

            if ($limit > 0) {
                $pages = $pages->limit($limit);
            }

            return $pages->column('ID');
        });

        $localised = FluentState::singleton()->withState(
            static function (FluentState $state) use ($globalLocale, $pageIds, $publish): int {
                $state->setLocale($globalLocale->Locale);
                $localised = 0;

                foreach ($pageIds as $pageId) {
                    /** @var SiteTree|FluentSiteTreeExtension $page */
                    $page = SiteTree::get()->byID($pageId);

                    if ($page->isDraftedInLocale()) {
                        continue;
                    }

                    $page->writeToStage(Versioned::DRAFT);
                    $localised += 1;

                    if (!$publish) {
                        continue;
                    }

                    // Check if the base record was published - if not then we don't need to publish
                    // as this would leak draft content, we only want to publish pages which were published
                    // before Fluent module was added
                    $pageId = $page->ID;
                    $isBaseRecordPublished = FluentState::singleton()->withState(
                        static function (FluentState $state) use ($pageId): bool {
                            $state->setLocale(null);
                            $page = SiteTree::get_by_id($pageId);

                            if ($page === null) {
                                return false;
                            }

                            return $page->isPublished();
                        }
                    );

                    if (!$isBaseRecordPublished) {
                        continue;
                    }

                    $page->publishRecursive();
                }

                return $localised;
            }
        );

        echo sprintf('Localised %d pages.', $localised) . PHP_EOL;
    }
}
