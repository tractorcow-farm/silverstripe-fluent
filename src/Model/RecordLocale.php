<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Control\Director;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ViewableData;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\State\FluentState;

/**
 * Object that represents details of a specific dataobject in a specific locale
 */
class RecordLocale extends ViewableData
{
    /**
     * @var Locale
     */
    protected $localeObject;

    /**
     * Object with possibly either fluent or filtered extension
     *
     * @var DataObject
     */
    protected $originalRecord;

    /**
     * Object in the appropriate locale (cached)
     *
     * @var DataObject
     */
    protected $record = null;

    /**
     * FluentLocale constructor.
     *
     * @param DataObject $record
     * @param Locale     $locale
     */
    public function __construct(DataObject $record, Locale $locale)
    {
        $this->originalRecord = $record;
        $this->localeObject = $locale;
    }

    /**
     * Get the locale object
     *
     * @return Locale
     */
    public function getLocaleObject()
    {
        return $this->localeObject;
    }

    /**
     * Get record (note: May not be localised in the correct locale)
     *
     * @return DataObject
     */
    protected function getOriginalRecord()
    {
        return $this->originalRecord;
    }

    /**
     * Get localised record
     *
     * @return DataObject
     */
    public function getRecord()
    {
        // Save cached result
        if (isset($this->record)) {
            return $this->record ?: null;
        }

        // If record isn't saved, use draft record
        if (!$this->originalRecord->isInDB()) {
            return $this->originalRecord;
        }

        // Reload localised record in the corret locale
        $record = FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale($this->getLocale());
            $originalRecord = $this->getOriginalRecord();
            return $originalRecord->get()->byID($originalRecord->ID);
        });

        // Reload this record in the correct locale
        $this->record = $record ?: false;
        return $record;
    }

    /**
     * Locale code
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getLocaleObject()->Locale;
    }

    /**
     * RFC1766 version of this locale
     *
     * @return string
     */
    public function getLocaleRFC1766()
    {
        return i18n::convert_rfc1766($this->getLocale());
    }

    /**
     * URLSegment of this language (not the record)
     *
     * @return string
     */
    public function getURLSegment()
    {
        return $this->getLocaleObject()->URLSegment;
    }

    /**
     * Title of this language
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getLocaleObject()->getTitle();
    }

    /**
     * @return string
     */
    public function getLanguageNative()
    {
        return $this->getLocaleObject()->getNativeName();
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return i18n::getData()->langFromLocale($this->getLocale());
    }

    /**
     * Check if this object can be viewed in this locale
     *
     * @return bool
     */
    public function canViewInLocale()
    {
        // Permission check to validate per-locale linking
        if ($this->originalRecord->hasMethod('canViewInLocale')
            && !$this->originalRecord->canViewInLocale($this->getLocale())
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get link to this object in the given locale
     *
     * @return string|null
     */
    public function getLink()
    {
        // Check if object is linkable
        if (!$this->getOriginalRecord()->hasMethod('Link')) {
            return null;
        }

        // Check if object is viewable in this locale
        // Note that this object could be filtered, or unpublished,
        // in which case fail over to locale base.
        $record = $this->getRecord();
        if (!$record || !$this->canViewInLocale()) {
            return $this->getLocaleObject()->getBaseURL();
        }

        // If original record implements LocaleLink, respect this
        // note: hasMethod() will infinite loop, so don't use this.
        if (method_exists($record, 'LocaleLink')) {
            return $record->LocaleLink($this->getLocale());
        }

        // Get link from localised record
        return $record->Link();
    }

    /**
     * @return string|null
     */
    public function getAbsoluteLink()
    {
        $link = $this->getLink();
        return $link ? Director::absoluteURL($link) : null;
    }

    /**
     * @return string
     */
    public function getLinkingMode()
    {
        if (!$this->canViewInLocale()) {
            return 'invalid';
        }

        if ($this->getLocale() === FluentState::singleton()->getLocale()) {
            return 'current';
        }

        return 'link';
    }

    /**
     * Check if object is visible (ignore published status)
     *
     * @return bool
     */
    public function IsVisible()
    {
        /** @var DataObject|FluentExtension|FluentVersionedExtension|FluentFilteredExtension $record */
        $record = $this->getOriginalRecord();

        // If object is filtered, object is not available (regardless of published status)
        if ($record->hasExtension(FluentFilteredExtension::class)
            && !$record->isAvailableInLocale($this->getLocaleObject())
        ) {
            return false;
        }
        return true;
    }

    /**
     * Check if record is visible on live
     *
     * @return bool
     */
    public function IsPublished()
    {
        // If object is filtered, object is not available (regardless of published status)
        if (!$this->IsVisible()) {
            return false;
        }

        /** @var DataObject|FluentExtension|FluentVersionedExtension|FluentFilteredExtension $record */
        $record = $this->getOriginalRecord();

        // Check if versioned item is published
        if ($record->hasExtension(FluentVersionedExtension::class)) {
            return $record->isPublishedInLocale($this->getLocale());
        }

        // Check if un-versioned item is saved
        if ($record->hasExtension(FluentExtension::class)) {
            return $record->existsInLocale($this->getLocale());
        }

        return true;
    }

    /**
     * Check if record is visible on draft
     *
     * @return bool
     */
    public function IsDraft()
    {
        /** @var DataObject|FluentExtension|FluentVersionedExtension|FluentFilteredExtension $record */
        $record = $this->getOriginalRecord();

        // Check if versioned item is saved in draft mode
        if ($record->hasExtension(FluentVersionedExtension::class)) {
            return $record->isDraftedInLocale($this->getLocale());
        }

        // Check if un-versioned item is saved
        if ($record->hasExtension(FluentExtension::class)) {
            return $record->existsInLocale($this->getLocale());
        }

        return true;
    }
}
