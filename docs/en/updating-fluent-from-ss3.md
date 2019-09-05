# Updating Fluent from SilverStripe 3

For websites that used fluent with SilverStripe 3 we provide an automatic migration task that will change the DB structure to be compatible with fluent / SilverStripe 4. This task is backed by a set of unit tests.

## Running the task
Even though with fluent v4 locales are set via the `/admin/locales` CMS section to the DB. It's required to have the locales defined in Fluent.locales yml config in order to run the BuildTask. For example this could look like:

```
Fluent:
  default_locale: de_CH
  locales:
    - de_CH
    - en_US
```

As a safety measure the Task doesn’t write the changes to the DB by default, it just outputs the queries it would run. To commit the changes `write=true` as an argument is needed.
To run the automated BuildTask, run the following from your command line:
```
vendor/bin/sake dev/tasks/FluentMigrationTask
```
or
```
vendor/bin/sake dev/tasks/FluentMigrationTask write=true
```

This command will do the following:

* Looks up all DataObjects that are Fluent-enabled
* builds SQL queries for all related tables, incl. versions and live tables. It assumes all localised fields are in place. If not, it will catch the DB error and print it.
* creates rows for each locale in the localised tables

What it does not do:
* check if fluent is configured properly
* clean up old data
* remove old localised columns from databases

The task is secure to run multiple times, as it uses `ON DUPLICATE KEY UPDATE`
