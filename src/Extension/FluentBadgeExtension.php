<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentBadgeExtension extends Extension
{
    /**
     * Push a badge to indicate the language that owns the current item
     *
     * @param DBField|null $badgeField
     */
    public function updateBadge(&$badgeField)
    {
        /** @var DataObject $record */
        $record = $this->owner->getRecord();

        $badgeField = $this->addFluentBadge($badgeField, $record);
    }

    public function updateBreadcrumbs(ArrayList $breadcrumbs)
    {
        // Ensure this doesn't run when it can't get the correct record
        if (!$this->owner->hasMethod('currentPage')) {
            return;
        }

        /** @var DataObject $record */
        $record = $this->owner->currentPage();
        if (!$record) {
            return;
        }

        // Get a possibly existing badge field from the last item in the breadcrumbs list
        $lastItem = $breadcrumbs->last();
        $badgeField = $lastItem->hasField('Extra') ? $lastItem->getField('Extra') : null;
        $newBadge = $this->addFluentBadge($badgeField, $record);

        $lastItem->setField('Extra', $newBadge);
    }

    /**
     * Add the Fluent state badge before any existing badges and return the result
     *
     * @param DBField|null $badgeField
     * @param DataObject $record
     * @return DBField|null
     */
    protected function addFluentBadge($badgeField, DataObject $record)
    {
        // Check for a fluent state before continuing
        if (!FluentState::singleton()->getLocale()
            || !$record->has_extension(FluentVersionedExtension::class)
        ) {
            return null;
        }

        $fluentBadge = $this->getBadge($record);
        // Add fluent badge before any existing badges
        $newBadge = DBField::create_field('HTMLFragment', $fluentBadge . $badgeField);

        return $newBadge;
    }

    /**
     * Given a record with Fluent enabled, return a badge that represents the state of it in the current locale
     *
     * @param DataObject $record
     * @return DBField
     */
    public function getBadge(DataObject $record)
    {
        /** @var Locale $currentLocale */
        $currentLocale = Locale::getCurrentLocale();

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
                $record->getSourceLocale()->getLocaleSuffix()
            )
        );
    }
}
