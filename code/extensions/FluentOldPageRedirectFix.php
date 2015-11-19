<?php

/**
 * Bug fix for infinite redirect while trying to show a FluentFilteredExtension disabled page.
 * OldPageRedirector::find_old_page uses a query which bypasess filtering and returns the same page
 * resulting in infinite loop.
 *
 * @see OldPageRedirector
 * @package fluent
 */
class FluentOldPageRedirectFix extends Extension
{
    public static $disableSkipIDFilter = false;

    public function onBeforeHTTPError404($request)
    {
        $this::$disableSkipIDFilter = true;
    }
}
