# How Fluent works

In older versions of Fluent, localisation was stored on a single table row.
However, versions 4.0.0 and onwards will store localisations for any model
separately to the main table in a similar table suffixed with `_Localised`.

For example, a versioned SiteTree object will have these tables:

 - `SiteTree`
 - `SiteTree_Live`
 - `SiteTree_Versions`
 - `SiteTree_Localised` (joined to SiteTree)
 - `SiteTree_Localised_Live` (joined to SiteTree_Live)
 - `SiteTree_Localised_Versions` (joined to SiteTree_Versions)

The unique key for the `_Localised` table is `RecordID`, `Locale`
(and `Version` for the versioned table).

However, the record ID of any model is distinct, regardless of the locale
you are viewing in, so there is always only one authoritative logical (and physical)
record for each object, even if there are multiple `_Localised` rows.

Missing localisations gracefully degrade, meaning that missing strings will simply be
drawn either from other locales declared under "Fallbacks" for the current locale, or
from the selected default locale's `_Localised` table.

**Note:** Once a page is published, no 'falling back' happens, as the data is copied to the locale on publish.

This method has the following benefits:

 * Seamless integration with other modules and extensions (such as Versioned)
 * Allows for installation easily on existing websites
 * Minimises the amount of special case code to handle localisations; A page has the
   same ID no matter the current locale!
 * Multiple localisations (even for newly added locales) can be created on the fly
   without rebuilding the database.
 * There is only ever one sitemap, so the page hierarchy doesn't need to be
   duplicated for each additional locale.
 * Additional locales are not constrained by MySQL physical row size limits, which
   was an issue in Fluent 3.x.
