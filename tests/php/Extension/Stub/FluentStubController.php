<?php

namespace TractorCow\Fluent\Tests\Extension\Stub;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class FluentStubController extends Controller implements TestOnly
{
    protected $recordId;

    public function __construct($recordId)
    {
        $this->recordId;
    }

    public function getRecord()
    {
        return DataObject::get()->byID($this->recordId);
    }
}
