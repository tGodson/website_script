<?php
/**
 * wc_homepage_builder.php
 * =======================
 * Drop-in WordPress-root landing-page (homepage) generator.
 *
 * Runs INSIDE WordPress (loads wp-load.php). Reads your store (brand, tagline,
 * top categories, featured products) and writes ONE polished homepage:
 *   - humanized copy that must NOT read as AI (strict banned-words + voice rules)
 *   - perfect heading hierarchy: exactly ONE <h1>, then <h2>/<h3>
 *   - correct internal links (every top category + featured products)
 *   - Rank Math meta for the front page
 *
 * OUTPUT_FORMAT:
 *   'flatsome' (default) — builds Flatsome UX Builder shortcodes
 *                          ([section]/[row]/[col], [ux_products], [featured_box],
 *                          [button]) so the page stays editable in UX Builder.
 *                          The AI writes only the TEXT; this script assembles the
 *                          shortcodes, so the structure is always valid.
 *   'html'               — plain semantic HTML (non-Flatsome themes).
 *
 * Progress streams live to the browser; the file self-deletes when done.
 *
 * --------------------------------------------------------------------------
 * HOW TO USE
 * --------------------------------------------------------------------------
 * 1) Edit CONFIG: paste your AI key, pick AI_PROVIDER, set STORE_NICHE.
 *    Flatsome users: leave OUTPUT_FORMAT = 'flatsome'.
 *    To wipe an existing homepage and rebuild it, set OVERWRITE_HOMEPAGE = true.
 * 2) Upload this ONE file to the WordPress root (public_html/ or, on Local,
 *    ~/Local Sites/<site>/app/public/).
 * 3) Open: http://yoursite.local/wc_homepage_builder.php  (local: no key)
 *          https://yourdomain/wc_homepage_builder.php?key=YOUR_SECRET
 *
 * NOTE: paste API keys in CONFIG (a browser-run PHP script can't read your
 * terminal export). The file self-deletes so keys don't linger.
 *
 * HEADING NOTE: this writes ONE <h1>. If your theme also prints the page title
 * as an <h1> on the homepage, hide the front-page title in the theme settings.
 *
 * ALLOWED USE: lawful catalogs only.
 */

// #############################################################################
// #                          C O N F I G U R A T I O N                        #
// #############################################################################

// ---- Access (local: no token; public/live: token auto-required) ------------
const REQUIRE_SECRET = false;
const SECRET = 'change-me-to-a-long-random-string';   // letters & numbers only

// ---- AI provider -----------------------------------------------------------
const AI_PROVIDER = 'gemini';            // 'gemini' (free) or 'claude'
const GEMINI_API_KEY = '';               // paste (https://aistudio.google.com/apikey)
const GEMINI_MODEL   = 'gemini-2.0-flash';
const GEMINI_RPM     = 10;
const ANTHROPIC_API_KEY = '';            // paste (https://console.anthropic.com)
const CLAUDE_MODEL      = 'claude-opus-4-8';

// ---- Output format ---------------------------------------------------------
const OUTPUT_FORMAT = 'flatsome';        // 'flatsome' (UX Builder) or 'html'

// ---- Store / SEO -----------------------------------------------------------
const BRAND_NAME = '';                   // '' = WordPress site title
const TAGLINE    = '';                   // '' = WordPress tagline
const STORE_NICHE = 'general consumer products';  // <-- EDIT per site
const SALES_ORIENTED   = true;
const HOMEPAGE_MIN_WORDS = 700;
const NUM_FEATURED_PRODUCTS = 6;
const INCLUDE_FAQ = true;

// ---- Behavior --------------------------------------------------------------
const OVERWRITE_HOMEPAGE     = false;    // true = CLEAR the current homepage and rebuild it
const CREATE_HOME_IF_MISSING = true;     // create + set a static "Home" page if none
const SELF_DELETE_WHEN_DONE  = true;

