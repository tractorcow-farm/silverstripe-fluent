<?php

namespace TractorCow\Fluent\Tests\Middleware;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use TractorCow\Fluent\Middleware\InitStateMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;

class InitStateMiddlewareTest extends SapphireTest
{
    /**
     * @param string $url
     * @param array $getVars
     * @param string $expected
     */
    #[DataProvider('isFrontendProvider')]
    public function testGetIsFrontend($url, $getVars, $expected)
    {
        $request = new HTTPRequest('GET', $url, $getVars);
        $result = (new InitStateMiddleware)->getIsFrontend($request);
        $this->assertSame($expected, $result, 'isFrontend detects whether a request is for the frontend website');
    }

    /**
     * @return array[]
     */
    public static function isFrontendProvider()
    {
        return [
            ['admin', [], false],
            ['admin/', [], false],
            ['dev/build', [], false],
            ['admin/graphql', [], false],
            ['graphql', [], true],
            ['/', [], true],
            ['foo', [], true],
            ['my-blog/my-post', [], true],
            // CMS preview is front-end, and if there's no localised copy the PreviewLink will be null
            ['my-blog/my-post', ['CMSPreview' => 1], true],
        ];
    }
}
