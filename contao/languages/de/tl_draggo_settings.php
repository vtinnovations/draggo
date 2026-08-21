<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_legend']        = 'KI (Claude)';
$GLOBALS['TL_LANG']['tl_draggo_settings']['image_legend']     = 'Bilder (Landingpage-Builder)';
$GLOBALS['TL_LANG']['tl_draggo_settings']['telemetry_legend'] = 'Telemetrie (optional)';

$GLOBALS['TL_LANG']['tl_draggo_settings']['img_provider']      = ['Bildquelle', 'Woher der Landingpage-Builder Bilder holt. Claude kann KEINE Bilder erzeugen – dafür ist OpenAI da. Leer = keine Auto-Bilder (Mediathek-Picker).'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_provider_opts'] = ['' => 'Aus (keine Auto-Bilder)', 'unsplash' => 'Unsplash (Stockfotos)', 'pexels' => 'Pexels (Stockfotos)', 'openai' => 'OpenAI (KI-Bildgenerierung)'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_unsplash_key']  = ['Unsplash Access-Key', 'Gratis unter unsplash.com/developers. Eigener Key = eigenes Limit.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_pexels_key']    = ['Pexels API-Key', 'Gratis unter pexels.com/api.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_openai_key']    = ['OpenAI API-Key', 'Für KI-Bildgenerierung (kostet pro Bild). platform.openai.com.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_openai_model']  = ['OpenAI Bild-Modell', 'Standard: gpt-image-1. Leer lassen für Standard.'];

$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_api_key']       = ['Claude API-Schlüssel', 'Dein Anthropic/Claude API-Key. Wird sicher in der Datenbank gespeichert.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_model']         = ['Modell', 'Standard: claude-opus-4-8. Leer lassen für Standard.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_max_rounds']    = ['Max. Rückfragen', 'Wie viele Frage-Runden der Agent maximal stellt (Standard 8).'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_no_self_review'] = ['Selbst-Review deaktivieren', 'Standardmäßig prüft & verbessert die KI jeden Entwurf automatisch (1 Extra-Aufruf). Hier abschaltbar, um Kosten zu sparen.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_telemetry']     = ['Telemetrie aktivieren', 'Sendet anonym NUR die Struktur erstellter Elemente (keine Inhalte) an die zentrale URL.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_telemetry_url'] = ['Telemetrie-URL', 'Ziel-Endpoint für die anonyme Struktur-Meldung.'];
