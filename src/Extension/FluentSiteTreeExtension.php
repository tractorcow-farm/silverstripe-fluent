<?php

namespace TractorCow\Fluent\Extension;

/**
 * Fluent extension for SiteTree
 */
class FluentSiteTreeExtension extends FluentVersionedExtension
{
    /**
     * Mark this extension as versionable
     *
     * @config
     * @var array
     */
    private static $versionableExtensions = [
        self::class => [ self::SUFFIX ],
    ];
}
