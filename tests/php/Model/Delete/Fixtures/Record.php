<?php

namespace TractorCow\Fluent\Tests\Model\Delete\Fixtures;

use SilverStripe\ORM\DataObject;

class Record extends DataObject
{
    private static $table_name = 'FluentDeleteTest_Record';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
