<?php
/**
 * wc_seo_optimizer.php
 * ====================
 * Drop-in WordPress-root SEO optimizer (the "upload + open URL" approach) — the
 * server-side twin of seo_csv_optimizer.py, with NO CSV needed.
 *
 * Runs INSIDE WordPress (loads wp-load.php). It reads your products and product
 * categories straight from WooCommerce and, from each title alone, generates:
 *
 *   PRODUCTS:
 *     - short description + long description (HTML: Overview, Features, Specs
 *       table, FAQ, call-to-action)
 *     - Rank Math meta title / meta description / focus keyword
 *     - product tags (+ secondary keywords)
 *     - average-market price (only if the price is empty)
 *     - internal interlinks to related products + the category page
 *
 *   CATEGORIES & SUBCATEGORIES:
 *     - a keyword-rich category description with interlinks to its products,
 *       its subcategories, and its parent category
 *     - Rank Math term title / description / focus keyword
 *
 * Progress streams live to the browser. Brand name + all URLs are taken from
 * WordPress automatically.
 *
 * --------------------------------------------------------------------------
 * HOW TO USE
 * --------------------------------------------------------------------------
 * 1) Edit the CONFIG block: paste your AI key, pick AI_PROVIDER, set STORE_NICHE.
 * 2) Upload JUST THIS ONE FILE to the WordPress root (public_html/ on Hostinger,
 *    or ~/Local Sites/<site>/app/public/ on Local).
 * 3) Open in your browser:
 *      Local:  http://yoursite.local/wc_seo_optimizer.php           (no key needed)
 *      Public: https://yourdomain/wc_seo_optimizer.php?key=YOUR_SECRET
 * 4) Watch it run. If it times out, just refresh — it RESUMES (products/terms
 *    that already have content are skipped unless FORCE_REGENERATE = true).
 * 5) When everything is done it DELETES ITSELF (SELF_DELETE_WHEN_DONE = true).
 *
 * NOTE: a browser-run PHP script CANNOT read API keys you `export`ed in your
 * terminal — paste them in the CONFIG block below. The file self-deletes when
 * done, so keys don't linger.
 *
 * ALLOWED USE: lawful product catalogs only.
 */

// #############################################################################
// #                          C O N F I G U R A T I O N                        #
// #############################################################################

// ---- Access (local: no token; public/live: token auto-required) ------------
const REQUIRE_SECRET = false;
const SECRET = 'change-me-to-a-long-random-string';

// ---- AI provider -----------------------------------------------------------
const AI_PROVIDER = 'gemini';            // 'gemini' (free) or 'claude'

const GEMINI_API_KEY = '';               // paste here (https://aistudio.google.com/apikey)
const GEMINI_MODEL   = 'gemini-2.0-flash';
const GEMINI_RPM     = 10;               // requests/min throttle (free tier ~15)

const ANTHROPIC_API_KEY = '';            // paste here (https://console.anthropic.com)
const CLAUDE_MODEL      = 'claude-opus-4-8'; // or 'claude-sonnet-4-6', 'claude-haiku-4-5'

// ---- What to do ------------------------------------------------------------
const GENERATE_PRODUCT_CONTENT  = true;  // short/long desc, meta, tags, price
const GENERATE_CATEGORY_CONTENT = true;  // category + subcategory descriptions
const ADD_INTERLINKS            = true;  // related-product + category interlinks
const FORCE_REGENERATE          = false; // false = skip items that already have content (resume);
                                         // true  = overwrite everything

// ---- Store / SEO -----------------------------------------------------------
const BRAND_NAME = '';                   // '' = use the WordPress site title
const STORE_NICHE = 'general consumer products';  // <-- EDIT per site: what the store sells
const SALES_ORIENTED = true;             // bias copy toward "buy", "for sale", "order"
const LONG_DESC_MIN_WORDS = 650;
const SHORT_DESC_WORDS    = 130;
const INTERLINKS_PER_PRODUCT = 3;
const CATEGORY_DESC_WORDS = 220;
const PRODUCT_LINKS_IN_CATEGORY = 8;     // max product links listed in a category description

// ---- Pricing ---------------------------------------------------------------
const SET_PRICE_IF_EMPTY = true;
const PRICE_ENDING = '.99';              // '' = whole number

// ---- Units / product type --------------------------------------------------
// Every product gets a clear "Sold as: <unit>" line next to the price so buyers
// know exactly what one purchase includes (the AI infers the unit from name/niche).
const SHOW_UNIT_OF_SALE = true;
//
// 'simple'   = one price per product (RECOMMENDED; correct for one-per-item goods
//              like engines, tools, apparel — the "Sold as" line tells buyers what they get).
// 'variable' = build size/option variations with per-option prices the AI PROPOSES.
//              Use ONLY for catalogs genuinely sold in sizes (mg, ml, tablets, packs).
//              AI-proposed sizes/prices are DRAFTS — review before publishing.
const PRODUCT_TYPE = 'simple';
const VARIATION_ATTRIBUTE = 'Option';   // FALLBACK label only — the AI picks one per product (e.g. Dosage (mg), Volume (L))

