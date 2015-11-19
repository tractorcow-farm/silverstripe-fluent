<?php

/**
 * Tests fluent
 *
 * @see SiteTree
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentTest extends SapphireTest
{
    protected static $fixture_file = 'FluentTest.yml';

    protected $extraDataObjects = array(
        'FluentTest_TranslatedObject',
        'FluentTest_FilteredObject'
    );

    protected $illegalExtensions = array(
        'SiteTree' => array(
            'SiteTreeSubsites',
            'Translatable'
        )
    );

    protected $_original_config_locales = null;
    protected $_original_config_default = null;
    protected $_original_config_aliases = null;

    protected function setDefaultConfig()
    {

        // Tweak configuration
        Config::inst()->remove('Fluent', 'locales');
        Config::inst()->update('Fluent', 'locales', array('fr_CA', 'en_NZ', 'en_US', 'es_ES'));
        Config::inst()->remove('Fluent', 'default_locale');
        Config::inst()->update('Fluent', 'default_locale', 'fr_CA');
        Config::inst()->remove('Fluent', 'aliases');
        Config::inst()->update('Fluent', 'aliases', array(
            'en_US' => 'usa'
        ));
        Config::inst()->update('Fluent', 'force_domain', false);
        Config::inst()->remove('Fluent', 'domains');
        Config::inst()->update('Fluent', 'domains', array(
            'www.example.com' => array(
                'locales' => array('es_ES', 'en_US'),
                'default_locale' => 'en_US'
            ),
            'www.example.ca' => array(
                'locales' => array('fr_CA')
            ),
            'www.example.co.nz' => array(
                'locales' => array('en_NZ')
            )
        ));
        Fluent::set_persist_locale('fr_CA');
    }

    public function setUpOnce()
    {

        // Ensure that Fluent doesn't interfere with scaffolding records from FluentTest.yml
        FluentExtension::set_enable_write_augmentation(false);

        Config::nest();
        $this->setDefaultConfig();

        // Force db regeneration using the above values
        self::kill_temp_db();
        self::create_temp_db();
        $this->resetDBSchema(true);

        parent::setUpOnce();

        FluentExtension::set_enable_write_augmentation(true);
        Config::unnest();
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();

        Fluent::set_persist_locale(null);
    }

    public function setUp()
    {

        // Ensure that Fluent doesn't interfere with scaffolding records from FluentTest.yml
        Config::nest();
        $this->setDefaultConfig();
        FluentExtension::set_enable_write_augmentation(false);
        parent::setUp();
        FluentExtension::set_enable_write_augmentation(true);

        // Reset fluent locale and domain mode
        Config::inst()->update('Fluent', 'force_domain', false);
        Fluent::set_persist_locale('fr_CA');
    }

    public function tearDown()
    {
        parent::tearDown();
        Config::unnest();
    }

    /**
     * Test that URLS for pages are generated correctly
     */
    public function testFluentURLs()
    {
        $home = $this->objFromFixture('Page', 'home');
        $about = $this->objFromFixture('Page', 'about');
        $staff = $this->objFromFixture('Page', 'staff');

        // When not in domain mode expect the locale to prefix the relative link
        $this->assertEquals('/', $home->Link());
        $this->assertEquals('/fr_CA/about-us/', $about->Link());
        $this->assertEquals('/fr_CA/about-us/my-staff/', $staff->Link());

        // When acting in domain mode behave a little differently.
        // Since fr_CA is the only locale on the www.example.ca domain, ensure that the locale
        // isn't unnecessarily added to the link.
        // See https://github.com/tractorcow/silverstripe-fluent/issues/75
        $homeDomainLink = $this->withURL('www.example.ca', '/', '/', function () use ($home) {
            return Page::get()->byID($home->ID)->Link();
        });
        $aboutDomainLink = $this->withURL('www.example.ca', '/', '/', function () use ($about) {
            return Page::get()->byID($about->ID)->Link();
        });
        $staffDomainLink = $this->withURL('www.example.ca', '/', '/', function () use ($staff) {
            return Page::get()->byID($staff->ID)->Link();
        });
        $this->assertEquals('/', $homeDomainLink);
        $this->assertEquals('/about-us/', $aboutDomainLink);
        $this->assertEquals('/about-us/my-staff/', $staffDomainLink);
    }

    /**
     * Test that alternate urls for a page work
     */
    public function testAlternateURLs()
    {
        $home = $this->objFromFixture('Page', 'home');
        $about = $this->objFromFixture('Page', 'about');
        $staff = $this->objFromFixture('Page', 'staff');

        $this->assertEquals('/', $home->LocaleLink('fr_CA'));
        $this->assertEquals('/en_NZ/', $home->LocaleLink('en_NZ'));
        $this->assertEquals('/es_ES/', $home->LocaleLink('es_ES'));
        $this->assertEquals('/en_NZ/about-us/', $about->LocaleLink('en_NZ'));
        $this->assertEquals('/es_ES/about-us/', $about->LocaleLink('es_ES'));
        $this->assertEquals('/en_NZ/about-us/my-staff/', $staff->LocaleLink('en_NZ'));
        $this->assertEquals('/es_ES/about-us/my-staff/', $staff->LocaleLink('es_ES'));
    }

    /**
     * Test that alternate baseurls work
     */
    public function testAlternateBaseURLS()
    {
        $oldURL = Director::baseURL();
        Config::inst()->update('Director', 'alternate_base_url', '/mysite/mvc1');

        $home = $this->objFromFixture('Page', 'home');
        $about = $this->objFromFixture('Page', 'about');
        $staff = $this->objFromFixture('Page', 'staff');

        // Test Link
        $this->assertEquals('/mysite/mvc1/', $home->Link());
        $this->assertEquals('/mysite/mvc1/fr_CA/about-us/', $about->Link());
        $this->assertEquals('/mysite/mvc1/fr_CA/about-us/my-staff/', $staff->Link());

        // Test LocaleLink
        $this->assertEquals('/mysite/mvc1/', $home->LocaleLink('fr_CA'));
        $this->assertEquals('/mysite/mvc1/en_NZ/', $home->LocaleLink('en_NZ'));
        $this->assertEquals('/mysite/mvc1/es_ES/', $home->LocaleLink('es_ES'));
        $this->assertEquals('/mysite/mvc1/en_NZ/about-us/', $about->LocaleLink('en_NZ'));
        $this->assertEquals('/mysite/mvc1/es_ES/about-us/', $about->LocaleLink('es_ES'));
        $this->assertEquals('/mysite/mvc1/en_NZ/about-us/my-staff/', $staff->LocaleLink('en_NZ'));
        $this->assertEquals('/mysite/mvc1/es_ES/about-us/my-staff/', $staff->LocaleLink('es_ES'));

        if ($oldURL) {
            Config::inst()->update('Director', 'alternate_base_url', $oldURL);
        } else {
            Config::inst()->remove('Director', 'alternate_base_url');
        }
    }

    /**
     * Test that aliases for a URL work
     */
    public function testAliases()
    {
        $home = $this->objFromFixture('Page', 'home');
        $about = $this->objFromFixture('Page', 'about');
        $staff = $this->objFromFixture('Page', 'staff');

        $this->assertEquals('/usa/', $home->LocaleLink('en_US'));
        $this->assertEquals('/usa/about-us/', $about->LocaleLink('en_US'));
        $this->assertEquals('/usa/about-us/my-staff/', $staff->LocaleLink('en_US'));
    }

    /**
     * Test that db fields for a translated objects is correctly extended
     */
    public function testTranslateFields()
    {
        $db = DataObject::custom_database_fields('FluentTest_TranslatedObject');
        ksort($db);

        $this->assertEquals(array(
            'Description' => 'Text',
            'Description_en_NZ' => 'Text',
            'Description_en_US' => 'Text',
            'Description_es_ES' => 'Text',
            'Description_fr_CA' => 'Text',
            'ImageID' => 'ForeignKey',
            'ImageID_en_NZ' => 'Int',
            'ImageID_en_US' => 'Int',
            'ImageID_es_ES' => 'Int',
            'ImageID_fr_CA' => 'Int',
            'Title' => 'Varchar(255)',
            'Title_en_NZ' => 'Varchar(255)',
            'Title_en_US' => 'Varchar(255)',
            'Title_es_ES' => 'Varchar(255)',
            'Title_fr_CA' => 'Varchar(255)',
            'URLKey' => 'Text'
        ), $db);
    }

    /**
     * Tests FluentExtension::Locales and LocaleInformation information
     */
    public function testLocaleInformation()
    {

        // Test filtered object
        Fluent::set_persist_locale('en_NZ');
        $item = $this->objFromFixture('FluentTest_FilteredObject', 'filtered1');
        $data = $this->withURL('www.notexample.com', '/', '/', function ($test) use ($item) {
            return $item->Locales()->toNestedArray();
        });
        $expected = array(
            array(
                'Locale' => 'fr_CA',
                'LocaleRFC1766' => 'fr-CA',
                'Alias' => 'fr_CA',
                'Title' => 'French (Canada)',
                'LanguageNative' => 'fran&ccedil;ais',
                'Link' => '/', // fr_CA home page
                'AbsoluteLink' => 'http://www.notexample.com/',
                'LinkingMode' => 'invalid'
            ),
            array(
                'Locale' => 'en_NZ',
                'LocaleRFC1766' => 'en-NZ',
                'Alias' => 'en_NZ',
                'Title' => 'English (New Zealand)',
                'LanguageNative' => 'English',
                'Link' => '/en_NZ/link/',
                'AbsoluteLink' => 'http://www.notexample.com/en_NZ/link/',
                'LinkingMode' => 'current'
            ),
            array(
                'Locale' => 'en_US',
                'LocaleRFC1766' => 'en-US',
                'Alias' => 'usa',
                'Title' => 'English (United States)',
                'LanguageNative' => 'English',
                'Link' => '/en_US/link/',
                'AbsoluteLink' => 'http://www.notexample.com/en_US/link/',
                'LinkingMode' => 'link'
            ),
            array(
                'Locale' => 'es_ES',
                'LocaleRFC1766' => 'es-ES',
                'Alias' => 'es_ES',
                'Title' => 'Spanish (Spain)',
                'LanguageNative' => 'espa&ntilde;ol',
                'Link' => '/es_ES/', // es_ES home page
                'AbsoluteLink' => 'http://www.notexample.com/es_ES/',
                'LinkingMode' => 'invalid'
            )
        );
        $this->assertEquals($expected, $data);

        // Put default locale back
        Fluent::set_persist_locale('fr_CA');
    }

    /**
     * Tests that multi-domain mode works
     */
    public function testDomainsInformation()
    {

        // Test localemenu in an in-scope domain
        Fluent::set_persist_locale('en_NZ');
        Config::inst()->update('Fluent', 'force_domain', true);

        $item = $this->objFromFixture('FluentTest_FilteredObject', 'filtered1');
        $data = $item->Locales()->toNestedArray();

        $expected = array(
            array(
                'Locale' => 'fr_CA',
                'LocaleRFC1766' => 'fr-CA',
                'Alias' => 'fr_CA',
                'Title' => 'French (Canada)',
                'LanguageNative' => 'fran&ccedil;ais',
                'Link' => 'http://www.example.ca/', // fr_CA home page
                'AbsoluteLink' => 'http://www.example.ca/',
                'LinkingMode' => 'invalid'
            ),
            array(
                'Locale' => 'en_NZ',
                'LocaleRFC1766' => 'en-NZ',
                'Alias' => 'en_NZ',
                'Title' => 'English (New Zealand)',
                'LanguageNative' => 'English',
                'Link' => 'http://www.example.co.nz/en_NZ/link/', // NZ domain
                'AbsoluteLink' => 'http://www.example.co.nz/en_NZ/link/',
                'LinkingMode' => 'current'
            ),
            array(
                'Locale' => 'en_US',
                'LocaleRFC1766' => 'en-US',
                'Alias' => 'usa',
                'Title' => 'English (United States)',
                'LanguageNative' => 'English',
                'Link' => 'http://www.example.com/en_US/link/', // US domain with en_US locale
                'AbsoluteLink' => 'http://www.example.com/en_US/link/',
                'LinkingMode' => 'link'
            ),
            array(
                'Locale' => 'es_ES',
                'LocaleRFC1766' => 'es-ES',
                'Alias' => 'es_ES',
                'Title' => 'Spanish (Spain)',
                'LanguageNative' => 'espa&ntilde;ol',
                'Link' => 'http://www.example.com/es_ES/', // US domain with es_ES home page
                'AbsoluteLink' => 'http://www.example.com/es_ES/',
                'LinkingMode' => 'invalid'
            )
        );
        $this->assertEquals($expected, $data);

        Config::inst()->update('Fluent', 'force_domain', false);
    }

    /**
     * Test output for helpers in domain mode
     */
    public function testDomainsHelpers()
    {
        Config::inst()->update('Fluent', 'force_domain', true);

        // Test Fluent::domains
        $this->assertEquals(
            array('www.example.com', 'www.example.ca', 'www.example.co.nz'),
            array_keys(Fluent::domains())
        );

        // Test Fluent::default_locale
        $usDefault = $this->withURL('www.example.com', '/', '/', function ($test) {
            return Fluent::default_locale(true);
        });
        $this->assertEquals('en_US', $usDefault);
        $this->assertEquals('en_US', Fluent::default_locale('www.example.com'));
        $this->assertEquals('fr_CA', Fluent::default_locale());

        // Test Fluent::domain_for_locale
        $this->assertEquals(null, Fluent::domain_for_locale('nl_NL'));
        $this->assertEquals('www.example.com', Fluent::domain_for_locale('en_US'));
        $this->assertEquals('www.example.com', Fluent::domain_for_locale('es_ES'));
        $this->assertEquals('www.example.ca', Fluent::domain_for_locale('fr_CA'));
        $this->assertEquals('www.example.co.nz', Fluent::domain_for_locale('en_NZ'));

        // Test Fluent::locales
        $usLocales = $this->withURL('www.example.com', '/', '/', function ($test) {
            return Fluent::locales(true);
        });
        $this->assertEquals(array('es_ES', 'en_US'), $usLocales);
        $this->assertEquals(array('es_ES', 'en_US'), Fluent::locales('www.example.com'));
        $this->assertEquals(array('fr_CA', 'en_NZ', 'en_US', 'es_ES'), Fluent::locales());

        Config::inst()->update('Fluent', 'force_domain', false);
    }

    /**
     * Test that filtered objects are queried correctly
     */
    public function testFilteredObjects()
    {

        // Check locale that should have some items
        Fluent::set_persist_locale('en_NZ');
        $ids = DataObject::get('FluentTest_FilteredObject')->sort('Title')->column('Title');
        $this->assertEquals(array('filtered 1', 'filtered 2'), $ids);

        // Check locale that has some items
        Fluent::set_persist_locale('fr_CA');
        $ids = DataObject::get('FluentTest_FilteredObject')->sort('Title')->column('Title');
        $this->assertEquals(array('filtered 2'), $ids);

        // Put default locale back
        Fluent::set_persist_locale('fr_CA');
    }

    /*
     * Test that locale filter can be augmented properly
     */
    public function testUpdateFilteredObject()
    {

        // Test basic filter
        $ids = DataObject::get('FluentTest_FilteredObject')->sort('Title')->column('Title');
        $this->assertEquals(array('filtered 2'), $ids);

        // Test that item can have filter changed
        $item = $this->objFromFixture('FluentTest_FilteredObject', 'filtered2');
        $item->setFilteredLocales('fr_CA');
        $this->assertTrue($item->LocaleFilter_fr_CA);
        $this->assertEquals(array('fr_CA'), $item->getFilteredLocales());

        // Test exclusion
        $this->assertEquals(array('en_NZ', 'en_US', 'es_ES'), $item->getFilteredLocales(false));

        // Test item set to foreign locale limits this item
        $item->setFilteredLocales('en_NZ', 'en_US');
        $item->write();
        $ids = DataObject::get('FluentTest_FilteredObject')->sort('Title')->column('Title');
        $this->assertEquals(array(), $ids);
    }

    /**
     * Test auto-scaffolding of CMS fields
     */
    public function testCMSFields()
    {
        $filtered1 = $this->objFromFixture('FluentTest_TranslatedObject', 'translated1');
        $fields = $filtered1->getCMSFields()->dataFields();

        $this->assertEquals(array('Title', 'Description', 'URLKey', 'Image'), array_keys($fields));
    }

    /**
     * Tests that DB fields are properly named
     */
    public function testDBFieldNaming()
    {
        $this->assertEquals('Title_en_NZ', Fluent::db_field_for_locale('Title', 'en_NZ'));
        $this->assertEquals('ParentID_en_NZ', Fluent::db_field_for_locale('ParentID', 'en_NZ'));
    }
    /**
     * Test that different filters against
     */
    public function testFilterConditions()
    {

        // In en_NZ locale
        Fluent::set_persist_locale('en_NZ');

        // Test basic search for english string
        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'this is an object')
            ->column('URLKey');
        $this->assertEquals(array('my-translated'), $urls);

        // Test basic search where language specific field is blank
        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'This colour is blue')
            ->column('URLKey');
        $this->assertEquals(array('my-translated-2'), $urls);

        // In en_US locale
        Fluent::set_persist_locale('en_US');

        // Test basic search for english string
        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'this is an object')
            ->column('URLKey');
        $this->assertEquals(array('my-translated'), $urls);

        // Default value differs from locale specific value
        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'This colour is blue')
            ->column('URLKey');
        $this->assertEquals(array(), $urls);

        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'This color is blue')
            ->column('URLKey');
        $this->assertEquals(array('my-translated-2'), $urls);

        // In fr_CA locales
        Fluent::set_persist_locale('fr_CA');

        // Default value differs from locale specific value
        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', "il s'agit d'un objet")
            ->column('URLKey');
        $this->assertEquals(array('my-translated'), $urls);

        $urls = DataObject::get('FluentTest_TranslatedObject')
            ->filter('Title', 'this is an object')
            ->column('URLKey');
        $this->assertEquals(array(), $urls);

        // Put default locale back
        Fluent::set_persist_locale('fr_CA');
    }

    /**
     * Mock a browser HTTP locale (Accept-Language header) for the purpose of a test
     *
     * @param string $lang Accept-Language header value
     * @param callable $callback Callback
     */
    public function withBrowserHTTPLanguage($lang, $callback)
    {
        $old = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $lang;
        try { // Ensure failed test don't break state
            $callback($this);
        } catch (Exception $ex) {
        }
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $old;
        if (!empty($ex)) {
            throw $ex;
        }
    }

    /**
     * Mock a request URL for the purpose of a test
     *
     * @param string $hostname Host name to use
     * @param string $baseURL BaseURL to use
     * @param string $url Request URL relative to BaseURL
     * @param callable $callback Callback
     */
    public function withURL($hostname, $baseURL, $url, $callback)
    {

        // Set hostname
        $oldHostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
        $_SERVER['HTTP_HOST'] = $hostname;

        // Set base URL
        $oldBaseURL = Config::inst()->get('Director', 'alternate_base_url');
        Config::inst()->update('Director', 'alternate_base_url', $baseURL);

        // Set URL
        $oldURL = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
        $_SERVER['REQUEST_URI'] = $url;

        try { // Ensure failed test don't break state
            $return = $callback($this);
        } catch (Exception $ex) {
        }

        // Revert URL
        $_SERVER['REQUEST_URI'] = $oldURL;

        // Revert baseURL
        if ($oldBaseURL) {
            Config::inst()->update('Director', 'alternate_base_url', $oldBaseURL);
        } else {
            Config::inst()->remove('Director', 'alternate_base_url');
        }

        // Revert hostname
        $_SERVER['HTTP_HOST'] = $oldHostname;

        if (!empty($ex)) {
            throw $ex;
        }
        return $return;
    }

    /**
     * Push a controller onto the stack to mock a particular request
     *
     * @param Controller $controller
     * @param callback $callback
     */
    public function withController(Controller $controller, $callback)
    {
        $controller->pushCurrent();
        try { // Ensure failed test don't break state
            $callback($this);
        } catch (Exception $ex) {
        }
        $controller->popCurrent();
        if (!empty($ex)) {
            throw $ex;
        }
    }

    /**
     * Test browser detection of locale
     */
    public function testDetectBrowserLocale()
    {
        $this->withBrowserHTTPLanguage('en-us;q=1,en;q=0.50', function ($test) {
            $test->assertEquals('en_US', Fluent::detect_browser_locale());
        });

        $this->withBrowserHTTPLanguage('fr,en', function ($test) {
            $test->assertEquals('fr_CA', Fluent::detect_browser_locale());
        });

        $this->withBrowserHTTPLanguage('en,fr-ca', function ($test) {
            $test->assertEquals('en_NZ', Fluent::detect_browser_locale());
        });

        $this->withBrowserHTTPLanguage('en-nz,fr,en', function ($test) {
            $test->assertEquals('en_NZ', Fluent::detect_browser_locale());
        });

        $this->withBrowserHTTPLanguage('fr-fr,en,fr', function ($test) {
            $test->assertEquals('en_NZ', Fluent::detect_browser_locale());
        });

        $this->withBrowserHTTPLanguage('fr-fr,en-uk,ms', function ($test) {
            $test->assertEmpty(Fluent::detect_browser_locale());
        });
    }

    /**
     * Tests overriding locale values
     */
    public function testWithLocale()
    {

        // Default value differs from locale specific value
        $names = DataObject::get('FluentTest_TranslatedObject')->sort('ID')->column('Title');
        self::assertEquals(
            array("il s'agit d'un objet", "Cette couleur est le bleu", "Un-translated object"),
            $names
        );

        // In en_NZ locale
        $names = Fluent::with_locale('en_NZ', function () {
            return DataObject::get('FluentTest_TranslatedObject')->sort('ID')->column('Title');
        });
        $this->assertEquals(
            array("this is an object", "This colour is blue", "Un-translated object"),
            $names
        );

        // Use default locale again
        $names = DataObject::get('FluentTest_TranslatedObject')->sort('ID')->column('Title');
        $this->assertEquals(
            array("il s'agit d'un objet", "Cette couleur est le bleu", "Un-translated object"),
            $names
        );
    }

    /**
     * Test that un-translated objects (never saved with Fluent before) can be correctly loaded
     */
    public function testUntranslatedObject()
    {
        $object = $this->objFromFixture('FluentTest_TranslatedObject', 'untranslated');

        // Assert that the raw row data is actually blank
        $row = DB::query('SELECT * FROM "FluentTest_TranslatedObject" WHERE "Description" = \'Third\'')->record();
        $this->assertEquals('Un-translated object', $row['Title']);
        $this->assertEmpty($row['Title_fr_CA']);
        $this->assertEmpty($row['Title_en_NZ']);
        $this->assertEmpty($row['Title_en_US']);
        $this->assertEmpty($row['Title_es_ES']);

        // Assert that all fields are properly set
        $this->assertEquals('Un-translated object', $object->Title);
        $this->assertEquals('Un-translated object', $object->Title_fr_CA); // Default field should autocorrect
        $this->assertEmpty($object->Title_en_NZ);
        $this->assertEmpty($object->Title_en_US);
        $this->assertEmpty($object->Title_es_ES);

        // Writing this field to the database should resolve the missing default field
        $object->forceChange();
        $object->write();
        $row = DB::query('SELECT * FROM "FluentTest_TranslatedObject" WHERE "Description" = \'Third\'')->record();
        $this->assertEquals('Un-translated object', $row['Title']);
        $this->assertEquals('Un-translated object', $row['Title_fr_CA']);
        $this->assertEmpty($row['Title_en_NZ']);
        $this->assertEmpty($row['Title_en_US']);
        $this->assertEmpty($row['Title_es_ES']);
    }

    /**
     * Test write operations with overriding locale values
     */
    public function testWriteWithLocale()
    {

        // Test creation in default locale
        $item = new FluentTest_TranslatedObject();
        $item->Title = 'Test Title';
        $item->write();
        $itemID = $item->ID;

        // Test basic detail
        $item = FluentTest_TranslatedObject::get()->byId($itemID);
        $this->assertEquals('Test Title', $item->Title);

        // Test update in alternate locale
        Fluent::with_locale('es_ES', function () use ($itemID) {
            $item = FluentTest_TranslatedObject::get()->byId($itemID);
            $item->Title = 'Spanish Title';
            $item->write();
        });

        // Default title is unchanged
        $item = FluentTest_TranslatedObject::get()->byId($itemID);
        $this->assertEquals('Test Title', $item->Title);

        // Test previously set alternate locale title change persists
        $esTitle = Fluent::with_locale('es_ES', function () use ($itemID) {
            $item = FluentTest_TranslatedObject::get()->byId($itemID);
            return $item->Title;
        });
        $this->assertEquals('Spanish Title', $esTitle);

        // Test object created in alternate locale
        $item2ID = Fluent::with_locale('es_ES', function () {
            $item2 = new FluentTest_TranslatedObject();
            $item2->Title = 'Spanish 2';
            $item2->write();
            return $item2->ID;
        });

        // Default title should be set
        $item2 = FluentTest_TranslatedObject::get()->byId($item2ID);
        $this->assertEquals('Spanish 2', $item2->Title);

        // Change title
        $item2->Title = 'English 2';
        $item2->write();

        // check alternate locale title unchanged
        $es2Title = Fluent::with_locale('es_ES', function () use ($item2ID) {
            $item2 = FluentTest_TranslatedObject::get()->byId($item2ID);
            return $item2->Title;
        });
        $this->assertEquals($es2Title, 'Spanish 2');

        // Test that object selected in default locale has the recently changed title
        $item2 = FluentTest_TranslatedObject::get()->byId($item2ID);
        $this->assertEquals('English 2', $item2->Title);
    }

    public function testFrontendDetection()
    {

        // Check that test controller counts as frontend
        $this->assertTrue(Fluent::is_frontend());
        $this->assertTrue(Fluent::is_frontend(true));

        // Check detection based on URL - frontend
        $this->withURL('www.example.com', '/mybase/', '/mybase/about/us', function ($test) {
            $test->assertTrue(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/mybase/', 'mybase/about/us', function ($test) {
            $test->assertTrue(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/', '/about/us', function ($test) {
            $test->assertTrue(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/', 'about/us', function ($test) {
            $test->assertTrue(Fluent::is_frontend(true));
        });

        // Check detection based on URL - admin
        $this->withURL('www.example.com', '/mybase/', '/mybase/admin/pages', function ($test) {
            $test->assertFalse(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/mybase/', 'mybase/admin/pages', function ($test) {
            $test->assertFalse(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/', '/admin/pages', function ($test) {
            $test->assertFalse(Fluent::is_frontend(true));
        });
        $this->withURL('www.example.com', '/', 'admin/pages', function ($test) {
            $test->assertFalse(Fluent::is_frontend(true));
        });

        // Test detection based on controller
        $this->withController(new ModelAsController(), function ($test) {
            $test->assertTrue(Fluent::is_frontend());
        });
        $this->withController(new LeftAndMain(), function ($test) {
            $test->assertFalse(Fluent::is_frontend());
        });
        $this->withController(new FluentTest_CMSController(), function ($test) {
            $test->assertFalse(Fluent::is_frontend());
        });
        $this->withController(new FluentTest_FrontendController(), function ($test) {
            $test->assertTrue(Fluent::is_frontend());
        });
    }

    /**
     * Test versioning of localised objects
     */
    public function testPublish()
    {

        // == Setup ==

        Fluent::set_persist_locale('fr_CA');
        Versioned::reading_stage('Stage');

        // Create new record in non-default locale
        $id = Fluent::with_locale('es_ES', function () {
            $page = new Page();
            $page->Title = 'ES Title';
            $page->MenuTitle = 'ES Title';
            $page->write();
            return $page->ID;
        });

        // == Check stage ==

        // Check that the record has a title in the default locale
        $page = Versioned::get_one_by_stage("SiteTree", "Stage", "\"SiteTree\".\"ID\" = $id");
        $this->assertEquals('ES Title', $page->Title);
        $this->assertEquals('ES Title', $page->MenuTitle);

        // Check that the record has a title in the foreign locale
        $record = Fluent::with_locale('es_ES', function () use ($id) {
            $page = Versioned::get_one_by_stage("SiteTree", "Stage", "\"SiteTree\".\"ID\" = $id");
            return $page->toMap();
        });
        $this->assertEquals('ES Title', $record['Title']);
        $this->assertEquals('ES Title', $record['MenuTitle']);

        // == Publish ==

        // Save title in default locale
        $page = Versioned::get_one_by_stage("SiteTree", "Stage", "\"SiteTree\".\"ID\" = $id");
        $page->Title = 'Default Title';
        $page->MenuTitle = 'Custom Title';
        $page->write();

        // Publish this record in the custom locale
        Fluent::with_locale('es_ES', function () use ($id) {
            $page = Versioned::get_one_by_stage("SiteTree", "Stage", "\"SiteTree\".\"ID\" = $id");
            $page->doPublish();
        });

        // == Check live ==

        // Check the live record has the correct title in the default locale
        $page = Versioned::get_one_by_stage("SiteTree", "Live", "\"SiteTree\".\"ID\" = $id");
        $this->assertEquals('Default Title', $page->Title);
        $this->assertEquals('Custom Title', $page->MenuTitle);

        // Check the live record has the correct title in the custom locale
        $record = Fluent::with_locale('es_ES', function () use ($id) {
            $page = Versioned::get_one_by_stage("SiteTree", "Live", "\"SiteTree\".\"ID\" = $id");
            return $page->toMap();
        });
        $this->assertEquals('ES Title', $record['Title']);
        $this->assertEquals('ES Title', $record['MenuTitle']);
    }

    /*
     * test if isFrontend gets ignored for ContentControllers. Yes they should not have this
     * method in the first place, but at least VirtualPages do. (patch #87)
     */

    public function testIsFrontendIgnorance()
    {
        $this->withController(new FluentTest_ContentController(), function ($test) {
            $test->assertTrue(Fluent::is_frontend());
        });
    }

    /**
     * Test that records created in non-default locale don't have missing values for default fields
     */
    public function testCreateInNonDefaultLocale()
    {
        Fluent::set_persist_locale('es_ES');

        // Create a record in this locale
        $record = new FluentTest_TranslatedObject();
        $record->Title = 'es title';
        $record->Description = 'es description';
        $record->write();
        $recordID = $record->ID;

        $row = DB::query(sprintf("SELECT * FROM \"FluentTest_TranslatedObject\" WHERE \"ID\" = %d", $recordID))->first();

        // Check that the necessary fields are assigned
        $this->assertEquals('es title', $row['Title']);
        $this->assertEquals('es title', $row['Title_es_ES']);
        $this->assertEquals('es title', $row['Title_fr_CA']);
        $this->assertEmpty($row['Title_en_NZ']);
        $this->assertEquals('es description', $row['Description']);
        $this->assertEquals('es description', $row['Description_es_ES']);
        $this->assertEquals('es description', $row['Description_fr_CA']);
        $this->assertEmpty($row['Description_en_NZ']);


        // modify locale in default locale
        Fluent::with_locale('fr_CA', function () use ($recordID) {
            $record = FluentTest_TranslatedObject::get()->byID($recordID);
            $record->Title = 'new ca title';
            $record->write();
        });

        // Check that the necessary fields are assigned
        $row = DB::query(sprintf("SELECT * FROM \"FluentTest_TranslatedObject\" WHERE \"ID\" = %d", $recordID))->first();
        $this->assertEquals('new ca title', $row['Title']);
        $this->assertEquals('es title', $row['Title_es_ES']);
        $this->assertEquals('new ca title', $row['Title_fr_CA']);
        $this->assertEmpty($row['Title_en_NZ']);
        $this->assertEquals('es description', $row['Description']);
        $this->assertEquals('es description', $row['Description_es_ES']);
        $this->assertEquals('es description', $row['Description_fr_CA']);
        $this->assertEmpty($row['Description_en_NZ']);

        // modify in another locale
        Fluent::with_locale('en_NZ', function () use ($recordID) {
            $record = FluentTest_TranslatedObject::get()->byID($recordID);
            $record->Title = 'nz title';
            $record->Description = 'nz description';
            $record->write();
        });

        // Check that the necessary fields are assigned
        $row = DB::query(sprintf("SELECT * FROM \"FluentTest_TranslatedObject\" WHERE \"ID\" = %d", $recordID))->first();
        $this->assertEquals('new ca title', $row['Title']);
        $this->assertEquals('es title', $row['Title_es_ES']);
        $this->assertEquals('new ca title', $row['Title_fr_CA']);
        $this->assertEquals('nz title', $row['Title_en_NZ']);
        $this->assertEquals('es description', $row['Description']);
        $this->assertEquals('es description', $row['Description_es_ES']);
        $this->assertEquals('es description', $row['Description_fr_CA']);
        $this->assertEquals('nz description', $row['Description_en_NZ']);
    }
}

/**
 * Test class for fluent translated objects
 */
class FluentTest_TranslatedObject extends DataObject implements TestOnly
{
    private static $extensions = array(
        'FluentExtension'
    );

    private static $db = array(
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'URLKey' => 'Text'
    );

    private static $has_one = array(
        'Image' => 'Image'
    );

    private static $translate = array(
        'Title',
        'Description',
        'ImageID'
    );
}

class FluentTest_FilteredObject extends DataObject implements TestOnly
{
    private static $extensions = array(
        'FluentFilteredExtension',
        'FluentExtension'
    );

    private static $db = array(
        'Title' => 'Varchar(255)'
    );

    public function Link()
    {
        return Controller::join_links(
            Director::baseURL(),
            Fluent::current_locale(),
            'link',
            '/'
        );
    }
}

class FluentTest_ContentController extends ContentController
{
    // a ContentController should not really provide a isFrontend method
    // this is just make sure patch #87 works
    public function isFrontend()
    {
        return false;
    }
}

class FluentTest_CMSController extends Controller
{
    public function isFrontend()
    {
        return false;
    }
}

class FluentTest_FrontendController extends Controller
{
    public function isFrontend()
    {
        return true;
    }
}
