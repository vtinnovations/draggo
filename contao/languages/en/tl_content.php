<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['CTE']['draggo_nav'] = ['Navigation (Draggo)', 'Page-tree navigation with a design preset (horizontal / side nav / hamburger).'];
$GLOBALS['TL_LANG']['CTE']['draggo_inserttag'] = ['Insert tag (Draggo)', 'Output any Contao insert tag at this position.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_it_legend'] = 'Insert tag';
$GLOBALS['TL_LANG']['tl_content']['draggo_legend'] = 'AI element';
$GLOBALS['TL_LANG']['tl_content']['draggo_blocktype'] = ['Element type', 'AI-generated element type.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_it'] = ['Insert tag', 'e.g. date · link_url::5 · news_url::3 (with or without {{ }}).'];

$GLOBALS['TL_LANG']['CTE']['draggo_icon'] = ['Icon (Draggo)', 'Single SVG icon from the library; size/colour via the Style tab.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_icon_legend'] = 'Icon';
$GLOBALS['TL_LANG']['tl_content']['draggo_icon']       = ['Icon', 'Icon key from the library (pickable in the editor).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_icon_link']  = ['Link', 'Optional URL or insert tag like {{link_url::5}}.'];

$GLOBALS['TL_LANG']['CTE']['draggo_button']  = ['Button (Draggo)', 'Button with a link; styled via the Style tab.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_icon']     = ['Icon', 'Optional icon before/after the text.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_icon_pos'] = ['Icon position', 'Before or after the button text.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_align']    = ['Alignment', 'Left / centre / right (when width < 100%).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_text']     = ['Centre text', 'Optional text between two lines.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ib_icon']      = ['Icon', 'Icon from the library instead of an image (takes precedence over upload).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_fade']     = ['Fade', 'Smooth cross-fade instead of sliding (1 slide).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_pause']    = ['Pause on hover', 'Autoplay pauses while the mouse is over it.'];
$GLOBALS['TL_LANG']['CTE']['draggo_spacer']  = ['Spacer (Draggo)', 'Vertical empty space.'];
$GLOBALS['TL_LANG']['CTE']['draggo_divider'] = ['Divider (Draggo)', 'Horizontal line.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_legend'] = 'Button';
$GLOBALS['TL_LANG']['tl_content']['draggo_spacer_legend'] = 'Spacer';
$GLOBALS['TL_LANG']['tl_content']['draggo_div_legend'] = 'Divider';
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_text']  = ['Button text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_url']   = ['Link', 'URL or insert tag like {{link_url::5}}.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_btn_blank'] = ['New window', 'Open the link in a new tab.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_space']     = ['Height (px)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_style'] = ['Line style', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_thickness'] = ['Thickness (px)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_width'] = ['Width (%)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_div_color'] = ['Colour', 'Hex, e.g. #cccccc.'];

