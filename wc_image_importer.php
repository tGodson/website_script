<?php
/**
 * wc_image_importer.php
 * =====================
 * Drop-in WordPress-root image importer (the "upload + open URL" approach).
 *
 * It runs INSIDE WordPress (loads wp-load.php), so it needs NO username /
 * password / Application Password. Instead it is protected by a secret token
 * in the URL. For each product it:
 *   - generates an image with the Ideogram API (prompt from name + niche + style)
 *   - sideloads it into the Media Library
 *   - sets it as the product's FEATURED image
 *   - sets the image ALT TEXT (from the CSV "Image alt text" column, else auto)
 * Progress streams live to the browser.
 *
 * --------------------------------------------------------------------------
 * HOW TO USE
 * --------------------------------------------------------------------------
 * 1) Edit the CONFIG block below: SECRET, IDEOGRAM_API_KEY, STORE_NICHE, IMAGE_STYLE.
 * 2) Upload JUST THIS ONE FILE to the WordPress root (public_html/ on Hostinger).
 *    No CSV needed — by default it reads your products straight from WooCommerce.
 * 3) Visit in your browser:
 *      Local:  http://yoursite.local/wc_image_importer.php           (no key needed)
 *      Public: https://yourdomain/wc_image_importer.php?key=YOUR_SECRET
 *              (on a public/live host a key is ALWAYS required — set SECRET below)
 * 4) Watch it run. If it times out, just refresh — it resumes (products that
 *    already have a featured image are skipped).
 * 5) When every product is done it DELETES ITSELF (SELF_DELETE_WHEN_DONE = true).
 *    Hands-off: upload, run, gone.
 *
 * (Optional) To drive it from a CSV instead, set CSV_FILE to the filename and
 * upload that CSV next to this file; alt text then comes from the CSV.
 *
 * ALLOWED USE: lawful product catalogs only.
 */

// #############################################################################
// #                          C O N F I G U R A T I O N                        #
// #############################################################################

// On a LOCAL site (.local / localhost) no token is needed — just open the URL.
// On a PUBLIC/live host a token is ALWAYS required (auto-enforced) — set SECRET
// and pass it as ?key=THIS. Set REQUIRE_SECRET = true to also force it on local.
const REQUIRE_SECRET = true;
const SECRET = 'Atk9Engines4271Xz';  // letters & numbers ONLY — symbols like # & % break the URL

// Ideogram API key.
const IDEOGRAM_API_KEY = '';  // get one at https://ideogram.ai
const IDEOGRAM_MODEL    = 'V_2';
const IDEOGRAM_ASPECT   = 'ASPECT_1_1';

// '' = read products DIRECTLY from WooCommerce (no CSV — the hands-off default).
// Set a filename only if you want to drive it from a CSV uploaded next to this file.
const CSV_FILE = '';

// What the products are (steers the image prompt) + the look you want.
const STORE_NICHE = 'High performance engines';
const IMAGE_STYLE = 'clean professional product photograph on a pure white background, '
                  . 'studio softbox lighting, sharp focus, high detail, no text, no watermark';

// ---- Logo watermark (overlays YOUR real logo after generation) -------------
// The image generator does NOT add your logo — this step does. Use a TRANSPARENT
// PNG (GD cannot read SVG; convert your .svg logo to .png first).
const WATERMARK_LOGO    = '';     // 'logo.png' next to this file, or an absolute path; '' = off
const WATERMARK_OPACITY = 0.55;   // 0..1 — lower = more see-through; tune for contrast
const WATERMARK_SCALE   = 0.20;   // logo width as a fraction of the image width
const WATERMARK_MARGIN  = 24;     // pixels in from the bottom-right corner

// Behavior
const SKIP_IF_HAS_IMAGE     = true;  // resume: skip products that already have a featured image
const MAX_PRODUCTS_PER_RUN  = 5;     // 0 = all; e.g. 10 to do a batch then stop
const SELF_DELETE_WHEN_DONE = true;  // auto-delete this file once every product is processed
// Which products to image when reading directly from WooCommerce:
const PRODUCT_STATUSES = ['publish', 'draft', 'pending', 'private'];