// ---- Tags (capped to avoid tag-sprawl / thin archive pages) ----------------
const MAX_TAGS = 5;                       // hard cap on tags written per product
const TAGS_FROM_SECONDARY_KEYWORDS = false; // keep secondary keywords in the COPY, not as tags

// ---- Wholesale (bulk bundles) ----------------------------------------------
// Creates a "Wholesale" category and a handful of BUNDLE products that group
// items customers use together. The AI proposes the groupings; the script prices
// each bundle at the members' combined price minus WHOLESALE_DISCOUNT, and keeps
// only bundles priced at or above WHOLESALE_MIN_PRICE.
const CREATE_WHOLESALE      = true;
const WHOLESALE_CATEGORY    = 'Wholesale';
const WHOLESALE_MIN_BUNDLES = 5;          // target at least this many bundles
const WHOLESALE_MAX_BUNDLES = 10;         // never create more than this many
const WHOLESALE_MIN_PRICE   = 1000;       // each bundle must be priced at least this
const WHOLESALE_DISCOUNT    = 0.15;       // discount vs buying the items separately

// ---- Compliance ------------------------------------------------------------
const COMPLIANCE_MODE = false;
const DISCLAIMER_HTML = '<p><em>Always read the label and use products as directed. '
                      . 'Consult a qualified professional where appropriate.</em></p>';
const PROHIBITED_WORDS = '';             // comma-separated words the copy must avoid

// ---- Behavior --------------------------------------------------------------
const MAX_PRODUCTS_PER_RUN  = 0;         // 0 = all; e.g. 10 to do a batch then stop
const SELF_DELETE_WHEN_DONE = true;      // auto-delete this file once fully finished
const PRODUCT_STATUSES = ['publish', 'draft', 'pending', 'private'];

// ---- Lawful-use guard (keep true) ------------------------------------------
const ENABLE_LAWFUL_USE_GUARD = true;

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
header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset='utf-8'><title>SEO Optimizer</title>";
echo "<body style='font:14px/1.5 monospace;background:#111;color:#ddd;padding:20px'>";
echo str_repeat(' ', 4096);

function out($msg, $color = '#ddd') {
    echo "<div style='color:$color'>" . esc_html($msg) . "</div>\n";
    flush();
}
function brand() { return BRAND_NAME !== '' ? BRAND_NAME : get_bloginfo('name'); }

// ---------------------------------------------------------------------------
// AI provider (Gemini / Claude) over HTTP, with throttle + JSON-retry
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
            'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 8192,
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
    if ($code == 429) { return [null, '429']; }
    if ($code != 200) { return [null, "Claude $code: " . substr(wp_remote_retrieve_body($resp), 0, 180)]; }
    $j = json_decode(wp_remote_retrieve_body($resp), true);
    $text = '';
    foreach (($j['content'] ?? []) as $b) { if (($b['type'] ?? '') === 'text') { $text .= $b['text']; } }
    return [$text, ''];
}

function extract_json($text) {
    $text = trim((string) $text);
    $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $data = json_decode($text, true);
    if (is_array($data)) { return $data; }
    $s = strpos($text, '{'); $e = strrpos($text, '}');
    if ($s !== false && $e !== false && $e > $s) {
        $data = json_decode(substr($text, $s, $e - $s + 1), true);
        if (is_array($data)) { return $data; }
    }
    return null;
}

/** Returns [array|null, error_string]. Retries up to 3x on invalid JSON / 429. */
function ai_json($prompt) {
    for ($attempt = 0; $attempt < 3; $attempt++) {
        ai_throttle();
        [$text, $err] = (AI_PROVIDER === 'gemini') ? ai_text_gemini($prompt) : ai_text_claude($prompt);
        if ($err === '429') { sleep(30 * ($attempt + 1)); continue; }
        if ($err) { return [null, $err]; }
        $data = extract_json($text);
        if ($data !== null) { return [$data, '']; }
        // invalid JSON -> retry
    }
    return [null, 'invalid JSON after retries'];
}

// ---------------------------------------------------------------------------
// Prompt builders
// ---------------------------------------------------------------------------

function sales_clause($name) {
    if (!SALES_ORIENTED) { return 'Write informative, professional copy with natural keyword usage.'; }
    return "Write sales-oriented copy. Naturally weave in commercial-intent phrases such as "
        . "\"buy $name\", \"$name for sale\", \"order $name online\", \"best price\", and "
        . "\"shop $name\". Use the focus keyword in the first 50 words and again in an H2. "
        . "Use many relevant secondary keywords throughout so search crawlers index the page "
        . "quickly — but keep it readable and natural.";
}

