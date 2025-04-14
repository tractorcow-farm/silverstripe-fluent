<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Core\Validation\ValidationResult;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;

/**
 * Supports GridFieldDetailForm_ItemRequest with extra actions
 *
 * @extends Extension<GridFieldDetailForm_ItemRequest>
 */
class FluentGridFieldExtension extends Extension
{
    use FluentAdminTrait;

    protected function updateFormActions(FieldList $actions)
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
