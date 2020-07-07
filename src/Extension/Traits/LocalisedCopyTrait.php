<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Forms\CopyLocaleAction;

/**
 * Trait LocalisedCopyTrait
 *
 * Provides a hook which is executed when object is localised
 * This is useful in case some action needs to be executed as a part of locale copy process
 * examples:
 * - related object needs to be duplicated into the new locale
 * - event needs to be dispatched when page is localised
 * - localised copy of a page needs to be executed outside of usual flow
 *
 * @see CopyLocaleAction
 */
trait LocalisedCopyTrait
{
    /**
     * Flag indicating if the object is going through copy to locale action
     * this is needed to determine if we need to perform localised copy
     * this is an in-memory only field
     *
     * @var bool
     */
    private $localisedCopyActive = false;

    public function getLocalisedCopyActive(): bool
    {
        return $this->localisedCopyActive;
    }

    public function setLocalisedCopyActive(bool $active): self
    {
        $this->localisedCopyActive = $active;

        return $this;
    }

    /**
     * Provides a safe way to temporarily alter the global flag
     * useful for unit tests
     *
     * @param callable $callback
     * @return mixed
     */
    public function withLocalisedCopyState(callable $callback)
    {
        $active = $this->localisedCopyActive;

        try {
            return $callback();
        } finally {
            $this->setLocalisedCopyActive($active);
        }
    }

    /**
     * Extension point in @see CopyLocaleAction::handleAction()
     *
     * @param string $fromLocale
     * @param string $toLocale
     */
    public function onBeforeCopyLocale(string $fromLocale, string $toLocale): void
    {
        $this->setLocalisedCopyActive(true);
    }

    /**
     * Extension point in @see CopyLocaleAction::handleAction()
     *
     * @param string $fromLocale
     * @param string $toLocale
     */
    public function onAfterCopyLocale(string $fromLocale, string $toLocale): void
    {
        $this->setLocalisedCopyActive(false);
    }

    /**
     * This is executed in @see FluentExtension::onBeforeWrite()
     */
    public function processLocalisedCopy(): void
    {
        if (!$this->localisedCopyNeeded()) {
            return;
        }

        $this->executeLocalisedCopy();
    }

    /**
     * Determine if localised copy is needed
     *
     * @return bool
     */
    protected function localisedCopyNeeded(): bool
    {
        $stage = Versioned::get_stage() ?: Versioned::DRAFT;

        if ($stage !== Versioned::DRAFT) {
            // only draft stage is relevant for the duplication
            return false;
        }

        if ($this->isInDB() && !$this->existsInLocale()) {
            // object has a base record and doesn't have a localised record and we are localising it
            return true;
        }

        if ($this->existsInLocale() && $this->getLocalisedCopyActive()) {
            // object has a localised record and the content is being overridden
            // from another locale (via copy to/from)
            // note that we can't rely on isChanged() because writeToStage() calls forceChange()
            // which would make this condition true every time
            return true;
        }

        // all other cases should not duplicate (normal edits)
        return false;
    }

    /**
     * This method implements functionality which needs to be executed as a part of copy to locale
     * for example, relation is localised and the related object needs to be duplicated into the new locale
     */
    abstract protected function executeLocalisedCopy(): void;
}