function compliance_clause() {
    if (!COMPLIANCE_MODE) { return ''; }
    $banned = PROHIBITED_WORDS !== '' ? PROHIBITED_WORDS : '(none specified)';
    return "\nCOMPLIANCE MODE: do NOT use any of these words/claims: $banned. Avoid medical/health "
        . "claims, guarantees, or non-compliant superlatives. Keep claims factual and conservative.";
}

function html_quote_rule() {
    return "In all HTML you produce, use SINGLE quotes for attributes (e.g. <a href='...'>), "
        . "NEVER double quotes, and escape any double quotes in visible text — this keeps the JSON valid.";
}

function build_product_prompt($name, $cat_label, $cat_url, $related) {
    $lines = '';
    foreach ($related as $r) { $lines .= "  - \"{$r['name']}\" -> {$r['url']}\n"; }
    if ($lines === '') { $lines = "  (none)\n"; }
    $brand = brand();
    $cur = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
    $min = LONG_DESC_MIN_WORDS; $sw = SHORT_DESC_WORDS;
    $variations_req = (PRODUCT_TYPE === 'variable')
        ? "- attribute: the single measurement dimension this product is sold by, WITH its unit, "
          . "chosen to fit the product type — e.g. 'Dosage (mg)' for tablets, 'Volume (ml)' or "
          . "'Volume (L)' for liquids, 'Weight (g)' for powders/packs, 'Quantity' for whole items "
          . "like engines or tools.\n"
          . "- variations: 2-5 realistic purchase options as {\"label\",\"price\"}, where each label "
          . "is a VALUE in that attribute's unit (e.g. '10mg', '500ml', '5L', '250g', '2'); "
          . "price = plain number in $cur. Base sizes and prices on typical market offerings for this niche.\n"
        : '';
    $variations_json = (PRODUCT_TYPE === 'variable')
        ? ",\"attribute\":\"...\",\"variations\":[{\"label\":\"...\",\"price\":0}]" : '';

    return "You are an expert e-commerce SEO copywriter for $brand, a store selling " . STORE_NICHE . ".\n\n"
        . "Write complete, original SEO content for ONE product, based only on its title and category.\n\n"
        . "PRODUCT TITLE: \"$name\"\nCATEGORY: $cat_label\nCATEGORY PAGE URL: $cat_url\nCURRENCY: $cur\n\n"
        . "RELATED PRODUCTS you may reference and link to (same category):\n$lines\n"
        . "REQUIREMENTS:\n- " . sales_clause($name) . compliance_clause() . "\n"
        . "- The long description must be valid HTML, at least $min words, with: <h2> Overview, "
        . "<h2> Key Features / Benefits (a <ul> list), <h2> Specifications (a small <table> of inferred "
        . "attributes), <h2> Frequently Asked Questions (3 Q&As as <h3> question + <p> answer phrased as "
        . "long-tail keywords), and a closing call-to-action.\n"
        . "- Include 2-4 internal links to the RELATED PRODUCTS above as <a href='...'> tags, plus one link "
        . "to the CATEGORY PAGE URL.\n"
        . "- short_description: marketing HTML, about $sw words.\n"
        . "- meta_title <= 60 chars, ideally ending with \" | $brand\".\n"
        . "- meta_description <= 155 chars with the focus keyword.\n"
        . "- price: realistic AVERAGE MARKET PRICE as a plain number in $cur (no symbol).\n"
        . "- unit: exactly what ONE purchase includes (the unit of sale), inferred from the product "
        . "type, INCLUDING the measurement where applicable (mg, ml, g, L, or a count) — e.g. "
        . "'per 10 mg vial', 'per 500 ml bottle', 'per 250 g pack', 'per 5 L container', "
        . "'one complete engine'. Be conservative; if unsure use 'each (1 unit)'.\n"
        . $variations_req
        . "- " . html_quote_rule() . "\n\n"
        . "Return ONLY a JSON object with EXACTLY these keys (no markdown, no commentary):\n"
        . "{\"short_description\":\"<html>\",\"long_description\":\"<html>\",\"meta_title\":\"...\","
        . "\"meta_description\":\"...\",\"focus_keyword\":\"...\",\"secondary_keywords\":[\"...\"],"
        . "\"tags\":[\"...\"],\"price\":0,\"unit\":\"...\"" . $variations_json . "}";
}

function build_category_prompt($name, $parent_name, $is_sub) {
    $brand = brand();
    $w = CATEGORY_DESC_WORDS;
    $kind = $is_sub ? "subcategory (under \"$parent_name\")" : "top-level category";
    return "You are an expert e-commerce SEO copywriter for $brand, a store selling " . STORE_NICHE . ".\n\n"
        . "Write an SEO description for the product $kind named \"$name\".\n\n"
        . "REQUIREMENTS:\n- " . sales_clause($name) . compliance_clause() . "\n"
        . "- description: valid HTML, about $w words. Open with the focus keyword in the first sentence, "
        . "explain what this category covers, why buy here, and include an <h2> subheading. Do NOT invent "
        . "product names or links — links are added automatically.\n"
        . "- meta_title <= 60 chars ending with \" | $brand\".\n"
        . "- meta_description <= 155 chars with the focus keyword.\n"
        . "- " . html_quote_rule() . "\n\n"
        . "Return ONLY a JSON object with EXACTLY these keys (no markdown, no commentary):\n"
        . "{\"description\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\","
        . "\"focus_keyword\":\"...\",\"secondary_keywords\":[\"...\"]}";
}

