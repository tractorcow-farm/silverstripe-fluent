<?php

namespace TractorCow\Fluent\Control;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentMemberExtension;
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

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // Add sortable field to locales
        if ($this->modelClass === Locale::class) {
            /** @var GridField $listField */
            $listField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
            $config = $listField->getConfig();
            $config->addComponent(new GridFieldOrderableRows('Sort'));
        }

        return $form;
    }

    public function getClientConfig()
    {
        /** @var Member|FluentMemberExtension $member */
        $member = Security::getCurrentUser();
        $locales = $member
            ? $member->getCMSAccessLocales()
            : Locale::getCached();
        $this->extend('updateFluentLocales', $locales);
        $locales = $locales->toArray();
        return array_merge(
            parent::getClientConfig(),
            [
                'fluent' => [
                    'locales' => array_map(function (Locale $locale) {
                        return [
                            'code'  => $locale->getLocale(),
                            'title' => $locale->getTitle(),
                        ];
                    }, $locales),
                    'locale'  => FluentState::singleton()->getLocale(),
                    'param'   => FluentDirectorExtension::config()->get('query_param'),
                ]
            ]
        );
    }
}
