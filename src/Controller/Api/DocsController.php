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
use Vtinnovations\Draggo\Docs\DocChatService;
use Vtinnovations\Draggo\Docs\DocGenerator;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Security\RequestGuard;

/**
 * Backend-only docs + grounded help chatbot for the editor. GET returns the
 * documentation sections (auto-generated + guides); POST answers a question
 * strictly from those docs via the configured AI client. Admin/edit-access only.
 */
final class DocsController
{
    private const MAX_HISTORY = 20;

    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ContaoFramework $framework,
        private readonly DocGenerator $docs,
        private readonly DocChatService $chat,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        $lang = $this->lang($request);

        return new JsonResponse([
            'sections' => $this->docs->sections($lang),
            'chatReady' => $this->chat->isReady(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        if (!$this->chat->isReady()) {
            return new JsonResponse([
                'ready' => false,
                'text' => 'Die KI ist nicht konfiguriert. Hinterlege einen API-Schlüssel unter Backend → Draggo → Einstellungen. Die Doku links kannst du auch ohne KI durchsuchen.',
                'sources' => [],
            ]);
        }

        $data = json_decode((string) $request->getContent(), true);
        $data = \is_array($data) ? $data : [];
        $history = [];
        foreach (\is_array($data['history'] ?? null) ? $data['history'] : [] as $m) {
            if (!\is_array($m)) {
                continue;
            }
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content !== '') {
                $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
            }
        }
        $history = \array_slice($history, -self::MAX_HISTORY);
        if ($history === []) {
            return new JsonResponse(['error' => ['code' => 'empty', 'message' => 'Keine Frage.']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $res = $this->chat->answer($history, $this->lang($request));
        } catch (\Throwable $e) {
            return new JsonResponse(['ready' => true, 'text' => 'Fehler bei der KI-Anfrage: ' . $e->getMessage(), 'sources' => []]);
        }

        return new JsonResponse(['ready' => true, 'text' => $res['text'], 'sources' => $res['sources']]);
    }

    private function lang(Request $request): string
    {
        return str_starts_with((string) ($GLOBALS['TL_LANGUAGE'] ?? $request->getLocale() ?? 'en'), 'de') ? 'de' : 'en';
    }
}
