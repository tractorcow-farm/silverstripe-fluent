<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

/**
 * Class Steed
 *
 * @property int $SaddleID
 * @method Saddle Saddle()
 */
class Steed extends Horse
{
    /**
     * @var string
     */
    private static $table_name = 'Steed';

    /**
     * @var array
     */
    private static $has_one = [
        'Saddle' => Saddle::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Saddle',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Saddle',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Saddle',
    ];

    /**
     * @var array
     */
    private static $field_include = [
        'SaddleID',
    ];

    /**
     * @var array
     */
    private static $localised_copy = [
        'Saddle',
    ];
}
