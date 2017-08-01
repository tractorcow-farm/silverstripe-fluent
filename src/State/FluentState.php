<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Control\HTTPRequest;
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

    /**
     * Current request object
     *
     * @var HTTPRequest
     */
    protected $request;

    /**
     * Get the currently active locale code
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the currently active locale code
     *
     * @param string $locale
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the currently active request
     *
     * @return HTTPRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the active request
     *
     * @param HTTPRequest $request
     * @return $this
     */
    public function setRequest(HTTPRequest $request)
    {
        $this->request = $request;
        return $this;
    }
}
