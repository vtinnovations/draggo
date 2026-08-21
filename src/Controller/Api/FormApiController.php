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
use Vtinnovations\Draggo\Form\FormBuilder;
use Vtinnovations\Draggo\Security\RequestGuard;

/**
 * Backend API for the visual form builder. CRUD on real tl_form / tl_form_field
 * rows via FormBuilder (Contao handles everything else). Admin/edit-access only;
 * all writes CSRF-checked.
 */
final class FormApiController
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ContaoFramework $framework,
        private readonly FormBuilder $builder,
    ) {
    }

    public function list(): JsonResponse
    {
        if (($g = $this->read()) !== null) {
            return $g;
        }

        return new JsonResponse(['forms' => $this->builder->forms(), 'kinds' => FormBuilder::kinds()]);
    }

    public function show(int $id): JsonResponse
    {
        if (($g = $this->read()) !== null) {
            return $g;
        }
        if (!$this->builder->formExists($id)) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'Formular nicht gefunden.']], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'meta'   => $this->builder->formMeta($id),
            'fields' => $this->builder->fields($id),
            'kinds'  => FormBuilder::kinds(),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        $title = (string) ($this->body($request)['title'] ?? '');
        $id = $this->builder->createForm($title, time());

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    public function addField(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        if (!$this->builder->formExists($id)) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'Formular nicht gefunden.']], Response::HTTP_NOT_FOUND);
        }
        $kind = (string) ($this->body($request)['kind'] ?? 'text');
        if (!\in_array($kind, FormBuilder::kinds(), true)) {
            return new JsonResponse(['error' => ['code' => 'bad_kind', 'message' => 'Unbekannter Feldtyp.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $fieldId = $this->builder->addField($id, $kind, time());

        return new JsonResponse(['id' => $fieldId, 'fields' => $this->builder->fields($id)], Response::HTTP_CREATED);
    }

    public function updateField(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        $this->builder->updateField($id, $this->body($request), time());

        return new JsonResponse(['ok' => true]);
    }

    public function deleteField(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        $this->builder->deleteField($id);

        return new JsonResponse(['ok' => true]);
    }

    public function duplicateField(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        $newId = $this->builder->duplicateField($id, time());

        return new JsonResponse(['ok' => $newId > 0, 'id' => $newId]);
    }

    public function reorder(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        $ids = array_map('intval', (array) ($this->body($request)['ids'] ?? []));
        $this->builder->reorder($id, $ids, time());

        return new JsonResponse(['ok' => true]);
    }

    public function meta(Request $request, int $id): JsonResponse
    {
        if (($g = $this->write($request)) !== null) {
            return $g;
        }
        if (!$this->builder->formExists($id)) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'Formular nicht gefunden.']], Response::HTTP_NOT_FOUND);
        }
        $this->builder->updateFormMeta($id, $this->body($request), time());

        return new JsonResponse(['ok' => true, 'meta' => $this->builder->formMeta($id)]);
    }

    // ── guards / helpers ─────────────────────────────────────────────
    private function read(): ?JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanEditForms();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function write(Request $request): ?JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCanEditForms();
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $d = json_decode((string) $request->getContent(), true);

        return \is_array($d) ? $d : [];
    }
}
