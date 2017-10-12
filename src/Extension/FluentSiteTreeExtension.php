<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;

/**
 * Fluent extension for SiteTree
 *
 * @property FluentSiteTreeExtension|SiteTree $owner
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    /**
     * Add the current locale's URL segment to the start of the URL
     *
     * @param string &$base
     * @param string &$action
     */
    public function updateRelativeLink(&$base, &$action)
    {
        // Don't inject locale to subpages
        if ($this->owner->ParentID && SiteTree::config()->nested_urls) {
            return;
        }

        // Get appropriate locale for this record
        $localeObj = $this->getRecordLocale();
        if (!$localeObj) {
            return;
        }

        // For blank/temp pages such as Security controller fallback to querystring
        if (!$this->owner->exists()) {
            $base = Controller::join_links(
                $base,
                '?' . FluentDirectorExtension::config()->get('query_param') . '=' . urlencode($localeObj->Locale)
            );
            return;
        }

        // Check if this locale is the default for its own domain
        if ($localeObj->getIsDefault()) {
            // For home page in the default locale, do not alter home URL
            if ($base === null || $base === RootURLController::get_homepage_link()) {
                return;
            }

            // If default locale shouldn't have prefix, then don't add prefix
            if (FluentDirectorExtension::config()->get('disable_default_prefix')) {
                return;
            }

            // For all pages on a domain where there is only a single locale,
            // then the domain itself is sufficient to distinguish that domain
            // See https://github.com/tractorcow/silverstripe-fluent/issues/75
            if ($localeObj->getIsOnlyLocale()) {
                return;
            }
        }

        // Simply join locale root with base relative URL
        $base = Controller::join_links($localeObj->getURLSegment(), $base);
    }

    /**
     * Update link to include hostname if in domain mode
     *
     * @param string $link root-relative url (includes baseurl)
     * @param string $action
     * @param string $relativeLink
     */
    public function updateLink(&$link, &$action, &$relativeLink)
    {
        // Get appropriate locale for this record
        $localeObj = $this->getRecordLocale();
        if (!$localeObj) {
            return;
        }

        // Don't rewrite outside of domain mode
        $domain = $localeObj->getDomain();
        if (!$domain) {
            return;
        }

        // Prefix with domain
        $link = Controller::join_links($domain->Link(), $link);
    }
}