// ---- Compliance ------------------------------------------------------------
const COMPLIANCE_MODE = false;
const DISCLAIMER_HTML = '<p><em>Always read the label and use products as directed. '
                      . 'Consult a qualified professional where appropriate.</em></p>';
const PROHIBITED_WORDS = '';

// ---- Lawful-use guard (keep true) ------------------------------------------
const ENABLE_LAWFUL_USE_GUARD = true;

// ---- STRICT anti-AI voice: banned words/phrases (told to the AI + scanned) --
const BANNED_AI_WORDS = [
    'elevate', 'unleash', 'unlock', 'seamless', 'seamlessly', 'robust', 'leverage',
    'cutting-edge', 'state-of-the-art', 'game-changer', 'game changer', 'revolutionize',
    'revolutionary', 'empower', 'testament', 'tapestry', 'realm', 'delve', 'embark',
    'plethora', 'myriad', 'curated', 'meticulous', 'meticulously', 'boasts', 'nestled',
    'look no further', "in today's fast-paced world", 'when it comes to', 'rest assured',
    "we've got you covered", 'one-stop shop', 'at the heart of', 'more than just',
    'take your', 'to the next level', 'whether you', 'not only',
];

// #############################################################################
// #                       END OF CONFIG — CODE BELOW                          #
// #############################################################################

$GUARD_TERMS = [
    'fentanyl','fentanil','sublimaze','duragesic','actiq','carfentanil','oxycodone',
    'oxycontin','hydrocodone','vicodin','percocet','morphine','codeine','tramadol',
    'heroin','opioid','opiate','ketamine','alprazolam','xanax','diazepam','valium',
    'adderall','amphetamine','methamphetamine','cocaine','mdma','lsd','ghb','nembutal',
    'pentobarbital','research chemical','schedule ii','schedule iii','controlled substance',
];

// ---- Auth gate -------------------------------------------------------------
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host_noport = preg_replace('/:\d+$/', '', $host);
$is_local = (substr($host_noport, -6) === '.local')
    || in_array($host_noport, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
    || (($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1');
if (REQUIRE_SECRET || !$is_local) {
    if (!isset($_GET['key']) || !hash_equals(SECRET, (string) $_GET['key'])) {
        http_response_code(403);
        exit('Forbidden — add ?key=YOUR_SECRET (a token is required on non-local sites).');
    }
}

// ---- Load WordPress --------------------------------------------------------
$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    $dir = __DIR__;
    for ($i = 0; $i < 6 && !file_exists($dir . '/wp-load.php'); $i++) { $dir = dirname($dir); }
    $wp_load = $dir . '/wp-load.php';
}
if (!file_exists($wp_load)) {
    http_response_code(500);
    exit('Could not locate wp-load.php — place this file in the WordPress root.');
}
require_once $wp_load;
if (!function_exists('wc_get_product')) {
    http_response_code(500);
    exit('WooCommerce is not active on this site.');
}

// ---- Stream output ---------------------------------------------------------
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);
ignore_user_abort(true);
@set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset='utf-8'><title>Homepage Builder</title>";
echo "<body style='font:14px/1.5 monospace;background:#111;color:#ddd;padding:20px'>";
echo str_repeat(' ', 4096);

function out($msg, $color = '#ddd') {
    echo "<div style='color:$color'>" . esc_html($msg) . "</div>\n";
    flush();
}
function brand()   { return BRAND_NAME !== '' ? BRAND_NAME : get_bloginfo('name'); }
function tagline() { return TAGLINE !== '' ? TAGLINE : get_bloginfo('description'); }
function shop_url() {
    $u = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
    return $u ?: home_url('/');
}

// ---------------------------------------------------------------------------
// AI provider (Gemini / Claude), throttle + JSON retry
// ---------------------------------------------------------------------------

function ai_throttle() {
    static $last = 0.0;
    $interval = (AI_PROVIDER === 'gemini') ? 60.0 / max(GEMINI_RPM, 1) : 0.0;
    if ($interval > 0) {
        $gap = microtime(true) - $last;
        if ($gap < $interval) { usleep((int) (($interval - $gap) * 1e6)); }
    }
    $last = microtime(true);
}

function ai_text_gemini($prompt) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL
         . ':generateContent?key=' . urlencode(GEMINI_API_KEY);
    $resp = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.9, 'maxOutputTokens' => 8192,
                                   'responseMimeType' => 'application/json']]),
        'timeout' => 180,
    ]);
    if (is_wp_error($resp)) { return [null, $resp->get_error_message()]; }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code == 429) { return [null, '429']; }
    if ($code != 200) { return [null, "Gemini $code: " . substr(wp_remote_retrieve_body($resp), 0, 180)]; }
    $j = json_decode(wp_remote_retrieve_body($resp), true);
    $text = '';
    foreach (($j['candidates'][0]['content']['parts'] ?? []) as $p) { $text .= $p['text'] ?? ''; }
    return [$text, ''];
}

