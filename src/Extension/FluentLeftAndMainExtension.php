<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\View\Requirements;

class FluentLeftAndMainExtension extends Extension
{
    public function init()
    {
        $module = ModuleLoader::getModule('tractorcow/silverstripe-fluent');
        Requirements::javascript($module->getRelativeResourcePath("client/dist/js/fluent.js"));
        Requirements::css($module->getRelativeResourcePath("client/dist/styles/fluent.css"));
    }
}
