<?php

/**
 * Fluent extension for ContentController
 *
 * @see ContentController
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentContentController extends Extension
{
    public function onBeforeInit()
    {
        Fluent::install_locale();
    }
}
