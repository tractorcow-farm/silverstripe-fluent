# Deletion policies

Fluent augments and replaces the default object deletion behaviour for objects. 
Based on the extensions applied to each object, invoking "->delete()" (or pushing the
delete button in gridfields) will trigger the policy.

## Standard policies

Each policy is described below

### DeleteRecordPolicy

This policy will delete the record from the current stage (e.g. live, draft).
This policy supports the ability to inject dependent policies (listed below)
which may conditionally suppress the base record being deleted.

A DeleteRecordPolicy with no dependent policies acts as the default silverstripe
deletion behaviour.

This policy is applied to any record with either `FluentExtension` or `FluentFilteredExtension`
applied.

### DeleteLocalisationPolicy

This policy will delete the localisation for the record in the current locale.
E.g. if you have a locale in both EN and CN, pressing delete in the CN locale
will delete only that locale, but not the EN (or base) record.

Note that if you have set `frontend_publish_required` config to `fallback` or `any`, then the record
will still be available in that locale, but will instead fall back to the failover instead
(depending on your configuration).

This policy is applied only to records with `FluentExtension` applied.

### DeleteFilterPolicy

This policy will remove the selected item from being visible in the current locale.

This policy is applied only to records with the `FluentFilteredExtension` extension.

## Overriding policies

You can modify the behaviour of any of these policies for one (or all) model types.

For example, if you wish to restore legacy behaviour, and ensure that deleting a record
removes records in all locales, you can either:

 - Implement a custom `DeletePolicyFactory`, and add only one policy per class.
 - Implement a custom policy altogether, and replace one or more of the default policies.   


### Example use case: Delete base record and ignore localisations / filters


```yaml
---
Name: my-fluentdelete
After:
  - '#fluentdelete'
---
SilverStripe\Core\Injector\Injector:
  TractorCow\Fluent\Model\Delete\DeletePolicy:
    factory: App\DeletePolicyFactory
```

And your factory

```php
<?php

namespace App;

use SilverStripe\Core\Injector\Factory;
use TractorCow\Fluent\Model\Delete\DeleteRecordPolicy;

class DeletePolicyFactory implements Factory
{
    public function create($service, array $params = [])
    {
        // Build policy
        $policy = new DeleteRecordPolicy();
        return $policy;
    }
}

```

Note: You can access the object being deleted via `$params[0]` if you need to do inspection.

