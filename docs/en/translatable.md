# Migration guide for Translatable sites

If you are migrating from the [Translatable module](https://github.com/silverstripe/silverstripe-translatable) you
can use the `ConvertTranslatableTask` to assist in migrating your database schema and data.

The steps to follow are:

1. Back up your DB.
2. Ensure you're logged into the CMS, as the subsequent migration scripts will require admin privileges, and it can
   be easier to log in at the start.
3. Remove the `Translatable` module completely prior to progressing.
4. [Configure](configuration.md) Fluent, taking care to apply the `FluentExtension` to any class previously extended
   by `Translatable` (`SiteTree` and `SiteConfig` are included by default).
5. [Install](docs/en/installation.md) the Fluent module.
6. Run a dev/build to ensure all additional table fields have been generated.
7. Back up your DB again.
8. Run the `ConvertTranslatableTask` either by visiting `dev/tasks/ConvertTranslatableTask` or by sake.

```bash
./sake dev/tasks/ConvertTranslatableTask
```

This will run the installation in a DB transaction (tested in MySQL).

This will not migrate anything filtered by locale, and will initially assume that all items are visible in all locales.
`FluentFilteredExtension` will need to be applied and migrated manually for each filtered dataobject.

This migration tool also assumes that the root Locale sitetree is the base sitetree for your website, but will not
delete pages that only exist in other locales. Some re-ordering and page deletion may be necessary after migration.

As a part of the migration process all translated versions of pages will be unpublished, merged back into the main
translation, and then published. If you have unpublished pages that should not appear in your site tree then make sure
to delete them prior to, or after migration.
