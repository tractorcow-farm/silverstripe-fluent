<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Core\Injector\Injectable;

/**
 * Stores the current fluent state
 */
class FluentState
{
    use Injectable;

    /**
     * Current locale
     *
     * @var string
     */
    protected $locale;

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }
}
