# Using DataObjects with Fluent

## CMS Fields

With each of the possible extensions that come with Fluent, it's necessary to configure your DataObjects to
correctly delegate your `$fields` to extensions. A different approach may be needed for each DataObject, depending
on how it generates it's fields.

In all cases, it is necessary to ensure that the `updateCMSFields` extension method is called once (and
only once) on each `FieldList` object.

### Default field scaffolder

If using a default field scaffolder (such as `SiteTree::getCMSFields` or `DataObject::getCMSFields`)
it is necessary to know that these methods both, by default, will call the
`extend('updateCMSFields', $fields)` extension before returning.

If you wish to add any translated form fields to the result of this call, then you should use `beforeUpdateCMSFields`

```php

class MyPage extends SiteTree {
	public function getCMSFields() {

		// Adding the Description field early will allow FluentField to decorate this with the appropriate 
		// CSS classes.
		$this->beforeUpdateCMSFields(function($fields) {
			$fields->addFieldToTab('Root.Main', new TextField('Description'));
		});

		// The result of this call will have had beforeUpdateCMSFields then updateCMSFields called on it
		$fields = parent::getCMSFields();
		$fields->removeByName('Content', true);
		return $fields;
	}
}

```

### Explicit field generation

If explicitly generating your field list, simply make sure to call the appropriate extension hook prior to returning.

```php

class MyObject extends DataObject {
	public function getCMSFields() {

		// Note the absence of any parent::getCMSFields
		$fields = new FieldList(
			new TextField('Title', 'Title', null, 255),
			new TextareaField('Description')
		);

		// This line is necessary, and only AFTER you have added your fields
		$this->extend('updateCMSFields', $fields);
		return $fields;
	}
}

```

### CMS Filtering

By default, filtering of objects with FluentFilteredExtension is disabled within the CMS.
This is because, should an object ever be filtered out of all locales, the object must then be
findable in order to re-enabled it.

In any case where it may be necessary to apply filtering rules within a CMS (For instance,
where a many_many relation may be managed by a checkboxset, and hidden objects should be
disabled) then this filter may be enforced within the CMS on a case by case basis.

The following will construct such a list, ensuring that only objects valid in the current
locale are given.

```php
public function getCMSFields() {
	$fields = parent::getCMSFields();
	// Causes filtering to be enabled within the admin
	$banners = $this->Banners()->setDataQueryParam(FluentFilteredExtension::FILTER_ADMIN, true);
	$fields->addFieldsToTab('Root.Banners', new CheckboxSetField('Banners', 'Banners', $banners));
	return $fields;
}
```
