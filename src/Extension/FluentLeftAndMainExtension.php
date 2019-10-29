<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * @property LeftAndMain $owner
 */
class FluentLeftAndMainExtension extends Extension
{
    use FluentAdminTrait;

    public function init()
    {
        Requirements::javascript("tractorcow/silverstripe-fluent:client/dist/js/fluent.js");
        Requirements::css("tractorcow/silverstripe-fluent:client/dist/styles/fluent.css");
    }
}
