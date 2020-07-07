<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTraitTest;

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

    protected function executeLocalisedCopy(): void
    {
        parent::executeLocalisedCopy();

        $original = $this->Tail();

        if (!$original->exists()) {
            return;
        }

        $duplicate = $original->duplicate();
        $this->TailID = $duplicate->ID;
    }
}
