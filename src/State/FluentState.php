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

    /**
     * Current domain
     *
     * @var string
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
     * @param string $domain
     * @return $this
     */
    public function setDomain($domain)
    {
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
}
