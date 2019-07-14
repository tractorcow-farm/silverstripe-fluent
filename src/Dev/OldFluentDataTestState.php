<?php


namespace TractorCow\Fluent\Dev;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest\OldFluentDataExtension;

class OldFluentDataTestState implements TestState
{
    protected static $oldSiteTreeFields = [
        'URL_Segment_en_US',
        'URL_Segment_de_AT',
        'Title_en_US',
        'Title_de_AT',
        'MenuTitle_en_US',
        'MenuTitle_de_AT',
        'Content_en_US',
        'Content_de_AT',
        'MetaDescription_en_US',
        'MetaDescription_de_AT',
        'ExtraMeta_en_US',
        'ExtraMeta_de_AT',
        'ReportClass_en_US',
        'ReportClass_de_AT'
    ];

    /**
     * Called on setup
     *
     * @param SapphireTest $test
     */
    public function setUp(SapphireTest $test)
    {
//        Config::modify()->set(SiteTree::class, 'old_fluent_fields', self::$oldSiteTreeFields);
//        Config::modify()->merge(SiteTree::class, 'extensions', [OldFluentDataExtension::class]);
    }

    /**
     * Called on tear down
     *
     * @param SapphireTest $test
     */
    public function tearDown(SapphireTest $test)
    {

    }

    /**
     * Called once on setup
     *
     * @param string $class Class being setup
     */
    public function setUpOnce($class)
    {
        Config::modify()->set(SiteTree::class, 'old_fluent_fields', self::$oldSiteTreeFields);
        Config::modify()->merge(SiteTree::class, 'extensions', [OldFluentDataExtension::class]);
    }

    /**
     * Called once on tear down
     *
     * @param string $class Class being torn down
     */
    public function tearDownOnce($class)
    {

    }
}
