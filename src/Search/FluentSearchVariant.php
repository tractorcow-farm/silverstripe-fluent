<?php

namespace TractorCow\Fluent\Search;

use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

if (!class_exists(SearchVariant::class)) {
    return;
}

/**
 * Allow FulltextSearch to index content from all configured locales, and support
 * users searching to be restricted to their active locale. Works in a very similar
 * way to SearchVariantVersioned from the FulltextSearch module (where that variant
 * restricts searches to live pages, etc.)
 */
class FluentSearchVariant extends SearchVariant
{
    public function appliesTo($class, $includeSubclasses)
    {
        return $this->appliesToEnvironment() &&
            SearchIntrospection::has_extension($class, FluentExtension::class, $includeSubclasses) &&
            Locale::getCached()->count();
    }

    public function currentState()
    {
        return FluentState::singleton()->getLocale();
    }

    public function reindexStates()
    {
        return Locale::getCached()->column('Locale');
    }

    public function activateState($state)
    {
        $fluentState = FluentState::singleton()->setLocale($state);
        $fluentState->setIsFrontend(true);
    }

    public function alterQuery($query, $index)
    {
        if (FluentState::singleton()->getIsFrontend() && Locale::getCached()->count()) {
            // Backwards compatibility for silverstripe/fulltextsearch 3.2/3.3
            $method = method_exists($query, 'addFilter') ? 'addFilter' : 'filter';
            $query->$method('_locale', [
                $this->currentState(),
                SearchQuery::$missing
            ]);
        }
    }

    public function alterDefinition($class, $index)
    {
        $this->addFilterField($index, '_locale', [
            'name' => '_locale',
            'field' => '_locale',
            'fullfield' => '_locale',
            'base' => DataObject::getSchema()->baseDataClass($class),
            'origin' => $class,
            'type' => 'String',
            'lookup_chain' => [
                [
                    'call' => 'variant',
                    'variant' => static::class,
                    'method' => 'currentState'
                ]
            ]
        ]);
    }
}
