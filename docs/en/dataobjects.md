# Using DataObjects with Fluent

## CMS Fields

With each of the possible extensions that come with Fluent, it's necessary to configure your DataObjects to
correctly delegate your `$fields` to extensions. A different approach may be needed for each DataObject, depending
on how it generates it's fields.

In all cases, it is necessary to ensure that the `updateCMSFields` extension method is called once on each fieldset.

### Default field scaffolder

If using the default field scaffolder or implementations (as per the implementation in either SiteTree::getCMSFields or 
DataObject::getCMSFields) it is necessary to know that these methods both, by default, will call the
`->extend('updateCMSFields', $fields);` extension before returning.

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
