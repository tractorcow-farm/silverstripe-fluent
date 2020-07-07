<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTraitTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\Traits\LocalisedCopyTrait;

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

    protected function executeLocalisedCopy(): void
    {
        parent::executeLocalisedCopy();

        $original = $this->Saddle();

        if (!$original->exists()) {
            return;
        }

        $duplicate = $original->duplicate();
        $this->SaddleID = $duplicate->ID;
    }
}