// CSV column headers (WooCommerce export defaults)
const COL_ID        = 'ID';
const COL_SKU       = 'SKU';
const COL_NAME      = 'Name';
const COL_CATEGORIES = 'Categories';
const COL_IMAGE_ALT = 'Image alt text';

// Lawful-use guard (keep true)
const ENABLE_LAWFUL_USE_GUARD = true;  // true = abort if product names/categories contain illegal terms

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
// No token needed on a local site; always required on a public/live host.
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host_noport = preg_replace('/:\d+$/', '', $host);   // strip any :port
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
    for ($i = 0; $i < 6 && !file_exists($dir . '/wp-load.php'); $i++) {
        $dir = dirname($dir);
    }
    $wp_load = $dir . '/wp-load.php';
}
if (!file_exists($wp_load)) {
    http_response_code(500);
    exit('Could not locate wp-load.php — place this file in the WordPress root.');
}
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

if (!function_exists('wc_get_product')) {
    http_response_code(500);
    exit('WooCommerce is not active on this site.');
}

// ---- Stream output to the browser -----------------------------------------
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);
ignore_user_abort(true);

header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset='utf-8'><title>Image Importer</title>";
echo "<body style='font:14px/1.5 monospace;background:#111;color:#ddd;padding:20px'>";
echo str_repeat(' ', 4096); // pad so browsers flush early

function out($msg, $color = '#ddd') {
    echo "<div style='color:$color'>" . esc_html($msg) . "</div>\n";
    flush();
}

// ---- Helpers ---------------------------------------------------------------

function parse_categories($raw) {
    $top = ''; $sub = '';
    foreach (array_filter(array_map('trim', explode(',', (string) $raw))) as $part) {
        if (strpos($part, '>') !== false) {
            $bits = array_map('trim', explode('>', $part));
            return [$bits[0], end($bits)];
        }
        if ($top === '') { $top = $part; }
    }
    return [$top, $sub];
}

function build_prompt($name, $top, $sub) {
    $cat = ($sub ?: $top);
    $cat = $cat ? " ($cat)" : '';
    return $name . $cat . ', ' . STORE_NICHE . '. ' . IMAGE_STYLE . '.';
}

function ideogram_image_url($prompt) {
    if (IDEOGRAM_API_KEY === '') { return [null, 'IDEOGRAM_API_KEY is empty']; }
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $resp = wp_remote_post('https://api.ideogram.ai/generate', [
            'headers' => ['Api-Key' => IDEOGRAM_API_KEY, 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['image_request' => [
                'prompt' => $prompt, 'model' => IDEOGRAM_MODEL,
                'magic_prompt_option' => 'OFF', 'style_type' => 'REALISTIC',
                'aspect_ratio' => IDEOGRAM_ASPECT,
            ]]),
            'timeout' => 120,
        ]);
        if (is_wp_error($resp)) { return [null, $resp->get_error_message()]; }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code == 429) { sleep(30 * ($attempt + 1)); continue; }
        if ($code != 200) { return [null, "Ideogram $code: " . substr(wp_remote_retrieve_body($resp), 0, 180)]; }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $url  = $data['data'][0]['url'] ?? '';
        return $url ? [$url, ''] : [null, 'no image URL in response'];
    }
    return [null, 'rate-limited, gave up'];
}

function imagecopymerge_alpha($dst, $src, $dx, $dy, $sx, $sy, $sw, $sh, $opacity) {
    $cut = imagecreatetruecolor($sw, $sh);
    imagecopy($cut, $dst, 0, 0, $dx, $dy, $sw, $sh);
    imagecopy($cut, $src, 0, 0, $sx, $sy, $sw, $sh);   // respects the logo's own alpha
    imagecopymerge($dst, $cut, $dx, $dy, 0, 0, $sw, $sh, $opacity);
}