// ---------------------------------------------------------------------------
// Interlink injectors (deterministic safety-net; idempotent)
// ---------------------------------------------------------------------------

function existing_hrefs($html) {
    preg_match_all('/href=["\']([^"\']+)["\']/', (string) $html, $m);
    return $m[1];
}

function inject_product_links($html, $related, $cat_url, $cat_name) {
    $related = array_values(array_filter($related, static fn($r) => !empty($r['url'])));
    $have = existing_hrefs($html);
    $missing = array_filter($related, fn($r) => !in_array($r['url'], $have, true));
    $need_cat = $cat_url && !in_array($cat_url, $have, true);
    if (!$missing && !$need_cat) { return $html; }
    $lis = '';
    foreach (array_slice($related, 0, INTERLINKS_PER_PRODUCT) as $r) {
        $lis .= '<li><a href="' . esc_url($r['url']) . '">' . esc_html($r['name']) . '</a></li>';
    }
    $block = $lis ? "\n<h2>Related Products</h2>\n<ul>$lis</ul>" : '';
    if ($need_cat) {
        $block .= "\n<p>Browse more in <a href=\"" . esc_url($cat_url) . '">' . esc_html($cat_name) . '</a>.</p>';
    }
    return $html . $block;
}

function inject_category_links($html, $products, $children, $parent) {
    $products = array_values(array_filter($products, static fn($r) => !empty($r['url'])));
    $children = array_values(array_filter($children, static fn($r) => !empty($r['url'])));
    if ($parent && empty($parent['url'])) { $parent = null; }
    $have = existing_hrefs($html);
    $block = '';
    $pl = '';
    foreach ($products as $r) {
        if (!in_array($r['url'], $have, true)) {
            $pl .= '<li><a href="' . esc_url($r['url']) . '">' . esc_html($r['name']) . '</a></li>';
        }
    }
    if ($pl) { $block .= "\n<h2>Shop This Category</h2>\n<ul>$pl</ul>"; }
    $cl = '';
    foreach ($children as $r) {
        if (!in_array($r['url'], $have, true)) {
            $cl .= '<li><a href="' . esc_url($r['url']) . '">' . esc_html($r['name']) . '</a></li>';
        }
    }
    if ($cl) { $block .= "\n<h3>Subcategories</h3>\n<ul>$cl</ul>"; }
    if ($parent && !in_array($parent['url'], $have, true)) {
        $block .= "\n<p>Part of <a href=\"" . esc_url($parent['url']) . '">' . esc_html($parent['name']) . '</a>.</p>';
    }
    return $html . $block;
}

function append_disclaimer($html) {
    if (COMPLIANCE_MODE && DISCLAIMER_HTML && strpos((string) $html, DISCLAIMER_HTML) === false) {
        return $html . "\n" . DISCLAIMER_HTML;
    }
    return $html;
}

function round_price($v) {
    $v = (float) $v;
    if ($v <= 0) { return ''; }
    return (strlen(PRICE_ENDING) && PRICE_ENDING[0] === '.') ? ((int) $v) . PRICE_ENDING : (string) round($v);
}

/**
 * Convert a product to a variable product with AI-PROPOSED draft variations.
 * $variations: array of ['label' => '10mg', 'price' => 145]. Returns true on success.
 */
function make_variable_product($pid, $attr_label, $variations) {
    if (!class_exists('WC_Product_Variable')) { return false; }
    $clean = [];
    foreach ($variations as $v) {
        $label = trim((string) ($v['label'] ?? ''));
        $price = round_price($v['price'] ?? 0);
        if ($label !== '' && $price !== '') { $clean[$label] = $price; }
    }
    if (!$clean) { return false; }

    wp_set_object_terms($pid, 'variable', 'product_type');

    $attribute = new WC_Product_Attribute();
    $attribute->set_id(0);                       // 0 = custom (per-product) attribute
    $attribute->set_name($attr_label);
    $attribute->set_options(array_keys($clean));
    $attribute->set_visible(true);
    $attribute->set_variation(true);

    $variable = new WC_Product_Variable($pid);
    $variable->set_attributes([$attribute]);
    $variable->save();

    $key = sanitize_title($attr_label);
    foreach ($clean as $label => $price) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($pid);
        $variation->set_attributes([$key => $label]);
        $variation->set_regular_price($price);
        $variation->set_status('publish');
        $variation->save();
    }
    WC_Product_Variable::sync($pid);
    return true;
}

