<?php

namespace TractorCow\Fluent\Tests\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;

class LocaleTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleTest.yml';

    public function testGetDefaultWithoutArguments()
    {
        $result = Locale::getDefault();

        $this->assertInstanceOf(Locale::class, $result);
        // Note: default_sort order is included here
        $this->assertSame('en_AU', $result->Locale, 'First Locale with IsDefault true is returned');
    }

    public function testGetDefaultWithDomainArgument()
    {
        $domain = $this->objFromFixture(Domain::class, 'spanish');
        $result = Locale::getDefault($domain->Domain);

        $this->assertInstanceOf(Locale::class, $result);
        $this->assertSame('es_US', $result->Locale, 'First Locale in Domain with IsDefault true is returned');
    }
}
