---
title: How Fluent works
summary: How Fluent localises data including table structures and benefits of the approach
icon: magic
---

# How fluent works

Fluent stores localisations for any model.

For example, a versioned [`SiteTree`](api:SilverStripe\CMS\Model\SiteTree) object will have these tables:

- `SiteTree`
- `SiteTree_Live`
- `SiteTree_Versions`
- `SiteTree_Localised` (joined to `SiteTree`)
- `SiteTree_Localised_Live` (joined to `SiteTree_Live`)
- `SiteTree_Localised_Versions` (joined to `SiteTree_Versions`)

The unique key for the `_Localised` table is `RecordID`, `Locale`
(and `Version` for the versioned table).

However, the record ID of any model is distinct, regardless of the locale
you are viewing in, so there is always only one authoritative logical (and physical)
record for each object, even if there are multiple `_Localised` rows.

Missing localisations gracefully degrade, meaning that missing strings will simply be
drawn either from other locales declared under "Fallbacks" for the current locale, or
from the selected default locale's `_Localised` table.

> [!NOTE]  
> Once a page is published, no 'falling back' happens, as the data is copied to the locale on publish.

This method has the following benefits:

- seamless integration with other modules and extensions (such as Versioned)
- allows for installation easily on existing websites
- minimises the amount of special case code to handle localisations; A page has the
  same ID no matter the current locale!
- multiple localisations (even for newly added locales) can be created on the fly
  without rebuilding the database.
- there is only ever one sitemap, so the page hierarchy doesn't need to be
  duplicated for each additional locale.
- additional locales are not constrained by MySQL physical row size limits.
