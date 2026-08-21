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
use Vtinnovations\Draggo\History\HistoryStore;
use Vtinnovations\Draggo\Security\RequestGuard;

/**
 * Restore points for a container. Snapshot the current state, list points,
 * roll back. Backend-firewalled; CSRF + content access enforced.
 */
final class HistoryApiController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestGuard $guard,
        private readonly HistoryStore $history,
    ) {
    }

    /** List restore points of an article. */
    public function listArticle(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanEditArticle($id);

            return new JsonResponse(['points' => $this->history->list($id, 'tl_article')]);
        });
    }

    /** Save a restore point. Body: {label?}. */
    public function snapshotArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditArticle($id);

            $label = (string) ($this->json($request)['label'] ?? '');
            $newId = $this->history->snapshot($id, 'tl_article', $label, time());

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    /** Roll back to a restore point. */
    public function restore(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCsrf($request);
            // $id is a SNAPSHOT id → authorize against the page it would restore to.
            $target = $this->history->targetOf($id);
            if ($target !== null && $target['ptable'] === 'tl_article') {
                $this->guard->assertCanEditArticle($target['pid']);
            }

            $where = $this->history->restore($id, time());

            return new JsonResponse(['ok' => true, 'pid' => $where['pid'], 'ptable' => $where['ptable']]);
        });
    }

    /** @param callable():JsonResponse $fn */
    private function safe(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** @return array<string,mixed> */
    private function json(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return \is_array($data) ? $data : [];
    }
}
