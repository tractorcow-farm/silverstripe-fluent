<?php

namespace TractorCow\Fluent\Tests\Middleware;

use PHPUnit_Framework_MockObject_MockObject;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Middleware\DetectLocaleMiddleware;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class DetectLocaleMiddlewareTest extends SapphireTest
{
    protected static $fixture_file = 'DetectLocaleMiddlewareTest.yml';

    /**
     * @var Stub\DetectLocaleMiddlewareSpy
     */
    protected $middleware;

    protected function setUp()
    {
        parent::setUp();
        $this->middleware = new Stub\DetectLocaleMiddlewareSpy;

        Config::modify()->set(FluentDirectorExtension::class, 'query_param', 'l');

        // Enable localedetection
        FluentDirectorExtension::config()->set('detect_locale', true);

        // Clear cache
        Locale::clearCached();
        Domain::clearCached();
    }

    public function testGetPersistKey()
    {
        $state = FluentState::singleton();
        $state->setIsFrontend(true);
        $this->assertSame('FluentLocale', $this->middleware->getPersistKey());

        $state->setIsFrontend(false);
        $this->assertSame('FluentLocale_CMS', $this->middleware->getPersistKey());
    }

    /**
     * @dataProvider localePriorityProvider
     * @param string $url
     * @param array $routeParams
     * @param array $queryParams
     * @param bool $persisted
     * @param string $header
     * @param string $expected
     */
    public function testGetLocalePriority($url, $routeParams, $queryParams, $persisted, $header, $expected)
    {
        $request = new HTTPRequest('GET', $url, $queryParams);
        $request->setRouteParams($routeParams);
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $this->middleware->setPersistLocale($request, null);

        if ($persisted) {
            $this->middleware->setPersistLocale($request, $persisted);
        }
        if ($header) {
            $request->addHeader('Accept-Language', $header);
        }

        $this->assertSame($expected, $this->middleware->getLocale($request));
    }

    /**
     * @return array[] List of tests with arguments: $url, $routeParams, $queryParams, $persisted, $header, $expected
     */
    public function localePriorityProvider()
    {
        return [/*
            // First priority: controller routing params
            ['/nz/foo', ['l' => 'en_NZ'], ['l' => 'en_AU'], 'fr_FR', 'es-US', 'en_NZ'],
            // Second priority: request params
            ['/foo', [], ['l' => 'en_AU'], 'fr_FR', 'es-US', 'en_AU'],
            // Third priority: persisted locale
            ['/foo', [], [], 'fr_FR', 'es-US', 'fr_FR'],
            // Default to the default locale when not on the homepage
            ['/foo', [], [], null, 'es-US', 'es_ES'],*/
            // Home page only - fourth priority is request header
            ['/', [], [], null, 'es-US', 'es_US'],
        ];
    }

    public function testLocaleIsAlwaysPersistedEvenIfNotSetByTheMiddleware()
    {
        $request = new HTTPRequest('GET', '/');
        FluentState::singleton()->setLocale('dummy');

        /** @var DetectLocaleMiddleware|PHPUnit_Framework_MockObject_MockObject $middleware */
        $middleware = $this->getMockBuilder(DetectLocaleMiddleware::class)
            ->setMethods(['getLocale', 'setPersistLocale'])
            ->getMock();

        $middleware->expects($this->never())->method('getLocale');
        $middleware->expects($this->once())->method('setPersistLocale')->with($request, 'dummy');

        $middleware->process($request, function () {
            // no-op
        });
    }
}
