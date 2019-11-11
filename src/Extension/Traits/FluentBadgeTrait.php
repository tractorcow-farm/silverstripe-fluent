<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;

trait FluentBadgeTrait
{
    /**
     * Add the Fluent state badge before any existing badges and return the result
     *
     * @param DBField|null $badgeField Existing badge to merge with
     * @param DataObject   $record
     * @return DBField|null
     */
    protected function addFluentBadge($badgeField, DataObject $record)
    {
        $fluentBadge = $this->getBadge($record);
        if (!$fluentBadge) {
            return $badgeField;
        }

        // Add fluent badge before any existing badges
        $newBadge = DBField::create_field('HTMLFragment', $fluentBadge . $badgeField);
        return $newBadge;
    }

    /**
     * Given a record with Fluent enabled, return a badge that represents the state of it in the current locale
     *
     * @param DataObject|FluentExtension $record
     * @return DBField|null
     */
    public function getBadge(DataObject $record)
    {
        /** @var Locale $currentLocale */
        $currentLocale = Locale::getCurrentLocale();
        if (!$currentLocale || !$record->has_extension(FluentExtension::class)) {
            return null;
        }

        // Bulid new badge
        $badgeClasses = ['badge', 'fluent-badge'];
        if ($currentLocale->getIsDefault()) {
            // Current locale should always show a "default" or green state badge
            $badgeClasses[] = 'fluent-badge--default';
            $tooltip = _t(__CLASS__ . '.BadgeDefault', 'Default locale');
        } elseif ($record->existsInLocale($currentLocale->Locale)) {
            // If the object has been localised in the current locale, show a "localised" state
            $badgeClasses[] = 'fluent-badge--localised';
            $tooltip = _t(__CLASS__ . '.BadgeInvisible', 'Localised in {locale}', [
                'locale' => $currentLocale->getTitle(),
            ]);
        } else {
            // Otherwise the state is that it hasn't yet been localised in the current locale, so is "invisible"
            $badgeClasses[] = 'fluent-badge--invisible';
            $tooltip = _t(__CLASS__ . '.BadgeLocalised', '{type} is not visible in this locale', [
                'type' => $record->i18n_singular_name(),
            ]);
        }

        return DBField::create_field(
            'HTMLFragment',
            sprintf(
                '<span class="%s" title="%s">%s</span>',
                implode(' ', $badgeClasses),
                $tooltip,
                $record->getSourceLocale()->getBadgeLabel()
            )
        );
    }
}
