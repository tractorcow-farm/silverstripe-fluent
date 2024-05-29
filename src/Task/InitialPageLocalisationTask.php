<?php

namespace TractorCow\Fluent\Task;

use SilverStripe\CMS\Model\SiteTree;

class InitialPageLocalisationTask extends InitialDataObjectLocalisationTask
{
    /**
     * @var string
     */
    private static $segment = 'initial-page-localisation-task';

    /**
     * @var string
     */
    protected $title = 'Initial SiteTree localisation';

    /**
     * @var string
     */
    protected $description = 'Intended for projects which already have some Pages when Fluent module is added.' .
    ' This dev task will localise / publish all Pages in the default locale. Locale setup has to be done before running this task.' .
    ' Pass limit=N to limit number of records to localise. Pass publish=1 to force publishing of localised Pages.' .
    ' Regardless, Pages which were not already published will not be published, only localised. Pages which were already localised will always be skipped.';

    /**
     * @var string[]
     */
    protected $include_only_classes = [
        SiteTree::class
    ];

    /**
     * @var string[]
     */
    protected $exclude_classes = [];

    /**
     * Soft dependency on CMS module
     * @return bool
     */
    function isEnabled(): bool
    {
        return class_exists(SiteTree::class) && parent::isEnabled();
    }
}
