<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/**
 * Allows you to cache a full list of objects without multiple DB queries
 */
trait CachableModel
{
    /**
     * @return ArrayList<static>
     */
    public static function getCached()
    {
        $serviceName = static::class . '_cached';
        if (Injector::inst()->has($serviceName)) {
            /** @var ArrayList $list */
            $list = Injector::inst()->get($serviceName);
            return $list;
        }

        // Check if db is ready for this model to be queried
        if (!static::databaseIsReady()) {
            return ArrayList::create();
        }

        // Query DB
        $dataList = DataObject::get(static::class);
        $sort = Config::inst()->get(static::class, 'default_sort');
        if ($sort) {
            $dataList = $dataList->orderBy($sort);
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

    public static function clearCached()
    {
        $serviceName = static::class . '_cached';
        Injector::inst()->unregisterNamedObject($serviceName);

        if (isset(static::$locales_by_title)) {
            static::$locales_by_title = null;
        }
    }

    /**
     * Check if the DB is able to safely query this model
     *
     * @return bool
     */
    protected static function databaseIsReady()
    {
        $object = DataObject::singleton(static::class);

        // if any of the tables aren't created in the database
        $table = $object->baseTable();
        if (!ClassInfo::hasTable($table)) {
            return false;
        }

        // if any of the tables don't have all fields mapped as table columns
        $dbFields = DB::field_list($table);
        if (!$dbFields) {
            return false;
        }

        $objFields = $object->getSchema()->databaseFields($object, false);
        $missingFields = array_diff_key($objFields, $dbFields);

        if ($missingFields) {
            return false;
        }

        return true;
    }
}
