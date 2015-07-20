<?php

/**
 * SiteTree extension class for translatable objects
 *
 * @see SiteTree
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentSiteTree extends FluentExtension {

	public function MetaTags(&$tags) {
		$tags .= $this->owner->renderWith('FluentSiteTree_MetaTags');
	}

	public function onBeforeWrite() {
		// Fix issue with MenuTitle not containing the correct translated value
		$this->owner->setField('MenuTitle', $this->owner->MenuTitle);

		parent::onBeforeWrite();
	}

	/**
	 * Ensure that the controller is correctly initialised
	 *
	 * @param ContentController $controller
	 */
	public function contentcontrollerInit($controller) {
		Fluent::install_locale();
	}

	public function updateRelativeLink(&$base, &$action) {

		// Don't inject locale to subpages
		if($this->owner->ParentID && SiteTree::config()->nested_urls) {
			return;
		}

		// For blank/temp pages such as Security controller fallback to querystring
		$locale = Fluent::current_locale();
		if(!$this->owner->exists()) {
			$base = Controller::join_links($base, '?'.Fluent::config()->query_param.'='.urlencode($locale));
			return;
		}

		// Check if this locale is the default for its own domain
		$domain = Fluent::domain_for_locale($locale);
		if($locale === Fluent::default_locale($domain)) {
			// For home page in the default locale, do not alter home url
			if($base === null) return;

			// For all pages on a domain where there is only a single locale,
			// then the domain itself is sufficient to distinguish that domain
			// See https://github.com/tractorcow/silverstripe-fluent/issues/75
			$domainLocales = Fluent::locales($domain);
			if(count($domainLocales) === 1) return;
		}

		// Simply join locale root with base relative URL
		$localeURL = Fluent::alias($locale);
		$base = Controller::join_links($localeURL, $base);
	}

	public function LocaleLink($locale) {

		// For blank/temp pages such as Security controller fallback to querystring
		if(!$this->owner->exists()) {
			$url = Controller::curr()->getRequest()->getURL();
			return Controller::join_links($url, '?'.Fluent::config()->query_param.'='.urlencode($locale));
		}

		return parent::LocaleLink($locale);
	}

	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);

		// Fix URLSegment field issue for root pages
		if(!SiteTree::config()->nested_urls || empty($this->owner->ParentID)) {
			$baseLink = Director::absoluteURL(Controller::join_links(
				Director::baseURL(),
				Fluent::alias(Fluent::current_locale()),
				'/'
			));
			$urlsegment = $fields->dataFieldByName('URLSegment');
			$urlsegment->setURLPrefix($baseLink);
		}
	}

	/**
	 * Resets all translated fields to their value in the default locale
	 */
	public function resetTranslations() {
		$translated = $this->getTranslatedTables();
		foreach($translated as $table => $fields) {
			foreach($fields as $field) {
				$defaultField = Fluent::db_field_for_locale($field, Fluent::default_locale());
				$defaultValue = $this->owner->$defaultField;
				
				foreach(Fluent::locales() as $locale) {
					if($locale === Fluent::default_locale()) continue;
					
					$localeField = Fluent::db_field_for_locale($field, $locale);
					$originalValue = $this->owner->$localeField;
					$this->owner->$localeField = $defaultValue;					

					// If these values differ, but a change isn't detected, then force a change
					if($this->owner->exists() && ($originalValue != $defaultValue) && !$this->owner->isChanged($field)) {
						$this->owner->forceChange();						
					}

					$this->owner->write();
				}
			}
		}
	}


	public function updateCMSActions(FieldList $actions) {
		$actions->addFieldToTab(
			'ActionMenus.MoreOptions', 
			FormAction::create(
				'doResetTranslations', 
				_t('Fluent.TranslationsResetButton', 'Reset translations to default')
			)
		);
	}
}
