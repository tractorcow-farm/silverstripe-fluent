<?php

namespace TractorCow\Fluent\Task;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;

class InitialPageLocalisationTask extends InitialDataObjectLocalisationTask
{
    protected static string $commandName = 'initial-page-localisation-task';

    protected string $title = 'Initial SiteTree localisation';

    protected static string $description = 'Intended for projects which already have some Pages when Fluent module is added';

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

    public static function getHelp(): string
    {
        $isCli = Director::is_cli();
        $limit = $isCli ? '--limit=N' : 'limit=N';
        $publish = $isCli ? '--publish' : 'publish=1';
        return <<<TXT
        This dev task will localise / publish all Pages in the default locale. Locale setup has to be done before running this task.
        Pass <info>$limit</> to limit number of records to localise. Pass <info>$publish</> to force publishing of localised Pages.
        Regardless, Pages which were not already published will not be published, only localised. Pages which were already localised will always be skipped.
        TXT;
    }
}
