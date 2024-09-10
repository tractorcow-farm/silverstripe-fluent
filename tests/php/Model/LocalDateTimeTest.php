<?php

namespace TractorCow\Fluent\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use TractorCow\Fluent\Extension\FluentDateTimeExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\LocalDateTime;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use PHPUnit\Framework\Attributes\DataProvider;

class LocalDateTimeTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleTest.yml';

    protected function setUp(): void
    {
        // SapphireTest SetUp() sets timezone to UTC
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentState::singleton()->setLocale('es_US');
        DBDatetime::set_mock_now('2021-01-12 13:00:12');
    }

    public function testFromDBDatetime()
    {
        /** @var DBDatetime|FluentDateTimeExtension $date */
        $date = DBField::create_field('Datetime', '2021-02-18 11:59:59'); // UTC

        // Convert to US timezone
        $localisedDate = $date->getLocalTime();
        $this->assertEquals('2021-02-18 06:59:59', $localisedDate->getLocalValue()); // US time, 5 hours before UTC

        // Internal time is non-modified (original UTC timezone)
        $this->assertEquals('2021-02-18 11:59:59', $localisedDate->getValue());

        // Change to NZ timezone
        $localisedDate->setTimezone('Pacific/Auckland');
        $this->assertEquals('2021-02-19 00:59:59', $localisedDate->getLocalValue()); // NZ is 13 hours after UTC
    }

    public function testSetValue()
    {
        $date = new LocalDateTime();
        $date->setLocalValue('2021-02-19 00:59:59', 'Pacific/Auckland');

        // Internal time is non-modified (original UTC timezone)
        $this->assertEquals('Pacific/Auckland', $date->getTimezone());
        $this->assertEquals('2021-02-18 11:59:59', $date->getValue()); // Converted back to UTC for storage
        $this->assertEquals('2021-02-19 00:59:59', $date->getLocalValue()); // NZ is 13 hours after UTC

        // Convert from NZ to US time
        $date->setTimezone('America/New_York');
        $this->assertEquals('2021-02-18 06:59:59', $date->getLocalValue()); // 5 hours before UTC

        // Test normal setValue (ignores timezone, sets as per server timezone)
        // Set value 1 hour into the future
        $date->setValue('2021-02-18 12:59:59');
        $this->assertEquals('2021-02-18 12:59:59', $date->getValue());
        $this->assertEquals('2021-02-18 07:59:59', $date->getLocalValue()); // 5 hours before UTC
    }


    /**
     * Test all DB locales
     *
     * @param $locales
     */
    #[DataProvider('provideTestSwitchLocales')]
    public function testSwitchLocales($locale, $expectedTime)
    {
        /** @var DBDatetime|FluentDateTimeExtension $date */
        $date = DBField::create_field('Datetime', '2021-01-12 13:00:12'); // UTC
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $expectedTime, $date) {
            $state->setLocale($locale);
            $this->assertEquals($expectedTime, $date->getLocalTime()->getLocalValue());
        });
    }

    public static function provideTestSwitchLocales()
    {
        return [
            [
                'locale'    => 'en_NZ',
                'localTIme' => '2021-01-13 02:00:12',
            ],
            [
                'locale'    => 'en_AU',
                'localTime' => '2021-01-12 23:00:12',
            ],
            [
                'locale'    => 'es_ES',
                'localTime' => '2021-01-12 14:00:12',
            ],
            [
                'locale'    => 'es_US',
                'localTime' => '2021-01-12 08:00:12',
            ],
        ];
    }
}
