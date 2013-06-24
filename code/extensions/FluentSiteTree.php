<?php

class FluentSiteTree extends FluentExtension {
	
	public function updateRelativeLink(&$base, &$action) {
		$locale = Fluent::current_locale();
		$base = Controller::join_links($locale, $base);
	}
}
