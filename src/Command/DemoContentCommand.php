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

namespace Vtinnovations\Draggo\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Schicht 3 (visueller Smoke-Test): seedet einen Demo-Artikel mit JEDEM
 * Draggo-Element + einer Grid-Reihe + Ausrichtungs-Testfällen, damit die ganze
 * Element-Palette auf einer Seite bei Desktop/Tablet/Mobil geprüft werden kann.
 *
 * Idempotent: leert den Demo-Artikel vorher. Bilder kommen aus files/demo*.png
 * (per contao:filesync angelegt), sonst bleiben bild-abhängige Elemente leer.
 *
 *   vendor/bin/contao-console contao:draggo:demo [pageId]
 */
#[AsCommand(name: 'contao:draggo:demo', description: 'Seedet einen Demo-Artikel mit allen Draggo-Elementen (Smoke-Test).')]
final class DemoContentCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('page', InputArgument::OPTIONAL, 'Ziel-Seiten-ID (regular). Standard: erste veröffentlichte regular-Seite.');
        $this->addOption('keep', null, InputOption::VALUE_NONE, 'Bestehende Inhalte des Demo-Artikels NICHT löschen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();
        $t = time();

        // 1. Ziel-Seite.
        $pageId = (int) ($input->getArgument('page') ?? 0);
        if ($pageId <= 0) {
            $pageId = (int) $this->db->fetchOne("SELECT id FROM tl_page WHERE type='regular' AND published='1' ORDER BY sorting LIMIT 1");
        }
        if ($pageId <= 0) {
            $io->error('Keine regular-Seite gefunden. Lege erst eine veröffentlichte Seite an.');

            return Command::FAILURE;
        }

        // 2. Demo-Artikel finden/anlegen.
        $articleId = (int) $this->db->fetchOne("SELECT id FROM tl_article WHERE pid=:p AND title='Draggo Demo' LIMIT 1", ['p' => $pageId]);
        if ($articleId <= 0) {
            $sorting = ((int) $this->db->fetchOne('SELECT MAX(sorting) FROM tl_article WHERE pid=:p', ['p' => $pageId])) + 128;
            $this->db->insert('tl_article', [
                'pid' => $pageId, 'sorting' => $sorting, 'tstamp' => $t,
                'title' => 'Draggo Demo', 'alias' => 'draggo-demo',
                'author' => (int) ($this->db->fetchOne('SELECT id FROM tl_user ORDER BY id LIMIT 1') ?: 1),
                'inColumn' => 'main', 'published' => '1',
            ]);
            $articleId = (int) $this->db->lastInsertId();
        }

        if (!$input->getOption('keep')) {
            $this->db->executeStatement("DELETE FROM tl_content WHERE pid=:a AND ptable='tl_article'", ['a' => $articleId]);
        }

        // 3. Bild-UUIDs (binär) für bild-abhängige Elemente.
        $imgs = $this->db->fetchFirstColumn("SELECT uuid FROM tl_files WHERE path LIKE 'files/demo%.png' ORDER BY path");
        $img = static fn (int $i): ?string => $imgs[$i] ?? ($imgs[0] ?? null);

        $sorting = 0;
        $self = $this;
        // Insert helper: serialise array fields, set the common managed flags.
        // Structural draggo_ columns that stay real columns; every other draggo_
        // field is virtual and lives in the consolidated draggo_props JSON.
        $keep = ['draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container', 'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile', 'draggo_props'];
        $add = function (string $type, array $fields = [], array $layout = []) use (&$sorting, $articleId, $t, $self, $keep): int {
            $sorting += 128;
            $row = [
                'pid' => $articleId, 'ptable' => 'tl_article', 'sorting' => $sorting, 'tstamp' => $t,
                'type' => $type, 'draggo_managed' => '1',
            ];
            $props = [];
            foreach ($fields as $k => $v) {
                $val = \is_array($v) ? serialize($v) : $v;
                // File UUIDs are binary → store the STRING UUID in JSON props
                // (binary would break json_encode); findByUuid accepts both.
                if (\is_string($val) && !mb_check_encoding($val, 'UTF-8')) {
                    $val = \Contao\StringUtil::binToUuid($val);
                }
                if (str_starts_with($k, 'draggo_') && !\in_array($k, $keep, true)) {
                    $props[$k] = $val; // content field → props JSON
                } else {
                    $row[$k] = $val;   // core column (headline) or structural draggo_
                }
            }
            if ($props !== []) {
                $row['draggo_props'] = (string) json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($layout !== []) {
                $row['draggo_layout'] = (string) json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $self->db->insert('tl_content', $row);
            $id = (int) $self->db->lastInsertId();
            // Per-element scope class lives in cssID[1] (normally set by the
            // synchronizer). Without it, .draggo-el-{id} styling never applies.
            $self->db->update('tl_content', ['cssID' => serialize(['', 'draggo-el-' . $id])], ['id' => $id]);

            return $id;
        };
        // Label (native headline) before each element so screenshots are readable.
        $label = function (string $text) use ($add): void {
            $add('headline', ['headline' => serialize(['unit' => 'h4', 'value' => '▸ ' . $text])]);
        };
        // Pretty element name: real CTE label (minus " (Draggo)") else Title-cased key.
        \Contao\System::loadLanguageFile('tl_content', 'de');
        $pretty = static function (string $type): string {
            $l = $GLOBALS['TL_LANG']['CTE'][$type] ?? null;
            $name = \is_array($l) ? (string) ($l[0] ?? '') : (string) ($l ?? '');
            if ($name !== '') {
                return trim((string) preg_replace('/\s*\(Draggo\)\s*$/i', '', $name));
            }

            return ucwords(str_replace('_', ' ', (string) preg_replace('/^draggo_/', '', $type)));
        };

        // ── Grid-Reihe (3 Spalten, responsive 6-6 / 1) + Element-Ausrichtung ──
        $label('GRID: 3 Spalten (Tablet 6-6, Mobil 1) + Element-Ausrichtung');
        $add('draggo_row_start', [
            'draggo_grid_preset' => '4-4-4', 'draggo_container' => 'container',
            'draggo_grid_tablet' => '6-6', 'draggo_grid_mobile' => '1',
        ], ['row' => ['bg' => '#f4f6fb', 'paddingBox' => ['top' => 24, 'right' => 24, 'bottom' => 24, 'left' => 24, 'unit' => 'px']], 'col' => []]);
        $add('draggo_counter', ['draggo_counter_end' => 1200, 'draggo_counter_suffix' => '+'], ['align' => 'center']);
        $add('draggo_col');
        $add('draggo_button', ['draggo_btn_text' => 'Zentriert', 'draggo_btn_url' => '#'], ['align' => 'center']);
        $add('draggo_col');
        $add('draggo_iconbox', ['draggo_ib_icon' => 'heart', 'draggo_ib_title' => 'Box', 'draggo_ib_text' => 'Icon-Box im Grid.', 'draggo_ib_pos' => 'top']);
        $add('draggo_row_stop');

        // ── Alle Elemente einzeln (Feld-Map des Audits) ──
        $DEMO = $this->demoMap($img);
        foreach ($DEMO as $type => $fields) {
            $label($pretty($type));
            $add($type, $fields);
        }

        $io->success(sprintf('Demo-Artikel #%d auf Seite #%d befüllt (%d Sortier-Schritte). Cache leeren + Seite öffnen.', $articleId, $pageId, $sorting / 128));
        $io->writeln('Danach: <info>ddev exec php vendor/bin/contao-console cache:clear</info>');

        return Command::SUCCESS;
    }

    /**
     * Minimal-Demodaten je Elementtyp (aus der Controller-Feld-Map). Array-Werte
     * werden vom Insert-Helper serialisiert.
     *
     * @param callable(int):?string $img  liefert Binär-UUID i (oder null)
     * @return array<string,array<string,mixed>>
     */
    private function demoMap(callable $img): array
    {
        $map = [
            'draggo_counter'   => ['draggo_counter_end' => 980, 'draggo_counter_suffix' => '%'],
            'draggo_progress'  => ['draggo_progress_value' => 75, 'draggo_progress_label' => 'Design', 'draggo_progress_show' => '1'],
            'draggo_iconlist'  => ['draggo_il_items' => ['Schnelle Lieferung', 'Kostenlose Beratung', '24/7 Support'], 'draggo_il_icon' => 'check'],
            'draggo_divider'   => ['draggo_div_style' => 'solid', 'draggo_div_thickness' => 2, 'draggo_div_width' => 100, 'draggo_div_color' => '#cccccc', 'draggo_div_align' => 'center', 'draggo_div_text' => 'oder'],
            'draggo_spacer'    => ['draggo_space' => 48],
            'draggo_icon'      => ['draggo_icon' => 'star'],
            'draggo_button'    => ['draggo_btn_text' => 'Jetzt kaufen', 'draggo_btn_url' => '#', 'draggo_btn_icon' => 'arrow-right', 'draggo_btn_icon_pos' => 'after'],
            'draggo_iconbox'   => ['draggo_ib_icon' => 'heart', 'draggo_ib_title' => 'Mit Liebe gemacht', 'draggo_ib_text' => 'Jedes Detail geprüft.', 'draggo_ib_pos' => 'top'],
            'draggo_quote'     => ['draggo_quote_text' => 'Qualität ist kein Zufall.', 'draggo_quote_author' => 'Max Mustermann'],
            'draggo_cta'       => ['draggo_cta_title' => 'Bereit loszulegen?', 'draggo_cta_text' => 'Starten Sie noch heute.', 'draggo_cta_btn' => 'Kontakt', 'draggo_cta_url' => '#'],
            'draggo_flipbox'   => ['draggo_flip_icon' => 'star', 'draggo_flip_ftitle' => 'Vorderseite', 'draggo_flip_ftext' => 'Maus drüber.', 'draggo_flip_btitle' => 'Rückseite', 'draggo_flip_btext' => 'Details.', 'draggo_flip_height' => 300],
            'draggo_alert'     => ['draggo_alert_type' => 'success', 'draggo_alert_title' => 'Erfolg!', 'draggo_alert_text' => 'Gespeichert.', 'draggo_alert_dismiss' => '1'],
            'draggo_accordion' => ['draggo_items' => [['key' => 'Was ist Draggo?', 'value' => '<p>Ein Seiten-Builder für Contao.</p>'], ['key' => 'Installation?', 'value' => '<p>Per Composer.</p>']], 'draggo_acc_multi' => ''],
            'draggo_tabs'      => ['draggo_items' => [['key' => 'Tab 1', 'value' => '<p>Inhalt 1.</p>'], ['key' => 'Tab 2', 'value' => '<p>Inhalt 2.</p>']]],
            'draggo_social'    => ['draggo_social_items' => [['key' => 'facebook', 'value' => 'https://facebook.com/x'], ['key' => 'instagram', 'value' => 'https://instagram.com/x'], ['key' => 'linkedin', 'value' => 'https://linkedin.com/in/x'], ['key' => 'youtube', 'value' => 'https://youtube.com/@x']]],
            'draggo_countdown' => ['draggo_cd_date' => time() + 86400 * 30, 'draggo_cd_expired' => 'Aktion beendet'],
            'draggo_share'     => ['draggo_sh_facebook' => '1', 'draggo_sh_linkedin' => '1', 'draggo_sh_email' => '1', 'draggo_sh_copy' => '1'],
            'draggo_pricelist' => ['draggo_prl_items' => [['key' => 'Espresso', 'value' => '2,50 €'], ['key' => 'Cappuccino', 'value' => '3,20 €'], ['key' => 'Latte', 'value' => '3,50 €']]],
            'draggo_steps'     => ['draggo_stp_items' => [['key' => 'Registrieren', 'value' => 'Konto anlegen.'], ['key' => 'Einrichten', 'value' => 'Projekt konfigurieren.'], ['key' => 'Loslegen', 'value' => 'Seite bauen.']]],
            'draggo_pricetable' => ['draggo_prt_title' => 'Pro', 'draggo_prt_price' => '29 €', 'draggo_prt_period' => '/Monat', 'draggo_prt_featured' => '1', 'draggo_prt_features' => ['Unbegrenzte Projekte', 'Premium-Support', 'Alle Vorlagen'], 'draggo_prt_btn' => 'Buchen', 'draggo_prt_url' => '#'],
            'draggo_anim'      => ['draggo_an_words' => ['kreativ', 'schnell', 'modern'], 'draggo_an_tag' => 'h2', 'draggo_an_effect' => 'rotate', 'draggo_an_before' => 'Wir sind', 'draggo_an_after' => 'für Sie da.'],
            'draggo_videoplaylist' => ['draggo_vp_items' => [['key' => 'Intro', 'value' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], ['key' => 'Tutorial', 'value' => 'https://vimeo.com/76979871']], 'draggo_vp_ratio' => '16:9'],
            'draggo_codeblock' => ['draggo_code' => "echo 'Hallo Welt';", 'draggo_code_lang' => 'php', 'draggo_code_copy' => '1'],
            'draggo_toc'       => ['draggo_toc_levels' => '2-3', 'draggo_toc_title' => 'Inhalt'],
            'draggo_map'       => ['draggo_map_query' => 'Brandenburger Tor, Berlin', 'draggo_map_zoom' => 14, 'draggo_map_height' => 360, 'draggo_map_consent' => '1'],
            'draggo_search'    => ['draggo_se_ph' => 'Suchen …', 'draggo_se_btn' => 'Los'],
            'draggo_pagetitle' => ['draggo_pt_tag' => 'h1', 'draggo_pt_source' => 'title'],
            'draggo_breadcrumb' => ['draggo_bc_sep' => '›'],
            'draggo_sitemap'   => ['draggo_sm_levels' => 3],
            'draggo_nav'       => ['draggo_nav_levels' => 2, 'draggo_nav_preset' => 'horizontal'],
        ];

        // Bild-abhängige Elemente nur, wenn Demo-Bilder vorhanden sind.
        if ($img(0) !== null) {
            $map['draggo_imagebox'] = ['draggo_ib_src' => $img(0), 'draggo_ib_title' => 'Unser Studio', 'draggo_ib_text' => 'Mitten in der Stadt.', 'draggo_ib_pos' => 'top'];
            $map['draggo_logo'] = ['draggo_logo_src' => $img(0), 'draggo_logo_link' => 'home'];
            $map['draggo_carousel'] = ['draggo_car_src' => array_values(array_filter([$img(0), $img(1), $img(2)])), 'draggo_car_arrows' => '1', 'draggo_car_dots' => '1', 'draggo_car_per' => 1];
            $map['draggo_gallery'] = ['draggo_ga_images' => array_values(array_filter([$img(0), $img(1), $img(2)])), 'draggo_ga_cols' => 3, 'draggo_ga_gap' => 8, 'draggo_ga_ratio' => '1:1'];
            $map['draggo_hotspot'] = ['draggo_hs_img' => $img(0), 'draggo_hs_points' => [['key' => '40,40', 'value' => 'Punkt A'], ['key' => '70,60', 'value' => 'Punkt B']]];
        }

        return $map;
    }
}
