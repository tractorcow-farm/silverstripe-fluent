<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injectable;
use TractorCow\Fluent\Model\Domain;

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
     * Current domain
     *
     * @var string
     */
    protected $domain;

    /**
     * Whether the website is running in domain segmentation mode
     *
     * @var boolean
     */
    protected $isDomainMode;

    /**
     * Current locale, normally set from either a session or cookie value
     *
     * @var boolean
     */
    protected $persistLocale;

    /**
     * Whether the request is for the frontend website
     *
     * @var boolean
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
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the current domain code
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set the current domain code
     *
     * @param  string $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Gets the locale currently set within either the session or cookie
     *
     * @return string|null The locale, if available
     */
    public function getPersistLocale()
    {
        return $this->persistLocale;
    }

    /**
     * Set the current locale, normally from either a session or cookie value
     *
     * @param  string $locale
     * @return $this
     */
    public function setPersistLocale($locale)
    {
        $this->persistLocale = $locale;
        return $this;
    }

    /**
     * Get whether the website is in domain segmentation mode
     *
     * @return boolean
     */
    public function getIsDomainMode()
    {
        return $this->isDomainMode;
    }

    /**
     * Set whether the website is in domain segmentation mode
     *
     * @param  boolean $isDomainMode
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
     * @return boolean
     */
    public function getIsFrontend()
    {
        return $this->isFrontend;
    }

    /**
     * Set whether a request is for the frontend website or not
     *
     * @param  boolean $isFrontend
     * @return $this
     */
    public function setIsFrontend($isFrontend)
    {
        $this->isFrontend = $isFrontend;
        return $this;
    }
}
