<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Forms\CopyLocaleAction;

/**
 * Custom handling of save & publish actions
 * this is needed to ensure correct localised version records are created
 * Currently, the default save / publish actions call @see Versioned::writeWithoutVersion() which breaks
 * the Fluent functionality
 * This extension injects the code which ensures the correct localisation is executed before the default action
 *
 * @see FluentSiteTreeExtension::updateSavePublishActions()
 * @see CopyLocaleAction::handleAction()
 *
 * @extends Extension<CMSMain>
 */
class FluentCMSMainExtension extends Extension
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'save_localised_copy',
        'publish_localised_copy',
    ];

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     * @throws HTTPResponse_Exception
     * @throws ValidationException
     */
    public function save_localised_copy($data, $form)
    {
        $owner = $this->owner;

        /** @var SiteTree $record */
        $record = $this->getRecordForLocalisedAction($data, $form);

        if ($record === null) {
            return $owner->save($data, $form);
        }

        // Check edit permissions
        if (!$record->canEdit()) {
            return $owner->save($data, $form);
        }

        // Localise record (this ensures correct localised version is created)
        $record->writeToStage(Versioned::DRAFT);

        return $owner->save($data, $form);
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     * @throws HTTPResponse_Exception
     * @throws ValidationException
     */
    public function publish_localised_copy($data, $form)
    {
        $owner = $this->owner;

        /** @var SiteTree $record */
        $record = $this->getRecordForLocalisedAction($data, $form);

        if ($record === null) {
            return $owner->publish($data, $form);
        }

        // Check edit permissions
        if (!$record->canEdit()) {
            return $owner->publish($data, $form);
        }

        // Check publishing permissions
        if (!$record->canPublish()) {
            return $owner->publish($data, $form);
        }

        // Localise record (this ensures correct localised version is created)
        $record->writeToStage(Versioned::DRAFT);

        return $owner->publish($data, $form);
    }

    /**
     * @param $data
     * @param $form
     * @return DataObject|null
     */
    protected function getRecordForLocalisedAction($data, $form): ?DataObject
    {
        $id = (int) $data['ID'];
        $className = $this->owner->config()->get('tree_class');

        if (!$id || !$className) {
            // Invalid inputs
            return null;
        }

        $singleton = DataObject::singleton($className);

        if (!$singleton->hasExtension(FluentVersionedExtension::class)) {
            // Class not localised with versions
            return null;
        }

        if (!$singleton->config()->get('localise_actions_enabled')) {
            // Feature not enabled
            return null;
        }

        /** @var DataObject|FluentVersionedExtension $record */
        $record = DataObject::get_by_id($className, $id);

        if ($record === null) {
            // No record
            return null;
        }

        if ($record->isDraftedInLocale()) {
            // Feature not active, record already localised
            return null;
        }

        return $record;
    }
}
