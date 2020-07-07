<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTraitTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\Traits\LocalisedCopyTrait;

/**
 * Class Animal
 *
 * @property string $Title
 */
class Animal extends DataObject implements TestOnly
{
    use LocalisedCopyTrait;

    /**
     * @var string
     */
    private static $table_name = 'Animal';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    protected function executeLocalisedCopy(): void
    {
        // no op
    }
}
