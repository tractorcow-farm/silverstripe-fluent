<?php

namespace TractorCow\Fluent\Control;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;
use TractorCow\Fluent\Model\Locale;

class LocaleAdmin extends ModelAdmin
{
    private static $url_segment = 'locales';

    private static $menu_title = 'Locales';

    private static $managed_models = [
        Locale::class,
    ];

    protected function init() {
        parent::init();
        Requirements::themedJavascript('client/dist/js/fluent.js');
    }
}
