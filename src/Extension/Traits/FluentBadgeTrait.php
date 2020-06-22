<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\HTML;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;

trait FluentBadgeTrait
{
    /**
     * Add the Fluent state badge before any existing badges and return the result
     *
     * @param DBField|null $badgeField Existing badge to merge with
     * @param DataObject $record
     * @return DBField|null
     */
    protected function addFluentBadge($badgeField, DataObject $record)
    {
        $fluentBadge = $this->getBadge($record);
        if (!$fluentBadge) {
            return $badgeField;
        }

        // Add fluent badge before any existing badges
        $newBadge = DBField::create_field(
            'HTMLFragment',
            $fluentBadge . $badgeField
        );
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
        if (!$currentLocale) {
            return null;
        }
        // Must have at least one fluent extension
        if (!$record->has_extension(FluentExtension::class) &&
            !$record->has_extension(FluentFilteredExtension::class)
        ) {
            return null;
        }
        $badge = $this->generateBadgeHTML($record, $currentLocale);
        return DBField::create_field('HTMLFragment', $badge);
    }

    /**
     * @param DataObject $record
     * @param Locale $locale
     * @param array $extraProperties
     * @return string
     */
    protected function generateBadgeHTML(
        DataObject $record,
        $locale,
        $extraProperties = []
    ) {
        $info = new RecordLocale($record, $locale);

        // Build new badge
        $badgeClasses = ['badge', 'fluent-badge'];
        if ($info->IsLive()) {
            // If the object has been localised in the current locale, show a "localised" state
            $badgeClasses[] = 'fluent-badge--default';
            $tooltip = _t(
                __TRAIT__ . '.BadgePublished',
                'Published in {locale}',
                [
                    'locale' => $locale->getTitle()
                ]
            );
        } elseif ($info->IsDraft()) {
            // Otherwise the state is that it hasn't yet been localised in the current locale, so is "invisible"
            $badgeClasses[] = 'fluent-badge--localised';
            $tooltip = _t(
                __TRAIT__ . '.BadgeDraft',
                'Saved but not visible in {locale}',
                [
                    'locale' => $locale->getTitle()
                ]
            );
        } else {
            // Otherwise the state is that it hasn't yet been localised in the current locale, so is "invisible"
            $badgeClasses[] = 'fluent-badge--invisible';
            $tooltip = _t(
                __TRAIT__ . '.BaggeInvisible',
                '{type} is not visible in this locale',
                [
                    'type' => $record->i18n_singular_name()
                ]
            );
        }

        $attributes = array_merge(
            [
                'class' => implode(' ', $badgeClasses),
                'title' => $tooltip
            ],
            $extraProperties
        );
        return HTML::createTag('span', $attributes, $locale->getBadgeLabel());
    }
}
