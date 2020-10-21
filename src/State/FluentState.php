<?php

namespace TractorCow\Fluent\State;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;

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
     * Current domain, if set
     *
     * @var string|null
     */
    protected $domain;

    /**
     * Whether the website is running in domain segmentation mode
     *
     * @var bool
     */
    protected $isDomainMode;

    /**
     * Whether the request is for the frontend website
     *
     * @var bool
     */
    protected $isFrontend;

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
        if ($locale && !is_string($locale)) {
            throw new InvalidArgumentException("Invalid locale");
        }
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the current domain code
     *
     * @return string|null
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set the current domain code
     *
     * @param string|null $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        if ($domain && !is_string($domain)) {
            throw new InvalidArgumentException("Invalid domain");
        }
        $this->domain = $domain;
        return $this;
    }

    /**
     * Get whether the website is in domain segmentation mode
     *
     * @return bool
     */
    public function getIsDomainMode()
    {
        return $this->isDomainMode;
    }

    /**
     * Set whether the website is in domain segmentation mode
     *
     * @param bool $isDomainMode
     * @return $this
     */
    public function setIsDomainMode($isDomainMode)
    {
        $this->isDomainMode = $isDomainMode;
        return $this;
    }

    /**
     * Get whether a request is for the frontend website or not
     *
     * @return bool
     */
    public function getIsFrontend()
    {
        return $this->isFrontend;
    }

    /**
     * Set whether a request is for the frontend website or not
     *
     * @param bool $isFrontend
     * @return $this
     */
    public function setIsFrontend($isFrontend)
    {
        $this->isFrontend = $isFrontend;
        return $this;
    }

    /**
     * Perform the given operation in an isolated state.
     * On return, the state will be restored, so any modifications are temporary.
     *
     * @param callable $callback Callback to run. Will be passed the nested state as a parameter
     * @return mixed Result of callback
     */
    public function withState(callable $callback)
    {
        $newState = clone $this;
        $oldLocale = i18n::get_locale(); // Backup locale in case the callback modifies this
        try {
            Injector::inst()->registerService($newState);
            return $callback($newState);
        } finally {
            Injector::inst()->registerService($this);
            i18n::set_locale($oldLocale);
        }
    }
}
