# Versioned history

Adding Fluent to your project has a large impact on versioned history.

## Initial state

When you add Fluent module to your project all your localised objects (i.e. pages) will not have localised data and may appear as they have no content (depends on your specific Fluent setup).
It is expected that the localised content will be provided by content authors or by other means (dev task, default records...).

## History view

History viewer will display only versions for a specific locale.
This does not follow Locale fallbacks - versions shown are exclusive only to current locale.

## Archive view

Archive view is available only for objects which have some archived content in current locale.
This does not follow locale fallbacks rule.

`Restore to draft` button is only available in locales which have some archived content.
Content author is expected to switch to the locale where the archive is available in order to allow `Restore to draft` action. 

## Site tree flags

`No source` flag indicates that the page is localised in some other locale but current locale does not have its own content nor does it inherit content form other locale.

## Common Versioned methods

Methods from `Versioned` will have altered behaviour.
The data lookup will target localised records instead of base records.
This may have impact on your CMS UI (buttons not showing up).

### Common methods which are impacted

* `isOnDraft()`
* `isPublished()`
* `isArchived()`
* `stagesDiffer()`

### How to query the base record

There are some cases which need to use data lookup from base record.
Example below shows how to do it.

```
// This will query the localised record
$object->isPublished()

// This will query the base record
$baseRecordPublished = FluentState::singleton()->withState(function (FluentState $state) use ($object): bool {
    $state->setLocale(null);

    return $object->isPublished();
});
```

## Known issues

There are some issues that may impact your project. It's recommended to review these before using this module.

### MySQL auto-increment

Affected MySQL version: `< 8`

Auto-increment values are kept in memory and when the SQL server restarts the values are recalculated to `highest used ID + 1`.
As a consequence, IDs can get reused. Consider this scenario:

* Create a new page (`ID` 4)
* Create a new page (`ID` 5)
* Archive the page with `ID` 5
* At this point the next auto-increment value is 6
* Restart SQL server
* At this point the next auto-increment value is 5
* Create a new page (`ID` 5)

What happens in this case is that the newly created page will inherit version history of the previously archived page.
Upgrading your MySQL version to 8 or higher fixes this issue.

### Version timings

This issues is mostly notable when you have a setup where pages have nested objects (i.e. blocks).
When a nested object is written, no page version is created.

If you properly configure `owns` and `own_by` and run publish action on your page, the versions written may end up in an inconsistent state.
For example page version may end up with a different timestamp compared to the timestamp of the block version.
This is more likely to happen if the publish action takes a long time.

One of the options to fix this is to use the [Versioned Snapshots module](https://github.com/silverstripe/silverstripe-versioned-snapshots).
