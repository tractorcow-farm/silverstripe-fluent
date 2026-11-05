<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Admin\AdminController;
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
        if ($gridField->getList()->filter('ID', $record->ID)->exists()) {
            return $this->owner->edit($request);
        }

        // The record is gone (e.g. archived) or excluded from a filtered list,
        // so go back to where the GridField lives, like
        // VersionedGridFieldItemRequest::doArchive() does. Removing the action
        // from the URL would drop the parent record of a nested GridField (e.g.
        // an element on a page) and land on the top-level list instead.
        if ($controller->hasMethod('getEditForm')) {
            $controller
                ->getEditForm()
                ->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        }
        $request->addHeader('X-Pjax', 'Content');
        $controller->getResponse()->addHeader('X-Status', rawurlencode($message));
        return $controller->redirect($this->getBackLink(), 302);
    }

    /**
     * Same as GridFieldDetailForm_ItemRequest::getBackLink(), which isn't public:
     * the top-level controller's back link if it has one, else the last
     * breadcrumb of the controller that owns the GridField.
     *
     * @return string
     */
    private function getBackLink(): string
    {
        $controller = $this->getToplevelController();
        $backLink = '';

        if ($controller instanceof AdminController) {
            $parent = $this->owner->getController();
            if ($controller->hasMethod('Backlink')) {
                $backLink = $controller->Backlink();
            } elseif ($parent->hasMethod('Breadcrumbs')) {
                $crumbs = $parent->Breadcrumbs(false);
                if ($crumbs && $crumbs->count()) {
                    $backLink = $crumbs->last()->Link;
                }
            }
        }

        return $this->owner
            ->getGridField()
            ->addAllStateToUrl($backLink ?: $controller->Link());
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
