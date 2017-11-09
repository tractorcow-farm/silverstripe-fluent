<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use TractorCow\Fluent\State\FluentState;

/**
 * Fluent extension for SiteTree
 *
 * @property FluentSiteTreeExtension|SiteTree $owner
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    private static $disable_locale_published_status_message = false;

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

        // Don't need to prepend domain if on the same domain
        if (FluentState::singleton()->getDomain() === $domain->Domain) {
            return;
        }

        // Prefix with domain
        $link = Controller::join_links($domain->Link(), $link);
    }

    /**
     * Check the current state of the Page in the current locale and set appropriate flags.
     *
     * fluentinherited: This state means that the data viewed on this page is being inherited from one of this Locale's
     * fallbacks.
     *
     * modified: This state means that the published data viewed on the frontend is being inherited from one of this
     * Local'es fallbacks, but that there is a drafted (unique) state awaiting publication.
     *
     * @param array $flags
     */
    public function updateStatusFlags(&$flags)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        // No need to update flags if the Page is already published in this locale
        if ($this->isPublishedInLocale()) {
            return;
        }

        // We only want one of these statuses added. Inherited the the stronger of the two.
        if (!$this->isDraftedInLocale()) {
            // Add new status flag for inherited.
            $flags['fluentinherited'] = array(
                'text' => _t(__CLASS__ . '.LOCALEINHERITEDSHORT', 'Inherited'),
                'title' => _t(__CLASS__ . '.LOCALEINHERITEDHELP', 'Page is inherited from fallback locale')
            );
        } else {
            // Override the 'modified' flag so that we don't get any duplication of flags.
            $flags['modified'] = array(
                'text' => _t(__CLASS__ . '.LOCALEDRAFTEDSHORT', 'Locale drafted'),
                'title' => _t(__CLASS__ . '.LOCALEDRAFTEDHELP', 'Drafted locale edition has not been published'),
            );
        }
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        $this->addLocaleStatusMessage($fields);
    }

    /**
     * @param FieldList $actions
     */
    public function updateCMSActions(FieldList $actions)
    {
        parent::updateCMSActions($actions);

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
        if ($this->owner->config()->get('disable_locale_published_status_message')) {
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

        if (!$this->isDraftedInLocale()) {
            $message = _t(
                __CLASS__ . 'LOCALESTATUSINHERITED',
                'Content for this Page is being inherited from another Locale. If you wish you make an independent copy
                of this Page, please use one of the "Copy" actions provided.'
            );
        } else {
            $message = _t(
                __CLASS__ . 'LOCALESTATUSDRAFT',
                'A draft has been created for this Locale, however, published content is still being inherited from
                another Locale. To publish this content for this Locale, use the "Save & publish" action provided.'
            );
        }

        $fields->unshift(
            LiteralField::create(
                'LocaleStatusMessage',
                sprintf(
                    '<p class="message notice">%s</p>',
                    $message
                )
            )
        );
    }

    /**
     * @param FieldList $actions
     */
    protected function updateSavePublishActions(FieldList $actions)
    {
        /** @var \SilverStripe\Forms\CompositeField $majorActions */
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