function ai_text_claude($prompt) {
    $resp = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'headers' => ['x-api-key' => ANTHROPIC_API_KEY, 'anthropic-version' => '2023-06-01',
                      'content-type' => 'application/json'],
        'body'    => wp_json_encode(['model' => CLAUDE_MODEL, 'max_tokens' => 8000,
                      'messages' => [['role' => 'user', 'content' => $prompt]]]),
        'timeout' => 180,
    ]);
    if (is_wp_error($resp)) { return [null, $resp->get_error_message()]; }
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    if ($code == 429) { return [null, '429']; }
    if ($code != 200) {
        $j = json_decode($body, true);
        $emsg = $j['error']['message'] ?? substr($body, 0, 200);
        if (stripos($emsg, 'credit balance') !== false || stripos($emsg, 'billing') !== false) {
            return [null, "Claude CREDIT/BILLING ($code): $emsg  >>> Top up at console.anthropic.com, "
                        . "or set AI_PROVIDER = 'gemini' (free) and paste a Gemini key."];
        }
        return [null, "Claude HTTP $code: $emsg"];
    }
    $j = json_decode($body, true);
    $stop = $j['stop_reason'] ?? '';
    $text = '';
    foreach (($j['content'] ?? []) as $b) { if (($b['type'] ?? '') === 'text') { $text .= $b['text']; } }
    if ($text === '') { return [null, "Claude returned no text (stop_reason=$stop)"]; }
    return [$text, ''];
}

// Escape raw control chars (newlines/tabs) that appear INSIDE JSON string values.
function json_escape_ctrl_in_strings($s) {
    $out = ''; $in = false; $esc = false; $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = $s[$i];
        if ($in) {
            if ($esc) { $out .= $ch; $esc = false; continue; }
            if ($ch === '\\') { $out .= $ch; $esc = true; continue; }
            if ($ch === '"') { $in = false; $out .= $ch; continue; }
            if ($ch === "\n") { $out .= '\\n'; continue; }
            if ($ch === "\r") { $out .= '\\r'; continue; }
            if ($ch === "\t") { $out .= '\\t'; continue; }
            $out .= $ch;
        } else {
            if ($ch === '"') { $in = true; }
            $out .= $ch;
        }
    }
    return $out;
}

function extract_json($text) {
    $text = trim((string) $text);
    $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $candidates = [$text];
    $s = strpos($text, '{'); $e = strrpos($text, '}');
    if ($s !== false && $e !== false && $e > $s) { $candidates[] = substr($text, $s, $e - $s + 1); }
    foreach ($candidates as $c) {
        $variants = [
            $c,
            preg_replace('/,\s*([}\]])/', '$1', $c),                              // trailing commas
            json_escape_ctrl_in_strings($c),                                     // raw newlines in strings
            preg_replace('/,\s*([}\]])/', '$1', json_escape_ctrl_in_strings($c)),
        ];
        foreach ($variants as $v) {
            $d = json_decode($v, true);
            if (is_array($d)) { return $d; }
        }
    }
    return null;
}

