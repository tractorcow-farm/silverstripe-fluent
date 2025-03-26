<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\State\FluentState;

class DuplicationTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'DuplicationTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        Animal::class,
        Horse::class,
        Steed::class,
        Tail::class,
        Ribbon::class,
        Saddle::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        Animal::class => [
            FluentExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_NZ');

            parent::setUp();
        });
    }

    /**
     * case: new object with defined relation is created
     * desired outcome: no additional changes
     *
     * @param bool $active
     * @dataProvider copyStateProvider
     */
    public function testCreateWithDefinedRelation(bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($active): void {
            $state->setLocale('en_NZ');

            $tail = Tail::create();
            $tail->Title = 'New Tail';
            $tail->write();

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            $horse = Horse::create();
            $horse->Title = 'New Horse';
            $horse->TailID = $tail->ID;

            $horse->withLocalisedCopyState(function () use ($horse, $active) {
                $horse->setLocalisedCopyActive($active);
                $horse->write();
            });

            $this->assertCount($tailsCount, $tails);
            $this->assertGreaterThan(0, $horse->TailID);
            $this->assertEquals((int) $tail->ID, (int) $horse->TailID);
        });
    }

    /**
     * case: existing object with defined relation is localised into a new / existing locale
     * desired outcome:
     *
     * if localised copy is active
     * a new duplicted related object is created for the target locale
     *
     * if localised copy is inactive
     * desired outcome: no additional changes
     *
     * @param string $locale
     * @param bool $duplicated
     * @param bool $active
     * @dataProvider localesProvider
     */
    public function testEditWithDefinedRelation(string $locale, bool $duplicated, bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $duplicated, $active): void {
            $state->setLocale($locale);

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            /** @var Horse|FluentExtension $horse */
            $horse = $this->objFromFixture(Horse::class, 'horse1');
            $horse->Title = 'Edited Title';

            $horse->withLocalisedCopyState(function () use ($horse, $active) {
                $horse->setLocalisedCopyActive($active);
                $horse->write();
            });

            /** @var Tail $oldTail */
            $oldTail = $this->objFromFixture(Tail::class, 'tail1');

            $this->assertGreaterThan(0, $horse->TailID);

            if ($duplicated) {
                $this->assertCount($tailsCount + 1, $tails);
                $newTail = $tails->first();
                $this->assertNotEquals((int) $oldTail->ID, (int) $horse->TailID);
                $this->assertEquals((int) $newTail->ID, (int) $horse->TailID);

                return;
            }

            $this->assertCount($tailsCount, $tails);
            $this->assertEquals((int) $oldTail->ID, (int) $horse->TailID);
        });
    }

    /**
     * case: new object with inherited relation is created
     * desired outcome: no additional changes
     *
     * @param bool $active
     * @dataProvider copyStateProvider
     */
    public function testCreateWithInheritedRelation(bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($active): void {
            $state->setLocale('en_NZ');

            $tail = Tail::create();
            $tail->Title = 'New Tail';
            $tail->write();

            $saddle = Saddle::create();
            $saddle->Title = 'New Saddle';
            $saddle->write();

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            $saddles = Saddle::get()->sort('ID', 'DESC');
            $saddlesCount = (int) $saddles->count();

            $steed = Steed::create();
            $steed->Title = 'New Steed';
            $steed->TailID = $tail->ID;
            $steed->SaddleID = $saddle->ID;

            $steed->withLocalisedCopyState(function () use ($steed, $active) {
                $steed->setLocalisedCopyActive($active);
                $steed->write();
            });

            $this->assertCount($tailsCount, $tails);
            $this->assertCount($saddlesCount, $saddles);
            $this->assertGreaterThan(0, $steed->TailID);
            $this->assertGreaterThan(0, $steed->SaddleID);
            $this->assertEquals((int) $tail->ID, (int) $steed->TailID);
            $this->assertEquals((int) $saddle->ID, (int) $steed->SaddleID);
        });
    }

    /**
     * case: existing object with inherited relation is localised into a new / existing locale
     * desired outcome:
     *
     * if localised copy is active
     * a new duplicted related object is created for the target locale
     *
     * if localised copy is inactive
     * desired outcome: no additional changes
     *
     * @param string $locale
     * @param bool $duplicated
     * @param bool $active
     * @dataProvider localesProvider
     */
    public function testEditWithInheritedRelation(string $locale, bool $duplicated, bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $duplicated, $active): void {
            $state->setLocale($locale);

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            $saddles = Saddle::get()->sort('ID', 'DESC');
            $saddlesCount = (int) $saddles->count();

            /** @var Steed|FluentExtension $steed */
            $steed = $this->objFromFixture(Steed::class, 'steed1');
            $steed->Title = 'Edited Title';

            $steed->withLocalisedCopyState(function () use ($steed, $active) {
                $steed->setLocalisedCopyActive($active);
                $steed->write();
            });

            /** @var Tail $tail */
            $oldTail = $this->objFromFixture(Tail::class, 'tail2');

            /** @var Saddle $tail */
            $oldSaddle = $this->objFromFixture(Saddle::class, 'saddle1');

            $this->assertGreaterThan(0, $steed->TailID);
            $this->assertGreaterThan(0, $steed->SaddleID);

            if ($duplicated) {
                $this->assertCount($tailsCount + 1, $tails);
                $this->assertCount($saddlesCount + 1, $saddles);

                $newTail = $tails->first();
                $newSaddle = $saddles->first();

                $this->assertNotEquals((int) $oldTail->ID, (int) $steed->TailID);
                $this->assertNotEquals((int) $oldSaddle->ID, (int) $steed->SaddleID);
                $this->assertEquals((int) $newTail->ID, (int) $steed->TailID);
                $this->assertEquals((int) $newSaddle->ID, (int) $steed->SaddleID);

                return;
            }

            $this->assertCount($tailsCount, $tails);
            $this->assertCount($saddlesCount, $saddles);
            $this->assertEquals((int) $oldTail->ID, (int) $steed->TailID);
            $this->assertEquals((int) $oldSaddle->ID, (int) $steed->SaddleID);
        });
    }

    /**
     * case: duplicate() called on localised record
     * desired outcome: has_one duplication is copied correctly to localised table
     */
    public function testDuplicate(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_NZ');

            /** @var Horse|FluentExtension $originalHorse */
            $originalHorse = $this->objFromFixture(Horse::class, 'horse1');

            // We need a second locale to be present before duplication so we can cover all cases
            $originalHorse->copyToLocale('ja_JP');
            FluentState::singleton()->withState(function (FluentState $state) use ($originalHorse): void {
                $state->setLocale('en_NZ');

                /** @var Horse $localisedHorse */
                $localisedHorse = Horse::get()->byID($originalHorse->ID);
                $ribbon = $localisedHorse->Tail()->Ribbon();
                // Make sure we have a different title in JP locale so we can assert this later
                $ribbon->Title .= ' JP';
                $ribbon->write();
            });

            $tail = $originalHorse->Tail();
            $originalTailID = $tail->ID;
            $horseCountBefore = Horse::get()->count();
            $tailsCountBefore = Tail::get()->count();
            $ribbonCountBefore = Ribbon::get()->count();

            // Duplicate the horse (and its tail by association)
            $duplicateHorse = $originalHorse->duplicate();
            $duplicateHorseID = $duplicateHorse->ID;

            $horseCountAfter = Horse::get()->count();
            $tailsCountAfter = Tail::get()->count();
            $ribbonCountAfter = Ribbon::get()->count();

            $this->assertEquals($horseCountBefore + 1, $horseCountAfter);
            $locales = [
                'en_NZ',
                'ja_JP',
            ];
            $localesCount = count($locales);
            $this->assertEquals(
                $tailsCountBefore + $localesCount,
                $tailsCountAfter,
                'We expect a duplicate to be created for each locale (Tail)'
            );
            $this->assertEquals(
                $ribbonCountBefore + $localesCount,
                $ribbonCountAfter,
                'We expect a duplicate to be created for each locale (Ribbon)'
            );

            // Re-fetch both horses so we are asserting on what's in the DB not just what's in memory
            $originalHorse = Horse::get()->byID($originalHorse->ID);

            $this->assertSame($originalTailID, $originalHorse->TailID);

            $localisedTailIDs = [];
            $localisedRibbonIDs = [];
            $localisedRibbonTitles = [];

            foreach ($locales as $locale) {
                [
                    $localisedTailID,
                    $localisedRibbonID,
                    $localisedRibbonTitle,
                ] = FluentState::singleton()->withState(
                    function (FluentState $state) use ($locale, $originalHorse, $duplicateHorseID): array {
                        $state->setLocale($locale);

                        $duplicateHorse = Horse::get()->byID($duplicateHorseID);
                        $this->assertNotSame($originalHorse->ID, $duplicateHorse->ID);
                        $this->assertNotSame($originalHorse->TailID, $duplicateHorse->TailID);
                        $this->assertNotSame($originalHorse->Tail()->RibbonID, $duplicateHorse->Tail()->RibbonID);

                        $tail = $duplicateHorse->Tail();
                        $ribbon = $tail->Ribbon();

                        return [
                            $duplicateHorse->TailID,
                            $ribbon->ID,
                            $ribbon->Title,
                        ];
                    }
                );

                $localisedTailIDs[] = $localisedTailID;
                $localisedRibbonIDs[] = $localisedRibbonID;
                $localisedRibbonTitles[] = $localisedRibbonTitle;
            }

            $localisedTailIDs = array_unique($localisedTailIDs);
            $localisedRibbonIDs = array_unique($localisedRibbonIDs);
            $this->assertCount($localesCount, $localisedTailIDs, 'We expect unique tail ID in each locale');
            $this->assertCount($localesCount, $localisedRibbonIDs, 'We expect unique ribbon ID in each locale');
            $this->assertCount($localesCount, $localisedRibbonTitles, 'We expect unique ribbon titles in each locale');
        });
    }

    public function localesProvider(): array
    {
        return [
            ['en_NZ', false, true],
            ['en_NZ', false, false],
            ['ja_JP', false, false],
            ['ja_JP', true, true],
        ];
    }

    public function copyStateProvider(): array
    {
        return [
            [false],
            [true],
        ];
    }
}
