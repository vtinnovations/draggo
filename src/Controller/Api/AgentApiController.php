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
use Vtinnovations\Draggo\Agent\AgentService;
use Vtinnovations\Draggo\Agent\TelemetryReporter;
use Vtinnovations\Draggo\Block\BlockTypeStore;
use Contao\StringUtil;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Image\ImageService;
use Vtinnovations\Draggo\Security\RequestGuard;

/**
 * Backend-authenticated API for the AI element generator. Lives under /contao
 * (Contao firewall) and re-asserts table access + CSRF on every mutation. The
 * conversation history is held by the client; the server is stateless.
 */
final class AgentApiController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestGuard $guard,
        private readonly AgentService $agent,
        private readonly BlockTypeStore $store,
        private readonly TelemetryReporter $telemetry,
        private readonly \Vtinnovations\Draggo\Editor\ContentSynchronizer $synchronizer,
        private readonly ImageService $images,
    ) {
    }

    /**
     * Build a whole page from a brief: the AI plans an ordered list of proven
     * section templates; we create one container per section. Body: {brief, lang}.
     */
    public function fromBrief(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanUseAi();
            $this->guard->assertCsrf($request);

            $payload = $this->json($request);
            $brief = trim((string) ($payload['brief'] ?? ''));
            if ($brief === '') {
                throw new InvalidInputException('Kein Brief angegeben.');
            }
            $lang = (($payload['lang'] ?? '') === 'en') ? 'en' : 'de';

            // The AI now plans page-type-appropriate sections AND writes real,
            // on-topic copy for each (overlaid onto the section template).
            return $this->createSections($id, $this->agent->composePage($brief, $lang));
        });
    }

    /**
     * Build a whole page from the user's OWN declared content: the AI maps the
     * supplied, labelled content onto fitting sections (using the user's words,
     * inventing nothing). Body: {content, lang}.
     */
    public function fromContent(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanUseAi();
            $this->guard->assertCsrf($request);

            $payload = $this->json($request);
            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '') {
                throw new InvalidInputException('Keine Inhalte angegeben.');
            }
            $lang = (($payload['lang'] ?? '') === 'en') ? 'en' : 'de';

            return $this->createSections($id, $this->agent->composeFromContent($content, $lang));
        });
    }

    /**
     * Create one container (tl_article) per composed section, overlaying the
     * AI/user content onto the section template. Returns {created, sections}.
     *
     * @param list<array{key:string,content:array<string,mixed>}> $sections
     */
    private function createSections(int $pageId, array $sections): JsonResponse
    {
        if ($sections === []) {
            return new JsonResponse(['created' => 0, 'sections' => []]);
        }

        $now = time();
        $after = null;
        $labels = [];
        $imagesActive = $this->images->isActive();
        foreach ($sections as $section) {
            $key = $section['key'];
            $content = $section['content'] ?? [];
            $layout = \Vtinnovations\Draggo\Template\SectionTemplates::articleLayout($key);

            // Source/generate a photo for this section (Unsplash/Pexels/OpenAI),
            // store it in the media library, and prefill it. hero uses it as the
            // container background; the others fill their image element. Failure
            // is silent — the section is still created (just without a photo).
            $query = trim((string) ($content['image_query'] ?? ''));
            if ($imagesActive && $query !== '') {
                $img = $this->images->imageFor($query);
                if ($img !== null) {
                    if ($key === 'hero') {
                        $layout['image'] = $img['path'];
                    } else {
                        $content['_imageBin'] = StringUtil::uuidToBin($img['uuid']);
                        $content['_imageAlt'] = $query;
                    }
                }
            }

            $title = \Vtinnovations\Draggo\Template\SectionTemplates::title($key);
            $articleId = $this->synchronizer->createArticle($pageId, $title, $now, $after);
            $this->synchronizer->insertTree(\Vtinnovations\Draggo\Template\SectionTemplates::items($key, $content), $articleId, 'tl_article', null, $now);
            if ($layout !== []) {
                $this->synchronizer->updateArticleLayout($articleId, $layout, $now);
            }
            $after = $articleId;
            $labels[] = $title;
        }

        return new JsonResponse(['created' => \count($labels), 'sections' => $labels], Response::HTTP_CREATED);
    }

    /** Generator availability + existing types (for the editor's KI tab). */
    public function status(): JsonResponse
    {
        return $this->safe(function (): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();

            return new JsonResponse([
                'ready'     => $this->agent->isReady(),
                'canUse'    => $this->guard->canUseAi(),
                'canDelete' => $this->guard->canDeleteBlockType(),
                'types'     => $this->store->all(),
            ]);
        });
    }

    /** One agent turn. Body: {messages:[{role,content}]}. */
    public function chat(Request $request): JsonResponse
    {
        return $this->safe(function () use ($request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanUseAi();
            $this->guard->assertCsrf($request);

            $payload = $this->json($request);
            $messages = \is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
            $history = [];
            foreach ($messages as $m) {
                if (\is_array($m) && isset($m['role'], $m['content'])) {
                    $history[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
                }
            }
            if ($history === []) {
                throw new InvalidInputException('Keine Nachricht.');
            }

            // Optional edit mode: revise an existing block type instead of
            // building a new one.
            $editSpec = null;
            $editType = (string) ($payload['editType'] ?? '');
            if ($editType !== '') {
                $spec = $this->store->find($editType);
                if ($spec !== null) {
                    $editSpec = $spec->toArray();
                }
            }

            $lang = (($payload['lang'] ?? '') === 'en') ? 'en' : 'de';

            return new JsonResponse($this->agent->turn($history, $editSpec, $lang, $this->parseImages($payload['images'] ?? null)));
        });
    }

    /**
     * Validate uploaded screenshots (Claude vision): accept up to 4 base64 data
     * URLs of safe image types, each ≤ ~5 MB. Returns [{media_type, data}].
     *
     * @return list<array{media_type:string,data:string}>
     */
    private function parseImages(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (\array_slice($raw, 0, 4) as $du) {
            if (!\is_string($du) || !preg_match('#^data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=]+)$#', $du, $m)) {
                continue;
            }
            $data = $m[2];
            // Base64 is ~4/3 of the byte size; Anthropic caps images at ~5 MB.
            if (\strlen($data) > 7_000_000 || base64_decode($data, true) === false) {
                continue;
            }
            $mt = $m[1] === 'jpg' ? 'jpeg' : $m[1];
            $out[] = ['media_type' => 'image/' . $mt, 'data' => $data];
        }

        return $out;
    }

    /** Persist a confirmed spec as a block type. Body: {spec:{…}}. */
    public function commit(Request $request): JsonResponse
    {
        return $this->safe(function () use ($request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanUseAi();
            $this->guard->assertCsrf($request);

            $payload = $this->json($request);
            $raw = \is_array($payload['spec'] ?? null) ? $payload['spec'] : [];
            $spec = $this->agent->validateSpec($raw);
            $this->store->save($spec, time());
            $this->telemetry->report($spec);

            return new JsonResponse(['type' => $spec->type, 'label' => $spec->label], Response::HTTP_CREATED);
        });
    }

    public function deleteType(string $type, Request $request): JsonResponse
    {
        return $this->safe(function () use ($type, $request): JsonResponse {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanDeleteBlockType();
            $this->guard->assertCsrf($request);
            $this->store->delete($type);

            return new JsonResponse(['ok' => true]);
        });
    }

    /** @param callable():JsonResponse $fn */
    private function safe(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        } catch (InvalidInputException $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_BAD_REQUEST);
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
