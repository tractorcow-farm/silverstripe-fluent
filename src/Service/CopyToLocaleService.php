<?php

namespace TractorCow\Fluent\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Copy content between locales
 */
class CopyToLocaleService
{
    use Injectable;

    /**
     * Copy data object content between locales
     *
     * @param string $class
     * @param int $id
     * @param string $fromLocale
     * @param string $toLocale
     * @throws ValidationException
     */
    public function copyToLocale(string $class, int $id, string $fromLocale, string $toLocale): void
    {
        // Load record in base locale
        FluentState::singleton()->withState(
            function (FluentState $sourceState) use ($class, $id, $fromLocale, $toLocale) {
                $sourceLocale = Locale::getByLocale($fromLocale);

                if (!$sourceLocale) {
                    return;
                }

                $sourceState->setLocale($sourceLocale->getLocale());

                // Load record in source locale
                $record = DataObject::get($class)->byID($id);

                if (!$record) {
                    return;
                }

                // Save record to other locale
                $sourceState->withState(function (FluentState $destinationState) use ($record, $fromLocale, $toLocale) {
                    $targetLocale = Locale::getByLocale($toLocale);

                    if (!$targetLocale) {
                        return;
                    }

                    $destinationState->setLocale($targetLocale->getLocale());

                    $record->invokeWithExtensions('onBeforeCopyLocale', $fromLocale, $toLocale);

                    // Write
                    /** @var DataObject|Versioned $record */
                    if ($record->hasExtension(Versioned::class)) {
                        $record->writeToStage(Versioned::DRAFT);
                    } else {
                        $record->forceChange();
                        $record->write();
                    }

                    $record->invokeWithExtensions('onAfterCopyLocale', $fromLocale, $toLocale);
                });
            }
        );
    }
}
