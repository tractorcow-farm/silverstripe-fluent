<?php

namespace TractorCow\Fluent\View;

use SilverStripe\View\TemplateGlobalProvider;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentTemplateGlobalProvider implements TemplateGlobalProvider
{
    public static function get_template_global_variables()
    {
        return [
            'CurrentLocale' => [
                'method' => 'getCurrentLocale',
                'casting' => 'Text',
            ],
            'CurrentLocaleObject' => [
                'method' => 'getCurrentLocaleObject',
            ],
        ];
    }

    /**
     * Returns the current locale
     *
     * @return string
     */
    public static function getCurrentLocale()
    {
        return FluentState::singleton()->getLocale();
    }

    /**
     * Returns the current locale object
     *
     * @return Locale|null
     */
    public static function getCurrentLocaleObject(): ?Locale
    {
        return Locale::getCurrentLocale();
    }
}