/** Overlay the configured logo (bottom-right) onto the image at $path. Rewrites $path as PNG. */
function watermark_image($path) {
    $logo_path = (substr(WATERMARK_LOGO, 0, 1) === '/') ? WATERMARK_LOGO : __DIR__ . '/' . WATERMARK_LOGO;
    if (!file_exists($logo_path)) { out('   [watermark] logo not found: ' . $logo_path, '#fa0'); return false; }

    // Preferred: Imagick (clean opacity; reads PNG, and SVG if the delegate is present)
    if (class_exists('Imagick')) {
        try {
            $img = new Imagick($path);
            $img->setImageFormat('png');
            $logo = new Imagick($logo_path);
            $tw = max(1, (int) round($img->getImageWidth() * WATERMARK_SCALE));
            $logo->resizeImage($tw, 0, Imagick::FILTER_LANCZOS, 1);
            if (WATERMARK_OPACITY < 1) {
                $logo->evaluateImage(Imagick::EVALUATE_MULTIPLY, (float) WATERMARK_OPACITY, Imagick::CHANNEL_ALPHA);
            }
            $x = max(0, $img->getImageWidth()  - $logo->getImageWidth()  - WATERMARK_MARGIN);
            $y = max(0, $img->getImageHeight() - $logo->getImageHeight() - WATERMARK_MARGIN);
            $img->compositeImage($logo, Imagick::COMPOSITE_OVER, $x, $y);
            $img->writeImage($path);
            $logo->clear(); $img->clear();
            return true;
        } catch (\Throwable $e) {
            out('   [watermark] Imagick error (' . $e->getMessage() . '); falling back to GD', '#fa0');
        }
    }

    // Fallback: GD (PNG logo only)
    if (strtolower(pathinfo($logo_path, PATHINFO_EXTENSION)) !== 'png') {
        out('   [watermark] GD needs a PNG logo (got .' . pathinfo($logo_path, PATHINFO_EXTENSION) . ')', '#fa0');
        return false;
    }
    $base = @imagecreatefromstring(@file_get_contents($path));
    $logo = @imagecreatefrompng($logo_path);
    if (!$base || !$logo) { return false; }
    imagealphablending($base, true);
    $bw = imagesx($base); $bh = imagesy($base);
    $lw = imagesx($logo); $lh = imagesy($logo);
    $tw = max(1, (int) round($bw * WATERMARK_SCALE));
    $th = max(1, (int) round($lh * $tw / $lw));
    $scaled = imagecreatetruecolor($tw, $th);
    imagealphablending($scaled, false);
    imagesavealpha($scaled, true);
    imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
    imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $tw, $th, $lw, $lh);
    $x = max(0, $bw - $tw - WATERMARK_MARGIN);
    $y = max(0, $bh - $th - WATERMARK_MARGIN);
    imagecopymerge_alpha($base, $scaled, $x, $y, 0, 0, $tw, $th, (int) round(WATERMARK_OPACITY * 100));
    imagepng($base, $path);
    return true;
}

function attach_image_to_product($image_url, $pid, $alt, $name) {
    $tmp = download_url($image_url, 120);
    if (is_wp_error($tmp)) { return [0, $tmp->get_error_message()]; }
    $ext = strtolower(pathinfo((string) parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) { $ext = 'png'; }
    if (WATERMARK_LOGO !== '' && watermark_image($tmp)) { $ext = 'png'; } // re-encoded as PNG
    $file = ['name' => sanitize_title($name) . '-' . $pid . '.' . $ext, 'tmp_name' => $tmp];
    $att_id = media_handle_sideload($file, $pid, $name);
    if (is_wp_error($att_id)) {
        if (file_exists($tmp)) { @unlink($tmp); }
        return [0, $att_id->get_error_message()];
    }
    set_post_thumbnail($pid, $att_id);
    update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
    return [$att_id, ''];
}

function resolve_pid($id, $sku) {
    $id = trim((string) $id);
    if ($id !== '' && get_post_type($id) === 'product') { return (int) $id; }
    $sku = trim((string) $sku);
    if ($sku !== '') {
        $pid = wc_get_product_id_by_sku($sku);
        if ($pid) { return (int) $pid; }
    }
    return 0;
}

/** Returns list of items: ['pid','name','top','sub','alt']. */
function load_items() {
    $items = [];
    if (CSV_FILE !== '' && file_exists(__DIR__ . '/' . CSV_FILE)) {
        $fh = fopen(__DIR__ . '/' . CSV_FILE, 'r');
        $header = fgetcsv($fh);
        if ($header === false) { fclose($fh); return []; } // empty CSV
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip BOM
        $idx = array_flip($header);
        while (($r = fgetcsv($fh)) !== false) {
            $get = function ($col) use ($r, $idx) {
                return isset($idx[$col]) && isset($r[$idx[$col]]) ? trim($r[$idx[$col]]) : '';
            };
            $name = $get(COL_NAME);
            if ($name === '') { continue; }
            $pid = resolve_pid($get(COL_ID), $get(COL_SKU));
            [$top, $sub] = parse_categories($get(COL_CATEGORIES));
            $alt = $get(COL_IMAGE_ALT) ?: ($name . ' product image');
            $items[] = compact('pid', 'name', 'top', 'sub', 'alt') + ['source' => 'csv'];
        }
        fclose($fh);
        return $items;
    }
    // Default: read products straight from WooCommerce (no CSV).
    $ids = wc_get_products(['status' => PRODUCT_STATUSES, 'limit' => -1, 'return' => 'ids']);
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) { continue; }
        $top = ''; $sub = '';
        $terms = get_the_terms($pid, 'product_cat');
        if (is_array($terms)) {
            foreach ($terms as $t) {
                if ($t->parent && $sub === '') { $sub = $t->name; }
                if (!$t->parent && $top === '') { $top = $t->name; }
            }
            if ($top === '') { $top = $terms[0]->name; }
        }
        $items[] = ['pid' => (int) $pid, 'name' => $p->get_name(),
                    'top' => $top, 'sub' => $sub, 'alt' => $p->get_name() . ' product image',
                    'source' => 'db'];
    }
    return $items;
}

