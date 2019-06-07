<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/7/19
 * Time: 7:17 PM
 * To change this template use File | Settings | File Templates.
 */

namespace TractorCow\Fluent\Extension;


use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use TractorCow\Fluent\State\FluentState;

class FluentGridFieldDetailForm_ItemRequest extends DataExtension
{

    public function updateItemEditForm(Form $form)
    {

        /* @var $state FluentState */
        $state = Injector::inst()->get(FluentState::class);
        $locale = $state->getLocale();
        $form->Fields()->push(HiddenField::create('l')->setValue($locale));

    }


}
