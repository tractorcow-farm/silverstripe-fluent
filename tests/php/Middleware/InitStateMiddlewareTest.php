<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use TractorCow\Fluent\Middleware\InitStateMiddleware;

class InitStateMiddlewareTest extends SapphireTest
{
    /**
     * @dataProvider isFrontendProvider
     */
    public function testGetIsFrontend($url, $expected)
    {
        $request = new HTTPRequest('GET', $url);
        $result = (new InitStateMiddleware)->getIsFrontend($request);
        $this->assertSame($expected, $result, 'isFrontend detects whether a request is for the frontend website');
    }

    /**
     * @return array[]
     */
    public function isFrontendProvider()
    {
        return [
            ['admin', false],
            ['admin/', false],
            ['dev/build', false],
            ['graphql', false],
            ['/', true],
            ['foo', true],
            ['my-blog/my-post', true],
        ];
    }
}
