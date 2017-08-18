<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use TractorCow\Fluent\State\FluentState;

/**
 * @property string $Domain
 * @property int $DefaultLocaleID
 * @method HasManyList Locales()
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

        // Don't show "Is Default" column, as this is not locale-specific default
        $localeConfig = GridFieldConfig_RelationEditor::create();
        /** @var GridFieldDataColumns $detailRow */
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

        // Use IsDefault if this is a member of this locale, or the first in the list of children
        $locales = Locale::getCached()->filter('DomainID', $this->ID);
        return $locales->filter('IsDefault', 1)->first()
            ?: $locales->first();
    }

    /**
     * @return string
     */
    public function DefaultLocaleTitle()
    {
        $locale = $this->DefaultLocale();
        if ($locale) {
            return $locale->getTitle();
        }
        return null;
    }

    /**
     * Get domain by the specifed hostname.
     * If `true` is passed, and the site is in domain mode, this will return current domain instead.
     *
     * @param string|null|true $domain
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

        // Get filtered domain
        return static::getCached()
            ->filter('Domain', $domain)
            ->first();
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
}
