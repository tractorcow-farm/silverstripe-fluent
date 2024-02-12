<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Control\Director;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\HasManyList;
use TractorCow\Fluent\State\FluentState;

/**
 * @property string $Domain
 * @property int $DefaultLocaleID
 * @method HasManyList<Locale> Locales()
 */
class Domain extends DataObject
{
    use CachableModel;

    private static $table_name = 'Fluent_Domain';

    private static $singular_name = 'Domain';

    private static $plural_name = 'Domains';

    private static $summary_fields = [
        'Domain' => 'Domain',
        'DefaultLocaleTitle' => 'Default Locale',
        'LocaleNames' => 'Locales',
    ];

    private static $db = [
        'Domain' => 'Varchar(150)',
    ];

    private static $has_many = [
        'Locales' => Locale::class,
    ];

    private static $has_one = [
        'DefaultLocale' => Locale::class,
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(
            new TabSet("Root", new Tab("Main"))
        );

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'Domain',
                _t(__CLASS__.'.DOMAIN_HOSTNAME', 'Domain Hostname')
            )->setAttribute('placeholder', 'www.mydomain.com')
        );

        if (!$this->exists()) {
            // Don't allow relations to be added until the record itself is saved
            $fields->addFieldToTab(
                'Root.Locales',
                LiteralField::create(
                    'UnsavedNotice',
                    '<p>' . _t(__CLASS__ . '.UnsavedNotice', "You can add locales once you've saved the domain.")
                )
            );
            $this->extend('updateCMSFields', $fields);
            return $fields;
        }

        // Don't show "Is Default" column, as this is not locale-specific default
        $localeConfig = GridFieldConfig_RelationEditor::create();
        $detailRow = $localeConfig->getComponentByType(GridFieldDataColumns::class);
        $detailRow->setDisplayFields([
            'Title' => 'Title',
            'Locale' => 'Locale',
            'URLSegment' => 'URLSegment',
        ]);

        $localeField = GridField::create(
            'Locales',
            _t(__CLASS__.'.DOMAIN_LOCALES', 'Locales'),
            $this->Locales(),
            $localeConfig
        );
        $fields->addFieldToTab('Root.Locales', $localeField);


        if ($this->Locales()->count() > 0) {
            $defaultField = DropdownField::create(
                'DefaultLocaleID',
                _t(__CLASS__.'.DEFAULT', 'Default locale'),
                $this->Locales()->map('ID', 'Title')
            )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)'));
            $fields->addFieldToTab('Root.Locales', $defaultField);
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function getTitle()
    {
        return $this->getField('Domain');
    }

    /**
     * Get default locale for this domain
     *
     * @return Locale
     */
    public function DefaultLocale()
    {
        // Return explicit default locale
        if ($this->DefaultLocaleID) {
            $locale = Locale::getCached()->byID($this->DefaultLocaleID);
            if ($locale) {
                return $locale;
            }
        }

        // Use IsGlobalDefault if this is a member of this locale, or the first in the list of children
        $locales = Locale::getCached()->filter('DomainID', $this->ID);
        return $locales->filter('IsGlobalDefault', 1)->first()
            ?: $locales->first();
    }

    /**
     * @return DBField|null
     */
    public function DefaultLocaleTitle()
    {
        $locale = $this->DefaultLocale();
        if ($locale) {
            return DBField::create_field('Text', $locale->getTitle());
        }
        return null;
    }

    /**
     * Get domain by the specifed hostname.
     * If `true` is passed, and the site is in domain mode, this will return current domain instead.
     *
     * @param string|true|Domain $domain
     * @return Domain
     */
    public static function getByDomain($domain)
    {
        // Map true -> actual domain
        if ($domain === true) {
            $state = FluentState::singleton();
            $domain = $state->getIsDomainMode() ? $state->getDomain() : null;
        }

        // No domain found
        if (!$domain) {
            return null;
        }

        if ($domain instanceof Domain) {
            return $domain;
        }

        // Get filtered domain
        return static::getCached()
            ->filter('Domain', $domain)
            ->first();
    }

    /**
     * Get locales for this domain
     *
     * @return ArrayList<Locale>
     */
    public function getLocales()
    {
        return Locale::getCached()->filter('DomainID', $this->ID);
    }

    /**
     * Get comma separated list of locales
     *
     * @return string
     */
    public function getLocaleNames()
    {
        return implode(', ', $this->getLocales()->column('Locale'));
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Remove default locale if not a sub-locale
        $default = $this->DefaultLocale();
        if (!$default) {
            return;
        }

        // Unset if not in child list
        $inList = $this->Locales()->byID($default->ID);
        if (!$inList) {
            $this->DefaultLocaleID = 0;
        }
    }

    /**
     * Get link to this domain
     *
     * @return string
     */
    public function Link()
    {
        return Director::protocol() . $this->Domain;
    }
}
