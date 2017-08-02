<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\i18n\i18n;

class i18nDefaultProvider implements DefaultProvider
{

    /**
     * Provide a default value for the given class and field for this locale
     *
     * @param string $class
     * @param string $field
     * @param Locale $locale
     * @return string
     */
    public function provideDefault($class, $field, Locale $locale)
    {
        $default = i18n::get_locale();
		try {
			i18n::set_locale($locale->Locale);
			return _t($class.'.'.$field.'_DEFAULT', '');
		} finally {
			i18n::set_locale($default);
		}
    }
}
