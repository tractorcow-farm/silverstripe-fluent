<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Convert;

/**
 * Additional Fluent Extension
 * Construct with fields that should not be copied when Fluent copies a translation
 * This is mainly so that Associated `has_one` fields don't point to the same object
 */
class FluentCustomDefaultFields extends Extension
{
    private $fieldDefaultValues;

    public function __construct($defaultValues)
    {
        $this->fieldDefaultValues = $defaultValues;
    }

    /**
     * Called from the `FluentExtension::localiseSelect()` will set the `sqlDefailt`
     *   to NULL if the field was passed in construction
     */
    public function updateLocaliseSelectDefault(&$sqlDefault, $table, $field, $locale)
    {
        if (array_key_exists($field, $this->fieldDefaultValues)) {
            $sqlDefault = Convert::raw2sql(str_replace('{{locale}}', $locale->Title, $this->fieldDefaultValues[$field]), true);
        }
    }
}