function ai_json($prompt) {
    $last = '';
    for ($attempt = 0; $attempt < 3; $attempt++) {
        ai_throttle();
        [$text, $err] = (AI_PROVIDER === 'gemini') ? ai_text_gemini($prompt) : ai_text_claude($prompt);
        if ($err === '429') { sleep(30 * ($attempt + 1)); continue; }
        if ($err) { return [null, $err]; }
        $last = (string) $text;
        $data = extract_json($last);
        if ($data !== null) { return [$data, '']; }
    }
    $snip = trim(preg_replace('/\s+/', ' ', mb_substr($last, 0, 240)));
    return [null, 'invalid JSON after 3 tries (' . json_last_error_msg() . '). Model returned: ' . $snip];
}

// ---------------------------------------------------------------------------
// STRICT anti-AI voice rules (shared by both formats)
// ---------------------------------------------------------------------------

function voice_rules() {
    $banned = implode(', ', BANNED_AI_WORDS);
    return "VOICE — STRICT, NON-NEGOTIABLE. Write like a real, experienced human brand copywriter for "
        . "THIS specific store. It must NOT read like AI. Hard rules:\n"
        . "- NEVER use these words/phrases or close variants: $banned.\n"
        . "- No hollow hype, no filler, no vague superlatives. Be concrete and specific to the actual "
        . "products and categories.\n"
        . "- Vary sentence length — mix short, punchy sentences with longer ones. Use contractions. "
        . "Write in second person ('you'). Prefer active voice.\n"
        . "- Do NOT open with 'Welcome to', 'In the world of', or a dictionary-style definition. Do NOT "
        . "end with 'In conclusion' or a recap of what you just said.\n"
        . "- Avoid tidy lists of three and repeated parallel sentence structures. Avoid em-dash pile-ups. "
        . "Don't start consecutive sentences the same way.\n";
}

function compliance_clause() {
    if (!COMPLIANCE_MODE) { return ''; }
    $banned = PROHIBITED_WORDS !== '' ? PROHIBITED_WORDS : '(none specified)';
    return "COMPLIANCE MODE: do NOT use these words/claims: $banned. Avoid medical/health claims, "
        . "guarantees, or non-compliant superlatives.\n";
}

// ---------------------------------------------------------------------------
// Prompts
// ---------------------------------------------------------------------------

function build_flatsome_prompt($categories, $products) {
    $brand = brand(); $tag = tagline();
    $cats  = ''; foreach ($categories as $c) { $cats  .= "  - {$c['name']}\n"; }
    $prods = ''; foreach ($products as $p)   { $prods .= "  - {$p['name']}\n"; }
    $sales = SALES_ORIENTED ? "Persuasive but genuine; gently push shoppers to browse and buy." : "Informative and helpful.";
    $faq   = INCLUDE_FAQ ? "3-4" : "0";
    $faqn = INCLUDE_FAQ ? "3-4" : "0";
    return "You are writing the HOMEPAGE copy for $brand, an online store selling " . STORE_NICHE . ". "
        . "Tagline: \"$tag\". $sales\n\n"
        . "Categories:\n$cats\nFeatured products:\n$prods\n"
        . voice_rules() . compliance_clause()
        . "Write these fields (plain text only — NO HTML, NO markdown):\n"
        . "- hero_headline: one strong headline containing the main keyword.\n"
        . "- hero_intro: 2-3 sentences that make someone want to shop here.\n"
        . "- cta_button: short button text (e.g. Shop the range).\n"
        . "- categories_heading, featured_heading, about_heading, why_us_heading: section headings.\n"
        . "- why_us_points: 3-4 items, each an object with 'title' and 'text' (one concrete sentence).\n"
        . "- about_text: 2-3 sentences of specific, trust-building copy.\n"
        . "- faq: $faqn items, each an object with 'q' (long-tail question) and 'a' (short honest answer).\n"
        . "- closing_cta: one closing line inviting the shopper to buy.\n"
        . "- meta_title (<= 60 chars, end with ' | $brand'), meta_description (<= 155 chars, main keyword), focus_keyword.\n"
        . "Aim for " . HOMEPAGE_MIN_WORDS . "+ words of copy across all fields.\n\n"
        . "OUTPUT RULES: return ONE valid JSON object and NOTHING else — no prose, no markdown, no code "
        . "fences, no comments, no trailing commas. Keep every value on a single line (no raw line breaks "
        . "inside strings). Use these exact keys: hero_headline, hero_intro, cta_button, categories_heading, "
        . "why_us_heading, why_us_points, featured_heading, about_heading, about_text, faq, closing_cta, "
        . "meta_title, meta_description, focus_keyword.";
}

