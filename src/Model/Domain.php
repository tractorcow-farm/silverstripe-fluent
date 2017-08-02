<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\ORM\DataObject;

class Domain extends DataObject
{
    use CachableModel;

    private static $table_name = 'FluentDomain';

    private static $singular_name = 'Domain';

    private static $plural_name = 'Domains';

    private static $summary_fields = [
        'Domain'
    ];

    private static $db = [
        'Domain' => 'Varchar(150)',
    ];

    private static $has_many = [
        'Locales' => Locale::class,
    ];

    public function getTitle()
    {
        return $this->getField('Domain');
    }
}
