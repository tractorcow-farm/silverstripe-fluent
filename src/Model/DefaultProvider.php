<?php

namespace TractorCow\Fluent\Model;

interface DefaultProvider
{
    /**
     * Provide a default value for the given class and field for this locale
     *
     * @param string $class
     * @param string $field
     * @param Locale $locale
     * @return string
     */
    public function provideDefault($class, $field, Locale $locale);
}
