<?php

namespace TractorCow\Fluent\Tests\Middleware;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use TractorCow\Fluent\Middleware\InitStateMiddleware;

class InitStateMiddlewareTest extends SapphireTest
{
    /**
     * @dataProvider isFrontendProvider
     * @param string $url
     * @param array $getVars
     * @param string $expected
     */
    public function testGetIsFrontend($url, $getVars, $expected)
    {
        $request = new HTTPRequest('GET', $url, $getVars);
        $result = (new InitStateMiddleware)->getIsFrontend($request);
        $this->assertSame($expected, $result, 'isFrontend detects whether a request is for the frontend website');
    }

    /**
     * @return array[]
     */
    public function isFrontendProvider()
    {
        return [
            ['admin', [], false],
            ['admin/', [], false],
            ['dev/build', [], false],
            ['graphql', [], false],
            ['/', [], true],
            ['foo', [], true],
            ['my-blog/my-post', [], true],
            ['my-blog/my-post', ['CMSPreview' => 1], false],
        ];
    }
}
