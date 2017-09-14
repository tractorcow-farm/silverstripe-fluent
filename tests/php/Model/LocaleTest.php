<?php

namespace TractorCow\Fluent\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;

class LocaleTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleTest.yml';

    public function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
    }

    public function testGetDefaultWithoutArguments()
    {
        $result = Locale::getDefault();

        $this->assertInstanceOf(Locale::class, $result);
        // Note: default_sort order is included here
        $this->assertSame('en_AU', $result->Locale, 'First Locale with IsDefault true is returned');
    }

    public function testGetDefaultWithDomainArgument()
    {
        // spanish has_one default locale
        /** @var Domain $domain */
        $domain = $this->objFromFixture(Domain::class, 'spanish');
        $result = Locale::getDefault($domain->Domain);

        $this->assertInstanceOf(Locale::class, $result);
        $this->assertSame('es_US', $result->Locale, 'Domain respects has_one to DefaultLocale');

        // kiwi doesn't has_one to any default, but the IsDefault is a child
        $domain2 = $this->objFromFixture(Domain::class, 'kiwi');
        $result2 = Locale::getDefault($domain2->Domain);

        $this->assertInstanceOf(Locale::class, $result2);
        $this->assertSame('en_AU', $result2->Locale, 'First Locale in Domain with IsDefault true is returned');
    }

    /**
     * @dataProvider isLocaleProvider
     */
    public function testIsLocale($locale, $input, $expected)
    {
        $localeObj = Locale::create()->setField('Locale', $locale);
        $this->assertSame($expected, $localeObj->isLocale($input));
    }

    /**
     * @return array[]
     */
    public function isLocaleProvider()
    {
        return [
            ['en_NZ', 'en_NZ', true],
            ['en_nz', 'en-NZ', true],
            ['en-NZ', 'en_nz', true],
            ['en-nz', 'en-nz', true],
            ['en_NZ', 'en-NZ-1990', true],
            ['en_NZ', 'en_AU', false],
            ['en_NZ', 'fr-fr-1990', false],
        ];
    }

    public function testGetNativeName()
    {
        $this->assertSame('Spanish', Locale::getByLocale('es_US')->getNativeName());
    }

    public function testGetBaseURLContainsDomainAndURLSegmentForNonDefaultLocale()
    {
        // es_ES has a domain but is not the default locale for that domain
        $result = Locale::getByLocale('es_ES')->getBaseURL();

        $this->assertContains('fluent.es', $result, "Locale's domain is in the URL");
        $this->assertContains('/es/', $result, 'URL segment for non-default locale is in the URL');
    }

    public function testGetBaseURLOnlyContainsDomainForDefaultLocale()
    {
        // es_US has a domain and is the default
        $result = Locale::getByLocale('es_US')->getBaseURL();

        $this->assertContains('fluent.es', $result, "Locale's domain is in the URL");
        $this->assertNotContains('/es-usa/', $result, 'URL segment is not in the URL for default locales');
    }
}
