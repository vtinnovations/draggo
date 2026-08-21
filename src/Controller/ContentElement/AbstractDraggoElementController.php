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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Base for every Draggo content-element controller. Element content used to live
 * in ~145 individual tl_content columns; it now lives consolidated in a single
 * JSON column (draggo_props). This base hydrates that JSON back onto the model
 * BEFORE the concrete controller runs, so every `$model->draggo_*` read keeps
 * working unchanged — the only edit each controller needs is `extends` this.
 *
 * Hydration never overwrites a real (kept) column that already carries a value,
 * so structural fields (grid, layout, blocktype) are untouched, and the code
 * also works during the transition while old columns may still exist.
 */
abstract class AbstractDraggoElementController extends AbstractContentElementController
{
    private ?EditionResolver $draggoEdition = null;

    /**
     * Setter injection (not constructor) so the entitlement check applies to
     * every element controller without touching the constructor of all ~60
     * subclasses.
     */
    #[Required]
    public function setDraggoEdition(EditionResolver $resolver): void
    {
        $this->draggoEdition = $resolver;
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array|null $classes = null): Response
    {
        // Entitlement gate, deliberately silent: an empty response, never a
        // public error text, because a licence problem is between the operator
        // and us and must not be shown to site visitors.
        //
        // Fails CLOSED. If the resolver was never injected the element renders
        // nothing, so removing the service definition disables Draggo rather
        // than unlocking it.
        if (null === $this->draggoEdition || !$this->draggoEdition->profile()->allowsElement((string) $model->type)) {
            return new Response('');
        }

        $this->hydrateProps($model);

        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * Decode draggo_props JSON and set each key on the model so reads like
     * `$model->draggo_an_before` resolve to the consolidated value. Keys whose
     * model property already holds a non-empty value are left as-is.
     */
    protected function hydrateProps(ContentModel $model): void
    {
        $raw = $model->draggo_props ?? null;
        if (empty($raw)) {
            return;
        }
        $props = json_decode((string) $raw, true);
        if (!\is_array($props)) {
            return;
        }
        // props holds only consolidated content fields (never structural/kept
        // columns), so it is authoritative for them and overrides any legacy
        // column value still present during the transition before the drop.
        foreach ($props as $key => $value) {
            if (\is_string($key) && $key !== '') {
                $model->{$key} = $value;
            }
        }
    }
}
