<?php

/**
 * Data extension class for a class which should only be present in one or more locales
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentFilteredExtension extends DataExtension
{
    /**
     * Data query key necessary to turn on admin filtering
     *
     * @var string
     */
    const FILTER_ADMIN = 'Fluent.FilterAdmin';

    /**
     * Set the filter of locales to the specified locale, or array of locales
     *
     * @param string|array $locale Locale, or list of locales
     * @param string $locale... Additional locales
     */
    public function setFilteredLocales($locales)
    {
        $locales = is_array($locales) ? $locales : func_get_args();
        foreach (Fluent::locales() as $locale) {
            $field = Fluent::db_field_for_locale("LocaleFilter", $locale);
            $this->owner->$field = in_array($locale, $locales);
        }
    }

    /**
     * Gets the list of locales this items is filtered against
     *
     * @param boolean $visible Set to false to get excluded instead of included locales
     * @return array List of locales
     */
    public function getFilteredLocales($visible = true)
    {
        $locales = array();
        foreach (Fluent::locales() as $locale) {
            if ($this->owner->canViewInLocale($locale) == $visible) {
                $locales[] = $locale;
            }
        }
        return $locales;
    }

    /**
     * Determine if this object is visible (or excluded) in the specified locale
     *
     * @param string $locale Locale to check against
     * @return boolean True if the object is visible in the specified locale
     */
    public function canViewInLocale($locale)
    {
        $field = Fluent::db_field_for_locale("LocaleFilter", $locale);
        return $this->owner->$field;
    }

    public static function get_extra_config($class, $extension, $args)
    {

        // Create a separate boolean field to indicate visibility in each field
        $db = array();
        $defaults = array();

        foreach (Fluent::locales() as $locale) {
            $field = Fluent::db_field_for_locale("LocaleFilter", $locale);
            // Copy field to translated field
            $db[$field] = 'Boolean(1)';
            $defaults[$field] = '1';
        }

        return array(
            'db' => $db,
            'defaults' => $defaults
        );
    }

    public function updateCMSFields(FieldList $fields)
    {
        // Present a set of checkboxes for filtering this item by locale
        $filterField = FieldGroup::create()
            ->setTitle(
                _t('Fluent.LocaleFilter', 'Locale Filter')
            )
            ->setDescription(
                _t('Fluent.LocaleFilterDescription', 'Check a locale to show this item on that locale')
            );

        foreach (Fluent::locales() as $locale) {
            $id = Fluent::db_field_for_locale("LocaleFilter", $locale);
            $fields->removeByName($id, true); // Remove existing (in case it was auto scaffolded)
            $title = i18n::get_locale_name($locale);
            $filterField->push(new CheckboxField($id, $title));
        }

        if ($fields->hasTabSet()) {
            $fields->findOrMakeTab('Root.Locales', _t('Fluent.TABLOCALES', 'Locales'));
            $fields->addFieldToTab('Root.Locales', $filterField);
        } else {
            $fields->add($filterField);
        }
    }

    /**
     * Amend freshly created DataQuery objects with the current locale and frontend status
     *
     * @param SQLQuery
     * @param DataQuery
     */
    public function augmentDataQueryCreation(SQLQuery $query, DataQuery $dataQuery)
    {
        $dataQuery->setQueryParam('Fluent.Locale', Fluent::current_locale());
        $dataQuery->setQueryParam('Fluent.IsFrontend', Fluent::is_frontend());
    }

    public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null)
    {
        if (!FluentOldPageRedirectFix::$disableSkipIDFilter) {
            // Skip ID based filters
            if ($query->filtersOnID()) {
                return;
            }
        }

        // Skip filter in the CMS, unless filtering is explicitly turned on
        $filterAdmin = $dataQuery->getQueryParam(self::FILTER_ADMIN);
        if (!$filterAdmin) {
            $isFrontend = $dataQuery->getQueryParam('Fluent.IsFrontend');
            if ($isFrontend === null) {
                $isFrontend = Fluent::is_frontend();
            }
            if (!$isFrontend) {
                return;
            }
        }

        // Add filter for locale
        $locale = $dataQuery->getQueryParam('Fluent.Locale') ?: Fluent::current_locale();
        $query->addWhere("\"$this->ownerBaseClass\".\"LocaleFilter_{$locale}\" = 1");
    }

    /**
     * Add 'Hidden' flag to the SiteTree object if the page is not present in this locale
     *
     * @param array $flags
     */
    public function updateStatusFlags(&$flags)
    {
        if (!$this->owner->{Fluent::db_field_for_locale("LocaleFilter", Fluent::current_locale())}) {
            $flags['fluenthidden'] = array(
                'text' => _t('Fluent.BadgeHiddenShort', 'Hidden'),
                'title' => _t('Fluent.BadgeHiddenHelp', 'Page is hidden in this locale'),
            );
        }
    }
}
