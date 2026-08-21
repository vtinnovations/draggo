<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Security\RequestGuard;
use Vtinnovations\Draggo\Security\TrustAnchors;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * Pagemount authorization: assertCanEditPage / assertCanEditArticle must deny a
 * user that Contao's USER_CAN_EDIT_ARTICLES voter rejects (restricted editor),
 * and pass when granted (admin / mounted page).
 *
 * Also covers the entitlement gate, which is now a REQUIRED dependency: the
 * guard must refuse rather than wave requests through when no licence is
 * active.
 */
final class RequestGuardTest extends TestCase
{
    /** @var list<string> */
    private array $projectDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->projectDirs as $dir) {
            TempDir::remove($dir);
        }
    }

    private function guard(Security $security, ?Connection $connection = null): RequestGuard
    {
        return new RequestGuard(
            $this->createMock(ContaoCsrfTokenManager::class),
            'token',
            $security,
            $connection ?? $this->createMock(Connection::class),
            $this->unlicensedResolver(),
        );
    }

    /**
     * A resolver over an empty store and an empty key ring: the state every
     * installation is in before activation.
     */
    private function unlicensedResolver(): EditionResolver
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        $projectDir = sys_get_temp_dir() . '/draggo-guard-' . bin2hex(random_bytes(6));
        $this->projectDirs[] = $projectDir;

        return new EditionResolver(
            new ActivationStore($projectDir),
            new TrustAnchors([]),
            new HostInventory($connection, $stack),
        );
    }

    public function testUnlicensedInstallationCannotEditContent(): void
    {
        $user = $this->createMock(\Contao\BackendUser::class);
        $user->method('__get')->with('isAdmin')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $this->expectException(AccessDeniedException::class);
        $this->guard($security)->assertCanEditContent();
    }

    public function testUnlicensedInstallationGrantsNoCapability(): void
    {
        $guard = $this->guard($this->createMock(Security::class));

        foreach ([EditionProfile::CAP_EDITOR, EditionProfile::CAP_LIBRARY, EditionProfile::CAP_GLOBALS, EditionProfile::CAP_AI] as $cap) {
            self::assertFalse($guard->allowsFeature($cap), $cap);
        }

        self::assertFalse($guard->allowsElement('draggo_button'));
        self::assertFalse($guard->allowsStructure('6-6'));
    }

    public function testContaoCoreElementsAreNeverTakenAwayByTheLicence(): void
    {
        $guard = $this->guard($this->createMock(Security::class));

        // Not Draggo's product, so no licence state may block them.
        self::assertTrue($guard->allowsElement('text'));
        self::assertTrue($guard->allowsElement('headline'));
        self::assertTrue($guard->allowsElement('youtube'));
    }

    public function testUnlicensedInstallationCannotUseAi(): void
    {
        $user = $this->createMock(\Contao\BackendUser::class);
        $user->method('__get')->with('isAdmin')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        self::assertFalse($this->guard($security)->canUseAi());
    }

    public function testAssertCanEditPageThrowsWhenNotGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with(ContaoCorePermissions::USER_CAN_EDIT_ARTICLES, 99)
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->guard($security)->assertCanEditPage(99);
    }

    public function testAssertCanEditPagePassesWhenGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $this->guard($security)->assertCanEditPage(5);
        $this->addToAssertionCount(1); // reached here → no exception thrown
    }

    public function testAssertCanEditArticleDeniesViaResolvedPage(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with(ContaoCorePermissions::USER_CAN_EDIT_ARTICLES, 7)
            ->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(7); // article → page 7

        $this->expectException(AccessDeniedException::class);
        $this->guard($security, $connection)->assertCanEditArticle(42);
    }

    public function testAssertCanEditArticleThrowsWhenArticleMissing(): void
    {
        $security = $this->createMock(Security::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false); // no such article

        $this->expectException(AccessDeniedException::class);
        $this->guard($security, $connection)->assertCanEditArticle(123);
    }

    public function testAssertCanEditArticleReturnsPageIdWhenGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(7);

        self::assertSame(7, $this->guard($security, $connection)->assertCanEditArticle(42));
    }

    public function testAssertCanEditFormsThrowsWithoutBackendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedException::class);
        $this->guard($security)->assertCanEditForms();
    }

    public function testAssertCanEditFormsDeniesWithoutTlFormAccess(): void
    {
        $user = $this->createMock(\Contao\BackendUser::class);
        $user->method('__get')->with('isAdmin')->willReturn(false);
        $user->method('hasAccess')->with('tl_form', 'tables')->willReturn(false);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $this->expectException(AccessDeniedException::class);
        $this->guard($security)->assertCanEditForms();
    }

    public function testAssertCanEditFormsPassesWithTlFormAccess(): void
    {
        $user = $this->createMock(\Contao\BackendUser::class);
        $user->method('__get')->with('isAdmin')->willReturn(false);
        $user->method('hasAccess')->with('tl_form', 'tables')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $this->guard($security)->assertCanEditForms();
        $this->addToAssertionCount(1); // reached here → no exception
    }
}
