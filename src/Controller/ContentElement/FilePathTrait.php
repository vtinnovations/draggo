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

namespace Vtinnovations\Draggo\Controller\ContentElement;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;

/**
 * Resolve a stored file reference to a root-relative URL. Editor/props store file
 * UUIDs as STRING UUIDs (JSON-safe); FilesModel::findByUuid() accepts both string
 * and binary, so this works for either. Returns '' when unresolvable.
 */
trait FilePathTrait
{
    protected function fileUrl(ContaoFramework $framework, mixed $uuid): string
    {
        if (empty($uuid)) {
            return '';
        }
        $framework->initialize();
        /** @var FilesModel $adapter */
        $adapter = $framework->getAdapter(FilesModel::class);
        $file = $adapter->findByUuid($uuid);
        $path = $file !== null ? (string) $file->path : '';

        return $path !== '' ? '/' . ltrim($path, '/') : '';
    }

    /**
     * Resolve a serialized list of file UUIDs to root-relative URLs (in order).
     *
     * @return list<string>
     */
    protected function fileUrls(ContaoFramework $framework, mixed $raw): array
    {
        $uuids = \Contao\StringUtil::deserialize($raw, true);
        $out = [];
        foreach ($uuids as $uuid) {
            $url = $this->fileUrl($framework, $uuid);
            if ($url !== '') {
                $out[] = $url;
            }
        }

        return $out;
    }
}
