<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentMemberExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FluentMemberExtensionTest.yml';

    protected function setUp(): void
    {
        parent::setUp();
        Locale::clearCached();
        Permission::reset();
    }

    public function testCMSAccessLocalesWithoutLocalePermissions(): void
    {
        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, 'author');
        $this->assertSame(['en_NZ', 'de_DE', 'fr_FR'], $author->getCMSAccessLocales()->column('Locale'));

        /** @var Member $visitor */
        $visitor = $this->objFromFixture(Member::class, 'visitor');
        $this->assertSame([], $visitor->getCMSAccessLocales()->column('Locale'));
    }

    public function testCMSAccessLocalesWithLocalePermissions(): void
    {
        $this->limitGroupToLocale('germanauthors', 'de_DE');

        /** @var Member $germanAuthor */
        $germanAuthor = $this->objFromFixture(Member::class, 'germanauthor');
        $this->assertSame(['de_DE'], $germanAuthor->getCMSAccessLocales()->column('Locale'));

        // Groups without a locale permission are still enabled in every locale
        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, 'author');
        $this->assertSame(['en_NZ', 'de_DE', 'fr_FR'], $author->getCMSAccessLocales()->column('Locale'));
    }

    public function testGroupsAreFilteredByCurrentLocale(): void
    {
        /** @var Member $germanAuthor */
        $germanAuthor = $this->objFromFixture(Member::class, 'germanauthor');
        $groupID = $this->idFromFixture(Group::class, 'germanauthors');

        FluentState::singleton()->withState(function (FluentState $state) use ($germanAuthor, $groupID) {
            $state->setLocale('fr_FR');
            $this->assertSame([$groupID], $germanAuthor->Groups()->column('ID'));

            $this->limitGroupToLocale('germanauthors', 'de_DE');
            $this->assertSame([], $germanAuthor->Groups()->column('ID'));

            $state->setLocale('de_DE');
            $this->assertSame([$groupID], $germanAuthor->Groups()->column('ID'));
        });
    }

    private function limitGroupToLocale(string $groupFixture, string $localeCode): void
    {
        $locale = Locale::getByLocale($localeCode);
        Permission::create([
            'Code' => $locale->getLocaleEditPermission(),
            'GroupID' => $this->idFromFixture(Group::class, $groupFixture),
        ])->write();
        Permission::reset();
    }
}
