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

/*
 * Adds the container (article) layout column. Rendered as scoped CSS by
 * ContainerLayoutListener via the draggo-art-{id} class on the article wrapper.
 */

$GLOBALS['TL_DCA']['tl_article']['fields']['draggo_layout'] = [
    'sql' => 'blob NULL',
];
