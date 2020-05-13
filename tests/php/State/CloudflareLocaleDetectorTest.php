<?php

namespace TractorCow\Fluent\Tests\State;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\CloudflareLocaleDetector;

class CloudflareLocaleDetectorTest extends SapphireTest
{
    protected static $fixture_file = 'CloudflareLocaleDetectorTest.yml';

    public function testPositives()
    {
        $middleware = CloudflareLocaleDetector::create();
        $request = new HTTPRequest('GET', 'about-us/');

        // NZ
        $request->addHeader('CF-IPCountry', 'NZ');
        $result = $middleware->detectLocale($request);
        $this->assertInstanceOf(Locale::class, $result);
        $this->assertEquals('en_NZ', $result->Locale);

        // US
        $request->addHeader('CF-IPCountry', 'us');
        $result = $middleware->detectLocale($request);
        $this->assertInstanceOf(Locale::class, $result);
        $this->assertEquals('es_US', $result->Locale);
    }

    public function testNegatives()
    {
        $middleware = CloudflareLocaleDetector::create();
        $request = new HTTPRequest('GET', 'about-us/');

        // AU
        $request->addHeader('CF-IPCountry', 'AU');
        $result = $middleware->detectLocale($request);
        $this->assertNull($result);

        // XX (unknown)
        $request->addHeader('CF-IPCountry', 'XX');
        $result = $middleware->detectLocale($request);
        $this->assertNull($result);

        // T1 (tor)
        $request->addHeader('CF-IPCountry', 'T1');
        $result = $middleware->detectLocale($request);
        $this->assertNull($result);
    }
}
