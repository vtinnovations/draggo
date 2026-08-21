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
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Form\FormBuilder;

/**
 * Draggo form element: renders a native Contao form (built visually in the Draggo
 * form builder) via Contao's FormGenerator — so submission, validation, e-mail
 * notification, spam protection and storage all remain Contao's, fully intact.
 *
 * Contao's Controller::getForm() needs a frontend page/request context, so it
 * returns nothing when the element is rendered for the EDITOR canvas (a backend
 * request) — leaving the chip blank. There we render a STATIC field preview
 * instead, using the same .formbody > .widget markup (incl. the per-field
 * draggo-fw-* width classes) so the canvas shows the real layout. The live site
 * always gets the real, working form.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_form')]
class FormElementController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly FormBuilder $builder,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $formId = (int) ($model->draggo_form_id ?? 0);
        [$idAttr, $cls] = $this->draggoScope($model);

        if ($formId <= 0) {
            // Unbound: invisible on the live site, a hint only in the editor canvas.
            return new Response('<div class="' . trim('draggo-form draggo-form--empty ' . $cls) . '"' . $idAttr . '><p class="draggo-form-hint">Noch kein Formular gewählt — im Editor anlegen oder verknüpfen.</p></div>');
        }

        // No frontend page in scope = editor canvas / backend. Contao's getForm()
        // there yields a backend wildcard ("### … ###"), not the real form, so
        // render a static field preview instead (same convention as the other
        // page-context Draggo elements, e.g. PageTitle/Sitemap).
        $objPage = $GLOBALS['objPage'] ?? null;
        if (!\is_object($objPage)) {
            return new Response($this->staticPreview($formId, $idAttr, $cls));
        }

        $this->framework->initialize();
        /** @var Controller $controller */
        $controller = $this->framework->getAdapter(Controller::class);
        $html = trim((string) $controller->getForm($formId));
        if ($html === '') {
            return new Response('');
        }

        return new Response('<div class="' . trim('draggo-form ' . $cls) . '"' . $idAttr . '>' . $html . '</div>');
    }

    /**
     * A non-interactive preview of the form's fields for the editor canvas.
     * Mirrors Contao's FE markup (.formbody > .widget.widget-{type}) and keeps
     * each field's `class` (which carries the draggo-fw-* width tokens) so the
     * editor's .draggo-form CSS lays the fields out exactly like the live form.
     */
    private function staticPreview(int $formId, string $idAttr, string $cls): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fields = $this->builder->fields($formId);

        if ($fields === []) {
            return '<div class="' . trim('draggo-form draggo-form--empty ' . $cls) . '"' . $idAttr . '><p class="draggo-form-hint">Formular ohne Felder — im Editor Felder hinzufügen.</p></div>';
        }

        $rows = '';
        foreach ($fields as $f) {
            $kind = (string) $f['kind'];
            $type = (string) $f['type'];
            $label = $e((string) $f['label']);
            $req = !empty($f['mandatory']) ? ' <span class="mandatory">*</span>' : '';
            $ph = $e((string) ($f['placeholder'] ?? ''));
            $wCls = $this->widthClasses(\is_array($f['width'] ?? null) ? $f['width'] : []);
            $widget = 'widget widget-' . $e($type) . ($wCls !== '' ? ' ' . $wCls : '');

            $body = match ($kind) {
                'textarea' => '<label>' . $label . $req . '</label><textarea rows="3" disabled></textarea>',
                'select' => '<label>' . $label . $req . '</label><select disabled>' . $this->optionsHtml($f, $e) . '</select>',
                'radio', 'checkbox' => '<fieldset class="' . ($kind === 'radio' ? 'radio_container' : 'checkbox_container') . '"><legend>' . $label . $req . '</legend>' . $this->choicesHtml($f, $kind, $e) . '</fieldset>',
                'upload' => '<label>' . $label . $req . '</label><input type="file" disabled>',
                'hidden' => '<label class="draggo-form-prev-hidden">' . ($label !== '' ? $label : 'Verstecktes Feld') . '</label>',
                'explanation', 'html' => '<div class="explanation">' . nl2br($e((string) ($f['text'] ?? ''))) . '</div>',
                'captcha' => '<label>' . ($label !== '' ? $label : 'Sicherheitsfrage') . $req . '</label><input type="text" disabled placeholder="🛡 Spamschutz">',
                // type=submit (not button) so it matches `.draggo-form button[type=submit]`
                // styling AND the per-element formBtn* rules — `disabled` + the canvas
                // pointer-events:none rule keep the preview inert.
                'submit' => '<button type="submit" disabled>' . ($e((string) ($f['slabel'] ?? '')) ?: 'Absenden') . '</button>',
                default => '<label>' . $label . $req . '</label><input type="' . ($kind === 'email' ? 'email' : ($kind === 'number' ? 'number' : ($kind === 'phone' ? 'tel' : 'text'))) . '" disabled' . ($ph !== '' ? ' placeholder="' . $ph . '"' : '') . '>',
            };

            $rows .= '<div class="' . $widget . '">' . $body . '</div>';
        }

        return '<div class="' . trim('draggo-form draggo-form--preview ' . $cls) . '"' . $idAttr . '><form><div class="formbody">' . $rows . '</div></form></div>';
    }

    /** Rebuild the draggo-fw-* token string from a {d,t,m} width map. */
    private function widthClasses(array $w): string
    {
        $out = [];
        foreach (['d' => 'draggo-fw-', 't' => 'draggo-fw-t-', 'm' => 'draggo-fw-m-'] as $k => $prefix) {
            $v = (string) ($w[$k] ?? '');
            if (\in_array($v, ['100', '75', '66', '50', '33', '25'], true)) {
                $out[] = $prefix . $v;
            }
        }

        return implode(' ', $out);
    }

    /** @param array<string,mixed> $f */
    private function optionsHtml(array $f, callable $e): string
    {
        $opts = \is_array($f['options'] ?? null) ? $f['options'] : [];
        if ($opts === []) {
            return '<option>—</option>';
        }
        $h = '';
        foreach ($opts as $o) {
            $h .= '<option>' . $e((string) ($o['label'] ?? '')) . '</option>';
        }

        return $h;
    }

    /** @param array<string,mixed> $f */
    private function choicesHtml(array $f, string $kind, callable $e): string
    {
        $opts = \is_array($f['options'] ?? null) ? $f['options'] : [];
        if ($opts === []) {
            $opts = [['label' => '—']];
        }
        $h = '';
        foreach ($opts as $o) {
            $h .= '<span><input type="' . $kind . '" disabled> ' . $e((string) ($o['label'] ?? '')) . '</span>';
        }

        return $h;
    }
}
