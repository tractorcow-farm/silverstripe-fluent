<?php

class FluentValidateTaskTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();

        // Tweak configuration
        Config::inst()->update('Fluent', 'locales', array('fr_CA', 'en_NZ', 'en_US', 'es_ES'));
    }

    public function testNonBaseClass() {
        $config = Config::inst();
        $config->update('Page', 'extensions', array('FluentExtension'));
        $validator = new FluentValidateTask();
        $result = $validator->validateConfig($config);
        $this->assertFalse($result->valid());
        $this->assertContains(
            'Class Page is not a base data class but has the following FluentExtensions: FluentExtension',
            $result->messageList()
        );
    }

    public function testMultipleExtensions() {
        $config = Config::inst();
        $config->update('SiteTree', 'extensions', array('FluentExtension'));
        $validator = new FluentValidateTask();
        $result = $validator->validateConfig($config);
        $this->assertFalse($result->valid());
        $this->assertContains(
            'Class SiteTree has multiple FluentExtension classes: FluentExtension, FluentSiteTree',
            $result->messageList()
        );
    }

    public function testDefaultConfigValid() {
        $config = Config::inst();
        $validator = new FluentValidateTask();
        $result = $validator->validateConfig($config);
        $this->assertTrue($result->valid());
    }
}
