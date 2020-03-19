<?php

namespace TractorCow\Fluent\Extension;

use LogicException;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Represents an object that can only exist in a single locale
 *
 * Note: You cannot use this extension on any object with the other fluent extensions
 *
 * @property int $LocaleID
 * @property FluentIsolatedExtension|DataObject $owner
 * @method Locale Locale()
 */
class FluentIsolatedExtension extends DataExtension
{
    private static $has_one = [
        'Locale' => Locale::class,
    ];

    public function onBeforeWrite()
    {
        if (empty($this->owner->LocaleID)) {
            $locale = Locale::getCurrentLocale();
            if ($locale) {
                $this->owner->LocaleID = $locale->ID;
            }
        }
    }

    /**
     * Amend freshly created DataQuery objects with the current locale and frontend status
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentDataQueryCreation(SQLSelect $query, DataQuery $dataQuery)
    {
        $state = FluentState::singleton();
        $dataQuery
            ->setQueryParam('Fluent.Locale', $state->getLocale())
            ->setQueryParam('Fluent.IsFrontend', $state->getIsFrontend());
    }

    /**
     * Safety checks for config are done on dev/build
     *
     * @throws LogicException
     */
    public function augmentDatabase()
    {
        // Safety check: This extension cannot be added with fluent or filtered extensions
        if ($this->owner->hasExtension(FluentFilteredExtension::class)
            || $this->owner->hasExtension(FluentExtension::class)
        ) {
            throw new LogicException(
                "FluentIsolatedExtension cannot be used with any other fluent extensions. Check "
                . get_class($this->owner)
            );
        }
    }

    /**
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $locale = $this->getDataQueryLocale($dataQuery);
        if (!$locale) {
            return;
        }

        // Check if we should filter in CMS (default to true)
        if (!$this->owner->config()->get('apply_isolated_locales_to_admin')
            && !FluentState::singleton()->getIsFrontend()
        ) {
            return;
        }

        // Apply filter
        $table = $this->owner->baseTable();
        $query->addWhere([
            "\"{$table}\".\"LocaleID\" = ?" => [$locale->ID],
        ]);
    }

    /**
     * Get current locale from given dataquery
     *
     * @param DataQuery $dataQuery
     * @return Locale|null
     */
    protected function getDataQueryLocale(DataQuery $dataQuery = null)
    {
        if (!$dataQuery) {
            return null;
        }

        $localeCode = $dataQuery->getQueryParam('Fluent.Locale');
        if ($localeCode) {
            return Locale::getByLocale($localeCode);
        }

        return Locale::getCurrentLocale();
    }
}
