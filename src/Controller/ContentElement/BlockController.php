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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Block\BlockTypeStore;
use Vtinnovations\Draggo\Block\CssScoper;
use Vtinnovations\Draggo\Block\TwigSandbox;
use Twig\Markup;

/**
 * The ONE controller that renders every AI-generated block type. Loads the
 * BlockSpec for the instance's draggo_blocktype, decodes its field values from
 * draggo_data, and renders the spec's template in the Twig sandbox. No
 * generated PHP is ever executed → no RCE. CSS is scoped to the element.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_block')]
class BlockController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly BlockTypeStore $store,
        private readonly TwigSandbox $sandbox,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();

        $typeKey = (string) ($model->draggo_blocktype ?? '');
        $spec = $typeKey !== '' ? $this->store->find($typeKey) : null;
        if ($spec === null) {
            return new Response('');
        }

        $data = json_decode((string) ($model->draggo_data ?? ''), true);
        $data = \is_array($data) ? $data : [];

        // Build the template's `fields` map, typed per spec.
        $fields = [];
        foreach ($spec->fields as $f) {
            $name = $f['name'];
            $val = $data[$name] ?? '';
            $fields[$name] = match ($f['type']) {
                'bool'            => (bool) $val,
                'number', 'range' => is_numeric($val) ? $val + 0 : 0,
                'image'           => $this->imageUrl((string) $val),
                'images'          => array_values(array_filter(array_map(fn ($p): string => $this->imageUrl((string) $p), \is_array($val) ? $val : []))),
                'list'            => array_values(array_map('strval', \is_array($val) ? $val : [])),
                // Rich text: render HTML safely (no escaping) — value is authored
                // by trusted backend users, like Contao's HTML element.
                'richtext'        => new Markup((string) $val, 'UTF-8'),
                // Icon field: resolve the stored value to icon markup so the
                // template just outputs {{ fields.NAME }} (no hardcoding).
                'icon'            => new Markup($this->iconMarkup((string) $val), 'UTF-8'),
                // Repeatable structured items → list of {key,value}; html/icon
                // members wrapped as Markup so templates render them directly.
                'pairs'           => $this->pairItems($val, false, false),
                'accordion'       => $this->pairItems($val, false, true),
                'iconlinks'       => $this->pairItems($val, true, false),
                default           => (string) $val,
            };
        }

        try {
            $html = $this->sandbox->render($spec->template, $fields);
        } catch (\Throwable) {
            return new Response(''); // never break the page on a bad template
        }

        [$idAttr, $cls] = $this->draggoScope($model);
        $scope = '.draggo-el-' . (int) $model->id;
        $style = $spec->css !== '' ? '<style>' . CssScoper::scope($spec->css, $scope) . '</style>' : '';

        // Optional element-scoped JS: runs with `el` = the block wrapper. Authored
        // by trusted backend users (same trust level as Contao's HTML element).
        $script = '';
        if ($spec->js !== '') {
            $script = '<script>(function(){var el=document.querySelector(' . json_encode($scope, JSON_UNESCAPED_SLASHES) . ');if(!el)return;try{' . $spec->js . '}catch(e){if(window.console)console.error("Draggo block JS:",e);}})();</script>';
        }

        return new Response($style . '<div class="' . trim('draggo-blk ' . $cls) . '"' . $idAttr . '>' . $html . '</div>' . $script);
    }

    /**
     * Map a repeatable {key,value} field to template items. $keyIcon → key
     * becomes icon Markup; $valHtml → value becomes HTML Markup (else plain).
     *
     * @return list<array{key:mixed,value:mixed}>
     */
    private function pairItems(mixed $val, bool $keyIcon, bool $valHtml): array
    {
        $out = [];
        foreach (\is_array($val) ? $val : [] as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $k = (string) ($row['key'] ?? '');
            $v = (string) ($row['value'] ?? '');
            $out[] = [
                'key'   => $keyIcon ? new Markup($this->iconMarkup($k), 'UTF-8') : $k,
                'value' => $valHtml ? new Markup($v, 'UTF-8') : $v,
            ];
        }

        return $out;
    }

    /**
     * Resolve an icon field value to markup. Font Awesome class → <i>; anything
     * else (emoji/text) is emitted escaped. SVG-set keys are not resolved here.
     */
    private function iconMarkup(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if (preg_match('/(?:^|\s)fa[bsrl]?-|fa-/', $v)) {
            $v = preg_replace('/[^a-z0-9 _-]/i', '', $v) ?? '';

            return '<i class="draggo-fa ' . $v . '"></i>';
        }

        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Stored block image values are plain files/ paths → root-absolute URL. */
    private function imageUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('#[\'"()\s]#', $path)) {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }
}
