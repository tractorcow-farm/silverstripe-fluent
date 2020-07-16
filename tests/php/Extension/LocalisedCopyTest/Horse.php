<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTest;

/**
 * Class Horse
 *
 * @property int $TailID
 * @method Tail Tail()
 */
class Horse extends Animal
{
    /**
     * @var string
     */
    private static $table_name = 'Horse';

    /**
     * @var array
     */
    private static $has_one = [
        'Tail' => Tail::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Tail',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Tail',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Tail',
    ];

    /**
     * @var array
     */
    private static $field_include = [
        'TailID',
    ];

    /**
     * @var array
     */
    private static $localised_copy = [
        'Tail',
    ];
}
