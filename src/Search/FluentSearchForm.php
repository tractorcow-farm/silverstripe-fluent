<?php

/**
 * SilverStripe core search work around when using Fluent
 * @link https://gist.github.com/baukezwaan/266c469758319daea5460f2a5647c525
 */

namespace TractorCow\Fluent\Search;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Search\SearchForm;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use TractorCow\Fluent\State\FluentState;

// Soft dependency on CMS module
if (!class_exists(SiteTree::class)) {
    return;
}

/**
 * Simple extension to enable SS Core search to work with SS Fluent
 */
class FluentSearchForm extends SearchForm
{
    /**
     * Return dataObjectSet of the results using current request to get info from form.
     * Wraps around {@link searchEngine()}.
     *
     * @return PaginatedList
     */
    public function getResults()
    {
        $keywords = $this->getSearchQuery() ?: '';
        $request = $this->getRequestHandler()->getRequest();
        // Define and generate keyword search patterns
        $keyword_patterns = [
            [
                'patterns' => [
                    '/()("[^()"]+")( and )("[^"()]+")()/i',
                    '/(^| )([^() ]+)( and )([^ ()]+)( |$)/i',
                ],
                'callback' => function ($matches) {
                    return ' +' . $matches[2] . ' +' . $matches[4] . ' ';
                },
            ],
            [
                'patterns' => [
                    '/(^| )(not )("[^"()]+")/i',
                    '/(^| )(not )([^() ]+)( |$)/i',
                ],
                'callback' => function ($matches) {
                    return ' -' . $matches[3];
                },
            ],
        ];
        foreach ($keyword_patterns as $k_p) {
            $keywords = preg_replace_callback(
                $k_p['patterns'],
                $k_p['callback'],
                $keywords
            );
        }

        // Generate and query for locale based search results
        $current_locale = FluentState::singleton()->getLocale();
        $sitetree_table_localised = "SiteTree_Localised_{$current_locale}";
        $sitetreetables = '"SiteTree_Live"."Title", "SiteTree_Live"."MenuTitle", "SiteTree_Live"."Content", "SiteTree_Live"."MetaDescription"';
        $sql = <<<SQL
            SELECT DISTINCT "SiteTree_Live"."ID", "SiteTree_Live"."Title", MATCH ({$sitetreetables}) AGAINST (?) AS Relevance
            FROM "SiteTree_Live"
            LEFT JOIN "SiteTree_Localised" AS "{$sitetree_table_localised}"
            ON "SiteTree_Live"."ID" = "{$sitetree_table_localised}"."RecordID" AND "{$sitetree_table_localised}"."Locale" = ? AND (("{$sitetree_table_localised}"."ID" IS NOT NULL))
            WHERE (MATCH ({$sitetreetables}) AGAINST (? IN BOOLEAN MODE) + MATCH ({$sitetreetables}) AGAINST (? IN BOOLEAN MODE) AND "SiteTree_Live"."ShowInSearch" <> 0)
            ORDER BY Relevance DESC
SQL;
        $params = [
            str_replace(['*', '+', '-'], '', $keywords),
            $current_locale,
            $keywords,
            htmlentities($keywords, ENT_NOQUOTES, 'UTF-8')
        ];
        // Generate results list
        $sitetree_objects = SiteTree::get()
            ->innerJoin(
                '(' . $sql . ')',
                '"Fulltext"."ID" = "SiteTree"."ID"',
                'Fulltext',
                20,
                $params
            )
            ->sort('"Fulltext"."Relevance" DESC');
        // Filter out non-viewable
        $results = new ArrayList();
        foreach ($sitetree_objects as $sto) {
            if (empty($sto) || !$sto->canView()) {
                continue;
            }
            $results->push($sto);
        }

        // Paginate results
        return PaginatedList::create($results)
            ->setPageStart($request->requestVar('start') ?: 0)
            ->setPageLength($this->getPageLength())
            ->setTotalItems($results->count());
    }
}