$GLOBALS['TL_LANG']['CTE']['draggo_iconbox']  = ['Icon box (Draggo)', 'Icon + title + text.'];
$GLOBALS['TL_LANG']['CTE']['draggo_imagebox'] = ['Image box (Draggo)', 'Image + title + text.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_box_legend'] = 'Content';
$GLOBALS['TL_LANG']['tl_content']['draggo_ib_src']   = ['Icon / image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ib_title'] = ['Title', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ib_text']  = ['Text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ib_pos']   = ['Media position', 'Top / left / right.'];

$GLOBALS['TL_LANG']['CTE']['draggo_accordion'] = ['Accordion (Draggo)', 'Collapsible title/content entries.'];
$GLOBALS['TL_LANG']['CTE']['draggo_tabs']      = ['Tabs (Draggo)', 'Tabs with title/content.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_items_legend'] = 'Entries';
$GLOBALS['TL_LANG']['tl_content']['draggo_items']    = ['Entries', 'Title (key) + content (value) per row.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_acc_multi'] = ['Multiple open', 'Allow several entries open at once.'];

$GLOBALS['TL_LANG']['CTE']['draggo_carousel'] = ['Image carousel (Draggo)', 'Slider of multiple images.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_legend'] = 'Carousel';
$GLOBALS['TL_LANG']['tl_content']['draggo_car_src']    = ['Images', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_per']    = ['Visible slides', 'How many images at once (1–6).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_arrows'] = ['Arrows', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_dots']   = ['Dots', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_auto']   = ['Autoplay', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_car_speed']  = ['Interval (sec)', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_alert']    = ['Alert box (Draggo)', 'Info/success/warning/error box, optionally dismissible.'];
$GLOBALS['TL_LANG']['CTE']['draggo_quote']    = ['Quote (Draggo)', 'Block quote with optional author.'];
$GLOBALS['TL_LANG']['CTE']['draggo_counter']  = ['Counter (Draggo)', 'Animated number, counts up on scroll.'];
$GLOBALS['TL_LANG']['CTE']['draggo_progress'] = ['Progress bar (Draggo)', 'Skill/progress bar, animated on scroll.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_alert_legend']    = 'Alert box';
$GLOBALS['TL_LANG']['tl_content']['draggo_alert_type']      = ['Type', 'Info, success, warning or error.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_alert_title']     = ['Title', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_alert_text']      = ['Text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_alert_dismiss']   = ['Dismissible', 'Show a close button.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_quote_legend']    = 'Quote';
$GLOBALS['TL_LANG']['tl_content']['draggo_quote_text']      = ['Quote', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_quote_author']    = ['Author / source', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_legend']  = 'Counter';
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_start']   = ['Start value', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_end']     = ['Target value', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_prefix']  = ['Prefix', 'Before the number, e.g. "+".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_suffix']  = ['Suffix', 'After the number, e.g. "%".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_counter_duration'] = ['Duration (ms)', 'Animation duration in milliseconds.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_progress_legend'] = 'Progress';
$GLOBALS['TL_LANG']['tl_content']['draggo_progress_label']  = ['Label', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_progress_value']  = ['Value (0–100)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_progress_show']   = ['Show percent', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_iconlist'] = ['Icon list (Draggo)', 'List with an icon as the bullet.'];
$GLOBALS['TL_LANG']['CTE']['draggo_cta']      = ['Call to action (Draggo)', 'Title + text + button as a call to action.'];
$GLOBALS['TL_LANG']['CTE']['draggo_social']   = ['Social icons (Draggo)', 'Linked icons to social networks.'];
$GLOBALS['TL_LANG']['CTE']['draggo_flipbox']  = ['Flip box (Draggo)', 'Card that flips to its back on hover.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_il_legend']     = 'Icon list';
$GLOBALS['TL_LANG']['tl_content']['draggo_il_icon']       = ['Icon', 'Icon key (picker in the editor).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_il_items']      = ['Items', 'One entry per line.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_legend']    = 'Call to action';
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_title']     = ['Title', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_text']      = ['Text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_btn']       = ['Button text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_url']       = ['Button link', 'URL or insert tag.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_cta_blank']     = ['New window', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_social_legend'] = 'Social icons';
$GLOBALS['TL_LANG']['tl_content']['draggo_social_items']  = ['Networks', 'Per row: pick an icon (Draggo or Font Awesome) + URL.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_legend']   = 'Flip box';
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_icon']     = ['Icon (front)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_ftitle']   = ['Title (front)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_ftext']    = ['Text (front)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_btitle']   = ['Title (back)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_btext']    = ['Text (back)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_btn']      = ['Button text (back)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_url']      = ['Button link (back)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_flip_height']   = ['Height (px)', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_logo']       = ['Logo (Draggo)', 'Image linked to the home page or a custom URL.'];
$GLOBALS['TL_LANG']['CTE']['draggo_pagetitle']  = ['Page title (Draggo)', 'Title of the current page (dynamic).'];
$GLOBALS['TL_LANG']['CTE']['draggo_breadcrumb'] = ['Breadcrumb (Draggo)', 'Path from the home page to the current page.'];
$GLOBALS['TL_LANG']['CTE']['draggo_sitemap']    = ['Sitemap (Draggo)', 'Nested list of published pages.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_logo_legend'] = 'Logo';
$GLOBALS['TL_LANG']['tl_content']['draggo_logo_src']    = ['Logo image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_logo_alt']    = ['Alternative text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_logo_link']   = ['Link', 'Home page, custom URL or no link.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_logo_url']    = ['Custom URL', 'Only with link mode "Custom URL".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_pt_legend']   = 'Page title';
$GLOBALS['TL_LANG']['tl_content']['draggo_pt_tag']      = ['HTML tag', 'H1–H3 or Div.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_pt_source']   = ['Source', 'Page title, navigation title or description.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_bc_legend']   = 'Breadcrumb';
$GLOBALS['TL_LANG']['tl_content']['draggo_bc_sep']      = ['Separator', 'Between levels, e.g. › or /.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sm_legend']   = 'Sitemap';
$GLOBALS['TL_LANG']['tl_content']['draggo_sm_root']     = ['Start page', 'Empty = root of the current website.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sm_levels']   = ['Levels', '1–6 nesting levels.'];

$GLOBALS['TL_LANG']['CTE']['draggo_gallery'] = ['Gallery (Draggo)', 'Image gallery with grid/masonry/tiles, hover effects + lightbox.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_legend'] = 'Gallery';
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_images'] = ['Images', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_layout'] = ['Layout', 'Grid, masonry or equal-size tiles.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_cols']   = ['Columns', '1–8.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_gap']    = ['Gap (px)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_ratio']  = ['Tile ratio', 'Only with layout "Tiles".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_hover']  = ['Hover effect', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ga_link']   = ['Link', 'None, open image or lightbox.'];

$GLOBALS['TL_LANG']['CTE']['draggo_videoplaylist'] = ['Video playlist (Draggo)', 'Player + list; YouTube/Vimeo/MP4, loads only on click (GDPR).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_vp_legend'] = 'Video playlist';
$GLOBALS['TL_LANG']['tl_content']['draggo_vp_items']  = ['Videos', 'Key = title, value = URL (YouTube/Vimeo/.mp4 or files/…).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_vp_ratio']  = ['Aspect ratio', '16:9, 4:3 or 1:1.'];

$GLOBALS['TL_LANG']['CTE']['draggo_anim']     = ['Animated headline (Draggo)', 'Headline with a rotating/typing word.'];
$GLOBALS['TL_LANG']['CTE']['draggo_hotspot']  = ['Hotspot image (Draggo)', 'Image with interactive points + tooltips.'];
$GLOBALS['TL_LANG']['CTE']['draggo_codeblock'] = ['Code block (Draggo)', 'Formatted code with language + copy button.'];
$GLOBALS['TL_LANG']['CTE']['draggo_scroll_stack'] = ['Stack-Reveal (Draggo)', 'Overlapping cards that slide over each other as you scroll.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_ss_legend']  = 'Stack-Reveal';
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_cards']   = ['Cards (title + text)', 'Each row = one card in the stack.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_num']     = ['Show numbering', '01, 02, 03 … above each card.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_height']  = ['Card height (vh)', 'Height per card in % of screen height. Default 78.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_top']     = ['Top offset (vh)', 'Where the card pins. Default 8.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_gap']     = ['Gap between cards (px)', 'Default 40.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ss_scale']   = ['Underlying card scale', '0.7–1.0, how much the covered card shrinks. Default 0.92.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_hpin'] = ['Horizontal-Pin (Draggo)', 'Section pins and scrolls sideways, panels reveal one by one.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_hp_legend'] = 'Horizontal-Pin';
$GLOBALS['TL_LANG']['tl_content']['draggo_hp_panels'] = ['Panels (title + text)', 'Each row = one panel revealed sideways.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_hp_track']  = ['Scroll length (vh)', 'How far you scroll until all panels pass. Empty = automatic.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_hp_w']      = ['Panel width (vw)', '% of screen width per panel. Default 70.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_hp_gap']    = ['Gap between panels (vw)', 'Default 3.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_split'] = ['Sticky-Split Story (Draggo)', 'Pinned image that swaps per text step as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sp_legend'] = 'Sticky-Split';
$GLOBALS['TL_LANG']['tl_content']['draggo_sp_steps']  = ['Steps (title + text)', 'Each row = one step, synced to the image at the same index.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sp_images'] = ['Images', 'One image per step (same order).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sp_side']   = ['Image side', 'Which side the pinned image sits on.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_text'] = ['Text-Highlight (Draggo)', 'Large statement that fills in word by word as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_th_legend'] = 'Text-Highlight';
$GLOBALS['TL_LANG']['tl_content']['draggo_th_text']   = ['Text', 'The sentence that lights up on scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_th_tag']    = ['HTML tag', 'h2/h3/p/div.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_zoom'] = ['Scroll-Zoom (Draggo)', 'Image zooms to full as you scroll into view.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_zm_legend'] = 'Scroll-Zoom';
$GLOBALS['TL_LANG']['tl_content']['draggo_zm_src']    = ['Image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_zm_alt']    = ['Alt text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_zm_from']   = ['Start scale', '0.2–0.95, how small it starts. Default 0.6.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_parallax'] = ['Parallax-Layers (Draggo)', 'Layers move at different speeds as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_legend'] = 'Parallax';
$GLOBALS['TL_LANG']['tl_content']['draggo_px_bg']     = ['Background layer', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_mid']    = ['Mid layer (optional)', 'Moves faster than the background.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_title']  = ['Heading', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_text']   = ['Text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_height'] = ['Height (vh)', 'Default 70.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_px_speed']  = ['Background speed', '0–1, how much the background offsets. Default 0.4.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_timeline'] = ['Pinned-Timeline (Draggo)', 'Progress line fills and steps activate as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_tl_legend'] = 'Timeline';
$GLOBALS['TL_LANG']['tl_content']['draggo_tl_steps']  = ['Steps (title + text)', 'Each row = one step on the timeline.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_beforeafter'] = ['Before/After (Draggo)', 'Two images with a slider to compare.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ba_legend']   = 'Before/After';
$GLOBALS['TL_LANG']['tl_content']['draggo_ba_before']   = ['"Before" image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ba_after']    = ['"After" image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_ba_lblbefore'] = ['"Before" label', 'Default: Vorher.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ba_lblafter']  = ['"After" label', 'Default: Nachher.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_revealgrid'] = ['Reveal-Grid (Draggo)', 'Cards rise in staggered as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_rg_legend'] = 'Reveal-Grid';
$GLOBALS['TL_LANG']['tl_content']['draggo_rg_items']  = ['Cards (title + text)', 'Each row = one card.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_rg_cols']   = ['Columns', '1–6. Default 3.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_svgdraw'] = ['SVG-Path-Draw (Draggo)', 'A line/shape draws itself as you scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_sd_legend'] = 'Draw line';
$GLOBALS['TL_LANG']['tl_content']['draggo_sd_shape']  = ['Shape', 'Line / wave / zigzag / underline / arrow.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_progress'] = ['Scroll-Progress (Draggo)', 'Reading-progress bar fixed at top/bottom.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_pr_legend'] = 'Progress';
$GLOBALS['TL_LANG']['tl_content']['draggo_pr_pos']    = ['Position', 'Top or bottom.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_pr_h']      = ['Height (px)', '1–20. Default 4.'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_curtain'] = ['Curtain-Cover (Draggo)', 'Full-bleed panels; the next slides over the previous.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ct_legend'] = 'Curtain';
$GLOBALS['TL_LANG']['tl_content']['draggo_ct_panels'] = ['Panels (title + text)', 'Each row = one full-screen panel.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ct_images'] = ['Background images', 'One image per panel (same order).'];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_textmask'] = ['Text-Mask (Draggo)', 'Large headline with an image visible through it.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_tm_legend'] = 'Text-Mask';
$GLOBALS['TL_LANG']['tl_content']['draggo_tm_text']  = ['Text', 'Short headline (max 60 chars).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_tm_img']   = ['Image (seen through the text)', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_scroll_tilt'] = ['Tilt-3D Cards (Draggo)', 'Cards tilt toward the cursor + reveal on scroll.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ti_legend'] = 'Tilt-Cards';
$GLOBALS['TL_LANG']['tl_content']['draggo_ti_cards'] = ['Cards (title + text)', 'Each row = one card.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_ti_cols']  = ['Columns', '1–6. Default 3.'];

$GLOBALS['TL_LANG']['CTE']['draggo_form'] = ['Form (Draggo)', 'Visually built form — Contao handles delivery, validation & spam protection.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_form_legend'] = 'Form';
$GLOBALS['TL_LANG']['tl_content']['draggo_form_id'] = ['Form', 'Build one in the Draggo editor or pick an existing Contao form.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_an_legend']   = 'Animated headline';
$GLOBALS['TL_LANG']['tl_content']['draggo_an_before']   = ['Text before', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_an_words']    = ['Rotating words', 'One per line.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_an_after']    = ['Text after', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_an_effect']   = ['Effect', 'Rotate or type.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_an_tag']      = ['HTML tag', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_hs_legend']   = 'Hotspot';
$GLOBALS['TL_LANG']['tl_content']['draggo_hs_img']      = ['Image', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_hs_points']   = ['Points', 'Key = "x,y" (percent), value = tooltip text.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_code_legend'] = 'Code';
$GLOBALS['TL_LANG']['tl_content']['draggo_code']        = ['Code', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_code_lang']   = ['Language', 'Label only, e.g. "PHP".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_code_copy']   = ['Copy button', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_pricetable'] = ['Price table (Draggo)', 'Price card with features + button.'];
$GLOBALS['TL_LANG']['CTE']['draggo_pricelist']  = ['Price list (Draggo)', 'Menu-style list: name … price.'];
$GLOBALS['TL_LANG']['CTE']['draggo_steps']      = ['Process steps (Draggo)', 'Numbered steps with title + text.'];
$GLOBALS['TL_LANG']['CTE']['draggo_search']     = ['Search (Draggo)', 'Search field for the Contao search page.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_prt_legend']  = 'Price table';
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_title']   = ['Title', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_price']   = ['Price', 'e.g. "29 €".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_period']  = ['Period', 'e.g. "/month".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_features'] = ['Features', 'One per line.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_btn']     = ['Button text', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_url']     = ['Button link', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_prt_featured'] = ['Highlight', 'Mark as the recommended plan.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_prl_legend']  = 'Price list';
$GLOBALS['TL_LANG']['tl_content']['draggo_prl_items']   = ['Entries', 'Key = name, value = price.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_stp_legend']  = 'Steps';
$GLOBALS['TL_LANG']['tl_content']['draggo_stp_items']   = ['Steps', 'Key = title, value = text.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_se_legend']   = 'Search';
$GLOBALS['TL_LANG']['tl_content']['draggo_se_page']     = ['Result page', 'Page with the Contao search module.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_se_ph']       = ['Placeholder', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_se_btn']      = ['Button text', ''];

$GLOBALS['TL_LANG']['CTE']['draggo_countdown'] = ['Countdown (Draggo)', 'Counts down to a target date.'];
$GLOBALS['TL_LANG']['CTE']['draggo_opennow']   = ['Open-now status (Draggo)', 'Live "Open now / Closed" badge — timezone-safe, with schema.org opening hours.'];
$GLOBALS['TL_LANG']['CTE']['draggo_themetoggle'] = ['Light/Dark toggle (Draggo)', 'Sun/moon button: visitors switch between light and dark mode (uses your colour tokens\' dark values).'];
$GLOBALS['TL_LANG']['CTE']['draggo_map']       = ['Google Maps (Draggo)', 'Map for an address, GDPR-friendly (click to load).'];
$GLOBALS['TL_LANG']['CTE']['draggo_share']     = ['Share buttons (Draggo)', 'Share the current page (Facebook/LinkedIn/email/link).'];
$GLOBALS['TL_LANG']['CTE']['draggo_toc']       = ['Table of contents (Draggo)', 'Jump links built from the page headings.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_cd_legend']    = 'Countdown';
$GLOBALS['TL_LANG']['tl_content']['draggo_cd_date']      = ['Target date', 'Date/time to count down to.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_cd_expired']   = ['Text after expiry', 'Shown once the date is reached.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_map_legend']   = 'Map';
$GLOBALS['TL_LANG']['tl_content']['draggo_map_query']    = ['Address / place', 'e.g. "Marienplatz, Munich".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_map_zoom']     = ['Zoom', '1 (world) to 21 (building).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_map_height']   = ['Height (px)', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_map_consent']  = ['GDPR consent', 'Load the map only after a click (recommended).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_share_legend'] = 'Share';
$GLOBALS['TL_LANG']['tl_content']['draggo_sh_facebook']  = ['Facebook', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_sh_linkedin']  = ['LinkedIn', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_sh_email']     = ['Email', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_sh_copy']      = ['Copy link', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_toc_legend']   = 'Table of contents';
$GLOBALS['TL_LANG']['tl_content']['draggo_toc_title']    = ['Heading', ''];
$GLOBALS['TL_LANG']['tl_content']['draggo_toc_levels']   = ['Levels', 'Which headings are included.'];

$GLOBALS['TL_LANG']['CTE']['draggo_global'] = ['Global block (Draggo)', 'Embeds a reusable "section" unit — centrally editable (live link).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_global_legend'] = 'Global block';
$GLOBALS['TL_LANG']['tl_content']['draggo_global_unit']   = ['Unit', 'Which unit (Draggo → Units) is embedded here. Changes there apply on all pages.'];

$GLOBALS['TL_LANG']['CTE']['draggo_readerfield'] = ['Reader field (Draggo)', 'Field of the current news/event detail page (title/teaser/image/date/author).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_rf_legend'] = 'Reader field';
$GLOBALS['TL_LANG']['tl_content']['draggo_rf_source'] = ['Data source', 'Auto-detects news or event.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_rf_field']  = ['Field', 'Which field is output.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_rf_tag']    = ['HTML tag', 'Only for the "Title" field.'];

$GLOBALS['TL_LANG']['CTE']['draggo_loop'] = ['Loop grid (Draggo)', 'Subpages of a page as cards (data-driven).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_legend'] = 'Loop / data source';
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_source']  = ['Source', 'Pages (subpages), news archive or events (calendar).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_archive'] = ['News archive', 'Only with source "News archive".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_calendar'] = ['Calendar', 'Only with source "Events (calendar)".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_category'] = ['News category', 'Optional filter (needs codefog/contao-news_categories). Empty = all.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_featured'] = ['Featured only', 'Show only news marked as "featured".'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_order']   = ['Order', 'Default (newest / order), oldest first or alphabetical.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_parent'] = ['Start page', 'Its subpages are output as cards.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_cols']   = ['Columns', '1–4.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_limit']  = ['Count', '0 = all.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_teaser'] = ['Description', 'Show the page description as a teaser.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_loop_more']   = ['More link', 'Show "More →".'];

$GLOBALS['TL_LANG']['tl_content']['draggo_nav_legend']  = 'Navigation';
$GLOBALS['TL_LANG']['tl_content']['draggo_nav_root']    = ['Start page', 'Subpages of this page are shown. Empty = top level of the website.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_nav_levels']  = ['Levels', 'How many nesting levels are shown (1–5).'];
$GLOBALS['TL_LANG']['tl_content']['draggo_nav_preset']  = ['Design', 'Horizontal, vertical side nav or hamburger menu.'];

$GLOBALS['TL_LANG']['tl_content']['draggo_grid_legend']       = 'Grid / columns';
$GLOBALS['TL_LANG']['tl_content']['draggo_responsive_legend'] = 'Responsive (tablet / mobile)';

$GLOBALS['TL_LANG']['tl_content']['draggo_grid_preset'] = ['Structure (desktop)', 'Predefined column split of the row.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_container']   = ['Container', 'Width constraint of the row.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_grid_custom'] = ['Custom columns', 'Column widths 1–12, e.g. 6,6 or 4,4,4.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_grid_tablet'] = ['Structure (tablet)', 'Column split from 768px. Empty = same as desktop.'];
$GLOBALS['TL_LANG']['tl_content']['draggo_grid_mobile'] = ['Structure (mobile)', 'Column split below 768px. Empty = stacked (full width).'];

$GLOBALS['TL_LANG']['tl_content']['draggo_grid_presets'] = [
    '1'       => '1 column',
    '6-6'     => '2 columns (50 / 50)',
    '4-8'     => 'Sidebar left (33 / 66)',
    '8-4'     => 'Sidebar right (66 / 33)',
    '4-4-4'   => '3 columns (thirds)',
    '3-6-3'   => '3 columns (narrow/wide/narrow)',
    '3-3-3-3' => '4 columns (quarters)',
    '2-8-2'   => 'Centred (narrow content)',
    'custom'  => 'Custom',
];

$GLOBALS['TL_LANG']['tl_content']['draggo_containers'] = [
    'container'       => 'Container (constrained)',
    'container-fluid' => 'Full width (fluid)',
    'none'            => 'No container',
];

