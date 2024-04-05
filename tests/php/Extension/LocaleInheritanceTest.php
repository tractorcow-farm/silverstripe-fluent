<?php

namespace TractorCow\Fluent\Tests\Extension;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;

class LocaleInheritanceTest extends SapphireTest
{
    protected static $fixture_file = 'LocaleInheritanceTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            FluentSiteTreeExtension::class,
        ],
    ];

    /**
     * @param string $cmsInheritanceMode
     * @param string $frontendInheritanceMode
     * @param bool $frontendContext
     * @param string $locale
     * @param string|null $expected
     * @return void
     * @throws ValidationException
     * @dataProvider sourceLocaleCasesProvider
     */
    public function testGetSourceLocale(
        string $cmsInheritanceMode,
        string $frontendInheritanceMode,
        bool $frontendContext,
        string $locale,
        ?string $expected
    ): void {
        Page::config()
            ->set('cms_localisation_required', $cmsInheritanceMode)
            ->set('frontend_publish_required', $frontendInheritanceMode);

        Versioned::withVersionedMode(function () use ($frontendContext, $locale, $expected): void {
            // Make sure we have the correct stage set
            Versioned::set_stage(Versioned::DRAFT);

            // Create the page in the default locale
            FluentState::singleton()->withState(
                function (FluentState $state) use ($frontendContext, $locale, $expected): void {
                    $state
                        ->setLocale('en_US')
                        ->setIsFrontend($frontendContext);

                    /** @var Page|FluentExtension $page */
                    $page = Page::create();
                    $page->Title = 'Page title';
                    $page->URLSegment = 'test-page';
                    $page->write();

                    $localeInformation = $page->LocaleInformation($locale);
                    $sourceLocaleObject = $localeInformation->getSourceLocale();
                    $sourceLocale = $sourceLocaleObject?->Locale;
                    $this->assertEquals(
                        $expected,
                        $sourceLocale,
                        'We expect a specific source locale (locale information)'
                    );

                    if (!$sourceLocale) {
                        return;
                    }

                    // Re-fetch the page in the target locale
                    $state->setLocale($locale);

                    /** @var Page|FluentExtension $page */
                    $page = Page::get()->byID($page->ID);

                    $this->assertNotNull($page, 'We expect the page to be available in this locale');
                    $sourceLocaleObject = $page->getSourceLocale();
                    $this->assertEquals(
                        $expected,
                        $sourceLocaleObject->Locale,
                        'We expect a specific source locale (page shorthand method)'
                    );
                }
            );
        });
    }

    public function sourceLocaleCasesProvider(): array
    {
        return [
            'default locale, cms with any mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'en_US',
                'en_US',
            ],
            'default locale, cms with exact mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'en_US',
                'en_US',
            ],
            'default locale, cms with any mode, frontend with exact mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_EXACT,
                true,
                'en_US',
                'en_US',
            ],
            'fallback locale, cms with any mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'de_DE',
                'en_US',
            ],
            'fallback locale, cms with exact mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'de_DE',
                'en_US',
            ],
            'fallback locale, cms with any mode, frontend with exact mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_EXACT,
                true,
                'de_DE',
                null,
            ],
            'fallback locale, cms with any mode, frontend with fallback mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_FALLBACK,
                true,
                'de_DE',
                'en_US',
            ],
            'fallback locale, cms with any mode, frontend with exact mode, cms context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_EXACT,
                false,
                'de_DE',
                'en_US',
            ],
            'no fallback locale, cms with any mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'es_ES',
                null,
            ],
            'no fallback locale, cms with exact mode, frontend with any mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_EXACT,
                FluentExtension::INHERITANCE_MODE_ANY,
                true,
                'es_ES',
                null,
            ],
            'no fallback locale, cms with any mode, frontend with exact mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_EXACT,
                true,
                'es_ES',
                null,
            ],
            'no fallback locale, cms with any mode, frontend with fallback mode, frontend context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_FALLBACK,
                true,
                'es_ES',
                null,
            ],
            'no fallback locale, cms with any mode, frontend with exact mode, cms context' => [
                FluentExtension::INHERITANCE_MODE_ANY,
                FluentExtension::INHERITANCE_MODE_EXACT,
                false,
                'es_ES',
                null,
            ],
        ];
    }
}
