<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;
use TractorCow\Fluent\Extension\Traits\FluentBadgeTrait;

/**
 * Supports GridFieldDetailForm_ItemRequest with extra actions
 *
 * @extends Extension<GridFieldDetailForm_ItemRequest>
 */
class FluentGridFieldExtension extends Extension
{
    use FluentAdminTrait;
    use FluentBadgeTrait;

    /**
     * Push a badge to indicate the language that owns the current item
     *
     * @param DBField|null $badgeField
     * @see VersionedGridFieldItemRequest::Breadcrumbs()
     */
    public function updateBadge(&$badgeField)
    {
        $record = $this->owner->getRecord();
        $badgeField = $this->addFluentBadge($badgeField, $record);
    }

    public function updateFormActions(FieldList $actions)
    {
        $this->updateFluentActions($actions, $this->owner->getRecord());
    }

    /**
     * @param Form   $form
     * @param string $message
     * @return HTTPResponse|string|DBHTMLText
     */
    public function actionComplete($form, $message)
    {
        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        // Copied from GridFieldDetailForm_ItemRequest::redirectAfterSave
        $controller = $this->getToplevelController();
        $gridField = $this->owner->getGridField();
        $record = $this->owner->getRecord();
        $request = $controller->getRequest();

        // Return new view, as we can't do a "virtual redirect" via the CMS Ajax
        // to the same URL (it assumes that its content is already current, and doesn't reload)
        if ($gridField->getList()->byID($record->ID)) {
            return $this->owner->edit($request);
        }

        // Changes to the record properties might've excluded the record from
        // a filtered list, so return back to the main view if it can't be found
        $url = $request->getURL();
        $noActionURL = $controller->removeAction($url);
        $request->addHeader('X-Pjax', 'Content');
        return $controller->redirect($noActionURL, 302);
    }

    /**
     * @return Controller
     */
    private function getToplevelController()
    {
        $next = $this->owner;
        while ($next && $next instanceof GridFieldDetailForm_ItemRequest) {
            $next = $next->getController();
        }
        return $next;
    }
}
