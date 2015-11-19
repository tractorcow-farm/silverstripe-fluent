<?php

/**
 * Provides rewrite of fluent searches for MySQLDatabase
 *
 * Warning: This class is extremely fragile, and sensitive to changes in {@see MySQLDatabase::searchEngine} behaviour
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentMySQLSearch implements FluentSearchAdapter
{
    /**
     * List of columns that make up the fulltext index of each table
     *
     * @var array
     */
    private static $search_columns = array(
        'SiteTree' => array('Title', 'MenuTitle', 'Content', 'MetaDescription'),
        'File' => array('Filename', 'Title', 'Content')
    );

    /**
     * Parse filters for extracting keywords and/or metadata on the search
     *
     * @var array
     */
    private static $keyword_patterns = array(
        'SiteTree' => "/MATCH \(Title, MenuTitle, Content, MetaDescription\) AGAINST \('(?<keywords>([^']|(\\\\'))*)' (?<boolean>IN BOOLEAN MODE)?\)\s+\+ MATCH \(Title, MenuTitle, Content, MetaDescription\) AGAINST \('(?<keywordshtml>([^']|(\\\\'))*)' (IN BOOLEAN MODE)?\)/im",
        'File' => "/MATCH \(Filename, Title, Content\) AGAINST \('(?<keywords>([^']|(\\\\'))*)' (?<boolean>IN BOOLEAN MODE)?\) AND ClassName = 'File'/im"
    );

    /**
     * Replacement patterns for "Relevance" select fragment
     *
     * @var array
     */
    private static $relevance_replacements = array(
        'SiteTree' => "MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('\$relevanceKeywords') + MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('\$htmlEntityRelevanceKeywords')",
        'File' => "MATCH (Filename, Title, Content) AGAINST ('\$relevanceKeywords')"
    );

    /**
     * Expected select fragment elements for rewriting returned columns.
     * These must be replaced using SQLQuery->replaceText as these
     * will not be present in the query until after SQL augmentation.
     *
     * @var array
     */
    private static $select_patterns = array(
        'SiteTree' => 'ParentID, Title, MenuTitle, URLSegment, Content, LastEdited, Created, _utf8\'\' AS "Filename", _utf8\'\' AS "Name"',
        'File' => 'ClassName, "File"."ID", _utf8\'\' AS "ParentID", Title, _utf8\'\' AS "MenuTitle", _utf8\'\' AS "URLSegment", Content, LastEdited, Created, Filename, Name'
    );

    /**
     * Replacement patterns for where fragments
     *
     * @var array
     */
    private static $where_replacements = array(
        'SiteTree' => "MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('\$keywords' \$boolean)
				+ MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('\$htmlEntityKeywords' \$boolean)",
        'File' => "MATCH (Filename, Title, Content) AGAINST ('\$keywords' \$boolean)"
    );

    /**
     * Determine the where fragment containing a fulltext search condition
     *
     * @param SQLQuery $query
     * @return string|boolean Returns the condition with a fulltext condition, or false if not found
     */
    protected function getSearchFilter(SQLQuery $query)
    {
        foreach ($query->getWhere() as $where) {
            if (preg_match('/^(\s*)MATCH(\s*)\(/i', $where)) {
                return $where;
            }
        }
        return false;
    }

    /**
     * Determine the class this query is searching against
     *
     * @param SQLQuery $query
     * @return string
     */
    protected function getClass(SQLQuery $query)
    {
        foreach ($query->getFrom() as $alias => $table) {
            return $alias;
        }
    }

    /**
     * Extract keywords (both SQL and HTML encoded keywords) and boolean mode flag from the query
     *
     * @param string $class Object to query
     * @param string $searchfilter
     * @param string &$keywords SQL escaped keywords
     * @param string &$keywordsHTML HTML escaped keywords
     * @param boolean &$booleanMode True if boolean mode
     */
    protected function getSearchParameters($class, $searchfilter, &$keywords, &$keywordsHTML, &$booleanMode)
    {
        $pattern = self::$keyword_patterns[$class];
        $result = preg_match($pattern, $searchfilter, $matches);
        $keywords = $result && isset($matches['keywords']) ? $matches['keywords'] : null;
        $keywordsHTML = $result && isset($matches['keywordshtml']) ? $matches['keywordshtml'] : null;
        $booleanMode = $result && !empty($matches['boolean']);
    }

    public function augmentSearch(SQLQuery &$query, DataQuery &$dataQuery = null)
    {

        // Skip non-search queries
        if (!($searchFilter = $this->getSearchFilter($query))) {
            return;
        }

        // Check translated columns on the searched class, making sure it's in the allowed search list
        $class = $this->getClass($query);
        if (empty($class) || !isset(self::$keyword_patterns[$class])) {
            return;
        }

        // Extract keywords
        $this->getSearchParameters($class, $searchFilter, $keywords, $keywordsHTML, $booleanMode);
        if (empty($keywords)) {
            return;
        }

        // Augment selected columns
        $translatedColumns = array_keys(FluentExtension::translated_fields_for($class));
        $this->augmentSelect($class, $translatedColumns, $query, $keywords, $keywordsHTML);

        // Augment filter columns
        $this->augmentFilter($class, $translatedColumns, $query, $keywords, $keywordsHTML, $booleanMode);
    }

    /**
     * Rewrites the SELECT fragment of a query.
     *
     * This is done in two stages:
     * - Augment queried columns
     * - Augment relevance column containing the MATCH
     *
     * @param string $class
     * @param array $translatedColumns Translated columns
     * @param SQLQuery $query
     * @param string $keywords SQL escaped keywords
     * @param string $keywordsHTML HTML escaped keywords
     */
    public function augmentSelect($class, $translatedColumns, SQLQuery $query, $keywords, $keywordsHTML)
    {

        // Augment the non-match pattern
        $pattern = self::$select_patterns[$class];
        $replacement = array();
        $locale = Fluent::current_locale();
        foreach (explode(',', $pattern) as $column) {
            $column = trim($column);
            if (in_array($column, $translatedColumns)) {
                $translatedField = Fluent::db_field_for_locale($column, $locale);
                $column = "CASE
					WHEN ($translatedField IS NOT NULL AND $translatedField != '')
					THEN $translatedField
					ELSE $column END AS \"$column\"";
            }
            $replacement[] = $column;
        }
        $query->replaceText($pattern, implode(', ', $replacement));

        // Augment the relevance section
        $relevancePattern = self::$relevance_replacements[$class];
        $translatedPattern = $relevancePattern;
        $searchColumns = self::$search_columns[$class];
        foreach (array_intersect($searchColumns, $translatedColumns) as $column) {
            $replacement = Fluent::db_field_for_locale($column, $locale);
            $translatedPattern = preg_replace('/\b'.preg_quote($column).'\b/', $replacement, $translatedPattern);
        }

        // If no fields were translated, then don't modify
        if ($translatedPattern === $relevancePattern) {
            return;
        }

        // Inject keywords into patterns
        $search = array(
            '/\$relevanceKeywords/i',
            '/\$htmlEntityRelevanceKeywords/i'
        );
        $replace = array(
            str_replace(array('*', '+', '-'), '', $keywords),
            str_replace(array('*', '+', '-'), '', $keywordsHTML)
        );
        $relevanceOriginal = preg_replace($search, $replace, $relevancePattern);
        $relevanceTranslated = preg_replace($search, $replace, $translatedPattern);

        // Augment relevance to include sum of both translated and untranslated segments
        $query->replaceText($relevanceOriginal, "$relevanceOriginal + $relevanceTranslated");
    }

    /**
     * Rewrites the WHERE fragment of a query
     *
     * @param string $class
     * @param array $translatedColumns Translated columns
     * @param SQLQUery $query
     * @param string $keywords SQL escaped keywords
     * @param string $keywordsHTML HTML escaped keywords
     */
    public function augmentFilter($class, $translatedColumns, SQLQuery $query, $keywords, $keywordsHTML, $booleanMode)
    {

        // Augment the search section
        $locale = Fluent::current_locale();
        $wherePattern = self::$where_replacements[$class];
        $translatedPattern = $wherePattern;
        $searchColumns = self::$search_columns[$class];
        foreach (array_intersect($searchColumns, $translatedColumns) as $column) {
            $replacement = Fluent::db_field_for_locale($column, $locale);
            $translatedPattern = preg_replace('/\b'.preg_quote($column).'\b/', $replacement, $translatedPattern);
        }

        // If no fields were translated, then don't modify
        if ($translatedPattern === $wherePattern) {
            return;
        }

        // Inject keywords into patterns
        $search = array(
            '/\$keywords/i',
            '/\$htmlEntityKeywords/i',
            '/\$boolean/i'
        );
        $replace = array(
            $keywords,
            $keywordsHTML,
            $booleanMode ? 'IN BOOLEAN MODE' : ''
        );
        $whereOriginal = preg_replace($search, $replace, $wherePattern);
        $whereTranslated = preg_replace($search, $replace, $translatedPattern);

        $where = $query->getWhere();
        $newWhere = array();
        foreach ($query->getWhere() as $where) {
            // Remove excessive whitespace which breaks string replacement
            $where = preg_replace('/\s+/im', ' ', $where);
            $whereOriginal = preg_replace('/\s+/im', ' ', $whereOriginal);
            $newWhere[] = str_replace($whereOriginal, "$whereOriginal + $whereTranslated", $where);
        }
        $query->setWhere($newWhere);
    }
}
