<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_legend']        = 'AI (Claude)';
$GLOBALS['TL_LANG']['tl_draggo_settings']['image_legend']     = 'Images (landing-page builder)';
$GLOBALS['TL_LANG']['tl_draggo_settings']['telemetry_legend'] = 'Telemetry (optional)';

$GLOBALS['TL_LANG']['tl_draggo_settings']['img_provider']      = ['Image source', 'Where the landing-page builder gets images. Claude CANNOT create images — use OpenAI for that. Empty = no auto-images (media picker).'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_provider_opts'] = ['' => 'Off (no auto-images)', 'unsplash' => 'Unsplash (stock photos)', 'pexels' => 'Pexels (stock photos)', 'openai' => 'OpenAI (AI image generation)'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_unsplash_key']  = ['Unsplash access key', 'Free at unsplash.com/developers. Your own key = your own quota.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_pexels_key']    = ['Pexels API key', 'Free at pexels.com/api.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_openai_key']    = ['OpenAI API key', 'For AI image generation (costs per image). platform.openai.com.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['img_openai_model']  = ['OpenAI image model', 'Default: gpt-image-1. Leave empty for the default.'];

$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_api_key']       = ['Claude API key', 'Your Anthropic/Claude API key. Stored securely in the database.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_model']         = ['Model', 'Default: claude-opus-4-8. Leave empty for the default.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_max_rounds']    = ['Max. follow-up questions', 'How many question rounds the agent asks at most (default 8).'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_no_self_review'] = ['Disable self-review', 'By default the AI automatically checks & improves every draft (1 extra call). Turn off here to save cost.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_telemetry']     = ['Enable telemetry', 'Anonymously sends ONLY the structure of created elements (no content) to the central URL.'];
$GLOBALS['TL_LANG']['tl_draggo_settings']['ai_telemetry_url'] = ['Telemetry URL', 'Target endpoint for the anonymous structure report.'];
