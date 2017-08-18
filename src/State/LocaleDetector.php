<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Control\HTTPRequest;
use TractorCow\Fluent\Model\Locale;

interface LocaleDetector
{
    /**
     * Detects locale
     *
     * @param HTTPRequest $request
     * @return Locale
     */
    public function detectLocale(HTTPRequest $request);
}
