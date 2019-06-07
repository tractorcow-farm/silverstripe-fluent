<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use TractorCow\Fluent\State\FluentState;

class FluentLeftAndMainExtension extends Extension
{
    public function init()
    {
        Requirements::javascript("tractorcow/silverstripe-fluent:client/dist/js/fluent.js");
        Requirements::css("tractorcow/silverstripe-fluent:client/dist/styles/fluent.css");
    }

    public function updateEditForm(Form $form)
    {
        /* @var $state FluentState */
        $state = Injector::inst()->get(FluentState::class);
        $locale = $state->getLocale();
        $form->Fields()->push(HiddenField::create('l')->setValue($locale));
    }

}
