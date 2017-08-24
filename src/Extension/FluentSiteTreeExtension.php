<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Control\Director;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Fluent extension for SiteTree
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    /**
     * Retrieves information about this object in the specified locale
     *
     * @param string $locale The locale (code) information to request, or null to use the default locale
     * @return ArrayData Mapped list of locale properties
     */
    public function LocaleInformation($locale = null)
    {
        // Check locale and get object
        if ($locale) {
            $localeObj = Locale::getByLocale($locale);
        } else {
            $localeObj = Locale::getDefault();
        }
        $locale = $localeObj->getLocale();

        // Check linking mode
        $linkingMode = $this->getLinkingMode($locale);

        // Check link
        $link = $this->owner->LocaleLink($locale);

        // Store basic locale information
        return ArrayData::create([
            'Locale' => $locale,
            'LocaleRFC1766' => i18n::convert_rfc1766($locale),
            'URLSegment' => $localeObj->getURLSegment(),
            'Title' => $localeObj->getTitle(),
            'LanguageNative' => $localeObj->getNativeName(),
            'Language' => i18n::getData()->langFromLocale($locale),
            'Link' => $link,
            'AbsoluteLink' => $link ? Director::absoluteURL($link) : null,
            'LinkingMode' => $linkingMode
        ]);
    }

    /**
     * Templatable list of all locales
     *
     * @return ArrayList
     */
    public function Locales()
    {
        $data = [];
        foreach (Locale::getCached() as $localeObj) {
            /** @var Locale $localeObj */
            $data[] = $this->owner->LocaleInformation($localeObj->getLocale());
        }
        return ArrayList::create($data);
    }

    /**
     * Return the linking mode for the current locale and object
     *
     * @param string $locale
     * @return string
     */
    public function getLinkingMode($locale)
    {
        $linkingMode = 'link';

        if ($this->owner->hasMethod('canViewInLocale') && !$this->owner->canViewInLocale($locale)) {
            $linkingMode = 'invalid';
        } elseif ($locale === FluentState::singleton()->getLocale()) {
            $linkingMode = 'current';
        }

        return $linkingMode;
    }
}
