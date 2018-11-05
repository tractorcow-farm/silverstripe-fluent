<?php

namespace TractorCow\Fluent\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CheckboxField;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use SilverStripe\Core\Config\Config;

class LocaleTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleTest.yml';

    public function setUp()
    {
        parent::setUp();

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
        FluentState::singleton()
            ->setLocale('es_US')
            ->setDomain('fluent.co.nz')
            ->setIsDomainMode(true);
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
        /** @var Domain $domain2 */
        $domain2 = $this->objFromFixture(Domain::class, 'kiwi');
        $result2 = Locale::getDefault($domain2->Domain);

        $this->assertInstanceOf(Locale::class, $result2);
        $this->assertSame('en_AU', $result2->Locale, 'First Locale in Domain with IsDefault true is returned');
    }

    public function testGetDefaultWithCurrentDomainArgument()
    {
        // Get current default
        $result = Locale::getDefault(true); // Should use fluent.co.nz current domain
        $this->assertInstanceOf(Locale::class, $result);
        $this->assertSame('en_AU', $result->Locale, 'First Locale in Domain with IsDefault true is returned');
    }

    /**
     * @dataProvider isLocaleProvider
     * @param string $locale
     * @param string $input
     * @param bool $expected
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

        // Turning off domain mode removes domain but not prefix
        FluentState::singleton()->setIsDomainMode(false);
        $result = Locale::getByLocale('es_ES')->getBaseURL();
        $this->assertNotContains('fluent.es', $result, "Locale's domain is in the URL");
        $this->assertContains('/es/', $result, 'URL segment for non-default locale is in the URL');
    }

    public function testBaseURLPrefixDisabled()
    {
        // Default base url includes the default url segment
        $result = Locale::getDefault()->getBaseURL();
        $this->assertContains('/au/', $result);

        // Default base url shortens the default locale url base by excluding the locale's url segment
        Config::inst()->set(FluentDirectorExtension::class, 'disable_default_prefix', true);
        $result = Locale::getDefault()->getBaseURL();
        $this->assertNotContains('/au/', $result);
    }

    public function testGetBaseURLOnlyContainsDomainForPrefixDisabledDefaultLocale()
    {
        Config::inst()->set(FluentDirectorExtension::class, 'disable_default_prefix', true);

        // es_US has a domain and is the default
        $result = Locale::getByLocale('es_US')->getBaseURL();
        $this->assertContains('fluent.es', $result, "Locale's domain is in the URL");
        $this->assertNotContains('/es-usa/', $result, 'URL segment is not in the URL for default locales');

        // When domain mode is turned off, prefix is now necessary
        FluentState::singleton()->setIsDomainMode(false);
        $result = Locale::getByLocale('es_US')->getBaseURL();
        $this->assertNotContains('fluent.es', $result, "Domain not used");
        $this->assertContains('/es-usa/', $result, 'URL Segment necessary for non-global default');
    }

    public function testGetSiblings()
    {
        $esUS = Locale::getByLocale('es_US');
        $this->assertListEquals([
            [ 'Locale' => 'es_US' ],
            [ 'Locale' => 'es_ES' ],
        ], $esUS->getSiblingLocales());

        // Test without domain mode
        FluentState::singleton()->setIsDomainMode(false);

        $esUS = Locale::getByLocale('es_US');
        $this->assertListEquals([
            [ 'Locale' => 'es_US' ],
            [ 'Locale' => 'es_ES' ],
            [ 'Locale' => 'en_NZ' ],
            [ 'Locale' => 'en_AU' ],
        ], $esUS->getSiblingLocales());
    }

    public function testGetIsDefault()
    {
        $esUS = Locale::getByLocale('es_US');
        $esES = Locale::getByLocale('es_ES');
        $enNZ = Locale::getByLocale('en_NZ');
        $enAU = Locale::getByLocale('en_AU');

        // In domain mode, two are default
        $this->assertTrue($esUS->getIsDefault()); // Locale.DefaultLocale = this
        $this->assertTrue($enAU->getIsDefault()); // IsGlobalDefault = 1
        $this->assertFalse($enNZ->getIsDefault());
        $this->assertFalse($esES->getIsDefault());

        // In non-domain mode, only one default
        FluentState::singleton()->setIsDomainMode(false);
        $this->assertFalse($esUS->getIsDefault());
        $this->assertTrue($enAU->getIsDefault()); // IsGlobalDefault = 1
        $this->assertFalse($enNZ->getIsDefault());
        $this->assertFalse($esES->getIsDefault());
    }

    public function testGetIsOnlyLocale()
    {
        $esUS = Locale::getByLocale('es_US');
        $esES = Locale::getByLocale('es_ES');

        $this->assertFalse($esUS->getIsOnlyLocale());
        $this->assertFalse($esUS->getIsOnlyLocale());

        // Delete esES will affect this
        $esES->delete();
        Locale::clearCached();
        Domain::clearCached();

        $this->assertTrue($esUS->getIsOnlyLocale());

        // Turning off domain mode means this locale is joined with all the other domain locales
        FluentState::singleton()->setIsDomainMode(false);
        $this->assertFalse($esUS->getIsOnlyLocale());
    }

    public function testGlobalDefaultCheckedOnFirstLocale()
    {
        Locale::get()->removeAll();
        Locale::clearCached();

        $firstLocale = new Locale;

        $fields = $firstLocale->getCMSFields();

        /** @var CheckboxField $checkbox */
        $checkbox = $fields->fieldByName('Root.Main.IsGlobalDefault');
        $this->assertTrue((bool) $checkbox->Value());
    }

    public function testGetLocaleSuffix()
    {
        $locale = Locale::getByLocale('es_US');

        $this->assertSame('US', $locale->getLocaleSuffix());
    }
}
