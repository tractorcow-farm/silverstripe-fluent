<?php

/**
 * Interface for search rewrite handlers
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
interface FluentSearchAdapter
{
    /**
     * Detect and rewrite any full text search in this query
     *
     * @param SQLQuery $query
     * @param DataQuery $dataQuery
     */
    public function augmentSearch(SQLQuery &$query, DataQuery &$dataQuery = null);
}
