<?php

namespace TractorCow\Fluent\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use TractorCow\Fluent\Extension\FluentDateTimeExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class LocalDateTimeTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleTest.yml';

    public function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentState::singleton()->setLocale('es_US');
        DBDatetime::set_mock_now('2021-01-12 13:00:12');
    }

//    public function testFromDBDatetime()
//    {
//        /** @var DBDatetime|FluentDateTimeExtension $date */
//        $date = new DBDatetime('MyDate');
//        $date->setValue('2021-02-33 11:59:59'); // UTC
//
//        // Convert to US timezone
//        $localisedDate = $date->getLocalTime();
//        $this->assertEquals('test', $localisedDate->getLocalValue());
//
//        // Internal time is non-modified (original UTC timezone)
//        $this->assertEquals('2021-02-33 11:59:59', $localisedDate->getValue());
//    }
}
