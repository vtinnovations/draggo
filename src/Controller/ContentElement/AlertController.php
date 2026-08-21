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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alert / notice box (Tier 2): four semantic variants + optional title and a
 * dismiss button (closing handled by draggo-frontend.js).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_alert')]
class AlertController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $type = \in_array($model->draggo_alert_type ?? '', ['info', 'success', 'warning', 'error'], true) ? $model->draggo_alert_type : 'info';
        $title = trim((string) ($model->draggo_alert_title ?? ''));
        $text = trim((string) ($model->draggo_alert_text ?? ''));
        if ($title === '' && $text === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dismiss = ($model->draggo_alert_dismiss ?? '') === '1';

        [$idAttr, $cls] = $this->draggoScope($model);

        // Optional type icon (Style tab → "Typ-Icon anzeigen"), read from layout.
        $icon = '';
        $iconCls = '';
        $layout = json_decode((string) ($model->draggo_layout ?? ''), true);
        if (\is_array($layout) && !empty($layout['alertIcon'])) {
            $fa = [
                'info'    => 'fas fa-info-circle',
                'success' => 'fas fa-check-circle',
                'warning' => 'fas fa-exclamation-triangle',
                'error'   => 'fas fa-times-circle',
            ][$type] ?? 'fas fa-info-circle';
            $icon = '<span class="draggo-alert-icon" aria-hidden="true"><i class="draggo-fa ' . $fa . '"></i></span>';
            $iconCls = ' draggo-alert--icon';
        }

        $body = '<div class="draggo-alert-body">'
            . ($title !== '' ? '<p class="draggo-alert-title">' . $e($title) . '</p>' : '')
            . ($text !== '' ? '<div class="draggo-alert-text">' . $this->richText($text) . '</div>' : '')
            . '</div>';
        $close = $dismiss ? '<button type="button" class="draggo-alert-close" aria-label="Schließen">×</button>' : '';

        return new Response(
            '<div class="' . trim('draggo-alert draggo-alert--' . $type . $iconCls . ' ' . $cls) . '"' . $idAttr . ' role="alert">' . $icon . $body . $close . '</div>',
        );
    }
}
