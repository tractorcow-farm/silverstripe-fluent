<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTest;

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
     * @dataProvider copyStateProvider
     */
    public function testCreateWithDefinedRealtion(bool $active): void
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
            $horse->write();

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
     * @dataProvider localesProvider
     */
    public function testEditWithDefinedRealtion(string $locale, bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $active): void {
            $state->setLocale($locale);

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            /** @var Horse $horse */
            $horse = $this->objFromFixture(Horse::class, 'horse1');
            $horse->Title = 'Edited Title';
            $horse->write();

            /** @var Tail $oldTail */
            $oldTail = $this->objFromFixture(Tail::class, 'tail1');

            $this->assertGreaterThan(0, $horse->TailID);

            if ($active) {
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
     * @dataProvider copyStateProvider
     */
    public function testCreateWithInheritedRealtion(bool $active): void
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
            $steed->write();

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
     * @param bool $active
     * @dataProvider localesProvider
     */
    public function testEditWithInheritedRealtion(string $locale, bool $active): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($locale, $active): void {
            $state->setLocale($locale);

            $tails = Tail::get()->sort('ID', 'DESC');
            $tailsCount = (int) $tails->count();

            $saddles = Saddle::get()->sort('ID', 'DESC');
            $saddlesCount = (int) $saddles->count();

            /** @var Steed $steed */
            $steed = $this->objFromFixture(Steed::class, 'steed1');
            $steed->Title = 'Edited Title';
            $steed->write();

            /** @var Tail $tail */
            $oldTail = $this->objFromFixture(Tail::class, 'tail2');

            /** @var Saddle $tail */
            $oldSaddle = $this->objFromFixture(Saddle::class, 'saddle1');

            $this->assertGreaterThan(0, $steed->TailID);
            $this->assertGreaterThan(0, $steed->SaddleID);

            if ($active) {
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

    public function localesProvider(): array
    {
        return [
            ['en_NZ', false],
            ['ja_JP', true],
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
