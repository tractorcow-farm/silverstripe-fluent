<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class FluentLeftAndMainExtension extends Extension
{
    public function init()
    {
        Requirements::javascript("tractorcow/silverstripe-fluent:client/dist/js/fluent.js");
        Requirements::css("tractorcow/silverstripe-fluent:client/dist/styles/fluent.css");
    }
}