// ---- Run -------------------------------------------------------------------
out('== WooCommerce Image Importer ==', '#6cf');
$items = load_items();
out('Loaded ' . count($items) . ' products from ' . (CSV_FILE && file_exists(__DIR__ . '/' . CSV_FILE) ? CSV_FILE : 'WooCommerce DB'));

if (ENABLE_LAWFUL_USE_GUARD) {
    $hay = '';
    foreach ($items as $it) { $hay .= strtolower($it['name'] . ' ' . $it['top'] . ' ' . $it['sub']) . ' '; }
    $hits = [];
    foreach ($GUARD_TERMS as $t) { if (strpos($hay, $t) !== false) { $hits[] = $t; } }
    if ($hits) {
        out('[ABORTED] Lawful-use guard triggered: ' . implode(', ', array_unique($hits)), '#f66');
        out('This tool is for lawful product catalogs only.', '#f66');
        exit;
    }
}

$done = 0; $i = 0; $total = count($items); $batched_out = false;
foreach ($items as $it) {
    $i++;
    @set_time_limit(0); // reset the timer each product so long runs don't die
    $pid = $it['pid'];
    if (!$pid) { out("[$i/$total] [skip] no product match: " . $it['name'], '#fa0'); continue; }

    if (SKIP_IF_HAS_IMAGE && has_post_thumbnail($pid)) {
        out("[$i/$total] skip (already has image): " . $it['name'], '#888');
        continue;
    }

    out("[$i/$total] " . $it['name'] . "  (product $pid)");
    [$url, $err] = ideogram_image_url(build_prompt($it['name'], $it['top'], $it['sub']));
    if (!$url) { out("   [skip] image failed: $err", '#f66'); continue; }

    [$att, $err2] = attach_image_to_product($url, $pid, $it['alt'], $it['name']);
    if (!$att) { out("   [error] attach failed: $err2", '#f66'); continue; }

    out("   [ok] attached media $att (alt: " . $it['alt'] . ')', '#6f6');
    $done++;
    if (MAX_PRODUCTS_PER_RUN && $done >= MAX_PRODUCTS_PER_RUN) {
        out("\n[batch] reached MAX_PRODUCTS_PER_RUN=" . MAX_PRODUCTS_PER_RUN . '; stopping. Refresh to continue.', '#6cf');
        $batched_out = true;
        break;
    }
}

out("\nDone. Attached images to $done products.", '#6cf');

if (!$batched_out && SELF_DELETE_WHEN_DONE) {
    if (@unlink(__FILE__)) {
        out('This file has deleted itself from the server. Fully done. ✅', '#6f6');
    } else {
        out('Could not auto-delete (file permissions) — please delete this file manually now.', '#fa0');
    }
} else {
    out('>>> Remember to DELETE this file from the server when fully finished. <<<', '#fa0');
}
echo "</body>";
