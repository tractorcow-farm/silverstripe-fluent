# How Fluent works

As opposed to Translatable which manages separate `SiteTree` objects for multiple
localisations, Fluent stores all localisations for properties on the same table row.

This method has the following benefits:

 * Seamless integration with other modules and extensions (such as Versioned)
 * Allows for installation easily on existing websites
 * Minimises the amount of special case code to handle localisations; A page has the
   same ID no matter the current locale!
 * The simplicity of the single-table method means that any object can be easily
   and transparently localised, even non-SiteTree DataObjects.
 * There is only ever one sitemap, so the page hierarchy doesn't need to be
   duplicated for each additional locale.

Fluent has a couple of built in rules for determining which fields to localise, but
these can be easily customised on a per-object bases (or even by customising the
global ruleset).

When querying data the SQL is augmented to replace all `SELECT` fragments for those
fields with conditionals; It will detect if a value for the localised field (such
as `Title_en_NZ`) exists, and use this if it does, otherwise using the base field
(`Title`) as the default. When a `DataObject` is written, the inverse is performed,
ensuring that the field related to the current locale is correctly written to.

Unfortunately, there's currently no localisation mechanism for SiteTree urls
(for the sake of simplicity). However, this could be implemented if requested. :)

## Creating translations

When creating a translation for a page in the CMS, it helps to know how the current
and default locale relate to each other, both when creating, editing, and
publishing a record.

It's ideal, but not always necessary, to create a record in the default locale. By
default all translations of that object will inherit the value entered in and saved
in the default locale.

If a record is NOT created in the default locale then the default value of that
record will be the value last saved in any locale. This behaviour may appear
confusing, unless this fact is understood.

Saving a record in any locale will create a saved state for that locale,
meaning it will no longer inherit the default value, but it's always easier
to set a good single default than to edit the record in each locale individually.