function ensure_product_cat($name) {
    $t = get_term_by('name', $name, 'product_cat');
    if ($t && !is_wp_error($t)) { return (int) $t->term_id; }
    $res = wp_insert_term($name, 'product_cat');
    return is_wp_error($res) ? 0 : (int) $res['term_id'];
}

/** Eligible products for bundling: priced, not a bundle itself. Returns [['id','name','price','cat']]. */
function wholesale_catalog($ids) {
    $cat = [];
    foreach ($ids as $pid) {
        if (get_post_meta($pid, '_wholesale_bundle', true)) { continue; }
        $p = wc_get_product($pid);
        if (!$p) { continue; }
        $price = (float) $p->get_price();
        if ($price <= 0) { $price = (float) $p->get_regular_price(); }
        if ($price <= 0) { continue; }
        [$top, $sub] = product_primary_terms($pid);
        $cat[] = ['id' => $pid, 'name' => $p->get_name(), 'price' => $price,
                  'cat' => $sub ? $sub->name : ($top ? $top->name : '')];
    }
    return $cat;
}

function build_bundle_prompt($catalog, $threshold_independent) {
    $brand = brand();
    $lines = '';
    foreach (array_slice($catalog, 0, 150) as $c) {
        $lines .= '  - ' . $c['name'] . ' | price ' . number_format($c['price'], 2)
                . ($c['cat'] ? ' | ' . $c['cat'] : '') . "\n";
    }
    $min = WHOLESALE_MIN_BUNDLES; $max = WHOLESALE_MAX_BUNDLES;
    $disc = (int) round(WHOLESALE_DISCOUNT * 100);
    return "You are a merchandising expert for $brand, a store selling " . STORE_NICHE . ".\n\n"
        . "Product catalog (name | price | category):\n$lines\n"
        . "Propose between $min and $max WHOLESALE BUNDLES that group products customers would "
        . "naturally buy and use TOGETHER (complementary items, complete kits, common combinations).\n"
        . "Rules:\n"
        . "- Use ONLY product names exactly as written above.\n"
        . "- Each bundle has 2-6 items, with an optional quantity per item.\n"
        . "- Size each bundle so the members' COMBINED price is at least "
        . number_format($threshold_independent, 0) . " (so after a $disc% discount it still exceeds "
        . number_format(WHOLESALE_MIN_PRICE, 0) . ").\n"
        . "- Give each bundle a DESCRIPTIVE, marketable NAME that reflects what's inside "
        . "(e.g. 'Small Block 350 Complete Engine Build Kit'), not a generic label like 'Bundle 1'.\n"
        . "- Add a one-line rationale.\n\n"
        . "Return ONLY JSON: {\"bundles\":[{\"title\":\"...\",\"rationale\":\"...\","
        . "\"items\":[{\"name\":\"exact product name\",\"qty\":1}]}]}";
}

/** Match an AI-proposed item name to a real product id via a normalized lookup. */
function match_product($name, $lookup) {
    $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    return $lookup[$key] ?? 0;
}

/** Create one bundle simple-product from an AI proposal. Returns id, or 0 if it can't reach the floor. */
function create_bundle_product($bundle, $lookup, $wholesale_cat_id) {
    $items = (array) ($bundle['items'] ?? []);
    if (!$items) { return 0; }
    $title = trim((string) ($bundle['title'] ?? ''));

    $members = [];
    $independent = 0.0;
    foreach ($items as $it) {
        $pid = match_product($it['name'] ?? '', $lookup);
        if (!$pid) { continue; }
        $p = wc_get_product($pid);
        if (!$p) { continue; }
        $price = (float) $p->get_price();
        if ($price <= 0) { $price = (float) $p->get_regular_price(); }
        if ($price <= 0) { continue; }
        $qty = max(1, (int) ($it['qty'] ?? 1));
        $members[] = ['id' => $pid, 'name' => $p->get_name(), 'qty' => $qty, 'price' => $price];
        $independent += $price * $qty;
    }
    if (count($members) < 2) { return 0; }                          // need a real combo

    $bundle_price = round_price($independent * (1 - WHOLESALE_DISCOUNT));
    if ($bundle_price === '' || (float) $bundle_price < WHOLESALE_MIN_PRICE) { return 0; } // below floor

    if ($title === '') {                                   // fallback descriptive name from members
        $names = array_map(static fn($m) => $m['name'], array_slice($members, 0, 3));
        $title = implode(' + ', $names) . ' Wholesale Bundle';
    }

    $bid = (int) wp_insert_post(['post_title' => $title, 'post_status' => 'publish', 'post_type' => 'product']);
    if (!$bid) { return 0; }
    wp_set_object_terms($bid, [$wholesale_cat_id], 'product_cat');
    wp_set_object_terms($bid, 'simple', 'product_type');

    $sym  = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    $disc = (int) round(WHOLESALE_DISCOUNT * 100);
    $li = '';
    foreach ($members as $m) {
        $li .= '<li>' . ($m['qty'] > 1 ? $m['qty'] . ' &times; ' : '')
             . '<a href="' . esc_url(get_permalink($m['id'])) . '">' . esc_html($m['name']) . '</a></li>';
    }
    $rationale = trim((string) ($bundle['rationale'] ?? ''));
    $desc = ($rationale ? '<p>' . esc_html($rationale) . '</p>' : '')
        . '<h2>What\'s in this wholesale bundle</h2><ul>' . $li . '</ul>'
        . '<p>Get this complete bundle for <strong>' . $sym . $bundle_price . '</strong> — about '
        . $disc . '% less than the ' . $sym . number_format($independent, 2)
        . ' it would cost to buy these items separately.</p>';

    $b = wc_get_product($bid);
    $b->set_description($desc);
    $b->set_short_description('<p>Wholesale bundle — ' . $disc . '% off vs buying the items separately. '
        . 'Sold as one complete kit.</p>');
    $b->set_regular_price($bundle_price);
    $b->set_catalog_visibility('visible');
    $b->save();

    update_post_meta($bid, '_wholesale_bundle', 1);
    update_post_meta($bid, '_bundle_members', wp_json_encode(array_column($members, 'id')));
    return $bid;
}

