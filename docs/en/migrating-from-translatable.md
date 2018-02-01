# Migrating from Translatable

For websites that have been using the [Translatable](https://github.com/silverstripe/silverstripe-translatable)
module in a SilverStripe 3 project, we have provided a BuildTask that you can run to migrate data into a format
for Fluent on SilverStripe 4. This can be used, for example, to aid a migration from CWP 1.0 to CWP 2.0, or any
SilverStripe project previously using Translatable.

## Running the task

To run the automated BuildTask, run the following from your command line:

```
vendor/bin/sake dev/tasks/ConvertTranslatableTask
```

This command will do the following:

* Run pre-requisite checks to ensure the system is ready for a data migration to be run - for example, ensuring
  that you have created some locales in the CMS already
* Looks up all DataObjects that are Fluent-enabled
* Find any Translatable database tables for each DataObject
* Set the Fluent locale for the original record's locale, then;
* Use the Fluent ORM implementation to write the new data into the database
* Remove Translatable's data: the Locale column on the base table and the _translationgroups database table

The task is safe to run multiple times, since it will only process a DataObject's data set once due to it cleaning
up after itself at the end of the task.

## Data structures

Translatable works by adding a "Locale" column to a translated DataObject's database table. This column is then
used to modify the SilverStripe's ORM SQL queries that are used to read data, essentially allowing the user to
have multiple websites separated by language.

Fluent (4.x) works in a similar way but doesn't split the base table. Instead it adds localisations to a separate
table (e.g. SiteTree_Localised), where each locale has a separate row, and if a locale doesn't have a row but has
a [configured fallback locale](configuration.md) it can return the fallback locale's record instead.

Since Fluent doesn't use the "Locale" column on the base table, we can simply copy pre-localised data from the
base table and (via the Fluent ORM) insert it directly into the \*_Localised table.

For more information, please see ["How Fluent works"](how-fluent-works.md).
