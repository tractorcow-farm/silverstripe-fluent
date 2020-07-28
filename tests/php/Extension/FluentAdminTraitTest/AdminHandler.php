<?php

namespace TractorCow\Fluent\Tests\Extension\FluentAdminTraitTest;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\Form;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;

/**
 * Dummy admin handler to hold trait
 */
class AdminHandler implements TestOnly
{
    use FluentAdminTrait;
    use Injectable;

    /**
     * @param Form   $form
     * @param string $message
     * @return string
     */
    public function actionComplete($form, $message)
    {
        $record = $form->getRecord();
        $record->flushCache(true); // Note: Flushes caches E.g. FluentVersionedExtension
        return $message;
    }
}
