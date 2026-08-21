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

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Security\RequestGuard;
use Vtinnovations\Draggo\Token\DefaultsStore;
use Vtinnovations\Draggo\Token\TokenStore;

/**
 * CRUD for design tokens (Phase E "Globals"). Managed from the Draggo editor.
 */
final class TokenApiController
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ContaoFramework $framework,
        private readonly TokenStore $tokens,
        private readonly DefaultsStore $defaults,
    ) {
    }

    public function getDefaults(): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            // Design tokens + global default styles are a paid feature: the
            // free tier may open the editor, not the global design system.
            $this->guard->assertFeature(EditionProfile::CAP_GLOBALS);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['defaults' => $this->defaults->get()]);
    }

    public function saveDefaults(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            // Design tokens + global default styles are a paid feature: the
            // free tier may open the editor, not the global design system.
            $this->guard->assertFeature(EditionProfile::CAP_GLOBALS);
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode((string) $request->getContent(), true);
        $this->defaults->save(\is_array($data['defaults'] ?? null) ? $data['defaults'] : [], time());

        return new JsonResponse(['ok' => true, 'css' => $this->globalsCss()]);
    }

    /**
     * Combined globals CSS (token :root vars + canvas-scoped default styles),
     * identical to what EditorController injects at load. Returned from every
     * mutating endpoint so the editor can re-apply globals live (no refresh).
     */
    private function globalsCss(): string
    {
        return $this->tokens->cssVars() . $this->defaults->css('.draggo-canvas', '.draggo-canvas ');
    }

    public function list(): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            // Design tokens + global default styles are a paid feature: the
            // free tier may open the editor, not the global design system.
            $this->guard->assertFeature(EditionProfile::CAP_GLOBALS);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['tokens' => $this->tokens->all()]);
    }

    public function save(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            // Design tokens + global default styles are a paid feature: the
            // free tier may open the editor, not the global design system.
            $this->guard->assertFeature(EditionProfile::CAP_GLOBALS);
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            $data = [];
        }
        try {
            $id = $this->tokens->save($data, time());
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => ['code' => 'invalid', 'message' => $e->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['id' => $id, 'css' => $this->globalsCss()]);
    }

    public function delete(int $id, Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            // Design tokens + global default styles are a paid feature: the
            // free tier may open the editor, not the global design system.
            $this->guard->assertFeature(EditionProfile::CAP_GLOBALS);
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        $this->tokens->delete($id);

        return new JsonResponse(['ok' => true, 'css' => $this->globalsCss()]);
    }
}
