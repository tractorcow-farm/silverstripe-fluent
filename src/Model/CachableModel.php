<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
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
        $serviceName = static::class . '.cached';
        if (Injector::inst()->has($serviceName)) {
            /** @var ArrayList $list */
            $list = Injector::inst()->get($serviceName);
            return $list;
        }

        // Query DB
        $dataList = DataObject::get(static::class)->sort('Locale');
        $sort = Config::inst()->get(static::class, 'default_sort');
        if ($sort) {
            $dataList = $dataList->sort($sort);
        }

        // Convert to arraylist
        $list = ArrayList::create($dataList->toArray());
        Injector::inst()->registerService($list, $serviceName);
        return $list;
    }
}
