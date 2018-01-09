<?php

namespace TractorCow\Fluent\Control;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class LocaleAdmin extends ModelAdmin
{
    private static $url_segment = 'locales';

    private static $menu_title = 'Locales';

    public $showImportForm = false;

    public $showSearchForm = false;

    private static $managed_models = [
        Locale::class,
        Domain::class,
    ];

    private static $menu_icon_class = 'font-icon-globe-1';

    protected function init()
    {
        parent::init();
        Requirements::javascript('tractorcow/silverstripe-fluent:client/dist/js/fluent.js');
        Requirements::css("tractorcow/silverstripe-fluent:client/dist/styles/fluent.css");
    }

    public function getClientConfig()
    {
        return array_merge(
            parent::getClientConfig(),
            [
                'fluent' => [
                    'locales' => array_map(function (Locale $locale) {
                        return [
                            'code' => $locale->getLocale(),
                            'title' => $locale->getTitle(),
                        ];
                    }, Locale::getCached()->toArray()),
                    'locale' => FluentState::singleton()->getLocale(),
                    'param' => FluentDirectorExtension::config()->get('query_param'),
                ]
            ]
        );
    }
}
