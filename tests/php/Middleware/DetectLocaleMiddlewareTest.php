<?php

namespace TractorCow\Fluent\Tests\Middleware;

use PHPUnit_Framework_MockObject_MockObject;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
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

    /**
     * @var string
     */
    protected $globalDefaultLocale;

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

        // Get defaults from fixture
        // Note: es_ES
        $this->globalDefaultLocale = Locale::get()->find('IsGlobalDefault', 1)->Locale;
    }

    public function testGetPersistKey()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setIsFrontend(true);
            $this->assertSame('FluentLocale', $this->middleware->getPersistKey());

            $newState->setIsFrontend(false);
            $this->assertSame('FluentLocale_CMS', $this->middleware->getPersistKey());
        });
    }

    /**
     * @dataProvider localePriorityProvider
     * @param string $url
     * @param array  $routeParams
     * @param array  $queryParams
     * @param bool   $persisted
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
        FluentState::singleton()->withState(function (FluentState $newState) {
            $request = new HTTPRequest('GET', '/');
            $newState->setIsFrontend(true);
            $newState->setLocale('en_AU');

            /** @var DetectLocaleMiddleware|PHPUnit_Framework_MockObject_MockObject $middleware */
            $middleware = $this->getMockBuilder(DetectLocaleMiddleware::class)
                ->setMethods(['getLocale', 'setPersistLocale'])
                ->getMock();

            $middleware->expects($this->never())->method('getLocale');
            $middleware->expects($this->once())->method('setPersistLocale')->with($request, 'en_AU');

            $middleware->process($request, function () {
                // no-op
            });
        });
    }

    public function testLocaleIsOnlyPersistedWhenSet()
    {
        $request = new HTTPRequest('GET', '/');
        FluentState::singleton()
            ->setLocale(null)
            ->setIsFrontend(true);

        /** @var DetectLocaleMiddleware|PHPUnit_Framework_MockObject_MockObject $middleware */
        $middleware = $this->getMockBuilder(DetectLocaleMiddleware::class)
            ->setMethods(['getLocale', 'setPersistLocale'])
            ->getMock();

        $middleware->expects($this->once())->method('getLocale')->willReturn(null);
        $middleware->expects($this->never())->method('setPersistLocale');

        $middleware->process($request, function () {
            // no-op
        });
    }

    public function testLocaleIsPersistedFromCookie()
    {
        $newLocale = 'fr_FR';
        $middleware = $this->middleware;
        $key = $middleware->getPersistKey();
        $request = new HTTPRequest('GET', '/');

        $sessionData = [];
        $sessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(['set', 'getAll'])
            ->setConstructorArgs([$sessionData])
            ->getMock();
        $sessionMock->expects($this->once())->method('set')->with($key, $newLocale);
        $sessionMock->expects($this->once())->method('getAll')->willReturn([true]);
        $request->setSession($sessionMock);

        Cookie::set($key, $newLocale);
        $middleware->process($request, function () {
            // no-op
        });

        // TODO PHPUnit's headers_sent() always returns true, so we can't check for cookie values.
        // PHPUnit has process isolation for this purpose, but we can't use it because autoloading breaks.
        // $this->assertEquals($newLocale, Cookie::get($key));
    }

    public function testLocaleIsPersistedFromSession()
    {
        $newLocale = 'fr_FR';
        $middleware = $this->middleware;
        $key = $middleware->getPersistKey();
        $request = new HTTPRequest('GET', '/');

        $sessionData = [$key => $newLocale];
        $sessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(['set', 'isStarted'])
            ->setConstructorArgs([$sessionData])
            ->getMock();

        $sessionMock->expects($this->any())->method('isStarted')->willReturn(true);
        $sessionMock->expects($this->once())->method('set')->with($key, $newLocale);
        $request->setSession($sessionMock);

        $middleware->process($request, function () {
            // no-op
        });

        // TODO PHPUnit's headers_sent() always returns true, so we can't check for cookie values.
        // PHPUnit has process isolation for this purpose, but we can't use it because autoloading breaks.
        // $this->assertEquals($newLocale, Cookie::get($key));
    }

    public function testLocaleIsNotPersistedFromSessionWhenSessionIsNotStarted()
    {
        $newLocale = 'fr_FR';
        $middleware = $this->middleware;
        $key = $middleware->getPersistKey();
        $request = new HTTPRequest('GET', '/');

        $sessionData = [$key => $newLocale];
        $sessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(['set', 'isStarted'])
            ->setConstructorArgs([$sessionData])
            ->getMock();

        $sessionMock->expects($this->any())->method('isStarted')->willReturn(false);
        $sessionMock->expects($this->never())->method('set');
        $request->setSession($sessionMock);

        $middleware->process($request, function () {
            // no-op
        });

        // TODO PHPUnit's headers_sent() always returns true, so we can't check for cookie values.
        // PHPUnit has process isolation for this purpose, but we can't use it because autoloading breaks.
        // $this->assertEquals($this->globalDefaultLocale, Cookie::get($key));
    }

    public function testLocaleIsNotPersistedFromCookieWhenPersistCookieFalse()
    {
        // TODO PHPUnit's headers_sent() always returns true, so we can't check for cookie values.
        // PHPUnit has process isolation for this purpose, but we can't use it because autoloading breaks.
        $this->markTestIncomplete();

        // $newLocale = 'fr_FR';
        // $middleware = $this->middleware;
        // $middleware->config()->update('persist_cookie', false);
        // $key = $this->middleware->getPersistKey();
        // $request = new HTTPRequest('GET', '/');
        //
        // $sessionData = [$key => $newLocale];
        // $sessionMock = $this->getMockBuilder(Session::class)
        //     ->setMethods(['set'])
        //     ->setConstructorArgs([$sessionData])
        //     ->getMock();
        // $sessionMock->expects($this->once())->method('set')->with($key, $this->globalDefaultLocale);
        // $request->setSession($sessionMock);
        //
        // $middleware->process($request, function () {
        //     // no-op
        // });
        //
        // $this->assertEquals($this->globalDefaultLocale, Cookie::get($key));
    }
}
