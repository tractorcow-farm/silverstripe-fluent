# Configuration

Most configuration is done via the CMS locales section.

Please make sure to REMOVE any `i18n::set_locale` calls from your `_config.php` file, as it
will interfere with locale bootstrapping in certain situations (such as `Security` controller actions).

## Locale configuration

You can create locales via the `/admin/locales` CMS section. 

Each locale has these fields in the CMS editor:
 - `Locale`: Dropdown which lets you select a locale code from the global locale source
 - `Title`: Name to use for this locale in the locale switcher
 - `URL Segment`: Defaults to the locale (e.g. `en_NZ`) but can be customised. Must be unique.
 
Check the box titled `This is the global default locale` to set this locale as the global default. 
 
**Note:** If using domains, you can additionally assign per-domain defaults as well.

 - `Domain`: Dropdown to assign this locale to a domain.
 
Navigate to the `Fallbacks` tab, which allows you to specify one or more fallback locales for this locale.

Once you add at least two locales to your site, you can begin localising your content. 

_**Important:** Pages in locales that fall back must be added and published in each locale you want them to be visible 
in - including the default locale. This essentially requires the re-publication of content in each locale, once content 
is localised. Be aware that the site will not appear as it did before the creation of Fluent locales until this step is 
completed._

If desired, Fluent can be enabled on a field by field basis. Note that non-translated fields on any page will be 
displayed in the default locale.

## Default locale options

### Disable default locale url segment prefix

`TractorCow\Fluent\Extension\FluentDirectorExtension.disable_default_prefix` (default: `false`)

This option will allow users to exclude the default locale urlsegment from pages in the default locale.

*Default behaviour (disabled)*

If this is left at the default, false, then the default locale will always be prefixed with the
urlsegment of that locale.

In addition to this, another hreflang will be generated for the root url `/` with the hreflange="x-default".

E.g.

```html
<link rel="alternate" hreflang="x-default" href="https://mysite.com/" />
<link rel="alternate" hreflang="en-nz" href="https://mysite.com/nz/" />
<link rel="alternate" hreflang="en-au" href="https://mysite.com/au/" />
```

It is recommended to leave this setting at the default false in order to ensure the correct
locale is set for every page, but in some cases (especially when upgrading websites) it
may be better to keep existing urls for the default locale intact.

Note: x-default is only added for the home pages. Other pages won't have x-default.

*Enabled behaviour*

If you prefer to keep the prefix off from all links in the default locale, you can set this option via
YML config. When this is enabled, the prefix will only be used (prepended) in links to non-default locales.

E.g.

```yaml
---
Name: myfluentconfig
---
TractorCow\Fluent\Extension\FluentDirectorExtension:
  disable_default_prefix: true
```

E.g.

```html
<link rel="alternate" hreflang="en-nz" href="https://mysite.com/" />
<link rel="alternate" hreflang="en-au" href="https://mysite.com/au/" />
```

Note: If you still wish to use x-default for the global locale, you can assign x-default
to the global locale via the CMS. However, this affects all pages, not just the home page.


## Field localisation configuration

Great, now we've set up our languages. Our next job is to decide which DataObjects, and which
fields of those DataObjects, should be localised.

By default Fluent will attempt to analyse the field type and name of each `DBField` specified in your `DataObject`.
Rules specified by the below configurations can be used to determine if a field should be included
or excluded, either by name, or by type (in order of priority):

 - `TractorCow\Fluent\Extension\FluentExtension.field_exclude` Exclude by name
 - `TractorCow\Fluent\Extension\FluentExtension.field_include` Include by name
 - `TractorCow\Fluent\Extension\FluentExtension.data_exclude` Exclude by type
 - `TractorCow\Fluent\Extension\FluentExtension.data_include` Include by type

E.g.

```yaml
---
Name: fluentfieldconfig
---
TractorCow\Fluent\Extension\FluentExtension:
  data_exclude:
    - Varchar(100)
    - DBHTMLText
```

Fields can also be filtered directly by name by using the `translate` config option, set to the fields you want
localised. Note that this must be on the same class as the database field is specified (not subclasses).

```yaml
---
Name: myblogconfig
---
Page:
  translate:
    - 'Heading'
    - 'Description'
```

