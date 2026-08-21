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

namespace Vtinnovations\Draggo\Dynamic;

/**
 * Curated catalogue of "Dynamic Tags" (Tier 3, the Contao-native equivalent of
 * Elementor's dynamic tags / WordPress shortcodes). Each entry maps a friendly
 * label to a real Contao insert tag. The editor offers these in a picker that
 * drops the raw insert tag into any text field; resolution happens server-side
 * via Controller::replaceInsertTags — no custom rendering, no new attack surface.
 *
 * @phpstan-type Tag array{label:string, insert:string, hint?:string}
 * @phpstan-type Group array{label:string, tags:list<Tag>}
 */
final class DynamicTags
{
    /**
     * @return list<Group>
     */
    public static function catalog(): array
    {
        return [
            [
                'label' => 'Seite',
                'tags'  => [
                    ['label' => 'Seitentitel',        'insert' => '{{page::title}}'],
                    ['label' => 'Seiten-Headline',    'insert' => '{{page::pageTitle}}', 'hint' => 'Navigations-/SEO-Titel.'],
                    ['label' => 'Seitenbeschreibung', 'insert' => '{{page::description}}'],
                    ['label' => 'Seiten-Alias',       'insert' => '{{page::alias}}'],
                    ['label' => 'Seiten-ID',          'insert' => '{{page::id}}'],
                    ['label' => 'Root-Seitentitel',   'insert' => '{{page::rootTitle}}', 'hint' => 'Titel der Startseite.'],
                ],
            ],
            [
                'label' => 'Mitglied',
                'tags'  => [
                    ['label' => 'Vorname',          'insert' => '{{user::firstname}}'],
                    ['label' => 'Nachname',         'insert' => '{{user::lastname}}'],
                    ['label' => 'Benutzername',     'insert' => '{{user::username}}'],
                    ['label' => 'E-Mail',           'insert' => '{{user::email}}'],
                ],
            ],
            [
                'label' => 'Datum & Zeit',
                'tags'  => [
                    ['label' => 'Datum (heute)',  'insert' => '{{date::d.m.Y}}'],
                    ['label' => 'Uhrzeit',        'insert' => '{{date::H:i}}'],
                    ['label' => 'Jahr',           'insert' => '{{date::Y}}'],
                    ['label' => 'Datum & Zeit',   'insert' => '{{date::d.m.Y H:i}}'],
                ],
            ],
            [
                'label' => 'Link / Verweis',
                'tags'  => [
                    ['label' => 'Link zu Seite …',  'insert' => '{{link_url::ID}}',  'hint' => 'ID durch Seiten-ID ersetzen.'],
                    ['label' => 'News-Link …',      'insert' => '{{news_url::ID}}',  'hint' => 'ID durch News-ID ersetzen.'],
                    ['label' => 'E-Mail-Link …',    'insert' => '{{email_url::mail@example.com}}'],
                ],
            ],
            [
                'label' => 'System',
                'tags'  => [
                    ['label' => 'Umgebungs-URL',    'insert' => '{{env::url}}'],
                    ['label' => 'Host',             'insert' => '{{env::host}}'],
                    ['label' => 'Request-URI',      'insert' => '{{env::request}}'],
                ],
            ],
        ];
    }
}
