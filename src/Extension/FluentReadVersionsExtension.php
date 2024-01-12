<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use TractorCow\Fluent\State\FluentState;

/**
 * Available since SilverStripe 4.3.x
 *
 * @extends Extension<ReadVersions>
 */
class FluentReadVersionsExtension extends Extension
{
    /**
     * Set a filter on the current locale in SourceLocale alias field. This field is added by
     * FluentExtension::augmentSQL, and this list is used in the history viewer via a GraphQL query.
     *
     * @param DataList &$list
     */
    public function updateList(DataList &$list)
    {
        /** @var DataObject $singleton */
        $singleton = Injector::inst()->get($list->dataClass());
        $locale = $list->dataQuery()->getQueryParam('Fluent.Locale') ?: FluentState::singleton()->getLocale();
        if (!$singleton->hasExtension(FluentExtension::class)
            || !$singleton->hasField('SourceLocale')
            || !$locale
        ) {
            return;
        }

        $locale = FluentState::singleton()->getLocale();

        $query = $list->dataQuery();
        $query->having(['"SourceLocale" = ?' => $locale]);

        $list = $list->setDataQuery($query);
    }
}
