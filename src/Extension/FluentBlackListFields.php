<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;

/**
 * Construct with fields that should not be copied when Fluent copies a translation
 * This is mainly so that Associated `has_one` fields don't point to the same object
 * e.g.
 * ```
 *   private static $extensions = [
 *       'TractorCow\Fluent\Extension\FluentBlackListFields("MainImageID", "ThumbnailImageID")'
 *   ];
 * ```
 */
class FluentBlackListFields extends Extension
{
    private $fieldsToNotCopy;

    public function __construct()
    {
        $this->fieldsToNotCopy = func_get_args();
    }

    /**
     * Called from the `FluentExtension::localiseSelect()` will set the `sqlDefailt`
     *   to NULL if the field was passed in construction
     */
    public function updateLocaliseSelectDefault(&$sqlDefault, $table, $field, $locale)
    {
        if (in_array($field, $this->fieldsToNotCopy)) {
            $sqlDefault = 'NULL';
        }
    }
}