function build_html_prompt($categories, $products) {
    $brand = brand(); $tag = tagline();
    $cats = ''; foreach ($categories as $c) { $cats .= "  - {$c['name']} -> {$c['url']}\n"; }
    $prods = ''; foreach ($products as $p)   { $prods .= "  - {$p['name']} -> {$p['url']}\n"; }
    $min = HOMEPAGE_MIN_WORDS;
    $faq = INCLUDE_FAQ ? "\n- An <h2> FAQ section with 3-4 <h3> questions each answered in a <p>." : '';
    return "You are writing the HOMEPAGE for $brand, an online store selling " . STORE_NICHE . ". "
        . "Tagline: \"$tag\".\n\n"
        . "TOP CATEGORIES (link each):\n$cats\nFEATURED PRODUCTS (link each):\n$prods\n"
        . voice_rules() . compliance_clause()
        . "STRUCTURE:\n- At least $min words of valid HTML.\n"
        . "- EXACTLY ONE <h1> (hero headline with the main keyword), then <h2> per section, <h3> only "
        . "nested under an <h2>. Never skip a level; never more than one <h1>.\n"
        . "- Sections: hero; 'Shop by Category' linking EVERY category; a benefits <ul>; 'Featured "
        . "Products' linking the featured products; a short About; $faq a closing call-to-action.\n"
        . "- Link every category and featured product with real <a href='...'> tags. Invent no other links.\n"
        . "- Use SINGLE quotes for HTML attributes, never double quotes. No inline CSS.\n\n"
        . "Return ONE valid JSON object and nothing else — no markdown, no code fences, no comments, no "
        . "trailing commas. Keys: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}";
}

// ---------------------------------------------------------------------------
// Assemblers
// ---------------------------------------------------------------------------

function esc($s) { return esc_html((string) $s); }

