<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Fluent extension for SiteTree
 *
 * @property FluentSiteTreeExtension|SiteTree $owner
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    /**
     * Determine if status messages are enabled
     *
     * @config
     * @var bool
     */
    private static $locale_published_status_message = true;

    /**
     * Add the current locale's URL segment to the start of the URL
     *
     * @param string &$base
     * @param string &$action
     */
    public function updateRelativeLink(&$base, &$action)
    {
        // Don't inject locale to subpages
        if ($this->owner->ParentID && SiteTree::config()->get('nested_urls')) {
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

        // Don't need to prepend domain if on the same domain
        if (FluentState::singleton()->getDomain() === $domain->Domain) {
            return;
        }

        // Prefix with domain
        $link = Controller::join_links($domain->Link(), $link);
    }

    /**
     * Check whether the current page is exists in the current locale.
     *
     * If it is invisible then we add a class to show it slightly greyed out in the site tree.
     *
     * @param array $flags
     */
    public function updateStatusFlags(&$flags)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        // If this page does not exist it should be "invisible"
        if (!$this->isDraftedInLocale() && !$this->isPublishedInLocale()) {
            $flags['fluentinvisible'] = [
                'text' => '',
                'title' => '',
            ];
        }
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        parent::updateCMSFields($fields);
        $this->addLocaleStatusMessage($fields);
        $this->addLocalePrefixToUrlSegment($fields);
    }

    /**
     * @param FieldList $actions
     */
    public function updateCMSActions(FieldList $actions)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        $this->updateSavePublishActions($actions);
    }

    /**
     * Adds a UI message to indicate whether you're editing in the default locale or not
     *
     * @param  FieldList $fields
     */
    protected function addLocaleStatusMessage(FieldList $fields)
    {
        // Don't display these messages if the owner class has asked us not to.
        if (!$this->owner->config()->get('locale_published_status_message')) {
            return;
        }

        // If the field is already present, don't add it a second time.
        if ($fields->fieldByName('LocaleStatusMessage')) {
            return;
        }

        // We don't need to add a status warning if a version of this Page has already been published for this Locale.
        if ($this->isPublishedInLocale()) {
            return;
        }

        $message = null;

        if ($this->owner->config()->get('frontend_publish_required')) {
            // If publishing is required, then we can just check whether or not this locale has been published.
            if (!$this->isPublishedInLocale()) {
                $message = _t(
                    __CLASS__ . '.LOCALESTATUSFLUENTINVISIBLE',
                    'This page will not be visible in this locale until it has been published.'
                );
            }
        } else {
            // If frontend publishing is *not* required, then we have two possibilities.
            if (!$this->isDraftedInLocale()) {
                // Our content hasn't been drafted or published. If this Locale has a Fallback, then content might be
                // getting inherited from that Fallback.
                $message = _t(
                    __CLASS__ . '.LOCALESTATUSFLUENTINHERITED',
                    'Content for this page may be inherited from another locale. If you wish you make an ' .
                    'independent copy of this page, please use one of the "Copy" actions provided.'
                );
            } elseif (!$this->isPublishedInLocale()) {
                // Our content has been saved to draft, but hasn't yet been published. That published content may be
                // coming from a Fallback.
                $message = _t(
                    __CLASS__ . '.LOCALESTATUSFLUENTDRAFT',
                    'A draft has been created for this locale, however, published content may still be ' .
                    'inherited from another. To publish this content for this locale, use the "Save & publish" ' .
                    'action provided.'
                );
            }
        }

        if ($message === null) {
            return;
        }

        $fields->unshift(
            LiteralField::create(
                'LocaleStatusMessage',
                sprintf(
                    '<p class="alert alert-info">%s</p>',
                    $message
                )
            )
        );
    }

    /**
     * Add the locale's URLSegment to the URL prefix for a page's URL segment field
     *
     * @param FieldList $fields
     * @return $this
     */
    protected function addLocalePrefixToUrlSegment(FieldList $fields)
    {
        // Ensure the field is available in the list
        $segmentField = $fields->fieldByName('Root.Main.URLSegment');
        if (!$segmentField || !($segmentField instanceof SiteTreeURLSegmentField)) {
            return $this;
        }

        // Mock frontend and get link to parent object / page
        $baseURL = FluentState::singleton()
            ->withState(function (FluentState $tempState) {
                $tempState->setIsDomainMode(true);
                $tempState->setIsFrontend(true);

                // Get relative link up until the current URL segment
                if (SiteTree::config()->get('nested_urls') && $this->owner->ParentID) {
                    $parentRelative = $this->owner->Parent()->RelativeLink();
                } else {
                    $parentRelative = '/';
                    $action = null;
                    $this->updateRelativeLink($parentRelative, $action);
                }

                // Get absolute base path
                $domain = Locale::getCurrentLocale()->getDomain();
                if ($domain) {
                    $parentBase = Controller::join_links($domain->Link(), Director::baseURL());
                } else {
                    $parentBase = Director::absoluteBaseURL();
                }

                // Join base / relative links
                return Controller::join_links($parentBase, $parentRelative);
            });


        $segmentField->setURLPrefix($baseURL);
        return $this;
    }

    /**
     * @param FieldList $actions
     */
    protected function updateSavePublishActions(FieldList $actions)
    {
        /** @var CompositeField $majorActions */
        $majorActions = $actions->fieldByName('MajorActions');

        // If another extension has removed this CompositeField then we don't need to update them.
        if ($majorActions === null) {
            return;
        }

        // There's no need to update actions in these ways if the Page has previously been published in this Locale.
        if ($this->isPublishedInLocale()) {
            return;
        }

        $isDraftedInLocale = $this->isDraftedInLocale();
        $actionSave = $majorActions->getChildren()->fieldByName('action_save');
        $actionPublish = $majorActions->getChildren()->fieldByName('action_publish');

        // Make sure no other extensions have removed this field.
        if ($actionSave !== null) {
            // Check that the Page doesn't have a current draft.
            if (!$isDraftedInLocale) {
                $actionSave->addExtraClass('btn-primary font-icon-save');
                $actionSave->setTitle(_t(__CLASS__ . '.LOCALECOPYTODRAFT', 'Copy to draft'));
                $actionSave->removeExtraClass('btn-outline-primary font-icon-tick');
            }
        }

        // Make sure no other extensions have removed this field.
        if ($actionPublish !== null) {
            $actionPublish->addExtraClass('btn-primary font-icon-rocket');
            $actionPublish->removeExtraClass('btn-outline-primary font-icon-tick');

            if ($isDraftedInLocale) {
                $actionPublish->setTitle(_t('SilverStripe\CMS\Model\SiteTree.BUTTONSAVEPUBLISH', 'Save & publish'));
            } else {
                $actionPublish->setTitle(_t(__CLASS__ . '.LOCALECOPYANDPUBLISH', 'Copy & publish'));
            }
        }
    }
}
