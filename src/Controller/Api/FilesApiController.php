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
use Contao\Dbafs;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Security\RequestGuard;

/**
 * Lists files under the Contao "files/" upload root so the editor can offer a
 * real picker (images, video, audio, documents) instead of a free-text path.
 * Each entry carries a "kind" so the picker can filter per field type.
 */
final class FilesApiController
{
    /** Extension → kind. Anything unlisted is reported as "other". */
    private const KINDS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif', 'bmp', 'ico'],
        'video' => ['mp4', 'webm', 'ogv', 'mov', 'm4v', 'avi'],
        'audio' => ['mp3', 'm4a', 'ogg', 'oga', 'wav', 'flac', 'aac'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rtf', 'odt'],
    ];
    private const LIMIT = 2000;

    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
    ) {
    }

    public function list(): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        // Flatten the kind map to ext => kind for O(1) lookup.
        $extKind = [];
        foreach (self::KINDS as $kind => $exts) {
            foreach ($exts as $ext) {
                $extKind[$ext] = $kind;
            }
        }

        $root = $this->projectDir . '/files';
        $files = [];
        $dirs = [];

        if (is_dir($root)) {
            $finder = (new Finder())
                ->files()
                ->in($root)
                ->name('/\.(' . implode('|', array_keys($extKind)) . ')$/i')
                ->sortByName();

            foreach ($finder as $file) {
                $path = 'files/' . str_replace('\\', '/', $file->getRelativePathname());
                $ext = strtolower($file->getExtension());
                $files[] = ['path' => $path, 'kind' => $extKind[$ext] ?? 'other'];
                if (\count($files) >= self::LIMIT) {
                    break;
                }
            }

            foreach ((new Finder())->directories()->in($root)->sortByName() as $dir) {
                $dirs[] = 'files/' . str_replace('\\', '/', $dir->getRelativePathname());
            }
        }

        return new JsonResponse(['files' => $files, 'dirs' => $dirs]);
    }

    /**
     * Mark an upload folder publicly accessible so its files resolve under
     * /files/…. Mirrors what Contao's `contao:symlinks` does at deploy time:
     *  - drop a `.public` marker (Contao's public-folder convention),
     *  - best-effort create the public/ symlink so the file is reachable NOW
     *    without a deploy step (silently ignored where symlinks aren't allowed —
     *    `contao:symlinks` then handles it on the next deploy).
     */
    private function ensurePublic(string $absDir): void
    {
        if (!is_dir($absDir)) {
            return;
        }
        $marker = $absDir . '/.public';
        if (!is_file($marker)) {
            @touch($marker);
        }

        $rel = str_replace('\\', '/', substr($absDir, \strlen($this->projectDir) + 1));
        if ($rel === '' || !str_starts_with($rel, 'files/')) {
            return;
        }
        $link = $this->projectDir . '/public/' . $rel;
        if (file_exists($link) || is_link($link)) {
            return;
        }
        $parent = \dirname($link);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        // Absolute target → correct at any folder depth (a relative prefix would
        // break for nested folders). If a public ancestor is already symlinked,
        // the file_exists() check above already returned (resolves through it).
        @symlink($absDir, $link);
    }

    /**
     * Resolve a client-supplied folder (relative, under files/) to an absolute
     * path, or null if it escapes the upload root / does not exist.
     */
    private function safeFolder(?string $raw): ?string
    {
        $rel = trim((string) $raw);
        $rel = str_replace('\\', '/', $rel);
        $rel = trim($rel, '/');
        if ($rel === '' || $rel === 'files') {
            return $this->projectDir . '/files';
        }
        if (!str_starts_with($rel, 'files/') || str_contains($rel, '..')) {
            return null;
        }

        $abs = $this->projectDir . '/' . $rel;
        $real = realpath($abs);
        $rootReal = realpath($this->projectDir . '/files');
        if ($real === false || $rootReal === false || !is_dir($real) || !str_starts_with($real, $rootReal)) {
            return null;
        }

        return $real;
    }

    /**
     * Upload one file into files/draggo/ and register it with the DBAFS so it
     * immediately has a UUID (required for storage). Extension is whitelisted
     * against the same kind map the picker uses.
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        $upload = $request->files->get('file');
        if ($upload === null) {
            // Empty $_FILES usually means the upload exceeded PHP's
            // upload_max_filesize / post_max_size (PHP drops the file silently).
            $hint = 'Keine Datei empfangen. Wahrscheinlich überschreitet die Datei das PHP-Limit (upload_max_filesize='
                . (ini_get('upload_max_filesize') ?: '?') . ', post_max_size=' . (ini_get('post_max_size') ?: '?') . ').';

            return new JsonResponse(['error' => ['code' => 'no_file', 'message' => $hint]], Response::HTTP_BAD_REQUEST);
        }
        if (!$upload->isValid()) {
            return new JsonResponse(['error' => ['code' => 'upload_error', 'message' => 'Upload-Fehler: ' . $upload->getErrorMessage()]], Response::HTTP_BAD_REQUEST);
        }

        // Resolve extension → kind (whitelist).
        $extKind = [];
        foreach (self::KINDS as $kind => $exts) {
            foreach ($exts as $ext) {
                $extKind[$ext] = $kind;
            }
        }
        $ext = strtolower($upload->getClientOriginalExtension());
        if (!isset($extKind[$ext])) {
            return new JsonResponse(['error' => ['code' => 'bad_type', 'message' => 'Dateityp nicht erlaubt.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Sanitise the base name; keep only safe characters.
        $base = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'datei';
        }
        $base = substr($base, 0, 120);

        // Target folder (defaults to files/draggo when none/invalid given).
        $absDir = $this->safeFolder($request->request->get('folder'));
        if ($absDir === null) {
            $absDir = $this->projectDir . '/files/draggo';
            if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                return new JsonResponse(['error' => ['code' => 'mkdir', 'message' => 'Zielordner nicht beschreibbar.']], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Avoid clobbering an existing file.
        $name = $base . '.' . $ext;
        $i = 1;
        while (file_exists($absDir . '/' . $name)) {
            $name = $base . '-' . $i++ . '.' . $ext;
        }

        try {
            $upload->move($absDir, $name);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => ['code' => 'move', 'message' => 'Upload fehlgeschlagen.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Make the target folder publicly accessible so the file resolves under
        // /files/… immediately — both for the picker thumbnail and the frontend
        // <img>. Without this, files in a non-public folder return 404.
        $this->ensurePublic($absDir);

        // Path relative to the project dir (always files/…).
        $path = str_replace('\\', '/', substr($absDir . '/' . $name, \strlen($this->projectDir) + 1));

        // Register in the DBAFS so a UUID exists for path↔uuid conversion.
        /** @var Dbafs $dbafs */
        $dbafs = $this->framework->getAdapter(Dbafs::class);
        $dbafs->addResource($path);

        return new JsonResponse(['path' => $path, 'kind' => $extKind[$ext]]);
    }

    /**
     * Create a subfolder under a (validated) parent folder.
     */
    public function folder(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();
            $this->guard->assertCanEditContent();
            $this->guard->assertCsrf($request);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $e->getMessage()]], Response::HTTP_FORBIDDEN);
        }

        // The editor sends a JSON body.
        $data = json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            $data = [];
        }

        $parent = $this->safeFolder($data['parent'] ?? null);
        if ($parent === null) {
            return new JsonResponse(['error' => ['code' => 'bad_parent', 'message' => 'Ungültiger Ordner.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($data['name'] ?? '')) ?? '';
        $name = trim($name, '-');
        if ($name === '') {
            return new JsonResponse(['error' => ['code' => 'bad_name', 'message' => 'Ungültiger Name.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $name = substr($name, 0, 120);

        $abs = $parent . '/' . $name;
        if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) {
            return new JsonResponse(['error' => ['code' => 'mkdir', 'message' => 'Ordner nicht erstellbar.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $this->ensurePublic($abs);

        $path = str_replace('\\', '/', substr($abs, \strlen($this->projectDir) + 1));

        /** @var Dbafs $dbafs */
        $dbafs = $this->framework->getAdapter(Dbafs::class);
        $dbafs->addResource($path);

        return new JsonResponse(['path' => $path]);
    }
}