/** Build valid Flatsome UX Builder shortcodes from the AI's text fields. */
function build_flatsome($f, $categories, $products) {
    $shop = shop_url();
    $btn  = esc($f['cta_button'] ?? 'Shop Now');
    $out  = '';

    // HERO — the single H1
    $out .= "[section label=\"Hero\" padding=\"60px\"]\n[row]\n[col span__sm=\"12\"]\n";
    $out .= '<h1>' . esc($f['hero_headline'] ?? (brand() . ' — ' . tagline())) . "</h1>\n";
    $out .= '<p>' . esc($f['hero_intro'] ?? '') . "</p>\n";
    $out .= "[button text=\"$btn\" link=\"" . esc_url($shop) . "\"]\n[/col]\n[/row]\n[/section]\n";

    // SHOP BY CATEGORY — explicit SEO links
    if ($categories) {
        $lis = '';
        foreach ($categories as $c) {
            if (!empty($c['url'])) { $lis .= '<li><a href="' . esc_url($c['url']) . '">' . esc($c['name']) . '</a></li>'; }
        }
        $out .= "[section label=\"Categories\"]\n[row]\n[col span__sm=\"12\"]\n"
             . '<h2>' . esc($f['categories_heading'] ?? 'Shop by Category') . "</h2>\n<ul>$lis</ul>\n"
             . "[/col]\n[/row]\n[/section]\n";
    }

    // WHY US — featured_box columns
    $pts = is_array($f['why_us_points'] ?? null) ? $f['why_us_points'] : [];
    if ($pts) {
        $span = count($pts) >= 3 ? 4 : (count($pts) === 2 ? 6 : 12);
        $out .= "[section label=\"Why Us\"]\n[row]\n[col span__sm=\"12\"]\n"
             . '<h2>' . esc($f['why_us_heading'] ?? 'Why Choose Us') . "</h2>\n[/col]\n[/row]\n[row]\n";
        foreach ($pts as $pt) {
            $t = esc(is_array($pt) ? ($pt['title'] ?? '') : $pt);
            $d = esc(is_array($pt) ? ($pt['text'] ?? '') : '');
            $out .= "[col span=\"$span\" span__sm=\"12\"]\n[featured_box]\n<h3>$t</h3>\n"
                 . ($d !== '' ? "<p>$d</p>\n" : '') . "[/featured_box]\n[/col]\n";
        }
        $out .= "[/row]\n[/section]\n";
    }

    // FEATURED PRODUCTS — native Flatsome product grid (renders cards + links)
    if ($products) {
        $ids = implode(',', array_map(static fn($p) => (int) $p['id'], $products));
        $out .= "[section label=\"Featured\"]\n[row]\n[col span__sm=\"12\"]\n"
             . '<h2>' . esc($f['featured_heading'] ?? 'Featured Products') . "</h2>\n"
             . "[ux_products ids=\"$ids\"]\n[/col]\n[/row]\n[/section]\n";
    }

    // ABOUT
    if (!empty($f['about_text'])) {
        $out .= "[section label=\"About\"]\n[row]\n[col span__sm=\"12\"]\n"
             . '<h2>' . esc($f['about_heading'] ?? 'About Us') . '</h2>' . "\n<p>" . esc($f['about_text']) . "</p>\n"
             . "[/col]\n[/row]\n[/section]\n";
    }

    // FAQ
    if (INCLUDE_FAQ && is_array($f['faq'] ?? null) && $f['faq']) {
        $faq = "<h2>Frequently Asked Questions</h2>\n";
        foreach ($f['faq'] as $qa) {
            $q = esc($qa['q'] ?? ''); $a = esc($qa['a'] ?? '');
            if ($q !== '') { $faq .= "<h3>$q</h3>\n<p>$a</p>\n"; }
        }
        $out .= "[section label=\"FAQ\"]\n[row]\n[col span__sm=\"12\"]\n" . $faq . "[/col]\n[/row]\n[/section]\n";
    }

    // CLOSING CTA
    if (!empty($f['closing_cta'])) {
        $out .= "[section label=\"CTA\"]\n[row]\n[col span__sm=\"12\"]\n<p>" . esc($f['closing_cta']) . "</p>\n"
             . "[button text=\"$btn\" link=\"" . esc_url($shop) . "\"]\n[/col]\n[/row]\n[/section]\n";
    }

    if (COMPLIANCE_MODE && DISCLAIMER_HTML) {
        $out .= "[section label=\"Notice\"]\n[row]\n[col span__sm=\"12\"]\n" . DISCLAIMER_HTML . "\n[/col]\n[/row]\n[/section]\n";
    }
    return $out;
}

function existing_hrefs($html) {
    preg_match_all('/href=["\']([^"\']+)["\']/', (string) $html, $m);
    return $m[1];
}

/** HTML mode: guarantee exactly one <h1>. */
function normalize_headings($html, $fallback_h1) {
    $count = preg_match_all('/<h1\b/i', $html, $m);
    if ($count === 0) { return '<h1>' . esc_html($fallback_h1) . "</h1>\n" . $html; }
    if ($count > 1 && preg_match('/<h1\b[^>]*>.*?<\/h1>/is', $html, $first)) {
        $token = '%%KEEP_FIRST_H1%%';
        $html = preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', $token, $html, 1);
        $html = preg_replace('/<h1(\b[^>]*)>/i', '<h2$1>', $html);
        $html = preg_replace('/<\/h1>/i', '</h2>', $html);
        $html = str_replace($token, $first[0], $html);
    }
    return $html;
}