function product_primary_terms($pid) {
    $top = null; $sub = null;
    $terms = get_the_terms($pid, 'product_cat');
    if (is_array($terms)) {
        foreach ($terms as $t) {
            if ($t->parent && !$sub) { $sub = $t; }
            if (!$t->parent && !$top) { $top = $t; }
        }
        if (!$top && $terms) { $top = $terms[0]; }
    }
    return [$top, $sub];
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

out('== WooCommerce SEO Optimizer ==  provider=' . AI_PROVIDER, '#6cf');

$ids = wc_get_products(['status' => PRODUCT_STATUSES, 'limit' => -1, 'return' => 'ids']);
out('Found ' . count($ids) . ' products');

// Lawful-use guard (scan names + categories)
if (ENABLE_LAWFUL_USE_GUARD) {
    $hay = '';
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if ($p) { $hay .= strtolower($p->get_name()) . ' '; }
    }
    foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]) as $t) {
        $hay .= strtolower($t->name) . ' ';
    }
    $hits = array_values(array_unique(array_filter($GUARD_TERMS, fn($t) => strpos($hay, $t) !== false)));
    if ($hits) {
        out('[ABORTED] Lawful-use guard triggered: ' . implode(', ', $hits), '#f66');
        out('This tool is for lawful product catalogs only.', '#f66');
        exit;
    }
}

// Build name/term index + related-product groups
$meta = [];
$groups = [];
foreach ($ids as $pid) {
    $p = wc_get_product($pid);
    if (!$p) { continue; }
    [$top, $sub] = product_primary_terms($pid);
    $key = $sub ? 'sub:' . $sub->term_id : ($top ? 'top:' . $top->term_id : 'none');
    $meta[$pid] = ['name' => $p->get_name(), 'top' => $top, 'sub' => $sub, 'key' => $key];
    $groups[$key][] = $pid;
}
function related_links($pid, $meta, $groups) {
    $out = [];
    foreach (($groups[$meta[$pid]['key']] ?? []) as $other) {
        if ($other == $pid) { continue; }
        $out[] = ['name' => $meta[$other]['name'], 'url' => get_permalink($other)];
        if (count($out) >= INTERLINKS_PER_PRODUCT) { break; }
    }
    return $out;
}

$processed = 0;
$batched_out = false;

