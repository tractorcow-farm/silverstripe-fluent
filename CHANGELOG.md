# Change Log

## [3.3.0](https://github.com/tractorcow/silverstripe-fluent/tree/3.3.0) (2015-11-06)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.2.3...3.3.0)

**Implemented enhancements:**

- API Take advantage of parameterised queries [\#162](https://github.com/tractorcow/silverstripe-fluent/pull/162) ([tractorcow](https://github.com/tractorcow))

## [3.2.3](https://github.com/tractorcow/silverstripe-fluent/tree/3.2.3) (2015-11-06)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.2.2...3.2.3)

**Implemented enhancements:**

- Lithuanian translation [\#154](https://github.com/tractorcow/silverstripe-fluent/pull/154) ([uniun](https://github.com/uniun))

**Fixed bugs:**

- BUG Fix default row values not being properly loaded when saving with… [\#147](https://github.com/tractorcow/silverstripe-fluent/pull/147) ([tractorcow](https://github.com/tractorcow))
- Fixed fluent dropdown presence on collapsed cms-menu [\#137](https://github.com/tractorcow/silverstripe-fluent/pull/137) ([ARNHOE](https://github.com/ARNHOE))
- BUG Fix creation of records in non-default locale [\#136](https://github.com/tractorcow/silverstripe-fluent/pull/136) ([tractorcow](https://github.com/tractorcow))

**Closed issues:**

- Portuguese not output on frontend [\#143](https://github.com/tractorcow/silverstripe-fluent/issues/143)
- Enhancement: API to determine if current page has translated content [\#142](https://github.com/tractorcow/silverstripe-fluent/issues/142)
- Default translated field value doesn't seem logical [\#132](https://github.com/tractorcow/silverstripe-fluent/issues/132)

**Merged pull requests:**

- Add tests for 3.2 [\#161](https://github.com/tractorcow/silverstripe-fluent/pull/161) ([tractorcow](https://github.com/tractorcow))
- adding public for better readibility [\#153](https://github.com/tractorcow/silverstripe-fluent/pull/153) ([spekulatius](https://github.com/spekulatius))
- turning spaces in tabs and adding some phpdoc blocks [\#152](https://github.com/tractorcow/silverstripe-fluent/pull/152) ([spekulatius](https://github.com/spekulatius))
- adding space [\#151](https://github.com/tractorcow/silverstripe-fluent/pull/151) ([spekulatius](https://github.com/spekulatius))
- remove trailing spaces in the codebase [\#150](https://github.com/tractorcow/silverstripe-fluent/pull/150) ([spekulatius](https://github.com/spekulatius))

## [3.2.2](https://github.com/tractorcow/silverstripe-fluent/tree/3.2.2) (2015-07-20)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.2.1...3.2.2)

**Fixed bugs:**

- FIX: Fixes \#135 Increased range of native lang strings. [\#131](https://github.com/tractorcow/silverstripe-fluent/pull/131) ([phptek](https://github.com/phptek))
- FIX: Fixes \#125 Duplicate locales in homepage URI [\#126](https://github.com/tractorcow/silverstripe-fluent/pull/126) ([phptek](https://github.com/phptek))

**Closed issues:**

- Native language string isn't always returned [\#130](https://github.com/tractorcow/silverstripe-fluent/issues/130)
- Homepage view breaks when locale is used [\#125](https://github.com/tractorcow/silverstripe-fluent/issues/125)
- CMS Page Filter is broken [\#124](https://github.com/tractorcow/silverstripe-fluent/issues/124)
- Some localised fields not indicated as such in CMS [\#122](https://github.com/tractorcow/silverstripe-fluent/issues/122)

## [3.2.1](https://github.com/tractorcow/silverstripe-fluent/tree/3.2.1) (2015-06-03)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.2.0...3.2.1)

**Implemented enhancements:**

- Subsite support for urlsegment [\#108](https://github.com/tractorcow/silverstripe-fluent/pull/108) ([lekoala](https://github.com/lekoala))

**Fixed bugs:**

- FIX: Ensure deferred CMS panels are locale-specific \(fixes \#94\) [\#119](https://github.com/tractorcow/silverstripe-fluent/pull/119) ([kinglozzer](https://github.com/kinglozzer))

**Closed issues:**

- GridField Translate [\#118](https://github.com/tractorcow/silverstripe-fluent/issues/118)
- Class "current" menu does not work properly [\#117](https://github.com/tractorcow/silverstripe-fluent/issues/117)
- Translation of URLs \(url segments\) [\#113](https://github.com/tractorcow/silverstripe-fluent/issues/113)
- Site tree not updating titles [\#94](https://github.com/tractorcow/silverstripe-fluent/issues/94)

## [3.2.0](https://github.com/tractorcow/silverstripe-fluent/tree/3.2.0) (2015-02-25)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.1.1...3.2.0)

**Implemented enhancements:**

- Add TemplateGlobalProvider to Fluent [\#84](https://github.com/tractorcow/silverstripe-fluent/issues/84)
- Default language alias in URL [\#75](https://github.com/tractorcow/silverstripe-fluent/issues/75)
- Use utf-8 locales [\#105](https://github.com/tractorcow/silverstripe-fluent/pull/105) ([tractorcow](https://github.com/tractorcow))
- CMS: lang selector links to display block [\#100](https://github.com/tractorcow/silverstripe-fluent/pull/100) ([willmorgan](https://github.com/willmorgan))

**Fixed bugs:**

- language switch in IE9 [\#90](https://github.com/tractorcow/silverstripe-fluent/issues/90)
- FluentExtension::updateCMSFields doesn't match UploadField [\#82](https://github.com/tractorcow/silverstripe-fluent/issues/82)
- BUG Sitemap.xml shows incorrect locale for domains [\#74](https://github.com/tractorcow/silverstripe-fluent/issues/74)
- infinite redirect when opening an url disabled with FluentFilteredExtens... [\#98](https://github.com/tractorcow/silverstripe-fluent/pull/98) ([Copperis](https://github.com/Copperis))
- updated conversion task to work for me via sake. Main changes are: [\#83](https://github.com/tractorcow/silverstripe-fluent/pull/83) ([oetiker](https://github.com/oetiker))

**Closed issues:**

- How to set empty aliase for default\_locale ? [\#109](https://github.com/tractorcow/silverstripe-fluent/issues/109)
- Question about language fall back in Model Admin [\#102](https://github.com/tractorcow/silverstripe-fluent/issues/102)
- Documentation [\#97](https://github.com/tractorcow/silverstripe-fluent/issues/97)
- Infinite redirect when opening url disabled with FluentFilteredExtension [\#96](https://github.com/tractorcow/silverstripe-fluent/issues/96)
- Can't create FluentFilteredExtension [\#85](https://github.com/tractorcow/silverstripe-fluent/issues/85)
- convert task [\#80](https://github.com/tractorcow/silverstripe-fluent/issues/80)
- Question: How to NOT show the text in the default language [\#79](https://github.com/tractorcow/silverstripe-fluent/issues/79)
- Migration of ShowInMenus [\#78](https://github.com/tractorcow/silverstripe-fluent/issues/78)
- FluentMenuExtension trouble [\#77](https://github.com/tractorcow/silverstripe-fluent/issues/77)
- Avoid full page refresh on locale change [\#76](https://github.com/tractorcow/silverstripe-fluent/issues/76)

## [3.1.1](https://github.com/tractorcow/silverstripe-fluent/tree/3.1.1) (2014-04-26)
[Full Changelog](https://github.com/tractorcow/silverstripe-fluent/compare/3.1.0...3.1.1)

The major feature in this version is the ability to translate urls; Meaning a page can have a urlsegment in
a user's native language. This version however requires Silverstripe CMS version 3.1.1 at least, and 3.1.0 
does not include this ability. No changes other than upgrading are required, as the URLSegment exclusion
to translated fields has been removed from the default `SiteTree.field_exclude` config.

Additional options for handling redirection on the homepage were added to tweak home page SEO. By default
the `Fluent.remember_locale` config is set to true, and will attempt to redirect users from the home page
to their last remembered locale. If this interferes with behaviour of your website you should set this to false.

An icon for all translated fields is now added in the CMS. In order to make sure that this appears correctly
for all dataobject fields, it's necessary to make sure that you utilise the `DataObject::beforeUpdateCMSFields`
method to ensure you have added any custom fields prior to extensions running `updateCMSFields`.

There is a new API for users to customise the visibility of pages in the menu. You can now add the
`FluentMenuExtension` extension to `SiteTree` to extend the single `Show In Menu` checkbox into
a list of options allowing visibility setting for each locale.

Also for users who wish to disable translation for a dataobject, you may now explicitly set the string value
of 'none' to the `translate` config for any dataobject, rather than having an empty array.

**Implemented enhancements:**

- ENHANCEMENT: Allow custom home segment [\#55](https://github.com/tractorcow/silverstripe-fluent/pull/55) ([bummzack](https://github.com/bummzack))

**Fixed bugs:**

- Zend Framework fails to detect temp dir. [\#70](https://github.com/tractorcow/silverstripe-fluent/pull/70) ([uniun](https://github.com/uniun))
- FIX Composer dependencies [\#60](https://github.com/tractorcow/silverstripe-fluent/pull/60) ([nbenn](https://github.com/nbenn))
- FIX Composer dependencies [\#59](https://github.com/tractorcow/silverstripe-fluent/pull/59) ([nbenn](https://github.com/nbenn))
- BUG Translation fixes [\#56](https://github.com/tractorcow/silverstripe-fluent/pull/56) ([uniun](https://github.com/uniun))

**Closed issues:**

- Language switching : Option to use different URLs on Redirect page type? [\#73](https://github.com/tractorcow/silverstripe-fluent/issues/73)
- domain setup does not work as expected -\> auto-switching domains fails [\#72](https://github.com/tractorcow/silverstripe-fluent/issues/72)
- Translating site title and Site tagline [\#71](https://github.com/tractorcow/silverstripe-fluent/issues/71)
- API Need option to toggle ShowInMenus by locale [\#68](https://github.com/tractorcow/silverstripe-fluent/issues/68)
- Homepage and 302 redirects [\#67](https://github.com/tractorcow/silverstripe-fluent/issues/67)
- Localized DropdownField missing green icon  [\#66](https://github.com/tractorcow/silverstripe-fluent/issues/66)
- Composer dependencies for framework 3.2 [\#65](https://github.com/tractorcow/silverstripe-fluent/issues/65)
- ENHANCEMENT request alternate seo [\#64](https://github.com/tractorcow/silverstripe-fluent/issues/64)
- DataObject CSV Import [\#63](https://github.com/tractorcow/silverstripe-fluent/issues/63)
- setlocale problem [\#62](https://github.com/tractorcow/silverstripe-fluent/issues/62)
- Locale Filter for pages [\#61](https://github.com/tractorcow/silverstripe-fluent/issues/61)
- phpunit: Notice: Undefined index: REQUEST\_URI [\#58](https://github.com/tractorcow/silverstripe-fluent/issues/58)
- ENHANCEMENT Possible support for UserForms? [\#53](https://github.com/tractorcow/silverstripe-fluent/issues/53)

**Merged pull requests:**

- Update nl.yml [\#69](https://github.com/tractorcow/silverstripe-fluent/pull/69) ([ARNHOE](https://github.com/ARNHOE))
- Added language files [\#57](https://github.com/tractorcow/silverstripe-fluent/pull/57) ([ARNHOE](https://github.com/ARNHOE))

## [3.1.0](https://github.com/tractorcow/silverstripe-fluent/tree/3.1.0) (2013-09-04)
**Implemented enhancements:**

- Domain filters for locales [\#43](https://github.com/tractorcow/silverstripe-fluent/issues/43)
- Need a nicer locale dropdown [\#41](https://github.com/tractorcow/silverstripe-fluent/issues/41)
- Integrate with Googlesitemaps module [\#36](https://github.com/tractorcow/silverstripe-fluent/issues/36)
- Translation for Enum [\#29](https://github.com/tractorcow/silverstripe-fluent/issues/29)
- SiteTree URLSegment [\#6](https://github.com/tractorcow/silverstripe-fluent/issues/6)
- Feature: Locale aliases [\#3](https://github.com/tractorcow/silverstripe-fluent/issues/3)
- Allow Alias to be called in LocaleMenu.ss [\#5](https://github.com/tractorcow/silverstripe-fluent/pull/5) ([ARNHOE](https://github.com/ARNHOE))

**Fixed bugs:**

- Googlesitemaps custom dataobjects [\#44](https://github.com/tractorcow/silverstripe-fluent/issues/44)
- SiteTreeURLSegmentField doesn't respect locale for top level pages [\#39](https://github.com/tractorcow/silverstripe-fluent/issues/39)
- loop Menu\(1\) and FluentFilteredExtension [\#27](https://github.com/tractorcow/silverstripe-fluent/issues/27)
- Where conditions not being properly evaluated [\#7](https://github.com/tractorcow/silverstripe-fluent/issues/7)
- ModelAdmin Dataobjects [\#4](https://github.com/tractorcow/silverstripe-fluent/issues/4)
- BUG. Impossible to flush the site on live environment. [\#51](https://github.com/tractorcow/silverstripe-fluent/pull/51) ([uniun](https://github.com/uniun))
- BUG compass reset was overruling SS styling [\#10](https://github.com/tractorcow/silverstripe-fluent/pull/10) ([ARNHOE](https://github.com/ARNHOE))

**Closed issues:**

- saving wrong value, when entering same value for Title and MenuTitle [\#52](https://github.com/tractorcow/silverstripe-fluent/issues/52)
- LocalisedFields, SiteTree and updateCMSFields problem [\#50](https://github.com/tractorcow/silverstripe-fluent/issues/50)
- detect\_locale not work [\#49](https://github.com/tractorcow/silverstripe-fluent/issues/49)
- Now fluent does not save the last user's language selection. [\#48](https://github.com/tractorcow/silverstripe-fluent/issues/48)
- LocalisedField in ModelAdmin [\#47](https://github.com/tractorcow/silverstripe-fluent/issues/47)
- Translatable URLs [\#46](https://github.com/tractorcow/silverstripe-fluent/issues/46)
- Problem with $\_SERVER\['HTTP\_ACCEPT\_LANGUAGE'\] [\#45](https://github.com/tractorcow/silverstripe-fluent/issues/45)
- Loading time [\#42](https://github.com/tractorcow/silverstripe-fluent/issues/42)
- Publishing page issue [\#38](https://github.com/tractorcow/silverstripe-fluent/issues/38)
- Problem with is\_frontend\(\) function \( Still problems \) [\#37](https://github.com/tractorcow/silverstripe-fluent/issues/37)
- Problem with is\_frontend\(\) function \( Very serious \) [\#35](https://github.com/tractorcow/silverstripe-fluent/issues/35)
- Flushing gives problems [\#33](https://github.com/tractorcow/silverstripe-fluent/issues/33)
- CMS Interface Language [\#32](https://github.com/tractorcow/silverstripe-fluent/issues/32)
- Login URL \(Security/login\) and website Locale selector [\#31](https://github.com/tractorcow/silverstripe-fluent/issues/31)
- URL resolution and FluentFilteredExtension \(NEW\) [\#30](https://github.com/tractorcow/silverstripe-fluent/issues/30)
- Frontend LocaleMenu LinkingMode [\#28](https://github.com/tractorcow/silverstripe-fluent/issues/28)
- URL resolution and FluentFilteredExtension [\#26](https://github.com/tractorcow/silverstripe-fluent/issues/26)
- Fluent filter needs better titles [\#25](https://github.com/tractorcow/silverstripe-fluent/issues/25)
- Comments module and Forum module problem [\#24](https://github.com/tractorcow/silverstripe-fluent/issues/24)
- Disable FluentMySQLSearch class [\#23](https://github.com/tractorcow/silverstripe-fluent/issues/23)
- Disable FluentMySQLSearch class [\#22](https://github.com/tractorcow/silverstripe-fluent/issues/22)
- has\_many or many\_many relations [\#21](https://github.com/tractorcow/silverstripe-fluent/issues/21)
- Language selection in CMS and frontend simultaneously \(Serious Problem\) [\#20](https://github.com/tractorcow/silverstripe-fluent/issues/20)
- Temp Locale \(New\) [\#19](https://github.com/tractorcow/silverstripe-fluent/issues/19)
- Browser Locale [\#18](https://github.com/tractorcow/silverstripe-fluent/issues/18)
- Temp Locale [\#17](https://github.com/tractorcow/silverstripe-fluent/issues/17)
- Problem with Lost password notification [\#16](https://github.com/tractorcow/silverstripe-fluent/issues/16)
- set\_date\_format and set\_time\_format deprecated [\#15](https://github.com/tractorcow/silverstripe-fluent/issues/15)
- license [\#14](https://github.com/tractorcow/silverstripe-fluent/issues/14)
- Login URL \(Security/login\) [\#13](https://github.com/tractorcow/silverstripe-fluent/issues/13)
- \[REQUEST\] Autolanguage mode \(browser language\) [\#12](https://github.com/tractorcow/silverstripe-fluent/issues/12)
- Fulltext search issues [\#11](https://github.com/tractorcow/silverstripe-fluent/issues/11)
- Translated fields get filled in [\#9](https://github.com/tractorcow/silverstripe-fluent/issues/9)
- CMS interface language [\#2](https://github.com/tractorcow/silverstripe-fluent/issues/2)
- Locale selector bit dodgy [\#1](https://github.com/tractorcow/silverstripe-fluent/issues/1)

**Merged pull requests:**

- Change dependency versions to work for 3.1.0-rc1. [\#34](https://github.com/tractorcow/silverstripe-fluent/pull/34) ([ARNHOE](https://github.com/ARNHOE))
- BaseURL within a specified locale [\#8](https://github.com/tractorcow/silverstripe-fluent/pull/8) ([ARNHOE](https://github.com/ARNHOE))



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*