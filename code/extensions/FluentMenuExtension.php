<?php

/**
 * Data extension for a page which requires locale-specific menu visibility
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentMenuExtension extends SiteTreeExtension
{
    /**
     * Set the filter of locales to the specified locale, or array of locales
     *
     * @param string|array $locale Locale, or list of locales
     * @param string $locale... Additional locales
     */
    public function setMenuFilteredLocales($locales)
    {
        $locales = is_array($locales) ? $locales : func_get_args();
        foreach (Fluent::locales() as $locale) {
            $field = Fluent::db_field_for_locale("ShowInMenus", $locale);
            $this->owner->$field = in_array($locale, $locales);
        }
    }

    /**
     * Gets the list of locales this items is filtered against
     *
     * @param boolean $visible Set to false to get excluded instead of included locales
     * @return array List of locales
     */
    public function getMenuFilteredLocales($visible = true)
    {
        $locales = array();
        foreach (Fluent::locales() as $locale) {
            if ($this->owner->showInMenusInLocale($locale) == $visible) {
                $locales[] = $locale;
            }
        }
        return $locales;
    }

    /**
     * Determine if this object is visible (or excluded) in the menu in the specified locale
     *
     * @param string $locale Locale to check against
     * @return boolean True if the object is visible in the specified locale
     */
    public function showInMenusInLocale($locale)
    {
        $field = Fluent::db_field_for_locale("ShowInMenus", $locale);
        return $this->owner->$field;
    }

    public static function get_extra_config($class, $extension, $args)
    {

        // Create a separate boolean field to indicate visibility in each field
        $db = array();
        $defaults = array();

        foreach (Fluent::locales() as $locale) {
            $field = Fluent::db_field_for_locale("ShowInMenus", $locale);
            // Copy field to translated field
            $db[$field] = 'Boolean(1)';
            $defaults[$field] = '1';
        }

        return array(
            'db' => $db,
            'defaults' => $defaults
        );
    }

    public function updateSettingsFields(FieldList $fields)
    {

        // Present a set of checkboxes for filtering this item by locale
        $menuFilterField = FieldGroup::create()
            ->setTitle($this->owner->fieldLabel('ShowInMenus'))
            ->setDescription(
                _t('Fluent.LocaleMenuFilterDescription', 'Select the locales where this item is visible in the menu')
            );

        foreach (Fluent::locales() as $locale) {
            $id = Fluent::db_field_for_locale("ShowInMenus", $locale);
            $fields->removeByName($id, true); // Remove existing (in case it was auto scaffolded)
            $title = i18n::get_locale_name($locale);
            $menuFilterField->push(new CheckboxField($id, $title));
        }

        $fields->removeByName('ShowInMenus', true);
        $fields->addFieldToTab('Root.Settings', $menuFilterField, 'CanViewType');
    }

    public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null)
    {

        // When filtering my menu, swap out condition for locale specific condition
        $locale = Fluent::current_locale();
        $field = Fluent::db_field_for_locale("ShowInMenus", $locale);
        $query->replaceText(
            "\"{$this->ownerBaseClass}\".\"ShowInMenus\"",
            "\"{$this->ownerBaseClass}\".\"{$field}\""
        );
    }
}
