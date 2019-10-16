# Migrating from single language site

In case you want to add fluent to an existing site to add multi language functionality you need to:

## Install fluent

use composer to install fluent, see [installation](installation.md)

## Configure fluent
* add locales

You can either do this in the backend, or for the first setup you can utitlise `default_records` to add the locales to the db. 
A fluent.yml might look like:

```
---
Name: myfluentconfig
After: '#fluentconfig'
---

TractorCow\Fluent\Model\Locale:
  default_records:
    nl:
      Title: German
      Locale: de_DE
      URLSegment: de
      IsGlobalDefault: 1
    en:
      Title: English
      Locale: en_GB
      URLSegment: en
```

When you run `dev/build?flush` again, this adds the records to the database if the locales table is still empty.

## Publish available pages in your default locale

Now your site is broken, cause no pages have been published and added as translated page in your default locale. 
You can either publish all pages manually or use [publishall](https://docs.silverstripe.org/en/4/developer_guides/debugging/url_variable_tools/#building-and-publishing-urls) to publish all pages in bulk.
If you run `/admin/pages/publishall` in your browser  your site will be fixed again and you can start adding translated content.  
