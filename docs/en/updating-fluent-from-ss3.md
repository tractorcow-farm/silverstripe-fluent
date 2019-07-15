# Updating Fluent from SilverStripe 3

For websites that used fluent with SilverStripe 3 we provide an automatic migration task that will change the DB structure to be compatible with fluent / SilverStripe 4. This task is backed by a set of unit tests.

## Running the task

To run the automated BuildTask, run the following from your command line:

```
vendor/bin/sake dev/tasks/FluentMigrationTask
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