or via PHP

```php
use SilverStripe\CMS\Model\SiteTree;

class Page extends SiteTree
{
    private static $db = [
        'Heading'     => 'Varchar(255)',
        'Description' => 'Text',
        'MetaNotes'   => 'Text',
    ];

    private static $translate = [
        'Heading',
        'Description'
    ];
}
```

In the above example, Heading and Description will be translated but not MetaNotes.

If you want to localise a `has_one` relation then you can add the field (with 'ID'
suffix included).

```yaml
BlogHolder:
  translate:
    - 'OwnerID'
```

**Note:** If you wish to translate `has_many` or `many_many` then those objects will need
to be filtered via another method. See [Locale based filter configuration](#locale-based-filter-configuration).

If you want to localise a `DataObject` that doesn't extend `SiteTree` then you'll need
to add the appropriate extension:

```yaml
---
Name: myextensions
---
MyDataObject:
  extensions:
    - 'TractorCow\Fluent\Extension\FluentExtension'
```

If `MyDataObject` is versioned, use `FluentVersionedExtension` instead and apply this config
_after_ the `Versioned` extension using an `after` block in your config title block.

```yaml
---
Name: myextensions
after: '#versionedfiles'
---
MyDataObject:
  extensions:
    - 'TractorCow\Fluent\Extension\FluentVersionedExtension'
``` 

Set the translate option to 'none' to disable all translation on that `DataObject`.

```php
class FormPage extends Page
{
    private static $translate = 'none';
}
```

**Note:** Editing any locale affects the `SiteTree(_live)` table. In contrast to SilverStripe 3, the SiteTree table is 
only used for non-localised fields.

## Frontend publish required

By default, DataObjects must be Localised in order for them to be viewed on the frontend. In the case of a `SiteTree`
record, this means that there must be a `SiteTree_Localised` row for this record and Locale to view the page in
`stage=Stage`, and there must be a `SiteTree_Localised_Live` row for this record and Locale to view the page in
`stage=Live`.

We can change this behaviour by updating the `frontend_publish_required` configuration.

Globally:
```yaml
TractorCow\Fluent\Extension\FluentExtension:
  frontend_publish_required: false
```

For a specific DataObject:
```yaml
MySite\Model\MyModel:
  frontend_publish_required: false
```

**Note:** If you are applying this via an `Extension`, be sure to apply it after the `FluentExtension`.

The result is that a DataObject that has *not* been Localised, will display on the frontend with content populated by
it's Fallbacks (the same beheviour as what you see when viewing DataObjects from within the CMS).

## SilverStripe Fluent and search

### SilverStripe Core search

To use SilverStripe Core search with Fluent, add the `FluentSearchForm` to your configuration.

```yaml
---
Name: myextensions
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\CMS\Search\SearchForm:
    class: TractorCow\Fluent\Search\FluentSearchForm
``` 

**Note:** If you're using the SilverStripe CMS `$SearchForm` to display the default SilverStripe template on the front-end, this will update form and input id and class selectors from 'SearchForm' to 'FluentSearchForm'. E.g. form element `id='SearchForm_SearchForm'` will become `id='FluentSearchForm_SearchForm'`.

## Locale based filter configuration

In addition to localising fields within a DataObject, a filter can also be applied
with the `TractorCow\Fluent\Extension\FluentFilteredExtension` extension to conditionally
show or hide DataObjects within specific locales. This will create a many_many relationship
between your object and the locales table.

This feature is also necessary in cases where has_many or many_many relationships will need
to be customised for each locale. For example, this could be applied to a `Product` with
limited availability in other countries.

**Note:** It's not necessary to actually localise this object in order for it to be
filterable; `FluentFilteredExtension` and `FluentExtension` each work independently.

**Warning:** This must be added to the base class, such as `SiteTree` in order for it to filter
for pages, or for queries of that base type.

```yaml
---
Name: myproductconfiguration
---
Product:
  extensions:
    - 'TractorCow\Fluent\Extension\FluentFilteredExtension'
```

Make sure that if (and only if) you are filtering a DataObject that doesn't call the default field scaffolder (such
as by calling `parent::getCMSFields()`), make sure that your code calls `extend('updateCMSFields', $fields)`
as demonstrated below.

