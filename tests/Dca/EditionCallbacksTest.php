<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Dca;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The action-wiring contract for the licence section, checked statically.
 *
 * A rendered button proves nothing on its own. What these assertions pin is the
 * chain: every control names a route, every route is registered, every route
 * resolves to a real controller method, and every one of those methods checks
 * permissions and CSRF before it touches the stored key or the network.
 *
 * The runtime half of this — actually clicking the buttons in a Contao backend
 * — could not be executed here (no PHP runtime available in this environment)
 * and is recorded as outstanding.
 */
final class EditionCallbacksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Rendered control => route name it posts to. */
    private const CONTROLS = [
        'Prüfen und aktivieren' => 'draggo_licence_activate',
        'Lizenz aktualisieren' => 'draggo_licence_refresh',
        'Lizenz entfernen' => 'draggo_licence_remove',
    ];

    private function callbacks(): string
    {
        return (string) file_get_contents(self::ROOT . '/src/Dca/EditionCallbacks.php');
    }

    private function controller(): string
    {
        return (string) file_get_contents(self::ROOT . '/src/Controller/Api/ActivationApiController.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function routes(): array
    {
        if (!class_exists(Yaml::class)) {
            self::markTestSkipped('symfony/yaml is not installed.');
        }

        return Yaml::parseFile(self::ROOT . '/config/routes.yaml');
    }

    public function testEveryRenderedControlNamesARegisteredRoute(): void
    {
        $callbacks = $this->callbacks();
        $routes = $this->routes();

        foreach (self::CONTROLS as $label => $route) {
            self::assertStringContainsString($label, $callbacks, "Control '$label' is not rendered.");
            self::assertStringContainsString("'" . $route . "'", $callbacks, "Control '$label' does not reference its route.");
            self::assertArrayHasKey($route, $routes, "Route '$route' is not registered.");
        }
    }

    public function testEveryRouteResolvesToAnExistingControllerMethod(): void
    {
        foreach ($this->routes() as $name => $definition) {
            if (!str_starts_with((string) $name, 'draggo_licence_')) {
                continue;
            }

            $parts = explode('::', $definition['controller']);
            $class = $parts[0];
            // The updater route uses a plain invokable controller (no
            // "::method" suffix); everything else names a method explicitly.
            $method = $parts[1] ?? '__invoke';

            self::assertTrue(class_exists($class), $class);
            self::assertTrue(method_exists($class, $method), $definition['controller']);

            if ('draggo_licence_updater' !== $name) {
                self::assertSame(['POST'], $definition['methods'], "$name must be POST-only.");
            }
        }
    }

    public function testEveryActionChecksPermissionAndCsrfBeforeDoingAnything(): void
    {
        $controller = $this->controller();

        foreach (['activate', 'refresh', 'remove'] as $action) {
            self::assertMatchesRegularExpression(
                '/public function ' . $action . '\(Request \$request\): Response\s*\{\s*if \(null !== \$deny = \$this->reject\(\$request\)\) \{/',
                $controller,
                sprintf('%s() must reject before any other work.', $action),
            );
        }

        // reject() is what enforces both checks.
        self::assertStringContainsString('!$user->isAdmin', $controller);
        self::assertStringContainsString('isTokenValid(new CsrfToken(', $controller);
    }

    public function testControlsAreRealSubmitButtonsNotDecoration(): void
    {
        $callbacks = $this->callbacks();

        self::assertStringContainsString('type="submit"', $callbacks);
        self::assertStringContainsString('formmethod="post"', $callbacks);
        self::assertStringContainsString('formaction="%s"', $callbacks);

        // The failure modes the wiring contract calls out explicitly.
        self::assertStringNotContainsString('href="#"', $callbacks);
        self::assertStringNotContainsString('javascript:void(0)', $callbacks);
    }

    public function testTheSectionCarriesACsrfTokenWithEveryAction(): void
    {
        self::assertStringContainsString('name="REQUEST_TOKEN"', $this->callbacks());
        self::assertStringContainsString('getDefaultTokenValue()', $this->callbacks());
    }

    public function testStateIsRenderedServerSideAndCannotHangOnALoader(): void
    {
        $callbacks = $this->callbacks();

        self::assertStringContainsString('$this->resolver->profile()', $callbacks);
        self::assertStringNotContainsString('Loading', $callbacks);
        self::assertStringNotContainsString('fetch(', $callbacks);
        self::assertStringNotContainsString('XMLHttpRequest', $callbacks);
    }

    public function testTheSectionShipsNoJavaScriptBundleThatCouldFailToBind(): void
    {
        $callbacks = $this->callbacks();

        self::assertStringNotContainsString('addEventListener', $callbacks);
        self::assertStringNotContainsString('data-action', $callbacks);
        self::assertStringNotContainsString('TL_JAVASCRIPT', $callbacks);
    }

    public function testTheBrowserNeverTalksToTheRegistryDirectly(): void
    {
        $callbacks = $this->callbacks();

        self::assertStringNotContainsString('v-t.one/api', $callbacks);
        self::assertStringNotContainsString('RegistryEndpoints', $callbacks);
    }

    public function testTheRenderedSectionNeverEmitsTheLicenceKey(): void
    {
        $callbacks = $this->callbacks();

        // The input is always rendered empty; a stored key is indicated by a
        // placeholder, never by its value.
        self::assertStringContainsString('value=""', $callbacks);

        // key() may be consulted to know whether something is stored, but its
        // value must never reach the markup.
        self::assertStringContainsString("\$stored = '' !== \$profile->key();", $callbacks);
        self::assertStringNotContainsString('e($profile->key())', $callbacks);
        self::assertStringNotContainsString('value="' . "'" . ' . ', $callbacks);
    }

    public function testTheSectionIsRegisteredOnTlSettingsWithTheContractHeadline(): void
    {
        $dca = (string) file_get_contents(self::ROOT . '/contao/dca/tl_settings.php');

        self::assertStringContainsString("applyToPalette('default', 'tl_settings')", $dca);
        self::assertStringContainsString(
            "addLegend('vtone_licence_legend', null, PaletteManipulator::POSITION_PREPEND)",
            $dca,
            'The licence section belongs in the shared legend at the top of Settings, like every other V&T product.',
        );
        self::assertStringContainsString(
            "addField('draggo_licence', 'vtone_licence_legend', PaletteManipulator::POSITION_APPEND)",
            $dca,
        );
        self::assertStringContainsString('input_field_callback', $dca);

        foreach (['en', 'de'] as $language) {
            $lang = (string) file_get_contents(self::ROOT . '/contao/languages/' . $language . '/tl_settings.php');

            self::assertStringContainsString(
                "\$GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] = 'V-T.ONE Licence management';",
                $lang,
                "The $language legend headline must match the shared contract exactly.",
            );
            self::assertStringContainsString(
                "'draggo_licence'] = ['Draggo',",
                $lang,
                "The $language field label must open with the product name.",
            );
        }
    }

    public function testTheUpdaterRouteIsPublicAndUsesTheProtocolPath(): void
    {
        $routes = $this->routes();

        self::assertArrayHasKey('draggo_licence_updater', $routes);
        self::assertSame('/rest/api/v1/draggo-license-updater', $routes['draggo_licence_updater']['path']);
        self::assertArrayNotHasKey(
            'defaults',
            $routes['draggo_licence_updater'],
            'The updater is server-to-server and must not be backend-scoped.',
        );
    }

    public function testAdministratorActionsAreBackendScoped(): void
    {
        foreach (self::CONTROLS as $route) {
            self::assertSame('backend', $this->routes()[$route]['defaults']['_scope']);
        }
    }

    public function testNoLegacyLicenceSurfaceRemains(): void
    {
        $settings = (string) file_get_contents(self::ROOT . '/contao/dca/tl_draggo_settings.php');

        self::assertStringNotContainsString('license_legend', $settings);
        self::assertStringNotContainsString('license_key', $settings);
        self::assertStringNotContainsString('license_status', $settings);

        foreach (['LicenseGuard', 'LicenseManager', 'LicenseVerifier', 'LicenseSettingsListener', 'LicenseLockdownListener'] as $class) {
            self::assertSame(
                [],
                glob(self::ROOT . '/src/**/' . $class . '.php') ?: [],
                "$class must be gone, not merely unreferenced.",
            );
        }
    }
}