/** HTML mode: append any missing category/product links. */
function ensure_home_links($html, $categories, $products) {
    $have = existing_hrefs($html);
    $cl = ''; $pl = '';
    foreach ($categories as $c) {
        if (!empty($c['url']) && !in_array($c['url'], $have, true)) {
            $cl .= '<li><a href="' . esc_url($c['url']) . '">' . esc_html($c['name']) . '</a></li>';
        }
    }
    foreach ($products as $p) {
        if (!empty($p['url']) && !in_array($p['url'], $have, true)) {
            $pl .= '<li><a href="' . esc_url($p['url']) . '">' . esc_html($p['name']) . '</a></li>';
        }
    }
    if ($cl) { $html .= "\n<h2>Shop by Category</h2>\n<ul>$cl</ul>"; }
    if ($pl) { $html .= "\n<h2>Featured Products</h2>\n<ul>$pl</ul>"; }
    if (COMPLIANCE_MODE && DISCLAIMER_HTML && strpos($html, DISCLAIMER_HTML) === false) { $html .= "\n" . DISCLAIMER_HTML; }
    return $html;
}

// ---------------------------------------------------------------------------
// Store data
// ---------------------------------------------------------------------------

function top_categories() {
    $out = [];
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0]);
    if (!is_array($terms)) { return $out; }
    foreach ($terms as $t) {
        if (strtolower($t->slug) === 'uncategorized') { continue; }
        $lk = get_term_link($t);
        if (is_wp_error($lk)) { continue; }
        $out[] = ['name' => $t->name, 'url' => $lk];
    }
    return $out;
}

function featured_products($n) {
    $ids = wc_get_products(['status' => 'publish', 'limit' => $n, 'featured' => true, 'return' => 'ids']);
    if (count($ids) < $n) {
        $more = wc_get_products(['status' => 'publish', 'limit' => $n * 2, 'orderby' => 'date',
            'order' => 'DESC', 'return' => 'ids', 'exclude' => $ids]);
        $ids = array_merge($ids, $more);
    }
    $out = [];
    foreach ($ids as $pid) {
        if (get_post_meta($pid, '_wholesale_bundle', true)) { continue; }
        $out[] = ['id' => (int) $pid, 'name' => get_the_title($pid), 'url' => get_permalink($pid)];
        if (count($out) >= $n) { break; }
    }
    return $out;
}

function resolve_front_page() {
    $front = (get_option('show_on_front') === 'page') ? (int) get_option('page_on_front') : 0;
    if ($front && get_post($front)) { return $front; }
    if (!CREATE_HOME_IF_MISSING) { return 0; }
    $front = (int) wp_insert_post(['post_title' => 'Home', 'post_type' => 'page',
        'post_status' => 'publish', 'post_content' => '']);
    if ($front) { update_option('show_on_front', 'page'); update_option('page_on_front', $front); }
    return $front;
}

function scan_ai_words($text) {
    $hay = strtolower(wp_strip_all_tags((string) $text));
    $hits = [];
    foreach (BANNED_AI_WORDS as $w) { if (strpos($hay, strtolower($w)) !== false) { $hits[] = $w; } }
    return array_values(array_unique($hits));
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

out('== Homepage Builder ==  provider=' . AI_PROVIDER . '  format=' . OUTPUT_FORMAT, '#6cf');

if (ENABLE_LAWFUL_USE_GUARD) {
    $hay = '';
    foreach (wc_get_products(['status' => ['publish', 'draft', 'pending', 'private'], 'limit' => -1, 'return' => 'ids']) as $pid) {
        $hay .= strtolower(get_the_title($pid)) . ' ';
    }
    foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]) as $t) { $hay .= strtolower($t->name) . ' '; }
    $hits = array_values(array_unique(array_filter($GUARD_TERMS, fn($t) => strpos($hay, $t) !== false)));
    if ($hits) {
        out('[ABORTED] Lawful-use guard triggered: ' . implode(', ', $hits), '#f66');
        out('This tool is for lawful catalogs only.', '#f66');
        exit;
    }
}

