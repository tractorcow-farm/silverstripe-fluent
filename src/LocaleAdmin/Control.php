<?php

namespace TractorCow\Fluent\Control;

use SilverStripe\Admin\ModelAdmin;
use TractorCow\Fluent\Model\Locale;

class LocaleAdmin extends ModelAdmin
{
    private static $url_segment = 'locales';

    private static $menu_title = 'Locales';

    private static $managed_models = [
        Locale::class,
    ];
}
