<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

// Soft dependency on CMS module
if (!class_exists(SiteTree::class)) {
    return;
}

/**
 * Fluent extension for SiteTree
 *
 * @extends FluentVersionedExtension<SiteTree&static>
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    use FluentAdminTrait;

    /**
     * Determine if status messages are enabled
     *
     * @config
     * @var bool
     */
    private static $locale_published_status_message = true;

    /**
     * Add alternate links to metatags
     *
     * @param string &$tags
     */
    public function MetaTags(&$tags)
    {
        $tags .= $this->owner->renderWith('FluentSiteTree_MetaTags');
    }

    /**
     * If this is the site home page, but still has it's own non-root url,
     * make sure we treat the root as x-default.
     *
     * @link https://github.com/tractorcow-farm/silverstripe-fluent/blob/master/docs/en/configuration.md#default-locale-options
     *
     * @return bool
     */
    public function getLinkToXDefault()
    {
        // If we disable the prefix for the default locale, this will be the default instead
        if (FluentDirectorExtension::config()->get('disable_default_prefix')) {
            return false;
        }

        // If the current domain only has one locale, there is no x-default
        $localeObj = $this->getRecordLocale();
        if ($localeObj && $localeObj->getIsOnlyLocale()) {
            return false;
        }

        // Only link to x-default on home page
        return $this->owner->URLSegment === RootURLController::get_homepage_link();
    }

    /**
     * Add the current locale's URL segment to the start of the URL
     *
     * @param string &$base
     * @param string &$action
     */
    public function updateRelativeLink(&$base, &$action)
    {

        if ($this->owner->hasMethod('disablePrependLocaleURLSegmentToLink') && $this->owner->disablePrependLocaleURLSegmentToLink()) {
            return;
        }
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
            // If default locale shouldn't have prefix, then don't add prefix
            if (FluentDirectorExtension::config()->get('disable_default_prefix')) {
                return;
            }

            // For all pages on a domain where there is only a single locale,
            // then the domain itself is sufficient to distinguish that domain
            // See https://github.com/tractorcow-farm/silverstripe-fluent/issues/75
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

        $this->updateModifiedFlag($flags);
        $this->updateArchivedFlag($flags);
        $this->updateNoSourceFlag($flags);

        // If this page does not exist it should be "invisible"
        if (!$this->isDraftedInLocale() && !$this->isPublishedInLocale()) {
            $flags['fluentinvisible'] = [
                'text'  => '',
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
        $this->updateRestoreAction($actions);
        $this->updateFluentActions($actions, $this->owner);
    }

    /**
     * Adds a UI message to indicate whether you're editing in the default locale or not
     *
     * @param FieldList $fields
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

        $message = $this->getLocaleStatusMessage();

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
     * @return string|string
     */
    protected function getLocaleStatusMessage(): ?string
    {
        $owner = $this->owner;

        if ($owner->config()->get('frontend_publish_required') === FluentExtension::INHERITANCE_MODE_EXACT) {
            // If publishing is required, then we can just check whether or not this locale has been published.
            if (!$this->isPublishedInLocale()) {
                return _t(
                    __CLASS__ . '.LOCALESTATUSFLUENTINVISIBLE',
                    'This page will not be visible in this locale until it has been published.'
                );
            }

            return null;
        }

        // If frontend publishing is *not* required, then we have multiple possibilities.
        if (!$this->isDraftedInLocale()) {
            $locale = FluentState::singleton()->getLocale();
            $info = $owner->LocaleInformation($locale);

            // Our content hasn't been drafted or published.
            if ($info->getSourceLocale()) {
                // If this Locale has a Fallback, then content might be getting inherited from that Fallback.
                return _t(
                    __CLASS__ . '.LOCALESTATUSFLUENTINHERITED',
                    'Content for this page may be inherited from another locale. If you wish you make an ' .
                    'independent copy of this page, please use one of the "Copy" actions provided.'
                );
            }

            // This locale doesn't have any content source
            return _t(
                __CLASS__ . '.LOCALESTATUSFLUENTUNKNOWN',
                'No content is available for this page. Please localise this page or provide a locale fallback.'
            );
        }

        if (!$this->isPublishedInLocale()) {
            // Our content has been saved to draft, but hasn't yet been published. That published content may be
            // coming from a Fallback.
            return _t(
                __CLASS__ . '.LOCALESTATUSFLUENTDRAFT',
                'A draft has been created for this locale, however, published content may still be ' .
                'inherited from another. To publish this content for this locale, use the "Save & publish" ' .
                'action provided.'
            );
        }

        return null;
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
     * Update SiteTree specific save/publish actions
     *
     * @param FieldList $actions
     */
    protected function updateSavePublishActions(FieldList $actions)
    {
        $owner = $this->owner;

        if (!$owner->config()->get('localise_actions_enabled')) {
            return;
        }

        if (!$owner->isInDB()) {
            return;
        }

        // There's no need to update actions in these ways if the Page has previously been drafted in this Locale.
        if ($owner->isDraftedInLocale()) {
            return;
        }

        // Actions from the base record may not be available because the base record may be considered not in draft state
        // due to localisation rules
        $baseActions = FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale(null);

            return $this->owner->getCMSActions();
        });

        /** @var CompositeField $baseMajorActions */
        $baseMajorActions = $baseActions->fieldByName('MajorActions');
        /** @var CompositeField $majorActions */
        $majorActions = $actions->fieldByName('MajorActions');

        // If another extension has removed this CompositeField then we don't need to update them.
        if ($baseMajorActions === null || $majorActions === null) {
            return;
        }

        $actionSave = $baseMajorActions->getChildren()->fieldByName('action_save');
        $actionPublish = $baseMajorActions->getChildren()->fieldByName('action_publish');
        $actionsToAdd = [];

        // Make sure no other extensions have removed this field.
        if ($actionSave !== null) {
            $actionSave->addExtraClass('btn-primary font-icon-translatable');
            $actionSave->setTitle(_t(__CLASS__ . '.LOCALECOPYTODRAFT', 'Copy to draft'));
            $actionSave->removeExtraClass('btn-outline-primary font-icon-tick');
            // Override action name as some additional processing is needed
            $actionSave->setName('action_save_localised_copy');
            $actionsToAdd[] = $actionSave;
        }

        // Make sure no other extensions have removed this field.
        if ($actionPublish !== null) {
            $actionPublish->addExtraClass('btn-primary font-icon-rocket');
            $actionPublish->removeExtraClass('btn-outline-primary font-icon-tick');
            $actionPublish->setTitle(_t(__CLASS__ . '.LOCALECOPYANDPUBLISH', 'Copy & publish'));
            // Override action name as some additional processing is needed
            $actionPublish->setName('action_publish_localised_copy');
            $actionsToAdd[] = $actionPublish;
        }

        $actionsToAdd = array_reverse($actionsToAdd);

        // Add customised base actions to actions (at the start of the set)
        foreach ($actionsToAdd as $action) {
            $majorActions->unshift($action);
        }
    }

    /**
     * Restore action needs to be removed if there is no version to revert to
     *
     * @param FieldList $actions
     */
    protected function updateRestoreAction(FieldList $actions): void
    {
        $owner = $this->owner;

        if (!$owner->existsInLocale() && $owner->hasArchiveInLocale()) {
            return;
        }

        $actions->removeByName('action_restore');
    }

    /**
     * Update modified flag to reflect localised record instead of base record
     * It doesn't make sense to have modified flag if page is not localised in current locale
     *
     * @param array $flags
     */
    protected function updateModifiedFlag(array &$flags): void
    {
        if (!array_key_exists('modified', $flags)) {
            return;
        }

        if ($this->owner->isDraftedInLocale()) {
            return;
        }

        unset($flags['modified']);
    }

    /**
     * Localise archived flag - remove archived flag if there is content on other locales
     *
     * @param array $flags
     */
    protected function updateArchivedFlag(array &$flags): void
    {
        if (!array_key_exists('archived', $flags)) {
            return;
        }

        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        if (!$this->owner->getLocaleInstances()) {
            return;
        }

        unset($flags['archived']);
    }

    /**
     * Add a flag which indicates that a page has content in other locale but the content is not being inherited
     *
     * @param array $flags
     */
    protected function updateNoSourceFlag(array &$flags): void
    {
        if (array_key_exists('archived', $flags)) {
            return;
        }

        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $owner = $this->owner;
        $info = $owner->LocaleInformation($locale);

        if ($info->getSourceLocale()) {
            return;
        }

        if (!$owner->getLocaleInstances()) {
            return;
        }

        $flags['removedfromdraft'] = [
            'text' => 'No source',
            'title' => 'This page exists in a different locale but the content is not inherited',
        ];
    }

    /**
     * @param Form   $form
     * @param string $message
     * @return HTTPResponse|string|DBHTMLText
     */
    public function actionComplete($form, $message)
    {
        return null;
    }

    /**
     * Augment Localisation tab with clickable locale links to allow easy navigation between page localisations
     *
     * @param $summaryColumns
     * @see FluentExtension::updateFluentCMSFields()
     */
    public function updateLocalisationTabColumns(&$summaryColumns)
    {
        parent::updateLocalisationTabColumns($summaryColumns);

        if (!array_key_exists('Title', $summaryColumns)) {
            return;
        }

        $controller = Controller::curr();

        if (!$controller) {
            return;
        }

        $request = $controller->getRequest();

        if (!$request) {
            return;
        }

        // This is to get URL only, getVars are not part of the URL
        $url = $this->owner->CMSEditLink();

        if (!$url) {
            return;
        }

        // Pass getVars separately so we can process them later
        $params = $request->getVars();
        $url = Director::makeRelative($url);

        $summaryColumns['Title'] = [
            'title' => 'Title',
            'callback' => function (Locale $object) use ($url, $params) {
                if (!$object->RecordLocale()) {
                    return null;
                }

                $recordLocale = $object->RecordLocale();
                $locale = $recordLocale->getLocale();
                $params['l'] = $locale;
                $localeLink = Controller::join_links($url, '?' . http_build_query($params));
                $localeTitle = Convert::raw2xml($recordLocale->getTitle());
                $render = sprintf('<a href="%s" target="_top">%s</a>', $localeLink, $localeTitle);

                return DBField::create_field('HTMLVarchar', $render);
            }
        ];
    }
}
