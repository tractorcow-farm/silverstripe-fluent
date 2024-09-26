# Migrating from single language site

In case you want to add fluent to an existing site to add multi language functionality you need to:

## Install fluent

Use composer to install fluent, see [installation](installation.md)

## Configure fluent

* Add locales

You can either do this in the backend, or for the first setup you can utitlise `default_records` to add the locales to
the db.
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

When you run `sake db:build --flush` again, this adds the records to the database if the locales table is still empty.

## Populating initial localised content for existing Pages and DataObjects in your default locale

Now your site is broken because nothing has been published and added as translated data in your default locale. You can
either manually localise all DataObjects &amp; Pages manually or use one of the automation options below.

### Automated tools for localisation

#### From the CMS (SiteTree only)

Use Silverstripe's
built-in [publishall](https://docs.silverstripe.org/en/4/developer_guides/debugging/url_variable_tools/#building-and-publishing-urls)
tool to publish all Pages in bulk.
Run `/admin/pages/publishall` in your browser and your site will be fixed again and you can start adding translated
content.

_This method will work with Pages only (not localised DataObjects)._

#### Commandline or Queued Jobs (SiteTree and DataObjects)

The `InitialPageLocalisation` and `InitialDataObjectLocalisationTask` dev tasks may be used to localise and, optionally,
publish your `Versioned` data (including Pages) from the commandline or queued as a job (if the Queued Jobs module is installed).

`InitialPageLocalisation` - localise all `SiteTree` objects (Pages)

`InitialDataObjectLocalisationTask` - localise all Fluent-enabled DataObjects (excluding `SiteTree`)

1. Example: Localise all Pages (default, without publishing)

   ```sh
   sake tasks:initial-page-localisation-task
   ```

2. Example: Localise &amp; publish all Pages

    ```sh
    sake tasks:initial-page-localisation-task --publish
    ```

3. Example: Localising Pages in batches can be done by using the `limit` option. 
   This will localise &amp; publish five pages on each run.

    ```sh
    sake tasks:initial-page-localisation-task --publish --limit=5
    ```

4. Example: All the same functionality is available for localising all DataObjects, including `Versioned` and non-Versioned classes

    ```sh
    sake tasks:initial-dataobject-localisation-task
    ```

    or

    ```sh
    sake tasks:initial-dataobject-localisation-task --publish --limit=5
    ```

#### Customize your own initialisation dev task

Perhaps you want to be more selective in how you initialise your localised content.
The `InitialDataObjectLocalisationTask` class can be easily extended to either list exactly which classes you want to
initially localise, or you can exclude specific classes from initialisation.

1. **Initialise specific classes:** The following example will create a task which localises **_ONLY_** `BlogPost`
pages, `Testimonial` objects, _and their subclasses (if any)_.

    ```php
    class CustomLocalisationTask extends InitialDataObjectLocalisationTask
    {
        /**
         * @var string
         */
        private static $segment = 'custom-localisation-initialisation-task';
    
        /**
         * @var string
         */
        protected $title = 'Custom localisation initialisation';
    
        /**
         * @var string[]
         */
        protected array $include_only_classes = [
            \SilverStripe\Blog\Model\BlogPost::class,
            \AcmeCo\Model\Testimonial::class
        ];
    
    }
    ```

2. **Initialise all DataObjects but exclude some:** The following example will create a task which localises **_ALL_**
DataObjects **_except_** `BlogPost` pages, `Testimonial` objects, _and their subclasses (if any)_.

    ```php
    class CustomLocalisationTask extends InitialDataObjectLocalisationTask
    {
        /**
         * @var string
         */
        private static $segment = 'custom-localisation-initialisation-task';
    
        /**
         * @var string
         */
        protected $title = 'Custom localisation initialisation';
    
        /**
         * @var string[]
         */
        protected array $exclude_classes = [
            \SilverStripe\Blog\Model\BlogPost::class,
            \AcmeCo\Model\Testimonial::class
        ];
    
    }
    ```

3. **One or the other:** You may specify `$include_only_classes` OR `$exclude_classes` - not both.
If `$include_only_classes` is not an empty array, `$exclude_classes` will be ignored.
