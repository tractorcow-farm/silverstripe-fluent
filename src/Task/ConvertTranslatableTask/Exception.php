<?php

namespace TractorCow\Fluent\Task\ConvertTranslatableTask;

use SilverStripe\Dev\Deprecation;

/**
 * @deprecated 7.3.0 Will be removed without equivalent functionality to replace it
 */
class Exception extends \Exception
{
    public function __construct()
    {
        parent::__construct();
        Deprecation::withSuppressedWarning(function () {
            Deprecation::notice(
                '7.3.0',
                'Will be removed without equivalent functionality to replace it',
                Deprecation::SCOPE_CLASS
            );
        });
    }
}
