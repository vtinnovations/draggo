<?php

declare(strict_types=1);

/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

namespace Vtinnovations\Draggo\Dca;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vtinnovations\Draggo\Agent\UsageSignal;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Settings\HostInventory;

/**
 * Renders the single licence surface: the "Draggo" field in the shared
 * "V-T.ONE Licence management" legend, in Contao → Settings.
 *
 * Everything here is server-rendered. The state is resolved on this request, so
 * it cannot get stuck loading, and the three controls are plain submit buttons
 * that use HTML's own `formaction` to re-point the surrounding settings form at
 * a dedicated route. That means no nested form (invalid HTML, silently dropped
 * by browsers), no custom JavaScript, and therefore no way for an asset that
 * evaluated before the palette existed to leave a visible button inert. The
 * form Contao already renders carries REQUEST_TOKEN, so CSRF comes along for
 * free.
 *
 * This is also the module-entry point for the once-per-session usage signal:
 * opening this section IS loading the package section.
 */
final class EditionCallbacks
{
    public function __construct(
        private readonly EditionResolver $resolver,
        private readonly HostInventory $hosts,
        private readonly UsageSignal $signal,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    /**
     * The whole section body. Contao passes the data container and an extra
     * label fragment; neither is needed, but the signature must tolerate them.
     *
     * Registered explicitly as `input_field_callback` in contao/dca/tl_settings.php
     * rather than through an attribute, so there is exactly one registration and
     * the wiring is visible in the DCA next to the palette entry it belongs to.
     */
    public function render(mixed $dc = null, string $xlabel = ''): string
    {
        $profile = $this->resolver->profile();

        // First entry into the package section in this backend session.
        $this->signal->moduleEntry();

        $admin = $this->isAdmin();

        $html = '<div class="widget draggo-license" style="max-width:640px">';
        $html .= '<h3>' . self::e($GLOBALS['TL_LANG']['tl_settings']['draggo_licence'][0] ?? 'Draggo') . '</h3>';
        $html .= '<div style="padding:12px 15px;border:1px solid var(--content-border);border-radius:4px;background:var(--content-bg)">';
        $html .= $this->status($profile);

        if (!$admin) {
            $html .= '<p class="tl_info">' . self::e('Nur Administratoren können die Lizenz verwalten.') . '</p></div></div>';

            return $html;
        }

        $html .= $this->controls($profile);

        return $html . '</div></div>';
    }

    /**
     * Current state, always resolved server-side: a coloured headline plus a
     * detail line, mirroring the "Guardian" / "Migrator" sections in the same
     * shared legend on the same Settings page so the three products present
     * one visual language. The full key never appears here
     * — {@see EditionProfile::key()} forbids it reaching a template.
     */
    private function status(EditionProfile $profile): string
    {
        if ($profile->active) {
            $html = $this->statusLine('var(--green)', 'Pro-Lizenz aktiv. Alle Funktionen freigeschaltet.');
            $html .= $this->detailLine($profile);

            return $html;
        }

        $reason = match ($profile->reason) {
            EditionProfile::REASON_NONE => 'Nicht lizenziert. Draggo ist gesperrt, Contao arbeitet unverändert weiter.',
            EditionProfile::REASON_EXPIRED => 'Lizenz abgelaufen. Bitte verlängern und anschließend „Lizenz aktualisieren" wählen.',
            EditionProfile::REASON_DOMAIN => 'Die Lizenz gilt nicht für eine der hier eingerichteten Domains.',
            EditionProfile::REASON_PACKAGE => 'Das hinterlegte Paket berechtigt nicht zur Nutzung von Draggo.',
            EditionProfile::REASON_PENDING => 'Die Lizenz ist noch nicht gültig.',
            EditionProfile::REASON_REFRESH => 'Die gespeicherte Lizenz muss einmalig aktualisiert werden.',
            EditionProfile::REASON_CRYPTO => 'Die Signaturprüfung ist auf diesem Server nicht verfügbar (PHP-Sodium fehlt).',
            EditionProfile::REASON_MALFORMED => 'Die gespeicherte Lizenz gehört nicht zu diesem Produkt.',
            default => 'Die gespeicherte Lizenz konnte nicht verifiziert werden.',
        };

        $configured = $this->hosts->configured();
        $domains = [] !== $configured ? implode(', ', $configured) : '—';

        $html = $this->statusLine('var(--red)', $reason);
        $html .= sprintf(
            '<div class="tl_gray" style="font-size:12px">%s: %s</div>',
            self::e('Domains dieser Installation'),
            self::e($domains),
        );

        return $html;
    }

    private function statusLine(string $colour, string $label): string
    {
        return sprintf(
            '<div style="font-size:15px;font-weight:bold;color:%s;margin-bottom:4px">%s</div>',
            $colour,
            self::e($label),
        );
    }

    /**
     * The five facts the sibling products show in this same dense
     * "·"-separated line and no more: which licence, which package, since when,
     * until when, last checked when. The bound domain, the signed domain set
     * and the allowance were dropped — record internals nobody acts on here.
     *
     * The licence appears only as {@see EditionProfile::maskedKey()}; the full
     * key() is never something this render path may echo.
     */
    private function detailLine(EditionProfile $profile): string
    {
        $line = sprintf(
            'Schlüssel: %s · Paket: %s · Gültig ab: %s · Gültig bis: %s · Zuletzt geprüft: %s',
            $profile->maskedKey(),
            strtoupper($profile->package),
            $this->moment($profile->startsAt),
            $profile->lifetime ? 'unbegrenzt' : $this->moment($profile->expiresAt),
            $this->moment($profile->verifiedAt),
        );

        return sprintf('<div class="tl_gray" style="font-size:12px;line-height:1.7">%s</div>', self::e($line));
    }

    private function moment(?int $timestamp): string
    {
        return null !== $timestamp && $timestamp > 0 ? date('d.m.Y H:i', $timestamp) : '—';
    }

    /**
     * The three controls. `formaction` re-targets the settings form per button,
     * so each click is one ordinary POST to one registered route.
     */
    private function controls(EditionProfile $profile): string
    {
        $stored = '' !== $profile->key();

        $html = '<label style="display:block;margin:12px 0 4px" for="draggo_licence_key"><strong>'
            . self::e('Lizenzschlüssel') . '</strong></label>';
        $html .= '<input type="text" name="draggo_licence_key" id="draggo_licence_key" '
            . 'autocomplete="off" spellcheck="false" maxlength="190" value="" '
            . 'style="width:100%;padding:6px;box-sizing:border-box" placeholder="'
            . self::e('XXXXX-XXXXX-XXXXX-XXXXX') . '">';

        $html .= '<div class="draggo-licence-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">';
        $html .= $this->button('draggo_licence_activate', 'Prüfen und aktivieren');

        if ($stored) {
            $html .= ' ' . $this->button('draggo_licence_refresh', 'Lizenz aktualisieren');
            $html .= ' ' . $this->button(
                'draggo_licence_remove',
                'Lizenz entfernen',
                'Lizenz wirklich entfernen? Draggo wird dadurch gesperrt.',
            );
        }

        return $html . '</div>';
    }

    private function button(string $route, string $label, ?string $confirm = null): string
    {
        // The token is embedded per button as well as by the surrounding form,
        // so the action stays protected no matter which form Contao renders.
        return sprintf(
            '<button type="submit" class="tl_submit" name="REQUEST_TOKEN" value="%s" formmethod="post" formaction="%s"%s>%s</button>',
            self::e($this->csrfTokenManager->getDefaultTokenValue()),
            self::e($this->router->generate($route)),
            null !== $confirm ? ' onclick="return confirm(\'' . self::e($confirm) . '\')"' : '',
            self::e($label),
        );
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof \Contao\BackendUser && $user->isAdmin;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