```php
public function getCMSFields()
{
	$fields = new FieldList(
		new TextField('Title', 'Title', null, 255)
	);
	$this->extend('updateCMSFields', $fields);
	return $fields;
}
```

Now, when editing this item in the CMS, there will be a gridfield where you can assign
visible locales for this object.

![Locale Filter](images/locale-filter.png "Locale filter")

Note: Although these objects will be filtered in the front end, this filter is disabled
in the CMS in order to allow access by site administrators in all locales.

## Single locale (isolated) filter

Sometimes it may be necessary to filter a record to the locale it was created in. A simple filter extension
`FluentIsolatedExtension` can be added to any object. This behaviour differs from `FluentFilteredExtension` in these ways:

 - Filtering is only applied to a single locale
 - The locale cannot be changed
 - The filter is applied to the CMS view by default (you need to change locale to see it)
 - There is no configuration / UX considerations. Deleting the record works like traditional silverstripe
   objects, as it's not complicated by the locale assignment logic of the other extensions. There are also
   no CMS fields related to the filtering.
 - You cannot use this extension on an object with `FluentExtension` or `FluentFilteredExtension`.
   They are mutually exclusive and will throw an exception if you try to use them together. 

## Routing and Locale Detection

The `DetectLocaleMiddleware` will detect if a locale has been requested (or is default) and is not the current
locale, and will redirect the user to that locale if needed.

Will cascade through different checks in order:
1. Routing path (e.g. `/de/ueber-uns`)
2. Request variable (e.g. `ueber-uns?FluentLocale=de`)
3. Domain (e.g. `http://example.de/ueber-uns`)
4. Session (if a session is already started)
5. Cookie (if `DetectLocaleMiddleware.persist_cookie` is configured)
6. Request headers (if `FluentDirectorExtension.detect_locale` is configured)

Additionally, detected locales will be set in cookies. This behaviour can be configured through
`DetectLocaleMiddleware.persist_cookie`. To solely rely on sessions (if session is started) and
stateless request data (routing path, request variable or domain), configure as follows:

```yaml
TractorCow\Fluent\Middleware\DetectLocaleMiddleware:
  persist_cookie: false
```

Note that locales will only be persisted to the session if the session is already started. If
you want to guarantee session persistence, you will need to ensure you call `->start()`
on the session in the active HTTPRequest via a \_config.php file, or add a higher priority
middleware that always starts the session ensuring it runs before `DetectLocaleMiddleware`.
Be aware that prematurely starting sessions may complicate HTTP caching in your website.

## Encoding

Setting proper encoding is important especially when your application needs to deal with non-latin characters.
Following configuration is recommended:

```yaml
SilverStripe\ORM\Connect\MySQLDatabase:
  connection_charset: utf8mb4
  connection_collation: utf8mb4_unicode_ci
  charset: utf8mb4
  collation: utf8mb4_unicode_ci
```

Note that using this configuration may cause some of your DB indexes to break as the indexes may increase in size.
General workaround is to change any `Varchar(255)` fields which are associated with indexes to `Varchar(191)`.
This can be done on configuration level and it can cover even fields which are not part of your code base.

### Known issues with non-latin characters

Core SilverStripe modules provide text processing functionality but part of it may not work properly with non-latin characters.
Knows issues are:

* incorrect detection of end of word
* incorrect detection of end of line
* use of functions which are not multi-byte safe

## CMS UI

There are some optional components which can be enabled / disabled.

### Page edit form action buttons

Using the page edit form buttons to localise pages may not work properly in all situations, hence the following configuration (enabled by default):

```yml
SilverStripe\CMS\Model\SiteTree:
  localise_actions_enabled: true
```

If it's disabled, the `Copy to draft` and `Copy & publish` buttons will not be displayed.
Users are expected to use the `Localisation` manager tab in the page edit form instead in such case.

#### When should I disable this setting?

In case your setup contains multiple top level locales (i.e. locales which have no fallbacks) you may need to review this setting.
Using the action buttons to localise pages may result in unpredictable results as the content for the newly localised page will be taken from the base record as no fallback locale exists.
This may still be fine in some cases, for example when migrating from a single language site.