// ---- PHASE 1: PRODUCTS -----------------------------------------------------
if (GENERATE_PRODUCT_CONTENT || ADD_INTERLINKS) {
    out("\n--- Products ---", '#6cf');
    $i = 0; $total = count($ids);
    foreach ($ids as $pid) {
        $i++;
        @set_time_limit(0);
        $p = wc_get_product($pid);
        if (!$p) { continue; }
        if (get_post_meta($pid, '_wholesale_bundle', true)) { continue; } // skip wholesale bundles
        $name = $p->get_name();
        $has = trim($p->get_description()) !== '';
        $do_ai = GENERATE_PRODUCT_CONTENT && (FORCE_REGENERATE || !$has);

        if (!$do_ai && !ADD_INTERLINKS) {
            out("[$i/$total] skip (has content): $name", '#888');
            continue;
        }

        $top = $meta[$pid]['top']; $sub = $meta[$pid]['sub'];
        $catTerm = $sub ?: $top;
        $cat_label = ($top ? $top->name : '') . ($sub ? ' > ' . $sub->name : '');
        $cat_url = $catTerm ? get_term_link($catTerm) : '';
        if (is_wp_error($cat_url)) { $cat_url = ''; }
        $related = related_links($pid, $meta, $groups);

        out("[$i/$total] $name" . ($do_ai ? '' : '  (interlinks only)'));

        if ($do_ai) {
            [$data, $err] = ai_json(build_product_prompt($name, $cat_label, $cat_url, $related));
            if (!$data) { out("   [skip] $err", '#f66'); continue; }

            // Optional: build DRAFT variable-product variations first (changes type).
            $made_variable = false;
            if (PRODUCT_TYPE === 'variable' && !empty($data['variations']) && is_array($data['variations'])) {
                $attr_label = trim((string) ($data['attribute'] ?? '')) ?: VARIATION_ATTRIBUTE;
                $made_variable = make_variable_product($pid, $attr_label, $data['variations']);
            }
            $p = wc_get_product($pid); // re-fetch — product type may have changed

            $long = (string) ($data['long_description'] ?? '');
            if (ADD_INTERLINKS) { $long = inject_product_links($long, $related, $cat_url, $catTerm ? $catTerm->name : ''); }
            $long = append_disclaimer($long);
            $p->set_description($long);

            // short description + a clear "Sold as: <unit>" line next to the price
            $short = (string) ($data['short_description'] ?? '');
            $unit  = trim((string) ($data['unit'] ?? ''));
            if ($unit !== '') { update_post_meta($pid, '_unit_of_sale', $unit); }
            if (SHOW_UNIT_OF_SALE && $unit !== '' && stripos($short, 'sold as') === false) {
                $short .= "\n<p><strong>Sold as:</strong> " . esc_html($unit) . '.</p>';
            }
            if ($short !== '') { $p->set_short_description($short); }

            // simple price (variable products carry their prices on the variations)
            if (!$made_variable && SET_PRICE_IF_EMPTY && (FORCE_REGENERATE || $p->get_regular_price() === '')) {
                $price = round_price($data['price'] ?? 0);
                if ($price !== '') { $p->set_regular_price($price); }
            }
            $p->save();

            // Rank Math meta
            if (!empty($data['meta_title']))      { update_post_meta($pid, 'rank_math_title', mb_substr((string) $data['meta_title'], 0, 70)); }
            if (!empty($data['meta_description'])) { update_post_meta($pid, 'rank_math_description', mb_substr((string) $data['meta_description'], 0, 160)); }
            if (!empty($data['focus_keyword']))    { update_post_meta($pid, 'rank_math_focus_keyword', (string) $data['focus_keyword']); }

            // Tags (capped; secondary keywords stay in the COPY, not as tags)
            $tags = array_values(array_unique(array_filter(array_map('trim', (array) ($data['tags'] ?? [])))));
            if (TAGS_FROM_SECONDARY_KEYWORDS) {
                $tags = array_values(array_unique(array_merge($tags,
                    array_filter(array_map('trim', (array) ($data['secondary_keywords'] ?? []))))));
            }
            $tags = array_slice($tags, 0, MAX_TAGS);
            if ($tags) {
                $existing = wp_get_object_terms($pid, 'product_tag', ['fields' => 'names']);
                wp_set_object_terms($pid, array_values(array_unique(array_merge($existing, $tags))), 'product_tag', false);
            }

            out('   [ok] content written' . ($made_variable ? ' + DRAFT variations (review prices/sizes)' : ''), '#6f6');
            $processed++;
        } else {
            // interlink-only: inject into existing description
            $long = inject_product_links($p->get_description(), $related, $cat_url, $catTerm ? $catTerm->name : '');
            $long = append_disclaimer($long);
            $p->set_description($long);
            $p->save();
            out("   [ok] interlinks added", '#6f6');
        }

        if (MAX_PRODUCTS_PER_RUN && $processed >= MAX_PRODUCTS_PER_RUN) {
            out("\n[batch] reached MAX_PRODUCTS_PER_RUN=" . MAX_PRODUCTS_PER_RUN . '; stopping. Refresh to continue.', '#6cf');
            $batched_out = true;
            break;
        }
    }
}

