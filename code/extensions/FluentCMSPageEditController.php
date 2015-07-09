<?php

class FluentCMSPageEditController extends DataExtension {

	public function doResetTranslations($data, $form) {
		$record = $this->owner->getRecord($data['ID']);
		$record->resetTranslations();

        $this->owner->getResponse()->addHeader("X-Pjax","Content");
        $this->owner->getResponse()->addHeader(
        	'X-Status', 
        	_t('Fluent.TranslationsResetSuccess','Translations reset to default')
        );

        return $this->owner->redirectBack();
	}
}