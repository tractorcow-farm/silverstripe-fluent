<?php

/**
 * Fluent extension for main CMS admin
 * 
 * @see LeftAndMain
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentLeftAndMain extends LeftAndMainExtension {
	public function init() {
		$dirName = basename(dirname(dirname(dirname(__FILE__))));
		$locales = json_encode(Fluent::locale_names());
		$locale = json_encode(Fluent::current_locale());
		
		// Force the variables to be written to the head, to ensure these are available for other scripts to pick up.
		Requirements::insertHeadTags(<<<EOT
<script type="text/javascript">
//<![CDATA[
	var fluentLocales = $locales;
	var fluentLocale = $locale;
//]]>
</script>
EOT
			,'FluentHeadScript'
		);
		Requirements::javascript("$dirName/javascript/fluent.js");
		Requirements::css("$dirName/css/fluent.css");
	}
}