$front_id = resolve_front_page();
if (!$front_id) {
    out('[STOP] No static front page. Set Settings > Reading > "A static page", or CREATE_HOME_IF_MISSING = true.', '#f66');
    exit;
}
out('Front page: #' . $front_id . ' — ' . get_the_title($front_id));

$existing = trim((string) get_post_field('post_content', $front_id));
if ($existing !== '' && !OVERWRITE_HOMEPAGE) {
    out('[STOP] The homepage already has content. Set OVERWRITE_HOMEPAGE = true to CLEAR and rebuild it '
      . '(this wipes the current Flatsome/UX Builder layout on the front page).', '#fa0');
    echo "</body>";
    exit;
}

$categories = top_categories();
$products   = featured_products(NUM_FEATURED_PRODUCTS);
out('Linking ' . count($categories) . ' categories and ' . count($products) . ' featured products.');
out('Generating homepage copy...');

if (OUTPUT_FORMAT === 'flatsome') {
    [$data, $err] = ai_json(build_flatsome_prompt($categories, $products));
    if (!$data || empty($data['hero_headline'])) {
        out('[ERROR] Could not generate copy' . ($err ? " ($err)" : '') . '. Nothing changed.', '#f66');
        echo "</body>"; exit;
    }
    $content = build_flatsome($data, $categories, $products);
} else {
    [$data, $err] = ai_json(build_html_prompt($categories, $products));
    if (!$data || empty($data['content'])) {
        out('[ERROR] Could not generate content' . ($err ? " ($err)" : '') . '. Nothing changed.', '#f66');
        echo "</body>"; exit;
    }
    $fallback_h1 = trim(explode('|', (string) ($data['meta_title'] ?? ''))[0]) ?: (brand() . ' — ' . tagline());
    $content = normalize_headings((string) $data['content'], $fallback_h1);
    $content = ensure_home_links($content, $categories, $products);
}

wp_update_post(['ID' => $front_id, 'post_content' => $content]);
if (!empty($data['meta_title']))       { update_post_meta($front_id, 'rank_math_title', mb_substr((string) $data['meta_title'], 0, 70)); }
if (!empty($data['meta_description'])) { update_post_meta($front_id, 'rank_math_description', mb_substr((string) $data['meta_description'], 0, 160)); }
if (!empty($data['focus_keyword']))    { update_post_meta($front_id, 'rank_math_focus_keyword', (string) $data['focus_keyword']); }

$words = str_word_count(wp_strip_all_tags(preg_replace('/\[[^\]]*\]/', ' ', $content)));
$h1s   = preg_match_all('/<h1\b/i', $content);
out("   [ok] homepage written ($words words, $h1s H1, " . count(existing_hrefs($content)) . ' category links)', '#6f6');

$ai_hits = scan_ai_words($content);
if ($ai_hits) {
    out('   [voice warning] AI-tell words slipped in: ' . implode(', ', $ai_hits)
      . ' — re-run to regenerate, or edit them out.', '#fa0');
} else {
    out('   [voice] clean — no banned AI words detected.', '#6f6');
}

if (SELF_DELETE_WHEN_DONE) {
    if (@unlink(__FILE__)) { out('This file has deleted itself from the server. Done. ✅', '#6f6'); }
    else { out('Could not auto-delete — please delete this file manually now.', '#fa0'); }
} else {
    out('>>> Remember to DELETE this file from the server. <<<', '#fa0');
}
echo "</body>";
