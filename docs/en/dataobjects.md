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

class MyPage extends SiteTree
{
	public function getCMSFields()
    {
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

If explicitly generating your `FieldList`, simply make sure to call the appropriate extension hook prior to returning.

```php

class MyObject extends DataObject
{
	public function getCMSFields()
    {
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

### Extensions

As mentioned in the CMS Fields heading, it is necessary to ensure that the `updateCMSFields` extension method is called
once (and only once) on each `FieldList` object. Fields added by other extensions will not have the necessary
decorations applied to it by the `FluentExtension`, unless it happens to be applied before `FluentExtension`. Since
extension order cannot be guaranteed, this poses a problem.

You can circumvent this issue by extending `FluentExtension` from your custom extension and then calling
`parent::updateCMSFields($fields)` at the end of your method. This way it is actually one call to `updateCMSFields`
using inheritance rather than two different extensions:

```php
use TractorCow\Fluent\Extension\FluentExtension;

class PlayerExtension extends FluentExtension
{
    protected function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('SomeIrrelevantField');
        parent::updateCMSFields($fields);
    }
}
```

#### Versioned extension

**Important:** If you're applying the `FluentVersionedExtension` to a versioned DataObject, you will need to ensure that
it is applied _after_ the `Versioned` extension. You can control this with an `after: '#versionedfiles'` rule in your
YAML title block. An example configuration block might look like this:

```yaml
---
Name: mysitefluent
After: '#versionedfiles'
---
SilverStripe\Assets\File:
  extensions:
    - TractorCow\Fluent\Extension\FluentVersionedExtension
  translate:
    - Title
```
