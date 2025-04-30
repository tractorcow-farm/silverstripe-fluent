<?php

namespace TractorCow\Fluent\Task\ConvertTranslatableTask;

use SilverStripe\Dev\Deprecation;

/**
 * @deprecated 7.3.0 Will be removed without equivalent functionality to replace it in a future major release
 */
class Exception extends \Exception
{
    public function __construct()
    {
        parent::__construct();
        Deprecation::withSuppressedNotice(function () {
            Deprecation::notice(
                '7.3.0',
                'Will be removed without equivalent functionality to replace it in a future major release',
                Deprecation::SCOPE_CLASS
            );
        });
    }
}
