<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TranslatedPage extends SiteTree implements TestOnly
{
    private static $db = [
        'TranslatedValue' => 'Varchar'
    ];

    private static $old_fluent_fields = [
      'TranslatedValue_en_US',
      'TranslatedValue_de_AT'
    ];

    private static $table_name = 'FluentTestPage';

    public function canView($member = null)
    {
        return true;
    }

    public function canPublish($member = null)
    {
        return true;
    }
}
