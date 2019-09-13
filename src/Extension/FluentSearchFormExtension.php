<?php

/**
 * SilverStripe core search work around when using Fluent
 * @link https://gist.github.com/baukezwaan/266c469758319daea5460f2a5647c525
 */

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Search\SearchForm;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use TractorCow\Fluent\State\FluentState;

class FluentSearchFormExtension extends SearchForm
{
    /**
     * Return dataObjectSet of the results using current request to get info from form.
     * Wraps around {@link searchEngine()}.
     *
     * @return SS_List
     */
    public function getResults()
    {
        // Get request data from request handler
        $request = $this->getRequestHandler()->getRequest();

        $keywords = $request->requestVar('Search');

        $andProcessor = function ($matches) {
            return ' +' . $matches[2] . ' +' . $matches[4] . ' ';
        };
        $notProcessor = function ($matches) {
            return ' -' . $matches[3];
        };

        $keyword_patterns = [
            'andProcessor' => '/()("[^()"]+")( and )("[^"()]+")()/i',
            'andProcessor' => '/(^| )([^() ]+)( and )([^ ()]+)( |$)/i',
            'notProcessor' => '/(^| )(not )("[^"()]+")/i',
            'notProcessor' => '/(^| )(not )([^() ]+)( |$)/i',
        ];

        foreach ($keyword_patterns as $callback => $pattern) {
            $keywords = preg_replace_callback($pattern, $$callback, $keywords);
        }

        $keywords = DB::get_conn()
            ->escapeString(
                $this->addStarsToKeywords($keywords)
            );

        $keywords_strrpl = str_replace(['*', '+', '-'], '', $keywords);
        $htmlEntityKeywords = htmlentities($keywords, ENT_NOQUOTES, 'UTF-8');

        $current_locale = FluentState::singleton()->getLocale();
        $sitetree_table_localised = 'SiteTree_Localised_' . $current_locale;
        $sitetreetables = 'SiteTree_Live.Title, SiteTree_Live.MenuTitle, SiteTree_Live.Content, SiteTree_Live.MetaDescription';

        $query = [
            'SELECT DISTINCT SiteTree_Live.ID, SiteTree_Live.Title,',
            "MATCH ({$sitetreetables}) AGAINST ('{$keywords_strrpl}') AS Relevance",
            'FROM SiteTree_Live',
            "LEFT JOIN SiteTree_Localised AS {$sitetree_table_localised}",
            "ON SiteTree_Live.ID = {$sitetree_table_localised}.RecordID AND {$sitetree_table_localised}.Locale = '{$current_locale}' AND (({$sitetree_table_localised}.ID IS NOT NULL))",
            'WHERE (',
            "MATCH ({$sitetreetables}) AGAINST ('{$keywords}' IN BOOLEAN MODE)",
            "+ MATCH ({$sitetreetables}) AGAINST ('{$htmlEntityKeywords}' IN BOOLEAN MODE)",
            'AND SiteTree_Live.ShowInSearch <> 0',
            ')',
            'ORDER BY Relevance DESC',
            'LIMIT 20'
        ];

        $customResults = DB::query(implode(' ', $query));

        $results = new ArrayList();

        foreach ($customResults as $record) {
            $st = SiteTree::get_by_id($record['ID']);
            if (!empty($st) && $st->canView()) {
                $results->push($st);
            }
        }

        return $results;
    }
}
