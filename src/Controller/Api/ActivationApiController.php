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

namespace Vtinnovations\Draggo\Controller\Api;

use Contao\BackendUser;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Vtinnovations\Draggo\Agent\RegistryClient;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * The three administrator actions behind the licence section in
 * Contao → Settings.
 *
 * Every one of them is a real POST to a registered route with a real permission
 * check and a real CSRF check, and every one ends in a redirect back to the
 * settings screen showing the new state. The browser never talks to the
 * registry: it posts here, and the server does the exchange. That keeps the
 * licence key server-side and keeps the whole flow auditable in one place.
 *
 * Licence management is admin-only. A backend user with content rights can edit
 * pages; only an administrator may activate or remove the product's licence.
 */
final class ActivationApiController
{
    public function __construct(
        private readonly RegistryClient $registry,
        private readonly EditionResolver $resolver,
        private readonly ActivationStore $store,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    /** Verify and activate a newly entered key. */
    public function activate(Request $request): Response
    {
        if (null !== $deny = $this->reject($request)) {
            return $deny;
        }

        $key = trim((string) $request->request->get('draggo_licence_key', ''));

        if ('' === $key) {
            return $this->back($request, 'error', 'Bitte einen Lizenzschlüssel eingeben.');
        }

        return $this->report($request, $this->registry->activate($key));
    }

    /**
     * Re-verify against the registry. Uses the stored key unless a replacement
     * was typed into the field.
     */
    public function refresh(Request $request): Response
    {
        if (null !== $deny = $this->reject($request)) {
            return $deny;
        }

        $replacement = trim((string) $request->request->get('draggo_licence_key', ''));

        return $this->report($request, $this->registry->refresh('' !== $replacement ? $replacement : null));
    }

    /**
     * Remove the licence. Draggo returns to its unlicensed state immediately —
     * Contao itself, and every page already built, are untouched.
     */
    public function remove(Request $request): Response
    {
        if (null !== $deny = $this->reject($request)) {
            return $deny;
        }

        $cleared = $this->store->clear();
        $this->resolver->forget();

        return $cleared
            ? $this->back($request, 'confirmation', 'Lizenz entfernt. Draggo ist gesperrt, Ihre Inhalte bleiben unverändert.')
            : $this->back($request, 'error', 'Lizenz konnte nicht entfernt werden.');
    }

    /**
     * Permission and CSRF. Both must pass before the request is allowed to
     * reach the stored key or the network.
     */
    private function reject(Request $request): ?Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || !$user->isAdmin) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $token = (string) $request->request->get('REQUEST_TOKEN', '');

        if ('' === $token || !$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Translate an exchange outcome into an administrator message.
     *
     * The messages are deliberately generic about causes: an operator needs to
     * know whether to retry, check the key or call support, not which
     * verification stage tripped.
     *
     * @param array{status: string, version: int} $outcome
     */
    private function report(Request $request, array $outcome): Response
    {
        [$type, $message] = match ($outcome['status']) {
            RegistryClient::OK => ['confirmation', 'Lizenz aktiv. Draggo ist freigeschaltet.'],
            RegistryClient::DENIED => ['error', 'Der Lizenzschlüssel wurde nicht akzeptiert. Bitte Schlüssel und Domain prüfen (v-t.one).'],
            RegistryClient::UNAVAILABLE => ['error', 'Lizenzserver derzeit nicht erreichbar. Die bestehende Lizenz bleibt unverändert.'],
            RegistryClient::STALE => ['error', 'Die Antwort war älter als der lokale Stand und wurde verworfen.'],
            RegistryClient::NO_DOMAIN => ['error', 'Für diese Installation ist keine Domain hinterlegt. Bitte im Startpunkt der Website eine Domain eintragen.'],
            default => ['error', 'Die Lizenz konnte nicht überprüft werden.'],
        };

        return $this->back($request, $type, $message);
    }

    private function back(Request $request, string $type, string $message): Response
    {
        try {
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add('contao.BE.' . $type, $message);
            }
        } catch (\Throwable) {
            // A missing session must not turn a completed action into an error.
        }

        return new RedirectResponse($this->router->generate('contao_backend', ['do' => 'settings']));
    }
}
