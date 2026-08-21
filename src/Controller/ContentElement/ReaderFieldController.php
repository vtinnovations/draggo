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
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FilesModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Reader\ReaderContext;

/**
 * Reader Field (Tier 4, Single/Theme-Builder): outputs ONE field of the current
 * news/event reader item (title / teaser / image / date / author). Lets users
 * design news + event detail layouts visually. Shows a placeholder in the editor.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_readerfield')]
class ReaderFieldController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ReaderContext $reader,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $field = \in_array($model->draggo_rf_field ?? '', ['title', 'teaser', 'image', 'date', 'author'], true) ? $model->draggo_rf_field : 'title';
        $source = \in_array($model->draggo_rf_source ?? '', ['auto', 'news', 'event'], true) ? $model->draggo_rf_source : 'auto';
        $tag = \in_array($model->draggo_rf_tag ?? '', ['h1', 'h2', 'h3', 'div'], true) ? $model->draggo_rf_tag : 'h1';

        [$idAttr, $cls] = $this->draggoScope($model);
        $wrapOpen = '<div class="' . trim('draggo-rf draggo-rf--' . $field . ' ' . $cls) . '"' . $idAttr . '>';
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $item = $this->reader->current($source);
        if ($item === null) {
            // Editor / non-reader page: show a labelled placeholder.
            $labels = ['title' => 'Titel', 'teaser' => 'Teaser', 'image' => 'Aufmacherbild', 'date' => 'Datum', 'author' => 'Autor'];
            return new Response($wrapOpen . '<span class="draggo-rf-ph">[' . ($labels[$field] ?? $field) . ']</span></div>');
        }

        [$type, $obj] = $item;
        $html = $this->field($field, $type, $obj, $tag, $e);
        if ($html === '') {
            return new Response('');
        }

        return new Response($wrapOpen . $html . '</div>');
    }

    /** @param callable(string):string $e */
    private function field(string $field, string $type, object $obj, string $tag, callable $e): string
    {
        switch ($field) {
            case 'title':
                $t = $type === 'news' ? (string) ($obj->headline ?? '') : (string) ($obj->title ?? '');
                return $t !== '' ? '<' . $tag . ' class="draggo-rf-title">' . $e($t) . '</' . $tag . '>' : '';

            case 'teaser':
                $teaser = (string) ($obj->teaser ?? '');
                return $teaser !== '' ? '<div class="draggo-rf-teaser">' . $teaser . '</div>' : '';

            case 'image':
                if (empty($obj->addImage) || empty($obj->singleSRC)) {
                    return '';
                }
                $this->framework->initialize();
                /** @var FilesModel $files */
                $files = $this->framework->getAdapter(FilesModel::class);
                $f = $files->findByUuid($obj->singleSRC);
                // Use the item's headline/title as alt (a11y) instead of empty.
                $imgAlt = $e(trim((string) ($obj->alt ?? $obj->headline ?? $obj->title ?? '')));
                return $f !== null ? '<img class="draggo-rf-img" src="/' . $e((string) $f->path) . '" alt="' . $imgAlt . '">' : '';

            case 'date':
                $ts = $type === 'news' ? (int) ($obj->date ?? 0) : (int) ($obj->startDate ?? $obj->startTime ?? 0);
                return $ts > 0 ? '<time class="draggo-rf-date">' . $e(date('d.m.Y', $ts)) . '</time>' : '';

            case 'author':
                $name = $this->reader->authorName($obj->author ?? 0);
                return $name !== '' ? '<span class="draggo-rf-author">' . $e($name) . '</span>' : '';
        }

        return '';
    }
}
