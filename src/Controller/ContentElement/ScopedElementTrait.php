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

use Contao\ContentModel;
use Contao\StringUtil;

/**
 * Shared cssID/scoping helper for Draggo fragment controllers that return RAW
 * HTML (no Contao template). Such controllers bypass Contao's automatic cssID
 * wrapper, so the per-element scope class `.draggo-el-{id}` (injected into
 * cssID by ContentSynchronizer::ensureContentCssClass) never reaches the DOM —
 * and ALL element styling (universal Style tab + per-widget scoped CSS) silently
 * fails to apply. This trait re-attaches the id + classes onto the root node.
 */
trait ScopedElementTrait
{
    /**
     * Returns the ` id="…"` attribute (incl. leading space, or '') and the
     * space-separated extra classes (cssID[1] — contains both user classes and
     * the `draggo-el-{id}` scope class) for the element's root node.
     *
     * @return array{0:string,1:string} [idAttr, classList]
     */
    protected function draggoScope(ContentModel $model): array
    {
        $cssID = StringUtil::deserialize($model->cssID, true);

        $cls = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($cssID[1] ?? '')) ?? '');
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($cssID[0] ?? '')) ?? '';
        $idAttr = $id !== '' ? ' id="' . $id . '"' : '';

        return [$idAttr, $cls];
    }

    /**
     * Render a rich-text body field. Values authored via the editor's WYSIWYG
     * are HTML (authored by trusted backend users — same trust level as Contao's
     * HTML element), emitted raw. Legacy plain-text values (no markup) are still
     * escaped + nl2br'd so old content keeps its line breaks.
     */
    protected function richText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Has real markup → trusted RTE HTML, output as-is.
        if ($value !== strip_tags($value)) {
            return $value;
        }

        return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
