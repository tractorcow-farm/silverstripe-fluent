<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;

/**
 * @extends Extension<LeftAndMain>
 */
class FluentLeftAndMainExtension extends Extension
{
    use FluentAdminTrait;

    protected function onInit()
    {
        Requirements::javascript("tractorcow/silverstripe-fluent:client/dist/js/fluent.js");
        Requirements::css("tractorcow/silverstripe-fluent:client/dist/styles/fluent.css");
    }

    /**
     * @param Form   $form
     * @param string $message
     * @return HTTPResponse|string|DBHTMLText
     * @throws HTTPResponse_Exception
     */
    public function actionComplete($form, $message)
    {
        $request = $this->owner->getRequest();
        $response = $this->owner->getResponseNegotiator()->respond($request);

        // Pass on message
        $response->addHeader('X-Status', rawurlencode($message));

        return $response;
    }
}