// ---- PHASE 2: WHOLESALE BUNDLES --------------------------------------------
if (!$batched_out && CREATE_WHOLESALE) {
    out("\n--- Wholesale bundles ---", '#6cf');
    $wcat = ensure_product_cat(WHOLESALE_CATEGORY);
    if (!$wcat) {
        out('   [warn] could not create the Wholesale category', '#fa0');
    } else {
        $existing = get_posts(['post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1,
            'fields' => 'ids', 'meta_key' => '_wholesale_bundle', 'meta_value' => 1]);
        if ($existing && !FORCE_REGENERATE) {
            out('   ' . count($existing) . ' wholesale bundles already exist — skipping. '
              . 'Set FORCE_REGENERATE = true to rebuild them.', '#888');
        } else {
            if ($existing && FORCE_REGENERATE) {
                foreach ($existing as $eid) { wp_delete_post($eid, true); }
                out('   removed ' . count($existing) . ' old bundles (force rebuild)');
            }
            $catalog = wholesale_catalog($ids);
            if (count($catalog) < 2) {
                out('   [skip] not enough priced products to build bundles.', '#fa0');
            } else {
                $threshold = (int) ceil(WHOLESALE_MIN_PRICE / (1 - WHOLESALE_DISCOUNT));
                $lookup = [];
                foreach ($catalog as $c) {
                    $lookup[strtolower(trim(preg_replace('/\s+/', ' ', $c['name'])))] = $c['id'];
                }
                [$data, $err] = ai_json(build_bundle_prompt($catalog, $threshold));
                $bundles = is_array($data) ? array_slice((array) ($data['bundles'] ?? []), 0, WHOLESALE_MAX_BUNDLES) : [];
                if (!$bundles) {
                    out('   [skip] AI proposed no bundles' . ($err ? " ($err)" : '') . '.', '#fa0');
                } else {
                    $made = 0;
                    foreach ($bundles as $bn) {
                        @set_time_limit(0);
                        $bid = create_bundle_product($bn, $lookup, $wcat);
                        if ($bid) { $made++; out('   [ok] bundle: ' . (string) ($bn['title'] ?? ''), '#6f6'); }
                        else      { out('   [skip] below $' . WHOLESALE_MIN_PRICE . ' or unmatched items: '
                                      . (string) ($bn['title'] ?? ''), '#fa0'); }
                    }
                    out("   created $made wholesale bundles"
                      . ($made < WHOLESALE_MIN_BUNDLES ? ' (fewer than the target ' . WHOLESALE_MIN_BUNDLES . ')' : ''), '#6cf');
                }
            }
        }
    }
}

// ---- PHASE 3: CATEGORIES & SUBCATEGORIES -----------------------------------
if (!$batched_out && (GENERATE_CATEGORY_CONTENT || ADD_INTERLINKS)) {
    out("\n--- Categories & Subcategories ---", '#6cf');
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    $i = 0; $total = is_array($terms) ? count($terms) : 0;
    foreach ($terms as $t) {
        $i++;
        @set_time_limit(0);
        $has = trim((string) $t->description) !== '';
        $do_ai = GENERATE_CATEGORY_CONTENT && (FORCE_REGENERATE || !$has);
        if (!$do_ai && !ADD_INTERLINKS) { out("[$i/$total] skip (has desc): {$t->name}", '#888'); continue; }

        // gather interlink targets
        $parent = null;
        if ($t->parent) {
            $pt = get_term($t->parent, 'product_cat');
            if ($pt && !is_wp_error($pt)) {
                $lk = get_term_link($pt);
                $parent = ['name' => $pt->name, 'url' => is_wp_error($lk) ? '' : $lk];
            }
        }
        $children = [];
        foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $t->term_id]) as $c) {
            $lk = get_term_link($c);
            $children[] = ['name' => $c->name, 'url' => is_wp_error($lk) ? '' : $lk];
        }
        $prod_links = [];
        $pids = get_posts(['post_type' => 'product', 'posts_per_page' => PRODUCT_LINKS_IN_CATEGORY,
            'fields' => 'ids', 'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id]]]);
        foreach ($pids as $pp) { $prod_links[] = ['name' => get_the_title($pp), 'url' => get_permalink($pp)]; }

        out("[$i/$total] {$t->name}" . ($do_ai ? '' : '  (interlinks only)'));

        if ($do_ai) {
            [$data, $err] = ai_json(build_category_prompt($t->name, $parent ? $parent['name'] : '', (bool) $t->parent));
            if (!$data) { out("   [skip] $err", '#f66'); continue; }
            $desc = (string) ($data['description'] ?? '');
            if (ADD_INTERLINKS) { $desc = inject_category_links($desc, $prod_links, $children, $parent); }
            $desc = append_disclaimer($desc);
            wp_update_term($t->term_id, 'product_cat', ['description' => $desc]);
            if (!empty($data['meta_title']))      { update_term_meta($t->term_id, 'rank_math_title', mb_substr((string) $data['meta_title'], 0, 70)); }
            if (!empty($data['meta_description'])) { update_term_meta($t->term_id, 'rank_math_description', mb_substr((string) $data['meta_description'], 0, 160)); }
            if (!empty($data['focus_keyword']))    { update_term_meta($t->term_id, 'rank_math_focus_keyword', (string) $data['focus_keyword']); }
            out('   [ok] description written', '#6f6');
        } else {
            $desc = inject_category_links((string) $t->description, $prod_links, $children, $parent);
            $desc = append_disclaimer($desc);
            wp_update_term($t->term_id, 'product_cat', ['description' => $desc]);
            out('   [ok] interlinks added', '#6f6');
        }
    }
}

out("\nDone. Generated content for $processed products" . ($batched_out ? ' (batch limit hit).' : '.'), '#6cf');

if (!$batched_out && SELF_DELETE_WHEN_DONE) {
    if (@unlink(__FILE__)) {
        out('This file has deleted itself from the server. Fully done. ✅', '#6f6');
    } else {
        out('Could not auto-delete — please delete this file manually now.', '#fa0');
    }
} else {
    out('>>> Remember to DELETE this file from the server when fully finished. <<<', '#fa0');
}
echo "</body>";
