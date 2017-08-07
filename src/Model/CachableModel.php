<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataObject;

/**
 * Allows you to cache a full list of objects without multiple DB queries
 */
trait CachableModel {

    /**
     * @return ArrayList
     */
    public static function getCached()
    {
        $serviceName = static::class . '_cached';
        if (Injector::inst()->has($serviceName)) {
            /** @var ArrayList $list */
            $list = Injector::inst()->get($serviceName);
            return $list;
        }

        // Query DB
        $dataList = DataObject::get(static::class);
        $sort = Config::inst()->get(static::class, 'default_sort');
        if ($sort) {
            $dataList = $dataList->sort($sort);
        }

        // If DB isn't ready, silently return empty array to prevent bootstrapping issues
        try {
            $list = ArrayList::create($dataList->toArray());
            Injector::inst()->registerService($list, $serviceName);
        } catch (DatabaseException $ex) {
            $list = ArrayList::create();
        }

        // Convert to arraylist
        return $list;
    }
}
