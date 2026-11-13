<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\Stub\FluentDataObject;

/**
 * Versioned::isLiveVersion(), isLatestDraftVersion() and isLatestVersion() compare a version number with the
 * Version column of the record they are called on.
 *
 * Fluent localises the version numbers (see FluentVersionedExtension::updateGetVersionNumberByStage()), but the
 * Version column of a record read from a stage belongs to the base record, so it holds the version of the newest write
 * in ANY locale. Without a localised answer, a locale only looked up to date when it happened to be written last.
 *
 * These tests write to the locales in turn, because that is what exposes the difference.
 */
class LocalisedVersionPredicatesTest extends SapphireTest
{
    /**
     * Locales only. en_CA inherits from en_NZ, jp_JP stands on its own.
     *
     * @var string
     */
    protected static $fixture_file = 'LocalisedVersionsTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        FluentDataObject::class,
    ];

    protected static $required_extensions = [
        FluentDataObject::class => [
            Versioned::class,
            FluentVersionedExtension::class,
        ],
    ];

    private const NZ = 'en_NZ';
    private const JP = 'jp_JP';
    private const CA = 'en_CA';

    /**
     * The record is the live version, and the latest one
     */
    private const CURRENT = [
        'isLiveVersion' => true,
        'isLatestDraftVersion' => true,
        'isLatestVersion' => true,
    ];

    /**
     * The record is the latest version, but it is not the live one (never published, or changed since)
     */
    private const DRAFT_NOT_LIVE = [
        'isLiveVersion' => false,
        'isLatestDraftVersion' => true,
        'isLatestVersion' => true,
    ];

    /**
     * The record is not the live version, nor the latest
     */
    private const NEITHER = [
        'isLiveVersion' => false,
        'isLatestDraftVersion' => false,
        'isLatestVersion' => false,
    ];

    /**
     * @var int
     */
    private $edits = 0;

    protected function setUp(): void
    {
        parent::setUp();

        FluentVersionedExtension::reset();
    }

    /**
     * Published in one locale, then another locale is drafted: the first one has not changed
     */
    public function testPublishedLocaleIsNotAffectedByDraftInOtherLocale(): void
    {
        $id = $this->create(self::NZ);
        $this->publish(self::NZ, $id);
        $this->edit(self::JP, $id);

        $this->assertPredicates(self::CURRENT, self::NZ, $id);
        $this->assertPredicates(self::DRAFT_NOT_LIVE, self::JP, $id);
    }

    /**
     * Published in every locale: all of them are current, whichever one was written last
     */
    public function testEveryPublishedLocaleIsCurrent(): void
    {
        $id = $this->create(self::NZ);
        $this->publish(self::NZ, $id);
        $this->edit(self::JP, $id);
        $this->publish(self::JP, $id);

        foreach ([self::NZ, self::JP] as $locale) {
            $this->assertPredicates(self::CURRENT, $locale, $id, Versioned::DRAFT);
            $this->assertPredicates(self::CURRENT, $locale, $id, Versioned::LIVE);
        }
    }

    /**
     * A change in the locale itself is still a change, and must not be hidden by the other locales
     */
    public function testChangeInLocaleIsStillReportedAsModified(): void
    {
        $id = $this->create(self::NZ);
        $this->publish(self::NZ, $id);
        $this->edit(self::JP, $id);
        $this->publish(self::JP, $id);

        $this->edit(self::NZ, $id);

        $this->assertPredicates(self::DRAFT_NOT_LIVE, self::NZ, $id);
        $this->assertPredicates(self::CURRENT, self::JP, $id);

        // Read from live, nz is still the published version - but no longer the latest one
        $this->assertPredicates([
            'isLiveVersion' => true,
            'isLatestDraftVersion' => false,
            'isLatestVersion' => false,
        ], self::NZ, $id, Versioned::LIVE);
        $this->assertPredicates(self::CURRENT, self::JP, $id, Versioned::LIVE);
    }

    /**
     * Fluent already knows per locale whether something is drafted, published and different between the two. What
     * a locale answers must agree with that, in whatever order the locales were written to.
     */
    public function testAgreesWithTheStateOfEachLocale(): void
    {
        foreach (self::writeOrders() as $name => $writes) {
            $id = $this->create(self::NZ);
            foreach ($writes as [$action, $locale]) {
                $this->{$action}($locale, $id);
            }

            foreach ([self::NZ, self::JP] as $locale) {
                [$drafted, $published, $differs] = $this->inLocale($locale, Versioned::DRAFT, function () use ($id, $locale) {
                    /** @var FluentDataObject|FluentVersionedExtension $record */
                    $record = FluentDataObject::get()->byID($id);

                    return [
                        $record->isDraftedInLocale($locale),
                        $record->isPublishedInLocale($locale),
                        $record->stagesDifferInLocale($locale),
                    ];
                });

                $this->assertSame([
                    'isLiveVersion' => $published && !$differs,
                    'isLatestDraftVersion' => $drafted,
                    'isLatestVersion' => $drafted,
                ], $this->predicates($locale, $id), "{$locale}, {$name}");
            }
        }
    }

    /**
     * Series of writes to a record that starts out as a draft in en_NZ, as pairs of [action, locale]
     *
     * @return array[]
     */
    private static function writeOrders(): array
    {
        return [
            'both published, jp last' => [
                ['publish', self::NZ], ['edit', self::JP], ['publish', self::JP],
            ],
            'both published, nz changed afterwards' => [
                ['publish', self::NZ], ['edit', self::JP], ['publish', self::JP], ['edit', self::NZ],
            ],
            'both published, jp changed afterwards' => [
                ['publish', self::NZ], ['edit', self::JP], ['publish', self::JP], ['edit', self::JP],
            ],
            'both published, both changed afterwards' => [
                ['publish', self::NZ], ['edit', self::JP], ['publish', self::JP], ['edit', self::NZ], ['edit', self::JP],
            ],
            'published again after a change' => [
                ['publish', self::NZ], ['edit', self::JP], ['publish', self::JP], ['edit', self::NZ],
                ['edit', self::JP], ['publish', self::JP], ['edit', self::NZ],
            ],
            'jp never published' => [
                ['publish', self::NZ], ['edit', self::JP], ['edit', self::NZ], ['publish', self::NZ],
            ],
            'nothing published' => [
                ['edit', self::JP], ['edit', self::NZ],
            ],
        ];
    }

    /**
     * A record that is read from one stage only, because it does not exist on the other one, is a record of that
     * stage like any other
     */
    public function testRecordOfASingleStageIsLocalised(): void
    {
        $id = $this->create(self::NZ);
        $this->edit(self::JP, $id);
        $this->edit(self::NZ, $id);

        $predicates = $this->inLocale(self::JP, Versioned::DRAFT, function () use ($id): array {
            /** @var FluentDataObject|Versioned $record */
            $record = FluentDataObject::get()
                ->setDataQueryParam('Versioned.mode', 'stage_unique')
                ->byID($id);
            $this->assertNotNull($record, 'The record exists on draft only, so it can be read');

            return $this->ask($record);
        });

        $this->assertSame(self::DRAFT_NOT_LIVE, $predicates);
    }

    /**
     * A version that is loaded as one specific version (history, rollback, compare) carries a real version number
     * in its Version column, so it keeps being compared as it is.
     */
    public function testSpecificVersionsAreComparedByTheirOwnVersionNumber(): void
    {
        $id = $this->create(self::NZ);
        $created = $this->versionOf(self::NZ, $id);
        $this->publish(self::NZ, $id);
        $published = $this->versionOf(self::NZ, $id, Versioned::LIVE);
        $this->edit(self::JP, $id);
        $this->edit(self::NZ, $id);
        $edited = $this->versionOf(self::NZ, $id);

        $this->assertNotSame($created, $published);
        $this->assertNotSame($published, $edited);

        // The version that was created, and is neither live nor the latest anymore
        $this->assertSame(self::NEITHER, $this->predicatesOfVersion(self::NZ, $id, $created));

        // What is live, but has been superseded by a newer draft
        $this->assertSame([
            'isLiveVersion' => true,
            'isLatestDraftVersion' => false,
            'isLatestVersion' => false,
        ], $this->predicatesOfVersion(self::NZ, $id, $published));

        // What is the latest draft
        $this->assertSame(self::DRAFT_NOT_LIVE, $this->predicatesOfVersion(self::NZ, $id, $edited));
    }

    /**
     * A locale that only inherits the record has no versions of its own, so it is never "the live version"
     * and never "the latest version" - as before.
     */
    public function testLocaleWithoutVersionsOfItsOwnIsNeverCurrent(): void
    {
        $id = $this->create(self::NZ);
        $this->publish(self::NZ, $id);

        $this->assertPredicates(self::CURRENT, self::NZ, $id);
        $this->assertPredicates(self::NEITHER, self::CA, $id);
        $this->assertPredicates(self::NEITHER, self::JP, $id);
    }

    /**
     * Without a locale there is nothing to localise: this is plain Versioned
     */
    public function testWithoutLocaleItBehavesLikeVersioned(): void
    {
        $id = $this->create(null);
        $this->publish(null, $id);

        $this->assertPredicates(self::CURRENT, null, $id);

        $this->edit(null, $id);

        $this->assertPredicates(self::DRAFT_NOT_LIVE, null, $id);
        $this->assertPredicates([
            'isLiveVersion' => true,
            'isLatestDraftVersion' => false,
            'isLatestVersion' => false,
        ], null, $id, Versioned::LIVE);
    }

    /**
     * @param array $expected
     * @param string|null $locale
     * @param int $id
     * @param string $stage Stage the record is read from
     */
    private function assertPredicates(array $expected, ?string $locale, int $id, string $stage = Versioned::DRAFT): void
    {
        $this->assertSame(
            $expected,
            $this->predicates($locale, $id, $stage),
            sprintf('Record read from %s in %s', $stage, $locale ?: 'no locale')
        );
    }

    /**
     * Read the record the way a new request in this locale would, and ask it the three questions
     *
     * @param string|null $locale
     * @param int $id
     * @param string $stage
     * @return bool[]
     */
    private function predicates(?string $locale, int $id, string $stage = Versioned::DRAFT): array
    {
        return $this->inLocale($locale, $stage, function () use ($id): array {
            /** @var FluentDataObject|Versioned $record */
            $record = FluentDataObject::get()->byID($id);
            $this->assertNotNull($record, 'The record can be read');

            return $this->ask($record);
        });
    }

    /**
     * Load one particular version of the record, as the history of a record does
     *
     * @param string|null $locale
     * @param int $id
     * @param int $version
     * @return bool[]
     */
    private function predicatesOfVersion(?string $locale, int $id, int $version): array
    {
        return $this->inLocale($locale, Versioned::DRAFT, function () use ($id, $version): array {
            /** @var FluentDataObject|Versioned $record */
            $record = Versioned::get_version(FluentDataObject::class, $id, $version);
            $this->assertNotNull($record, "Version {$version} can be read");

            return $this->ask($record);
        });
    }

    /**
     * @param FluentDataObject|Versioned $record
     * @return bool[]
     */
    private function ask(DataObject $record): array
    {
        return [
            'isLiveVersion' => $record->isLiveVersion(),
            'isLatestDraftVersion' => $record->isLatestDraftVersion(),
            'isLatestVersion' => $record->isLatestVersion(),
        ];
    }

    /**
     * Create a record in a locale, as a draft
     *
     * @param string|null $locale
     * @return int
     */
    private function create(?string $locale): int
    {
        return $this->inLocale($locale, Versioned::DRAFT, function (): int {
            $record = FluentDataObject::create();
            $record->Title = 'Created';
            $record->write();

            return (int) $record->ID;
        });
    }

    /**
     * Save a change to a record in a locale, as a draft
     *
     * @param string|null $locale
     * @param int $id
     */
    private function edit(?string $locale, int $id): void
    {
        $this->inLocale($locale, Versioned::DRAFT, function () use ($id): void {
            $record = FluentDataObject::get()->byID($id);
            $record->Title = 'Edit ' . ++$this->edits;
            $record->write();
        });
    }

    /**
     * Publish the draft of a record in a locale. Records that are not localised in the locale yet are saved first,
     * like the CMS does.
     *
     * @param string|null $locale
     * @param int $id
     */
    private function publish(?string $locale, int $id): void
    {
        $this->inLocale($locale, Versioned::DRAFT, function () use ($id): void {
            /** @var FluentDataObject|Versioned $record */
            $record = FluentDataObject::get()->byID($id);
            $record->Title = 'Publish ' . ++$this->edits;
            $record->write();
            $record->publishSingle();
        });
    }

    /**
     * The number of the version the record has on a stage, in a locale
     *
     * @param string|null $locale
     * @param int $id
     * @param string $stage
     * @return int
     */
    private function versionOf(?string $locale, int $id, string $stage = Versioned::DRAFT): int
    {
        return $this->inLocale($locale, $stage, function () use ($id, $stage): int {
            return (int) Versioned::get_versionnumber_by_stage(FluentDataObject::class, $stage, $id, false);
        });
    }

    /**
     * Run something as a fresh request, reading from a stage, in a locale
     *
     * @param string|null $locale
     * @param string $stage
     * @param callable $callback
     * @return mixed
     */
    private function inLocale(?string $locale, string $stage, callable $callback)
    {
        return FluentState::singleton()->withState(function (FluentState $state) use ($locale, $stage, $callback) {
            $state->setLocale($locale);

            return Versioned::withVersionedMode(function () use ($stage, $callback) {
                Versioned::set_stage($stage);

                // Version numbers are cached for the length of a request
                FluentVersionedExtension::reset();
                DataObject::singleton(FluentDataObject::class)->flushCache();

                return $callback();
            });
        });
    }
}
