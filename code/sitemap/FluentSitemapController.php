<?php

if (class_exists('GoogleSitemapController')) {

    /**
     * Provides extensions for multilingual views of pages in sitemap.xml
     * @link https://support.google.com/webmasters/answer/2620865?hl=en&ref_topic=2370587
     *
     * @see GoogleSitemapController
     * @package fluent
     * @author Damian Mooyman <damian.mooyman@gmail.com>
     */
    class FluentSitemapController extends GoogleSitemapController
    {
        public function init()
        {
            parent::init();

            // Reset any session locale to use the default locale as the standard 'base' locale
            Fluent::set_persist_locale(Fluent::default_locale(true));
        }
    }
}
