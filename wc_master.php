<?php
/**
 * wc_master.php — one drop-in WordPress-root tool for the whole SEO build.
 * =======================================================================
 * Upload to the site root, open the URL, it does exactly what you toggle ON,
 * then deletes itself. Everything is controlled from the CONFIG block below.
 *
 * HOW TO RUN — upload this file to the WordPress root (next to wp-load.php), then
 * open it in your browser:
 *   Live / public :  https://your-site.com/wc_master.php?key=YOUR_SECRET
 *   Temp / staging:  https://temp123.hostinger.dev/wc_master.php?key=YOUR_SECRET
 *   Local         :  http://your-site.local/wc_master.php        (no key needed)
 *   The ?key= value must EXACTLY match SECRET below and be letters/numbers only
 *   (no # & % symbols — those break the URL). It self-deletes when finished;
 *   re-upload to run again.
 *
 * PRODUCTS (one AI call per product covers every enabled field):
 *   - short description, long description (append or overwrite)
 *   - price (simple or AI-proposed variable variations)
 *   - Rank Math meta (title / description / focus keyword)
 *   - tags, product image (Ideogram, optional logo watermark)
 *   - internal interlinks (deterministic — no AI)
 *   - categories: create SEO use-based categories and group products into them
 *     (auto, or from your own list) + category descriptions with interlinks
 * PAGES (after products, so they read the finished catalog):
 *   - homepage (Flatsome UX Builder or HTML), contact, privacy, terms,
 *     shipping, refund/returns, FAQ, about, and N blog posts.
 *
 * Every AI text is humanized (strict anti-AI-voice rules + banned words).
 * Reads each product's title + short + long + category for context.
 *
 * ALLOWED USE: lawful catalogs only. Legal pages are AI drafts, not legal advice.
 */

// #############################################################################
// #                          C O N F I G U R A T I O N                        #
// #############################################################################

// ---- Access (local: no token; public/live: token auto-required) ------------
const REQUIRE_SECRET = true;                 // live = keep true
const SECRET = '1234';   // ?key=THIS  (no # & % symbols)

// ---- AI provider -----------------------------------------------------------
const AI_PROVIDER = 'openai';                // 'gemini' (free) | 'claude' | 'openai' (ChatGPT)
const GEMINI_API_KEY = '';                   // https://aistudio.google.com/apikey
const GEMINI_MODEL   = 'gemini-2.0-flash';
const GEMINI_RPM     = 10;
const ANTHROPIC_API_KEY = '';                // https://console.anthropic.com  <-- paste your Claude key here
const CLAUDE_MODEL      = 'claude-haiku-4-5';  // fast + high rate limits, ideal for bulk. (Opus = 'claude-opus-4-8' if you want top quality)
const CLAUDE_RPM        = 0;                   // 0 = NO throttle (full speed, like the old script — fine for Haiku/Sonnet). Set ~5 ONLY if you use Opus and hit rate limits
const CLAUDE_MAX_TOKENS = 4096;              // max output tokens per call. Smaller = fewer rate-limit hits; RAISE it if long descriptions get cut off
const OPENAI_API_KEY = '';                   // https://platform.openai.com/api-keys  <-- paste your ChatGPT key here (AI_PROVIDER='openai')
const OPENAI_MODEL   = 'gpt-4o-mini';        // 'gpt-4o-mini' = cheap + fast; 'gpt-4o' = higher quality
const OPENAI_RPM     = 0;                     // 0 = no throttle

// ---- Store identity --------------------------------------------------------
const BRAND_NAME = '';                        // '' = site title
const TAGLINE    = '';                        // '' = site tagline
const STORE_NICHE = 'general consumer products';   // <-- EDIT: what the store sells
const SALES_ORIENTED = true;
const REFERENCE_URL = '';   // OPTIONAL: one URL of a comparable store's product or shop page. If set, the script reads its
                            // price tier, unit style (simple/variable), description depth and category naming as GUIDANCE for
                            // the AI (which still writes 100% original copy). Leave '' to skip. Best on sites with JSON-LD product data.

// ---- PRODUCT operations: toggle each ON/OFF --------------------------------
const DO_SHORT_DESC   = true;   const SHORT_DESC_MODE = 'fill';  // how to write it: 'append' (add after existing) | 'overwrite' (replace) | 'fill' (write ONLY when empty; skip if it already has one)
const DO_LONG_DESC    = true;   const LONG_DESC_MODE  = 'fill';  // 'append' | 'overwrite' | 'fill'
const SHORT_DESC_WORDS = '80-130';    // word RANGE (soft target, not a hard stop) so the AI has room to write well. A single number like '120' also works
const LONG_DESC_WORDS  = '600-900';   // word RANGE for the long description. A single number (e.g. '600') also works
const DO_META         = true;   const OVERWRITE_META  = false;   // Rank Math meta
const DO_TAGS         = true;   const OVERWRITE_TAGS  = false;  // false = fill only if empty
const DO_PRICE        = false;   const OVERWRITE_PRICE = false;  // false = set only if empty
const PRODUCT_TYPE    = 'simple';               // 'simple' or 'variable' (AI proposes options)
const PRICE_ENDING    = '.99';                  // '' = whole number
const SHORT_DESC_INCLUDE_UNIT = true;           // add "Sold as: <unit>" to short desc?
const MAX_TAGS        = 5;

const DO_INTERLINKS   = true;   const INTERLINKS_PER_PRODUCT = 3;   // deterministic, no AI
const REMOVE_FOREIGN_LINKS = true; // strip links pointing to OTHER domains (keeps the anchor text)
const SITE_DOMAIN = '';             // your REAL domain WITH scheme, e.g. 'https://mysite.com' (include the subfolder if WordPress lives in one: 'https://mysite.com/shop'). Leave EMPTY to auto-detect — recommended when the site is already on its real domain
                                    // from the live site. Set it when running locally or on a temporary URL so
                                    // generated interlinks + foreign-link stripping use your real domain.

// ---- IMAGES (Ideogram) -----------------------------------------------------
const DO_IMAGE            = false;
const SKIP_IF_HAS_IMAGE   = true;
const USE_LOGO_AS_PRODUCT_IMAGE = true;   // NO API needed: set the logo as the featured image for products that have NO image. Leave ON as the everyday default; turn OFF when you want DO_IMAGE to generate real images instead (with RESET_PROGRESS, generation replaces logo placeholders)
const IDEOGRAM_API_KEY      = '';
const IDEOGRAM_ASPECT       = 'ASPECT_1_1';   // 1:1 square. Kept in ASPECT_x_y form; auto-converted to v3's '1x1'
const IDEOGRAM_RENDER_SPEED = 'QUALITY';      // Ideogram 3.0 render tier — higher = SHARPER label text. 'QUALITY' (sharpest, ~$0.09/img) | 'DEFAULT' (~$0.06) | 'TURBO' (~$0.03) | 'FLASH' (fastest/cheapest). Set lower to cut spend
const IDEOGRAM_STYLE_TYPE   = 'REALISTIC';    // 'REALISTIC' (product photos) | 'GENERAL' | 'DESIGN' | 'AUTO' | 'FICTION'
const IMAGE_STYLE = 'clean professional studio product photograph, single product alone and centered on a seamless '
                  . 'soft-lit background, studio softbox lighting, sharp focus, high detail, realistic materials. '
                  . 'NO person, NO hands, NO fingers, NO human face anywhere in the frame. If the product is packaged, the label is '
                  . 'MINIMAL and clean: the ONLY text anywhere on the package is the large product name in a bold, correctly spelled '
                  . 'sans-serif. There is NO other writing of any kind, NO small print, NO tagline or slogan, NO ingredient list, '
                  . 'NO net weight, NO barcode, NO nutrition panel, NO directions and NO secondary lines; the rest of the label is '
                  . 'plain and empty. If the product is unpackaged, show NO text at all';
const IMAGE_NEGATIVE = 'person, people, human, man, woman, child, hand, hands, fingers, arm, arms, face, faces, portrait, '
                     . 'model, mannequin, body, skin, crowd, misspelled text, gibberish text, distorted letters, random characters, '
                     . 'small text, fine print, tiny letters, secondary text, subtitle, tagline, slogan, ingredient list, nutrition facts, '
                     . 'barcode, QR code, directions text, disclaimer text, busy cluttered label, paragraph of text, watermark, extra limbs, deformed';   // v3 negative_prompt — excludes humans, garbled + small filler text
// GUARANTEED-correct label text: generate a BLANK-label product photo, then burn the real product name on as a crisp caption bar.
const IMAGE_TEXT_OVERLAY = false;   // true = generate a BLANK white front label, then WE print the 3-tier label (product name / use / brand) onto it — the ONLY text on the pack, perfect spelling every time. false = let the model draw the label text
const IMAGE_LABEL_USE_FALLBACK = true;   // if no AI 'use' phrase exists, use the product's category as the middle line (e.g. 'Pain Relief')
const OVERLAY_FONT       = '';     // optional .ttf next to this file (e.g. 'font.ttf') or absolute path for the caption; '' = Imagick built-in / auto-detected system font
const IMAGE_STYLE_BLANK  = 'clean professional studio product photograph of a single product, FRONT-FACING and centered on a '
                         . 'seamless soft muted light-gray studio background (NOT white), studio softbox lighting, sharp focus, high detail. '
                         . 'NO person, NO hands, NO fingers, NO human face. The package has ONE LARGE clean BLANK bright-white matte '
                         . 'label panel across the front-center, completely empty and smooth with NO text, NO letters, NO numbers, NO logos '
                         . 'and NO markings of any kind. If the product is unpackaged, show it bare against the gray background';
const WATERMARK_LOGO    = '';    // transparent PNG next to this file, or absolute path; '' = off
const WATERMARK_OPACITY = 0.55;  const WATERMARK_SCALE = 0.20;  const WATERMARK_MARGIN = 24;

// ---- CATEGORIES ------------------------------------------------------------
const DO_CATEGORIES   = false;                 // create + group products into categories
const CATEGORY_MODE   = 'auto';                // 'auto' (AI groups by use) or 'manual'
const CATEGORY_LIST   = ['Category A', 'Category B'];  // used only when CATEGORY_MODE = 'manual'
const AUTO_CATEGORY_COUNT = '6-12';            // target count for auto mode
const DO_CATEGORY_CONTENT = true;             // write category descriptions + interlinks
const OVERWRITE_CATEGORY_DESC = true;

// ---- PAGES (run only after products; published live) -----------------------
const DO_HOMEPAGE   = true;   const HOMEPAGE_FORMAT = 'flatsome';  // 'flatsome' or 'html'
const OVERWRITE_HOMEPAGE = true;
const DO_CONTACT    = true;
const CONTACT_PHONE    = '';
const CONTACT_EMAIL    = '';
const CONTACT_LOCATION = 'United States';
const DO_PRIVACY    = false;
const DO_TERMS      = false;
const DO_SHIPPING   = false;
const DO_REFUND     = false;
const DO_FAQ        = false;
const DO_ABOUT      = false;
const DO_BLOG       = true;   // create SEO blog posts: post #1 goes live now, the rest auto-schedule into the future
const BLOG_COUNT          = 45;      // total posts to create (spread across refreshes if BLOG_PER_RUN is set)
const BLOG_CADENCE_DAYS   = 2;       // days between scheduled posts. Your example (Mon, Wed, Fri, Sun) = every 2 days. Set 1 for daily
const BLOG_FIRST_LIVE     = true;    // true = publish post #1 immediately, schedule #2.. into the future. false = schedule ALL (nothing live today)
const BLOG_PER_RUN        = 8;       // max posts to WRITE per page-load, so a 45-post run can't time out — refresh to write the next batch. 0 = all at once
const BLOG_INTERNAL_LINKS = 4;       // minimum internal links per post (shop / categories / products / other posts). Guaranteed by a deterministic top-up
const BLOG_EXTERNAL_LINK  = true;    // add ONE outbound link to an authoritative .gov/.mil/.edu/.int or public info/legal source — never a business/competitor
const BLOG_WORDS          = '900-1300';   // article length target
const BLOG_CATEGORY       = '';      // optional blog category name to file every post under ('' = none)
const BLOG_LAYOUT         = 'no-sidebar';   // Flatsome blog layout (verified theme keys). 'no-sidebar' = clean full-width posts (recommended, stops the category-widget sidebar pushing content) | 'right-sidebar' | 'left-sidebar' | '' = leave your current setting untouched
const REPAIR_POST_LINKS   = false;   // MAINTENANCE MODE: re-scan every existing post and remove any internal link that points to a NOT-YET-PUBLISHED post (the 404 case), then re-top-up. No AI, no new posts. Turn every DO_*/FORCE_*/REMOVE_* off to run this alone. Set back to false after.
const OVERWRITE_PAGES = true;                 // legal/info pages: overwrite if they already have content

// ---- BRANDING (site identity: name, colors, logo — the "Appearance > Customize" bits) ----------------
const ENSURE_SEARCH_VISIBLE = true;   // force "Search engine visibility" ON (blog_public=1) every run. Restored/migrated/staging sites often silently carry the "Discourage search engines" flag, which noindexes the WHOLE site so nothing indexes no matter how many sitemaps you submit. Leave ON.
const DO_BRANDING       = true;   // set the site title, an AI-chosen color palette, and a generated logo (icon + brand name)
const BRANDING_OVERWRITE= false;   // false = set each item ONLY where the site hasn't been branded yet (safe). true = force name/colors/logo every run
const SITE_TAGLINE      = '';      // '' = keep the current tagline  (the site TITLE is always BRAND_NAME — no separate setting)
const LOGO_BG           = '#ffffff';   // logo canvas background. White blends with Flatsome's near-white header. Use a dark hex ONLY if your header is dark
const LOGO_URL          = '';      // OPTIONAL: paste a PUBLIC/live logo image URL (png/jpg) to USE that as the logo (downloaded into the media library once). Overrides generation. Also used by USE_LOGO_AS_PRODUCT_IMAGE
const GENERATE_LOGO     = true;    // when LOGO_URL is empty: true = build a logo with the TEXT AI (writes an SVG icon) + a code-drawn wordmark, with a monogram-badge fallback — NO image API needed; false = don't create a logo at all

// ---- Compliance + anti-AI voice --------------------------------------------
const COMPLIANCE_MODE = true;
const DISCLAIMER_HTML = '<p><em>Always read the label and use products as directed. Consult a qualified '
                      . 'professional where appropriate.</em></p>';
const PROHIBITED_WORDS = '';
const BANNED_AI_WORDS = [
    'elevate','unleash','unlock','seamless','seamlessly','robust','leverage','cutting-edge',
    'state-of-the-art','game-changer','revolutionize','revolutionary','empower','testament','tapestry',
    'realm','delve','embark','plethora','myriad','curated','meticulous','meticulously','boasts','nestled',
    'look no further',"in today's fast-paced world",'when it comes to','rest assured',"we've got you covered",
    'one-stop shop','at the heart of','more than just','to the next level',
];

// ---- Behavior --------------------------------------------------------------
const MAX_PRODUCTS_PER_RUN  = 0;               // 0 = all in one run; e.g. 20 = do 20 NEW products per run, then refresh for more
const RESET_PROGRESS        = false;           // true = wipe saved progress and reprocess EVERY product. Fires only ONCE (safe to leave on across refreshes); re-arms after the job finishes or when set back to false
const REPROCESS_IDS         = [];              // redo only THESE product IDs even if already done, e.g. [12, 45, 99]. Also fires ONCE. (Use OVERWRITE_* = true so it replaces, not appends)
const FORCE_IN_STOCK        = true;           // set every product + variation status to "In stock" (and keep it there)
const SELF_DELETE_WHEN_DONE = true;
const PRODUCT_STATUSES = ['publish', 'draft', 'pending', 'private'];
const PUBLISH_STATE = 'publish';               // pages/blog status (you chose publish)
const REPLACE_PRODUCT_CATEGORIES = false;      // ONLY used by DO_CATEGORIES grouping. false = ADD the grouped category and
                                               // KEEP the product's existing categories (safe). true = REMOVE existing
                                               // categories and replace them (destructive — this is what wiped categories).
const APPEND_MARKER = '<span class="wcm-added"></span>';  // guards "append" so it only happens once. A <span> survives WordPress kses; an HTML comment gets stripped when saving unauthenticated
const APPEND_SIG    = 'wcm-added';             // stable substring to detect the marker (matches the new span AND the old comment — backward-compatible)
const ENABLE_LAWFUL_USE_GUARD = false;   // toggle the lawful-use catalog guard on/off (false = off; useful when a legitimate catalog trips a false positive)

// #############################################################################
// #                       END OF CONFIG — CODE BELOW                          #
// #############################################################################

$GUARD_TERMS = ['fentanyl','fentanil','sublimaze','duragesic','actiq','carfentanil','oxycodone','oxycontin',
    'hydrocodone','vicodin','percocet','morphine','codeine','tramadol','heroin','opioid','opiate','ketamine',
    'alprazolam','xanax','diazepam','valium','adderall','amphetamine','methamphetamine','cocaine','mdma','lsd',
    'ghb','nembutal','pentobarbital','research chemical','schedule ii','schedule iii','controlled substance'];

// ---- Auth gate + WordPress -------------------------------------------------
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$hn = preg_replace('/:\d+$/', '', $host);
$is_local = (substr($hn, -6) === '.local') || in_array($hn, ['localhost','127.0.0.1','::1','[::1]'], true)
    || (($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1');
if (REQUIRE_SECRET || !$is_local) {
    if (!isset($_GET['key']) || !hash_equals(SECRET, (string) $_GET['key'])) {
        http_response_code(403); exit('Forbidden — add ?key=YOUR_SECRET');
    }
}
$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) { $d = __DIR__; for ($i=0;$i<6 && !file_exists($d.'/wp-load.php');$i++){$d=dirname($d);} $wp_load = $d.'/wp-load.php'; }
if (!file_exists($wp_load)) { http_response_code(500); exit('wp-load.php not found — place this file in the WordPress root.'); }
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
if (!function_exists('wc_get_product')) { http_response_code(500); exit('WooCommerce is not active.'); }

@ini_set('output_buffering','off'); @ini_set('zlib.output_compression','0');
while (ob_get_level() > 0) { ob_end_flush(); } ob_implicit_flush(true); ignore_user_abort(true); @set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');
@header('X-LiteSpeed-Purge: *');   // tell the LiteSpeed server (Hostinger default) to purge its page cache — sent before output so edits show without a manual clear
echo "<!doctype html><meta charset='utf-8'><title>WC Master</title>";
echo "<body style='font:14px/1.5 monospace;background:#111;color:#ddd;padding:20px'>", str_repeat(' ', 4096);
function out($m,$c='#ddd'){ echo "<div style='color:$c'>".esc_html($m)."</div>\n"; flush(); }
function esc($s){ return esc_html((string) $s); }
function brand(){ return BRAND_NAME !== '' ? BRAND_NAME : get_bloginfo('name'); }
function tagline(){ return TAGLINE !== '' ? TAGLINE : get_bloginfo('description'); }
function currency(){ return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD'; }
function shop_url(){ $u = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : ''; return $u ?: home_url('/'); }
function site_email(){ return CONTACT_EMAIL!=='' ? CONTACT_EMAIL : 'sales@'.site_host(); }   // sales@<domain>; site_host() honors SITE_DOMAIN so the email matches the site's real domain / its links, not a staging host
function us_phone(){ if(CONTACT_PHONE!=='') return CONTACT_PHONE;   // placeholder only: 555-01xx is the reserved fictional range (never a real line) — replace it later
    static $ph=null; if($ph!==null) return $ph;                       // ONE number for the whole run...
    $ph=(string)get_option('wcm_phone'); if($ph!=='') return $ph;     // ...and persisted, so every page (contact, FAQ, shipping...) shows the SAME phone
    $a=['212','213','305','312','404','415','469','512','617','646','702','713','786','813','917']; $ph='('.$a[mt_rand(0,count($a)-1)].') 555-'.sprintf('%04d',mt_rand(100,199));
    update_option('wcm_phone',$ph,false); return $ph; }

// ---------------------------------------------------------------------------
// AI stack (Gemini / Claude) — throttle, billing-aware errors, tolerant parser
// ---------------------------------------------------------------------------
function ai_throttle(){ static $last=0.0; $rpm=(AI_PROVIDER==='gemini')?GEMINI_RPM:((AI_PROVIDER==='openai')?OPENAI_RPM:CLAUDE_RPM); $iv=$rpm>0?60.0/$rpm:0.0;   // rpm=0 => no throttle (full speed)
    if($iv>0){ $g=microtime(true)-$last; if($g<$iv) usleep((int)(($iv-$g)*1e6)); } $last=microtime(true); }

function ai_text_gemini($prompt){
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.urlencode(GEMINI_API_KEY);
    $r=wp_remote_post($url,['headers'=>['Content-Type'=>'application/json'],'timeout'=>180,
        'body'=>wp_json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],
            'generationConfig'=>['temperature'=>0.9,'maxOutputTokens'=>8192,'responseMimeType'=>'application/json']])]);
    if(is_wp_error($r)) return [null,$r->get_error_message()];
    $code=wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r);
    if($code==429){ $ra=(int)wp_remote_retrieve_header($r,'retry-after'); return [null,'429'.($ra>0?':'.$ra:'')]; }
    if($code!=200) return [null,"Gemini $code: ".substr($body,0,200)];
    $j=json_decode($body,true); $t='';
    foreach(($j['candidates'][0]['content']['parts']??[]) as $p){ $t.=$p['text']??''; }
    return $t!=='' ? [$t,''] : [null,'Gemini empty response'];
}
function ai_text_claude($prompt){
    $r=wp_remote_post('https://api.anthropic.com/v1/messages',['timeout'=>180,
        'headers'=>['x-api-key'=>ANTHROPIC_API_KEY,'anthropic-version'=>'2023-06-01','content-type'=>'application/json'],
        'body'=>wp_json_encode(['model'=>CLAUDE_MODEL,'max_tokens'=>CLAUDE_MAX_TOKENS,'messages'=>[['role'=>'user','content'=>$prompt]]])]);
    if(is_wp_error($r)) return [null,$r->get_error_message()];
    $code=wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r);
    if($code==429){ $ra=(int)wp_remote_retrieve_header($r,'retry-after'); return [null,'429'.($ra>0?':'.$ra:'')]; }
    if($code!=200){ $j=json_decode($body,true); $m=$j['error']['message']??substr($body,0,200);
        if(stripos($m,'credit balance')!==false||stripos($m,'billing')!==false)
            return [null,"Claude CREDIT/BILLING ($code): $m  >>> Top up, or set AI_PROVIDER='gemini' (free)."];
        return [null,"Claude HTTP $code: $m"]; }
    $j=json_decode($body,true); $stop=$j['stop_reason']??''; $t='';
    foreach(($j['content']??[]) as $b){ if(($b['type']??'')==='text') $t.=$b['text']; }
    return $t!=='' ? [$t,''] : [null,"Claude no text (stop_reason=$stop)"];
}
function ai_text_openai($prompt){   // ChatGPT / OpenAI Chat Completions (JSON mode)
    $r=wp_remote_post('https://api.openai.com/v1/chat/completions',['timeout'=>180,
        'headers'=>['Authorization'=>'Bearer '.OPENAI_API_KEY,'Content-Type'=>'application/json'],
        'body'=>wp_json_encode(['model'=>OPENAI_MODEL,'temperature'=>0.9,'response_format'=>['type'=>'json_object'],'messages'=>[['role'=>'user','content'=>$prompt]]])]);
    if(is_wp_error($r)) return [null,$r->get_error_message()];
    $code=wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r);
    if($code==429){ $ra=(int)wp_remote_retrieve_header($r,'retry-after'); return [null,'429'.($ra>0?':'.$ra:'')]; }
    if($code!=200){ $j=json_decode($body,true); $m=$j['error']['message']??substr($body,0,200);
        if(stripos($m,'quota')!==false||stripos($m,'billing')!==false||stripos($m,'insufficient')!==false)
            return [null,"OpenAI CREDIT/BILLING ($code): $m  >>> Top up, or set AI_PROVIDER='gemini' (free)."];
        return [null,"OpenAI HTTP $code: $m"]; }
    $j=json_decode($body,true); $t=(string)($j['choices'][0]['message']['content']??'');
    return $t!=='' ? [$t,''] : [null,'OpenAI empty response'];
}
function json_escape_ctrl($s){ $o='';$in=false;$e=false;$n=strlen($s);
    for($i=0;$i<$n;$i++){ $c=$s[$i];
        if($in){ if($e){$o.=$c;$e=false;continue;} if($c==='\\'){$o.=$c;$e=true;continue;}
            if($c==='"'){$in=false;$o.=$c;continue;}
            if($c==="\n"){$o.='\\n';continue;} if($c==="\r"){$o.='\\r';continue;} if($c==="\t"){$o.='\\t';continue;}
            $o.=$c; } else { if($c==='"')$in=true; $o.=$c; } }
    return $o; }
function extract_json($text){ $t=trim((string)$text);
    $t=preg_replace('/^```[a-zA-Z]*\s*/','',$t); $t=preg_replace('/\s*```$/','',$t);
    $cand=[$t]; $s=strpos($t,'{'); $e=strrpos($t,'}'); if($s!==false&&$e!==false&&$e>$s) $cand[]=substr($t,$s,$e-$s+1);
    foreach($cand as $c){ foreach([$c,preg_replace('/,\s*([}\]])/','$1',$c),json_escape_ctrl($c),
        preg_replace('/,\s*([}\]])/','$1',json_escape_ctrl($c))] as $v){ $d=json_decode($v,true); if(is_array($d)) return $d; } }
    return null; }
function ai_json($prompt){ $last=''; $rate=false;
    for($a=0;$a<3;$a++){ ai_throttle();
        [$t,$err]=(AI_PROVIDER==='gemini')?ai_text_gemini($prompt):((AI_PROVIDER==='openai')?ai_text_openai($prompt):ai_text_claude($prompt));
        if($err!==null && strpos((string)$err,'429')===0){ $rate=true;             // honor Retry-After if the API sent one
            $ra=(($c=strpos($err,':'))!==false)?(int)substr($err,$c+1):0; sleep($ra>0?min($ra+1,120):30*($a+1)); continue; }
        if($err) return [null,$err];
        $last=(string)$t; $d=extract_json($last); if($d!==null) return [$d,''];
    }
    if($rate && $last==='') return [null,'rate-limited (429) after 3 tries — wait a minute and refresh, or lower GEMINI_RPM'];
    return [null,'invalid JSON after 3 tries ('.json_last_error_msg().'). Model: '.trim(preg_replace('/\s+/',' ',mb_substr($last,0,200)))]; }

// ---------------------------------------------------------------------------
// Shared prompt clauses + text helpers
// ---------------------------------------------------------------------------
function voice_rules(){ $b=implode(', ',BANNED_AI_WORDS);
    return "VOICE — STRICT: write like a real human brand copywriter; it must NOT read like AI. "
        . "NEVER use these words/phrases or close variants: $b. No hollow hype or filler; be concrete and "
        . "specific. Vary sentence length, use contractions, second person ('you'), active voice. Don't open "
        . "with 'Welcome to' or 'In the world of'; don't end with 'In conclusion'. Avoid tidy lists of three. "
        . "NEVER use em dashes or en dashes (the — or – characters) anywhere; use commas, periods, or parentheses instead.\n"; }
function compliance_clause(){ if(!COMPLIANCE_MODE) return '';
    $b=PROHIBITED_WORDS!==''?PROHIBITED_WORDS:'(none specified)';
    return "COMPLIANCE: never use these words/claims: $b. Avoid medical/health claims and guarantees.\n"; }
function html_quote_rule(){ return "Use SINGLE quotes for all HTML attributes, never double quotes. Return ONE "
    . "valid JSON object only — no markdown, no code fences, no comments, no trailing commas — and keep each "
    . "HTML value on a single line (no raw line breaks inside strings).\n"; }
// turn a length spec into natural prompt wording: '80-130' -> 'between 80 and 130 words' (a soft range, never a hard stop); '120' -> 'around 120 words'
function words_phrase($spec){ $spec=trim((string)$spec);
    if(preg_match('/^(\d+)\s*[-–]\s*(\d+)$/',$spec,$m)) return 'between '.$m[1].' and '.$m[2].' words (a flexible target, not a hard limit)';
    return 'around '.$spec.' words'; }
// standard company facts every info/legal/FAQ page must use — the SAME email + phone everywhere, worldwide shipping, costs shown at checkout (never invented figures)
function company_facts(){ return "COMPANY FACTS — use these EXACT details wherever contact info or specifics are needed, and never invent different ones:\n"
    ."- Store name: ".brand()."\n- Support email: ".site_email()."\n- Support phone: ".us_phone()."\n- Based in: ".CONTACT_LOCATION."\n- Support hours: Monday to Friday, 9:00 AM to 5:00 PM.\n"
    ."- Shipping: we ship WORLDWIDE, to anywhere in the world (USA and international). Do NOT state any exact shipping price or dollar figure; say the exact shipping cost is calculated automatically and shown at checkout before payment.\n"
    ."- Tone: standard, professional, reassuring and POSITIVE about what the store can do; never say we cannot do something, frame any limit positively.\n"; }
function excerpt($html,$n=280){ return trim(mb_substr(wp_strip_all_tags((string)$html),0,$n)); }
function existing_hrefs($html){ preg_match_all('/href=["\']([^"\']+)["\']/',(string)$html,$m); return $m[1]; }
// --- domain handling: use SITE_DOMAIN when set, otherwise the live site's own URL ---
function site_base(){ static $b=null; if($b===null){ $b=SITE_DOMAIN!==''?rtrim(SITE_DOMAIN,'/'):rtrim((string)home_url(),'/');
    if($b!=='' && !preg_match('#^https?://#i',$b)){ $sc=parse_url((string)home_url(),PHP_URL_SCHEME)?:'https'; $b=$sc.'://'.preg_replace('#^/+#','',$b); } } return $b; }   // if SITE_DOMAIN was set WITHOUT a scheme (e.g. 'domain.com'), add https:// — otherwise rewritten hrefs become schemeless ("domain.com/...") and the browser DOUBLES the domain (domain.com/domain.com/...) -> 404
function site_host(){ static $h=null; if($h===null){ $h=preg_replace('/^www\./','',strtolower((string)parse_url(site_base(),PHP_URL_HOST))); } return $h; }
function site_link($u){ if(SITE_DOMAIN===''||!$u||!is_string($u)) return $u; $h=rtrim((string)home_url(),'/'); $b=site_base(); if($h===$b) return $u; return strpos($u,$h)===0?$b.substr($u,strlen($h)):$u; }   // $b is scheme-normalized (via site_base) so the result is always an absolute URL, never a schemeless "domain.com/..." that a browser would double
function post_pretty_link($p){ $po=is_object($p)?$p:get_post($p); if(!$po) return ''; if($po->post_status==='publish') return get_permalink($po);
    $c=clone $po; $c->post_status='publish'; return get_permalink($c); }   // scheduled/future posts: get_permalink() returns an ugly ?p=ID (which 404s until the post is live); a publish-status clone yields the eventual PRETTY url (slug + date) — clean for SEO and it resolves the moment the post goes live
// --- foreign-link stripping: keep this site's links, unwrap links to other domains ---
function is_foreign_url($url){ $url=trim((string)$url); if($url==='') return false;
    if($url[0]==='#') return false;                                   // in-page anchor
    if(strncmp($url,'//',2)!==0 && $url[0]==='/') return false;        // root-relative = internal
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    if(in_array($scheme,['mailto','tel','javascript'],true)) return false;
    $host=parse_url($url,PHP_URL_HOST); if(!$host) return false;       // relative (no host) = internal
    $h=preg_replace('/^www\./','',strtolower($host)); $live=preg_replace('/^www\./','',strtolower((string)parse_url((string)home_url(),PHP_URL_HOST)));
    return $h!==site_host() && $h!==$live; }   // internal if it matches EITHER the configured real domain OR the current live host (so on staging we don't unwrap the site's own absolute self-links)
function strip_foreign_links($html){ if(strpos((string)$html,'<a')===false) return (string)$html;
    return preg_replace_callback('/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
        fn($m)=>is_foreign_url($m[1])?$m[2]:$m[0], (string)$html); }
// --- one-H1-per-page lock: demote any <h1> in generated content to <h2> (product title stays the H1) ---
function demote_h1($html){ if(stripos((string)$html,'<h1')===false) return (string)$html;
    return preg_replace('/<(\/?)h1(\b[^>]*)>/i','<$1h2$2>',(string)$html); }
function append_disclaimer($html){ if(COMPLIANCE_MODE && DISCLAIMER_HTML && strpos((string)$html,DISCLAIMER_HTML)===false) return $html."\n".DISCLAIMER_HTML; return $html; }
// strip AI-cliché em/en dashes from generated content (word-hyphens like "grass-fed" are left alone)
function dedash($s){ $s=(string)$s; if($s==='') return $s;
    $s=str_replace(['&mdash;','&#8212;','&#x2014;','&ndash;','&#8211;','&#x2013;'],'—',$s);   // normalize entities to the character first
    $s=preg_replace('/\s*[—–]\s*/u',', ',$s);        // em/en dash used as punctuation -> comma
    $s=preg_replace('/(?:,\s*){2,}/',', ',$s);       // collapse doubled commas
    $s=preg_replace('/(^|>)\s*,\s+/','$1',$s);       // drop a stray leading comma at the start or right after a tag
    return preg_replace('/[ \t]{2,}/',' ',$s); }
/** true if a field should be (re)written given its current value and the mode ('append'|'overwrite'|'fill'). */
function field_need($cur,$mode){ $cur=(string)$cur;
    if($mode==='overwrite') return true;                 // always rewrite
    if($mode==='fill') return trim($cur)==='';           // only when empty
    return trim($cur)==='' || strpos($cur,APPEND_SIG)===false; }   // append: empty, or not yet appended
/** apply text per mode. Returns [value, changed]. Mirrors field_need exactly. */
function round_price($v){ $s=preg_replace('/[^0-9.]/','',(string)$v); if(substr_count($s,'.')>1){ $p=strrpos($s,'.'); $s=str_replace('.','',substr($s,0,$p)).substr($s,$p); }   // collapse thousands-dot noise (e.g. "1.234.56" -> "1234.56") so only the last dot is the decimal point
    $v=(float)$s; if($v<=0) return '';   // return '' for missing/invalid so callers skip (both test $pr!=='')
    if(PRICE_ENDING==='') return (string)(int)round($v);                              // whole number (e.g. 24.40 -> "24")
    return number_format(floor($v)+(float)PRICE_ENDING,2,'.',''); }                   // charm price: keep the dollar part, force the configured ending (e.g. 24.40 -> "24.99")
function apply_text($cur,$new,$mode){ $cur=(string)$cur; $new=(string)$new; if($new==='') return [$cur,false];
    if(trim($cur)===''){ return [$mode==='append' ? $new."\n".APPEND_MARKER : $new, true]; }   // empty -> fill it; in append mode plant the marker NOW so a later reprocess doesn't append a 2nd copy
    if($mode==='fill') return [$cur,false];              // has content + fill-only -> leave it untouched
    if($mode==='overwrite') return [$new,true];          // replace
    if(strpos($cur,APPEND_SIG)!==false) return [$cur,false];        // append: already appended once
    return [$cur."\n".APPEND_MARKER."\n".$new,true]; }   // append after existing

// ---------------------------------------------------------------------------
// Images (Ideogram + optional GD/Imagick watermark)
// ---------------------------------------------------------------------------
function ideogram_url($prompt,$negative=null,$aspect=null){ if(IDEOGRAM_API_KEY==='') return [null,'IDEOGRAM_API_KEY empty'];
    // Ideogram 3.0 (v3) — far better label-text rendering than the legacy v1/v2 endpoint. Body is multipart/form-data
    // (top-level fields, no image_request wrapper), aspect is '1x1'-style, and rendering_speed drives text sharpness.
    // $negative/$aspect override the product-image defaults (the logo generator passes its own, since it must NOT exclude 'logo').
    $ar=str_replace('_','x',strtolower(str_replace('ASPECT_','',$aspect?:IDEOGRAM_ASPECT)));   // 'ASPECT_1_1' -> '1x1'
    $fields=['prompt'=>$prompt,'negative_prompt'=>($negative!==null?$negative:image_negative()),'aspect_ratio'=>$ar,'rendering_speed'=>IDEOGRAM_RENDER_SPEED,'magic_prompt'=>'OFF','style_type'=>IDEOGRAM_STYLE_TYPE];
    $boundary='----wcm'.wp_generate_password(20,false,false);   // unique multipart boundary; letters/numbers only
    $body=''; foreach($fields as $k=>$v){ $body.='--'.$boundary."\r\n".'Content-Disposition: form-data; name="'.$k.'"'."\r\n\r\n".$v."\r\n"; }
    $body.='--'.$boundary."--\r\n";
    for($a=0;$a<4;$a++){ $r=wp_remote_post('https://api.ideogram.ai/v1/ideogram-v3/generate',['timeout'=>120,
        'headers'=>['Api-Key'=>IDEOGRAM_API_KEY,'Content-Type'=>'multipart/form-data; boundary='.$boundary],
        'body'=>$body]);
        if(is_wp_error($r)) return [null,$r->get_error_message()];
        $code=wp_remote_retrieve_response_code($r); if($code==429){ sleep(30*($a+1)); continue; }
        if($code!=200) return [null,"Ideogram $code: ".substr((string)wp_remote_retrieve_body($r),0,200)];
        $u=(json_decode(wp_remote_retrieve_body($r),true)['data'][0]['url']??'');
        return $u?[$u,'']:[null,'no image URL']; }
    return [null,'rate-limited']; }
function icm_alpha($dst,$src,$dx,$dy,$sw,$sh,$op){ $cut=imagecreatetruecolor($sw,$sh);
    imagecopy($cut,$dst,0,0,$dx,$dy,$sw,$sh); imagecopy($cut,$src,0,0,0,0,$sw,$sh); imagecopymerge($dst,$cut,$dx,$dy,0,0,$sw,$sh,$op); }
function watermark($path){ $logo=(substr(WATERMARK_LOGO,0,1)==='/')?WATERMARK_LOGO:__DIR__.'/'.WATERMARK_LOGO;
    if(!file_exists($logo)) return false;
    if(class_exists('Imagick')){ try{ $img=new Imagick($path); $img->setImageFormat('png'); $lg=new Imagick($logo);
        $tw=max(1,(int)round($img->getImageWidth()*WATERMARK_SCALE)); $lg->resizeImage($tw,0,Imagick::FILTER_LANCZOS,1);
        if(WATERMARK_OPACITY<1) $lg->evaluateImage(Imagick::EVALUATE_MULTIPLY,(float)WATERMARK_OPACITY,Imagick::CHANNEL_ALPHA);
        $x=max(0,$img->getImageWidth()-$lg->getImageWidth()-WATERMARK_MARGIN); $y=max(0,$img->getImageHeight()-$lg->getImageHeight()-WATERMARK_MARGIN);
        $img->compositeImage($lg,Imagick::COMPOSITE_OVER,$x,$y); $img->writeImage($path); return true; }catch(\Throwable $e){} }
    if(strtolower(pathinfo($logo,PATHINFO_EXTENSION))!=='png') return false;
    $base=@imagecreatefromstring(@file_get_contents($path)); $lg=@imagecreatefrompng($logo); if(!$base||!$lg) return false;
    imagealphablending($base,true); $bw=imagesx($base);$bh=imagesy($base); $lw=imagesx($lg);$lh=imagesy($lg);
    $tw=max(1,(int)round($bw*WATERMARK_SCALE)); $th=max(1,(int)round($lh*$tw/$lw));
    $sc=imagecreatetruecolor($tw,$th); imagealphablending($sc,false); imagesavealpha($sc,true);
    imagefill($sc,0,0,imagecolorallocatealpha($sc,0,0,0,127)); imagecopyresampled($sc,$lg,0,0,0,0,$tw,$th,$lw,$lh);
    icm_alpha($base,$sc,max(0,$bw-$tw-WATERMARK_MARGIN),max(0,$bh-$th-WATERMARK_MARGIN),$tw,$th,(int)round(WATERMARK_OPACITY*100));
    imagepng($base,$path); return true; }
// choose the model style/negatives: when overlaying our own caption we ask for a BLANK label and forbid the model from drawing ANY text
function image_style(){ return IMAGE_TEXT_OVERLAY ? IMAGE_STYLE_BLANK : IMAGE_STYLE; }
function image_negative(){ return IMAGE_TEXT_OVERLAY ? IMAGE_NEGATIVE.', text, letters, numbers, words, writing, typography, logo, printed label, caption' : IMAGE_NEGATIVE; }
// --- deterministic label overlay: print the product name / use / brand onto a blank pack so spelling is ALWAYS correct ---
function overlay_font(){ static $f=null; if($f!==null) return $f; $f='';
    if(OVERLAY_FONT!==''){ $c=(OVERLAY_FONT[0]==='/')?OVERLAY_FONT:__DIR__.'/'.OVERLAY_FONT; if(@file_exists($c)){ $f=$c; return $f; } }
    foreach(['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf','/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf','/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        '/Library/Fonts/Arial.ttf','/System/Library/Fonts/Supplemental/Arial.ttf','C:/Windows/Fonts/arialbd.ttf'] as $c){ if(@file_exists($c)){ $f=$c; break; } }
    return $f; }
function overlay_label($path,$L){ $name=trim(preg_replace('/\s+/',' ',(string)($L['name']??''))); if($name==='') return false;
    $use=trim(preg_replace('/\s+/',' ',(string)($L['use']??''))); $brand=trim(preg_replace('/\s+/',' ',(string)($L['brand']??''))); $font=overlay_font();
    if(class_exists('Imagick')){ try{ return label_imagick($path,$name,$use,$brand,$font); }catch(\Throwable $e){} }   // fall through to GD on any Imagick failure
    return label_gd($path,$name,$use,$brand,$font); }
function wrap_imagick($img,$draw,$words,$maxW,$maxLines){ $lines=[]; $cur='';
    foreach($words as $wd){ $try=$cur===''?$wd:$cur.' '.$wd; $m=$img->queryFontMetrics($draw,$try);
        if($m['textWidth']<=$maxW){ $cur=$try; continue; }
        if($cur==='') return null; $lines[]=$cur; $cur=$wd; if(count($lines)>=$maxLines) return null;
        $m2=$img->queryFontMetrics($draw,$cur); if($m2['textWidth']>$maxW) return null; }
    if($cur!=='') $lines[]=$cur; return count($lines)<=$maxLines?$lines:null; }
function label_imagick($path,$name,$use,$brand,$font){ $img=new Imagick($path); $img->setImageFormat('png');
    $w=$img->getImageWidth(); $h=$img->getImageHeight(); if($w<1||$h<1) return false; $maxW=$w*0.78;
    $mk=function($size) use($font){ $d=new ImagickDraw(); if($font) $d->setFont($font); $d->setTextAlignment(Imagick::ALIGN_CENTER);
        $d->setFillColor(new ImagickPixel('#141414')); $d->setStrokeColor(new ImagickPixel('rgba(255,255,255,0.7)')); $d->setStrokeWidth(max(1.0,$size*0.035)); $d->setStrokeAntialias(true); $d->setFontSize($size); return $d; };
    $fit=function($text,$hi,$lo,$max2) use($img,$maxW,$mk){ if($text==='') return null; $words=preg_split('/\s+/',$text);
        for($s=$hi;$s>=$lo;$s--){ $d=$mk($s); $ls=wrap_imagick($img,$d,$words,$maxW,$max2); if($ls) return ['size'=>$s,'lines'=>$ls,'draw'=>$d]; }
        $d=$mk($lo); $t=$text; while($t!==''){ $m=$img->queryFontMetrics($d,$t.'…'); if($m['textWidth']<=$maxW) break; $t=mb_substr($t,0,mb_strlen($t)-1); }
        return ['size'=>$lo,'lines'=>[$t!==''?$t.'…':$text],'draw'=>$d]; };
    // enforced hierarchy: name biggest, use a little smaller, brand a little smaller than use
    $N=$fit($name,(int)round($h*0.11),(int)round($h*0.05),2); if(!$N) return false;
    $U=$use!==''?$fit($use,max((int)round($h*0.045),(int)round($N['size']*0.78)),(int)round($h*0.04),2):null;
    $B=$brand!==''?$fit($brand,max((int)round($h*0.035),(int)round(($U?$U['size']:$N['size'])*0.82)),(int)round($h*0.03),1):null;
    // name + use as a centered block around 0.40H; brand near the bottom of the pack
    $nLineH=$N['size']*1.16; $blockH=$nLineH*count($N['lines']) + ($U?($U['size']*0.5+$U['size']*1.16*count($U['lines'])):0);
    $y=$h*0.40-$blockH/2+$N['size']*0.80;
    foreach($N['lines'] as $ln){ $img->annotateImage($N['draw'],$w/2,$y,0,$ln); $y+=$nLineH; }
    if($U){ $y+=$U['size']*0.5; foreach($U['lines'] as $ln){ $img->annotateImage($U['draw'],$w/2,$y,0,$ln); $y+=$U['size']*1.16; } }
    if($B){ $by=$h*0.82; foreach($B['lines'] as $ln){ $img->annotateImage($B['draw'],$w/2,$by,0,$ln); $by+=$B['size']*1.16; } }
    $img->writeImage($path); $img->clear(); return true; }
function wrap_gd($font,$size,$words,$maxW,$maxLines){ $lines=[]; $cur='';
    foreach($words as $wd){ $try=$cur===''?$wd:$cur.' '.$wd; $bb=imagettfbbox($size,0,$font,$try); $tw=$bb[2]-$bb[0];
        if($tw<=$maxW){ $cur=$try; continue; }
        if($cur==='') return null; $lines[]=$cur; $cur=$wd; if(count($lines)>=$maxLines) return null;
        $bb2=imagettfbbox($size,0,$font,$cur); if(($bb2[2]-$bb2[0])>$maxW) return null; }
    if($cur!=='') $lines[]=$cur; return count($lines)<=$maxLines?$lines:null; }
function label_gd($path,$name,$use,$brand,$font){ $base=@imagecreatefromstring(@file_get_contents($path)); if(!$base) return false;
    imagealphablending($base,true); imagesavealpha($base,true); $w=imagesx($base); $h=imagesy($base); $maxW=$w*0.78;
    if($font && function_exists('imagettftext') && function_exists('imagettfbbox')){
        $dark=imagecolorallocate($base,20,20,20); $halo=imagecolorallocate($base,255,255,255);
        $fit=function($text,$hi,$lo,$max2) use($font,$maxW){ if($text==='') return null; $words=preg_split('/\s+/',$text);
            for($s=$hi;$s>=$lo;$s--){ $ls=wrap_gd($font,$s,$words,$maxW,$max2); if($ls) return ['size'=>$s,'lines'=>$ls]; } return ['size'=>$lo,'lines'=>[$text]]; };
        $draw=function($size,$lines,$cy) use($base,$font,$w,$dark,$halo){ $lineH=(int)round($size*1.16); $y=(int)round($cy);
            foreach($lines as $ln){ $bb=imagettfbbox($size,0,$font,$ln); $x=(int)round(($w-($bb[2]-$bb[0]))/2);
                foreach([[-2,0],[2,0],[0,-2],[0,2]] as $o) imagettftext($base,$size,0,$x+$o[0],$y+$o[1],$halo,$font,$ln);   // white halo for legibility on any label
                imagettftext($base,$size,0,$x,$y,$dark,$font,$ln); $y+=$lineH; } return $y; };
        $N=$fit($name,(int)round($h*0.11),(int)round($h*0.05),2); if(!$N){ imagedestroy($base); return false; }
        $U=$use!==''?$fit($use,max((int)round($h*0.045),(int)round($N['size']*0.78)),(int)round($h*0.04),2):null;
        $B=$brand!==''?$fit($brand,max((int)round($h*0.035),(int)round(($U?$U['size']:$N['size'])*0.82)),(int)round($h*0.03),1):null;
        $nLineH=(int)round($N['size']*1.16); $blockH=$nLineH*count($N['lines']) + ($U?(int)round($U['size']*0.5+$U['size']*1.16*count($U['lines'])):0);
        $y=$draw($N['size'],$N['lines'],$h*0.40-$blockH/2+$N['size']*0.80);
        if($U){ $y+=(int)round($U['size']*0.5); $draw($U['size'],$U['lines'],$y); }
        if($B){ $draw($B['size'],$B['lines'],$h*0.82); }
        imagepng($base,$path); imagedestroy($base); return true; }
    // no usable TTF: built-in bitmap font (legible, plain) — name + brand on a centered bar
    $white=imagecolorallocate($base,255,255,255); $bar=imagecolorallocatealpha($base,0,0,0,60);
    $gf=5; $fw=imagefontwidth($gf); $fh=imagefontheight($gf); $txt=$name.($brand!==''?'  -  '.$brand:''); $max=(int)floor($maxW/$fw); if($max<1) $max=1;
    if(mb_strlen($txt)>$max) $txt=mb_substr($txt,0,max(1,$max-1)).'…'; $barH=$fh+16; $y0=(int)round($h*0.5-$barH/2);
    imagefilledrectangle($base,0,$y0,$w,$y0+$barH,$bar); $x=(int)round(($w-strlen($txt)*$fw)/2);
    imagestring($base,$gf,$x,$y0+(int)round(($barH-$fh)/2),$txt,$white); imagepng($base,$path); imagedestroy($base); return true; }
function attach_image($url,$pid,$alt,$name,$label=null){ $tmp=download_url($url,120); if(is_wp_error($tmp)) return [0,$tmp->get_error_message()];
    $ext=strtolower(pathinfo((string)parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION)); if(!in_array($ext,['png','jpg','jpeg','webp','gif'],true)) $ext='png';
    if(IMAGE_TEXT_OVERLAY && overlay_label($tmp,$label?:['name'=>$name])) $ext='png';   // print the 3-tier label (name / use / brand) — perfect spelling
    if(WATERMARK_LOGO!=='' && watermark($tmp)) $ext='png';
    $att=media_handle_sideload(['name'=>sanitize_title($name).'-'.$pid.'.'.$ext,'tmp_name'=>$tmp],$pid,$name);
    if(is_wp_error($att)){ @unlink($tmp); return [0,$att->get_error_message()]; }
    set_post_thumbnail($pid,$att); update_post_meta($att,'_wp_attachment_image_alt',$alt); return [$att,'']; }

// ---------------------------------------------------------------------------
// Branding: AI color palette + generated logo (icon emblem + composited brand name)
// ---------------------------------------------------------------------------
function hex_ok($c){ return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/',trim($c)); }
// deterministic fallback palette derived from the brand name (no AI) so branding still works with no text key / failed call
function palette_fallback(){ $seed=crc32(strtolower(brand().'|'.STORE_NICHE)); $h=$seed%360;
    $prim=hsl_hex($h,0.55,0.42); $sec=hsl_hex(($h+150)%360,0.60,0.48); $acc=hsl_hex(($h+30)%360,0.65,0.45);
    return ['primary'=>$prim,'secondary'=>$sec,'accent'=>$acc]; }
function hsl_hex($h,$s,$l){ $c=(1-abs(2*$l-1))*$s; $x=$c*(1-abs(fmod($h/60,2)-1)); $m=$l-$c/2;
    if($h<60){$r=$c;$g=$x;$b=0;}elseif($h<120){$r=$x;$g=$c;$b=0;}elseif($h<180){$r=0;$g=$c;$b=$x;}
    elseif($h<240){$r=0;$g=$x;$b=$c;}elseif($h<300){$r=$x;$g=0;$b=$c;}else{$r=$c;$g=0;$b=$x;}
    return sprintf('#%02x%02x%02x',(int)round(($r+$m)*255),(int)round(($g+$m)*255),(int)round(($b+$m)*255)); }
function brand_palette(){ static $p=null; if($p!==null) return $p; $p=palette_fallback();
    [$d]=ai_json("Choose a professional website color palette for ".brand().", an online store selling ".STORE_NICHE.". "
        ."Pick colors that fit the feel of that niche (readable, not neon). Return ONE JSON object: "
        ."{\"primary\":\"#RRGGBB\",\"secondary\":\"#RRGGBB\",\"accent\":\"#RRGGBB\"} — full 6-digit hex, no comments.");
    if(is_array($d)){ foreach(['primary','secondary','accent'] as $k){ if(hex_ok($d[$k]??null)) $p[$k]=strtolower(trim($d[$k])); } }
    return $p; }
// hex -> [r,g,b]
function hex_rgb($h){ $h=ltrim((string)$h,'#'); if(strlen($h)===3) $h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))]; }
function is_dark_hex($h){ [$r,$g,$b]=hex_rgb($h); return (0.299*$r+0.587*$g+0.114*$b)<140; }   // perceived luminance
// brand initials for the monogram badge: 'Acme Health' -> 'AH', 'Nootropics' -> 'NO'
function brand_initials(){ $parts=array_values(array_filter(preg_split('/\s+/',trim(brand()))));
    if(!$parts) return 'A'; if(count($parts)>=2) return strtoupper(mb_substr($parts[0],0,1).mb_substr($parts[count($parts)-1],0,1));
    return strtoupper(mb_substr($parts[0],0,2)); }
function logo_tmp_png(){ return get_temp_dir().'wcm-icon-'.substr(md5(brand().microtime()),0,10).'.png'; }
// strip anything unsafe/unsupported from AI-written SVG before we rasterize it locally (we never store or serve the raw SVG)
function sanitize_svg($svg){ $svg=(string)$svg;
    $svg=preg_replace('#<script\b.*?</script>#is','',$svg);
    $svg=preg_replace('#<foreignObject\b.*?</foreignObject>#is','',$svg);
    $svg=preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i','',$svg);             // inline event handlers
    $svg=preg_replace('/(?:xlink:href|href)\s*=\s*("[^"]*"|\'[^\']*\')/i','',$svg); // external references (no SSRF)
    return preg_match('#<svg\b.*</svg>#is',$svg,$m) ? $m[0] : ''; }
// rasterize an SVG string to a transparent PNG (Imagick only). '' if this host has no SVG support.
function rasterize_svg($svg){ if($svg===''||!class_exists('Imagick')) return '';
    try{ $im=new Imagick(); $im->setBackgroundColor(new ImagickPixel('transparent'));
        $im->readImageBlob("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$svg);
        $im->setImageFormat('png'); $im->resizeImage(400,400,Imagick::FILTER_LANCZOS,1,true);
        $tmp=logo_tmp_png(); $im->writeImage($tmp); $im->clear(); return @file_exists($tmp)?$tmp:''; }
    catch(\Throwable $e){ return ''; } }
// draw a clean monogram badge (brand initials in a colored rounded square) — pure code, no API, always works
function monogram_icon($palette){ $sz=400; $font=overlay_font(); $ini=brand_initials();
    $pc=hex_ok($palette['primary'])?$palette['primary']:'#446084'; [$pr,$pg,$pb]=hex_rgb($pc);
    if(class_exists('Imagick')){ try{ $c=new Imagick(); $c->newImage($sz,$sz,new ImagickPixel('transparent')); $c->setImageFormat('png');
        $bg=new ImagickDraw(); $bg->setFillColor(new ImagickPixel($pc)); $bg->roundRectangle(0,0,$sz-1,$sz-1,72,72); $c->drawImage($bg);
        $t=new ImagickDraw(); if($font) $t->setFont($font); $t->setFillColor(new ImagickPixel('#ffffff')); $t->setTextAlignment(Imagick::ALIGN_CENTER); $t->setFontSize($sz*0.42);
        $c->annotateImage($t,$sz/2,$sz*0.60,0,$ini); $tmp=logo_tmp_png(); $c->writeImage($tmp); $c->clear(); return @file_exists($tmp)?$tmp:''; }catch(\Throwable $e){} }
    $im=imagecreatetruecolor($sz,$sz); imagesavealpha($im,true); imagealphablending($im,false);
    imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127)); imagealphablending($im,true);
    imagefilledrectangle($im,0,0,$sz,$sz,imagecolorallocate($im,$pr,$pg,$pb)); $white=imagecolorallocate($im,255,255,255);
    if($font && function_exists('imagettftext')){ $fs=$sz*0.40; $bb=imagettfbbox($fs,0,$font,$ini); imagettftext($im,$fs,0,(int)(($sz-($bb[2]-$bb[0]))/2),(int)(($sz+($bb[1]-$bb[7]))/2),$white,$font,$ini); }
    else { $gf=5; imagestring($im,$gf,(int)(($sz-imagefontwidth($gf)*strlen($ini))/2),(int)($sz/2-imagefontheight($gf)/2),$ini,$white); }
    $tmp=logo_tmp_png(); imagepng($im,$tmp); imagedestroy($im); return @file_exists($tmp)?$tmp:''; }
// Build the wordmark logo with the EXISTING text AI (no image API): the AI writes a flat SVG icon, we rasterize it and
// composite the brand name (+tagline) as real text. If SVG can't be rasterized here, we draw a monogram badge instead.
function generate_logo($palette){ $icon='';
    [$d]=ai_json("Design a simple, modern, FLAT VECTOR icon that symbolizes ".STORE_NICHE." for the brand ".brand().". "
        ."No text, no letters, no words inside the icon. Use only these hex colors: ".$palette['primary'].", ".$palette['secondary'].", ".$palette['accent']." and white. "
        ."Return ONE JSON object: {\"svg\":\"<svg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'>...</svg>\"} — a COMPLETE standalone SVG: a viewBox, clean geometric shapes, centered with padding, NO <script>, NO <text>, NO external images.");
    $svg=is_array($d)?trim((string)($d['svg']??'')):'';
    if($svg!=='') $icon=rasterize_svg(sanitize_svg($svg));   // AI icon when the host can rasterize SVG...
    if($icon==='') $icon=monogram_icon($palette);            // ...otherwise a guaranteed code-drawn monogram badge
    if($icon==='') return [null,'could not build a logo icon'];
    $out=wp_upload_dir(); $dir=trailingslashit($out['path']); $file=$dir.'wcm-logo-'.substr(md5(brand().microtime()),0,8).'.png';
    $ok=compose_logo($icon,$file,brand(),tagline(),$palette); @unlink($icon);
    if(!$ok) return [null,'logo compose failed (no usable system font for the wordmark)'];
    return [trailingslashit($out['url']).basename($file),$file]; }
// composite icon + name (+tagline) onto a LOGO_BG canvas. Imagick preferred, GD fallback.
function compose_logo($iconPath,$outPath,$name,$tagline,$palette){ $font=overlay_font();
    $bg=hex_ok(LOGO_BG)?LOGO_BG:'#ffffff'; $textcol=is_dark_hex($bg)?'#ffffff':$palette['primary']; $subcol=is_dark_hex($bg)?'#dddddd':'#666666';
    $H=220; $pad=24; $iconW=180;
    if(class_exists('Imagick')){ try{
        $icon=new Imagick($iconPath); $icon->setImageFormat('png'); $icon->resizeImage($iconW,$iconW,Imagick::FILTER_LANCZOS,1);
        if(is_dark_hex($bg)) $icon->transparentPaintImage(new ImagickPixel('white'),0.0,6000,false);   // knock out white so a light icon sits on a dark bar
        $canvas=new Imagick(); $canvas->newImage(900,$H,new ImagickPixel($bg)); $canvas->setImageFormat('png');
        $canvas->compositeImage($icon,Imagick::COMPOSITE_OVER,$pad,(int)(($H-$iconW)/2));
        $tx=$pad+$iconW+$pad; $tw=900-$tx-$pad;
        $d=new ImagickDraw(); if($font) $d->setFont($font); $d->setFillColor(new ImagickPixel($textcol)); $d->setTextAlignment(Imagick::ALIGN_LEFT);
        $size=64; for(;$size>=22;$size-=2){ $d->setFontSize($size); $m=$canvas->queryFontMetrics($d,$name); if($m['textWidth']<=$tw) break; }
        $d->setFontSize($size); $ny=$tagline!==''?$H/2-6:$H/2+$size*0.35; $canvas->annotateImage($d,$tx,$ny,0,$name);
        if($tagline!==''){ $d2=new ImagickDraw(); if($font) $d2->setFont($font); $d2->setFillColor(new ImagickPixel($subcol)); $d2->setTextAlignment(Imagick::ALIGN_LEFT);
            $ss=24; for(;$ss>=12;$ss-=1){ $d2->setFontSize($ss); $m=$canvas->queryFontMetrics($d2,$tagline); if($m['textWidth']<=$tw) break; }
            $d2->setFontSize($ss); $canvas->annotateImage($d2,$tx,$H/2+$size*0.55,0,$tagline); }
        $canvas->trimImage(0); $canvas->setImagePage(0,0,0,0);   // trim empty canvas space to the content
        $canvas->writeImage($outPath); $canvas->clear(); $icon->clear(); return true; }catch(\Throwable $e){} }
    // GD fallback
    if(!function_exists('imagettftext')||!$font) return false;
    $icon=@imagecreatefromstring(@file_get_contents($iconPath)); if(!$icon) return false;
    $iw=imagesx($icon); $ih=imagesy($icon); $sc=imagecreatetruecolor($iconW,$iconW); imagealphablending($sc,true);
    imagecopyresampled($sc,$icon,0,0,0,0,$iconW,$iconW,$iw,$ih);
    $canvas=imagecreatetruecolor(900,$H); [$br,$bg2,$bb]=hex_rgb($bg); imagefill($canvas,0,0,imagecolorallocate($canvas,$br,$bg2,$bb));
    imagecopy($canvas,$sc,$pad,(int)(($H-$iconW)/2),0,0,$iconW,$iconW);
    [$tr,$tg,$tb]=hex_rgb($textcol); $tc=imagecolorallocate($canvas,$tr,$tg,$tb);
    $tx=$pad+$iconW+$pad; $tw=900-$tx-$pad; $size=54;
    for(;$size>=20;$size-=2){ $bb2=imagettfbbox($size,0,$font,$name); if(($bb2[2]-$bb2[0])<=$tw) break; }
    $ny=$tagline!==''?(int)($H/2-4):(int)($H/2+$size*0.35); imagettftext($canvas,$size,0,$tx,$ny,$tc,$font,$name);
    if($tagline!==''){ [$sr,$sg,$sb]=hex_rgb($subcol); $scc=imagecolorallocate($canvas,$sr,$sg,$sb); $ss=22;
        for(;$ss>=11;$ss-=1){ $bb3=imagettfbbox($ss,0,$font,$tagline); if(($bb3[2]-$bb3[0])<=$tw) break; }
        imagettftext($canvas,$ss,0,$tx,(int)($H/2+$size*0.6),$scc,$font,$tagline); }
    imagepng($canvas,$outPath); imagedestroy($canvas); imagedestroy($icon); imagedestroy($sc); return true; }
// download a logo image URL into the media library once; return attachment id (0 on failure)
function sideload_logo($url){ $url=trim((string)$url); if($url==='') return 0;
    $tmp=download_url($url,120); if(is_wp_error($tmp)) return 0;
    $ext=strtolower(pathinfo((string)parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION)); if(!in_array($ext,['png','jpg','jpeg','webp','gif'],true)) $ext='png';
    $att=media_handle_sideload(['name'=>'wcm-logo.'.$ext,'tmp_name'=>$tmp],0,brand().' logo');
    if(is_wp_error($att)){ @unlink($tmp); return 0; }
    update_post_meta($att,'_wp_attachment_image_alt',brand().' logo'); return (int)$att; }
// resolve ONE reusable logo attachment id (cached in wcm_logo_att): provided LOGO_URL > existing custom_logo > existing site_logo. 0 if none available.
function resolve_logo_attachment(){ static $id=null; if($id!==null) return $id;
    $cached=(int)get_option('wcm_logo_att'); if($cached && get_post($cached)) return $id=$cached;
    if(LOGO_URL!==''){ $a=sideload_logo(LOGO_URL); if($a){ update_option('wcm_logo_att',$a,false); return $id=$a; } }
    $cl=(int)get_theme_mod('custom_logo'); if($cl && get_post($cl)){ update_option('wcm_logo_att',$cl,false); return $id=$cl; }   // custom_logo is already an attachment id
    $sl=get_theme_mod('site_logo'); if(is_string($sl)&&$sl!==''){ $a=sideload_logo($sl); if($a){ update_option('wcm_logo_att',$a,false); return $id=$a; } }
    return $id=0; }
function form_hint($h){ foreach([
    // food / snacks / drinks
    'chip'=>'a printed stand-up snack pouch','crisp'=>'a printed stand-up snack pouch','snack'=>'a printed stand-up snack pouch','plantain'=>'a printed stand-up snack pouch',
    'juice'=>'a labeled glass bottle of juice','drink'=>'a labeled bottle','smoothie'=>'a labeled bottle','tea'=>'a labeled box of tea','coffee'=>'a labeled coffee bag','honey'=>'a labeled honey jar','oil'=>'a labeled bottle','sauce'=>'a labeled bottle','spice'=>'a labeled spice jar','flour'=>'a labeled paper bag','cereal'=>'a printed carton',
    // cosmetics / personal care
    'cream'=>'a labeled cosmetic jar','lotion'=>'a labeled pump bottle','serum'=>'a labeled dropper bottle','soap'=>'a wrapped/boxed soap bar','shampoo'=>'a labeled bottle','balm'=>'a labeled tin','scrub'=>'a labeled cosmetic jar','perfume'=>'a labeled glass perfume bottle',
    // generic packaging keywords
    'sachet'=>'a sealed sachet','pouch'=>'a resealable pouch','tube'=>'a labeled tube','jar'=>'a labeled jar','bottle'=>'a labeled bottle','box'=>'a printed box','carton'=>'a printed carton','packet'=>'a sealed packet','powder'=>'a sealed labeled pouch of powder','liter'=>'a labeled liquid container',
    // unpackaged goods (no label)
    'engine'=>'a complete automotive engine, unpackaged','tool'=>'the bare tool, unpackaged','part'=>'the bare part, unpackaged','hammer'=>'a bare hammer, unpackaged',
] as $k=>$v){ if(strpos($h,$k)!==false) return $v; } return ''; }
function image_subject($pid,$name,$catname){ $s=trim((string)get_post_meta($pid,'_image_subject',true)); if($s!=='') return $s;
    $u=trim((string)get_post_meta($pid,'_unit_of_sale',true)); $p=wc_get_product($pid); $short=$p?excerpt($p->get_short_description(),200):'';
    $hint=form_hint(strtolower($u.' '.$short.' '.$name.' '.$catname)); $cat=$catname?" for $catname":'';
    if($u!=='') return "$name$cat, supplied as $u".($hint?" — shown as $hint":''); if($hint) return "$name$cat, shown as $hint"; return "$name$cat, ".STORE_NICHE; }

// ---------------------------------------------------------------------------
// Interlinks (deterministic)
// ---------------------------------------------------------------------------
function inject_links($html,$related,$cat_url,$cat_name){ $related=array_values(array_filter($related,fn($r)=>!empty($r['url'])));
    $have=existing_hrefs($html); $miss=array_filter($related,fn($r)=>!in_array($r['url'],$have,true)); $needcat=$cat_url&&!in_array($cat_url,$have,true);
    if(!$miss&&!$needcat) return $html; $li='';
    foreach($miss as $r){ $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; }   // only the links not already present — never duplicates
    $b=$li?"\n<h2>Related Products</h2>\n<ul>$li</ul>":''; if($needcat) $b.="\n<p>Browse more in <a href=\"".esc_url($cat_url).'">'.esc($cat_name).'</a>.</p>';
    return $html.$b; }

// ---------------------------------------------------------------------------
// Blog links: keep our own internal links, allow ONE authoritative outbound
// link (gov/edu/public-info/legal), unwrap every other external link so no
// business/competitor URL ever survives. Then top up internal links to target.
// ---------------------------------------------------------------------------
function blog_authoritative_host($host){ $h=preg_replace('/^www\./','',strtolower((string)$host)); if($h==='') return false;
    if(preg_match('/(^|\.)(gov|mil|edu|int)$/',$h)) return true;   // .gov .mil .edu .int as the ACTUAL TLD only — strict, so a registrable lookalike like "shop.gov.io" is NOT treated as authoritative
    $ok=['gov.uk','gov.au','gov.ca','gov.in','gov.za','gov.sg','gov.br','gov.nz','gov.ie','ac.uk','edu.au','nhs.uk',   // legit foreign government / public-academic second-level domains (suffix-matched, closes the .gov.<cc> loophole)
         'who.int','un.org','europa.eu','ec.europa.eu','efsa.europa.eu','ema.europa.eu','nih.gov','ncbi.nlm.nih.gov',
         'fda.gov','cdc.gov','ftc.gov','usda.gov','epa.gov','osha.gov','nist.gov','cpsc.gov','sec.gov','irs.gov','loc.gov',
         'law.cornell.edu','wikipedia.org'];   // curated non-commercial public/reference sources
    foreach($ok as $d){ if($h===$d || substr($h,-(strlen($d)+1))==='.'.$d) return true; } return false; }
function blog_is_internal_href($u){ $u=trim((string)$u); if($u===''||$u[0]==='#') return false; if($u[0]==='/') return true;
    $host=parse_url($u,PHP_URL_HOST); return $host && preg_replace('/^www\./','',strtolower($host))===site_host(); }
function blog_count_internal($html){ $n=0; foreach(existing_hrefs($html) as $u){ if(blog_is_internal_href($u)) $n++; } return $n; }
function blog_href_key($u){ $u=trim((string)$u); $pp=parse_url($u); $path=isset($pp['path'])?$pp['path']:$u; $k=rtrim(strtolower((string)$path),'/'); return $k===''?'/':$k; }
// Always-live, non-blog link targets (shop, categories, products, contact, homepage). Memoized — these never depend on the schedule.
function blog_static_pool(){ static $p=null; if($p!==null) return $p; $raw=[];
    $raw[]=['url'=>shop_url(),'name'=>'our full catalog']; $raw[]=['url'=>home_url('/'),'name'=>brand()];
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'number'=>20]) as $t){ if(is_wp_error($t)||strtolower($t->slug)==='uncategorized') continue; $u=get_term_link($t); if(!is_wp_error($u)) $raw[]=['url'=>$u,'name'=>$t->name]; }
    foreach(get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>30,'orderby'=>'ID','order'=>'ASC']) as $po){ $raw[]=['url'=>get_permalink($po),'name'=>get_the_title($po)]; }   // full objects prime the post cache -> get_permalink/get_the_title are cache hits
    $cp=get_page_by_path('contact-us'); if($cp) $raw[]=['url'=>get_permalink($cp->ID),'name'=>'contact us'];
    $seen=[]; $p=[]; foreach($raw as $r){ $u=site_link($r['url']); if(!$u||is_wp_error($u)||isset($seen[$u])) continue; $seen[$u]=1; $p[]=['url'=>$u,'name'=>$r['name']]; } return $p; }
// Every known blog post (publish + future) with its LOCAL publish date string. Seeds once from the DB, then accumulates posts we create this run (call with $add) so later posts can link back to earlier ones in the SAME batch.
function blog_known_posts($add=null){ static $l=null;
    if($l===null){ $l=[]; foreach(get_posts(['post_type'=>'post','post_status'=>array('publish','future'),'numberposts'=>-1,'orderby'=>'date','order'=>'ASC']) as $po){ $u=site_link(post_pretty_link($po)); if($u&&!is_wp_error($u)) $l[]=['url'=>$u,'name'=>get_the_title($po),'date'=>(string)$po->post_date]; } }   // pretty URL even for future posts (not ?p=ID)
    if(is_array($add)) $l[]=$add; return $l; }
// Links a post publishing at $cut may use: the always-live pool PLUS any blog post that goes live at or before $cut (so the link is valid the moment THIS post appears). $cut = the authoring post's local 'Y-m-d H:i:s'.
function blog_linkable($cut){ $pool=blog_static_pool(); foreach(blog_known_posts() as $b){ if(strcmp((string)$b['date'],(string)$cut)<=0) $pool[]=['url'=>$b['url'],'name'=>$b['name']]; } return $pool; }
function blog_internal_ok($url,$cut){ $full=(isset($url[0])&&$url[0]==='/')?home_url($url):$url; $pid=url_to_postid($full); if($pid<=0) return false;   // fallback for links not already in the offered set
    $st=get_post_status($pid); if($st==='publish') return true;                                                    // already live -> never 404s
    if($st==='future') return strcmp((string)get_post_field('post_date',$pid),(string)$cut)<=0;                    // scheduled -> ok only if it publishes at/before this post goes live
    return false; }
function blog_link_allow($cut){ static $c=[]; $key=$cut.'|'.count(blog_known_posts()); if(isset($c[$key])) return $c[$key];   // memoize per (cutoff, #known posts): same $dl is reused across menu/filter/topup for one post; the count in the key prevents a STALE allowlist being reused after the known-post set grew (e.g. a page cached at now, then blog posts get created at the same second)
    $allow=[]; foreach(blog_linkable($cut) as $r){ $allow[blog_href_key($r['url'])]=1; }   // every live page + every post scheduled at/before $cut
    $bp=get_page_by_path('blog'); if($bp) $allow[blog_href_key(get_permalink($bp->ID))]=1;   // the /blog listing is always live
    return $c[$key]=$allow; }
// Page/category guard: unwrap only INTERNAL links that aren't live by $cut (external + mailto/tel/anchor left untouched — pages handle external via strip_foreign_links). Use $cut='now' since pages publish immediately.
function strip_future_internal_links($html,$cut){ if(strpos((string)$html,'<a')===false) return (string)$html; $allow=blog_link_allow($cut);
    return preg_replace_callback('/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', function($m) use($allow,$cut){
        $url=trim($m[1]); if(is_foreign_url($url)) return $m[0]; $lo=strtolower($url);
        if($url===''||$url[0]==='#'||strncmp($lo,'mailto:',7)===0||strncmp($lo,'tel:',4)===0) return $m[0];
        if(isset($allow[blog_href_key($url)])) return $m[0];
        return blog_internal_ok($url,$cut) ? $m[0] : $m[2]; },(string)$html); }
function blog_filter_links($html,$cut){ if(strpos((string)$html,'<a')===false) return [(string)$html,false]; $extKept=false;
    $allow=blog_link_allow($cut);
    $out=preg_replace_callback('/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', function($m) use(&$extKept,$allow,$cut){
        $url=trim($m[1]);
        if(!is_foreign_url($url)){                                                        // internal / relative / mailto / tel / anchor
            $lo=strtolower($url);
            if($url===''||$url[0]==='#'||strncmp($lo,'mailto:',7)===0||strncmp($lo,'tel:',4)===0) return $m[0];   // anchors + mailto/tel are always safe -> keep
            if(isset($allow[blog_href_key($url)])) return $m[0];                          // a page/post we offered (live now or scheduled at/before this post) -> keep
            return blog_internal_ok($url,$cut) ? $m[0] : $m[2];                           // fallback resolve + date-check; unwrap future/scheduled-later/hallucinated (they 404 when this post goes live)
        }
        if(BLOG_EXTERNAL_LINK && !$extKept && blog_authoritative_host((string)parse_url($url,PHP_URL_HOST))){ $extKept=true; return $m[0]; }
        return $m[2];                                                                    // any other external (incl. business/competitor) -> unwrap, keep the text
    },(string)$html); return [$out,$extKept]; }
function blog_link_menu($idx,$cut){ if((int)BLOG_INTERNAL_LINKS<=0) return [];   // 0 = user wants no internal links; don't suggest any
    $posts=[]; foreach(blog_known_posts() as $b){ if(strcmp((string)$b['date'],(string)$cut)<=0) $posts[]=['url'=>$b['url'],'name'=>$b['name'],'date'=>$b['date']]; }   // sibling posts already live by $cut
    usort($posts,fn($a,$b)=>strcmp((string)$a['date'],(string)$b['date']));         // force chronological order (a manual delete+recreate could seed the list out of order) so "newest first" below is always correct
    $static=blog_static_pool(); $np=count($posts); $ns=count($static); $k=min((int)BLOG_INTERNAL_LINKS+1,$np+$ns); if($k<=0) return [];   // menu size tracks BLOG_INTERNAL_LINKS (was hardcoded 6, which over-fed the AI)
    $out=[]; $seen=[]; $wantPosts=min($np,(int)ceil($k/2));                          // reserve about half (rounded up) of the menu for sibling posts, which used to sit at the tail of the pool and were almost never offered
    for($i=$np-1;$i>=0 && count($out)<$wantPosts;$i--){ if(isset($seen[$posts[$i]['url']])) continue; $seen[$posts[$i]['url']]=1; $out[]=$posts[$i]; }   // newest sibling posts first (most topically relevant backward links)
    for($i=0;$i<$ns && count($out)<$k;$i++){ $x=$static[($idx*3+$i)%$ns]; if(isset($seen[$x['url']])) continue; $seen[$x['url']]=1; $out[]=$x; }   // fill with shop/categories/products, rotated by post index so posts vary
    for($i=$np-1;$i>=0 && count($out)<$k;$i--){ if(isset($seen[$posts[$i]['url']])) continue; $seen[$posts[$i]['url']]=1; $out[]=$posts[$i]; }   // backfill leftover slots from remaining siblings (e.g. a very small static pool) so the menu is never short when material exists
    return $out; }
function blog_topup_links($html,$target,$cut){ $target=(int)$target; if($target<=0) return $html; $cur=blog_count_internal($html); if($cur>=$target) return $html;
    $have=[]; foreach(existing_hrefs($html) as $u){ $have[rtrim((string)$u,'/')]=1; } $need=$target-$cur; $li='';
    $sib=[]; foreach(blog_known_posts() as $b){ if(strcmp((string)$b['date'],(string)$cut)<=0) $sib[]=['url'=>$b['url'],'name'=>$b['name']]; } $sib=array_reverse($sib);   // sibling posts (newest first) BEFORE products/categories, so the deterministic top-up grows post-to-post linking too, not only product links
    foreach(array_merge($sib,blog_static_pool()) as $r){ if($need<=0) break; $ru=rtrim((string)$r['url'],'/'); if(isset($have[$ru])) continue; $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; $have[$ru]=1; $need--; }
    return $li==='' ? $html : $html."\n<h2>Explore more</h2>\n<ul>$li</ul>"; }
function blog_article_prompt($ti,$idx,$cut){ $links=''; foreach(blog_link_menu($idx,$cut) as $m){ $links.='  - '.$m['name'].': '.$m['url']."\n"; }
    $ext = BLOG_EXTERNAL_LINK
        ? "OUTBOUND LINK: include EXACTLY ONE outbound link, and add one to almost every article where it fits. Use ONLY an authoritative, non-commercial source: a .gov, .mil, .edu or .int page (prefer nih.gov, pubmed.ncbi.nlm.nih.gov, fda.gov, cdc.gov, clinicaltrials.gov, medlineplus.gov, who.int), or a well-known public-reference page (en.wikipedia.org). NEVER link to a store, brand, blog, marketplace, competitor or any commercial business — commercial links are automatically stripped out and would simply vanish. Only omit the outbound link if genuinely nothing relevant exists.\n"
        : "Do not add any outbound external links.\n";
    return "Write a ".BLOG_WORDS." word SEO blog article titled \"$ti\" for ".brand().", which sells ".STORE_NICHE.". Write for real buyers and to rank in Google. "
        .voice_rules().compliance_clause()
        ."STRUCTURE: valid HTML only — ONE <h1> (the title), then <h2>/<h3> sections, short scannable paragraphs, at least one <ul> list, and a short FAQ of 2-3 <h3> questions with answers.\n"
        ."INTERNAL LINKS: weave about ".BLOG_INTERNAL_LINKS." natural, in-context <a> links (NOT a link dump) to these pages of OUR OWN site, using the EXACT URLs shown, only where they genuinely fit — and prefer linking to our other blog posts in the list when relevant:\n".($links?:"  (none available yet)\n")
        .$ext.html_quote_rule()
        ."Return JSON: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}"; }

// ---------------------------------------------------------------------------
// Reference store (optional) — fetched ONCE, gives the AI a real market anchor
// ---------------------------------------------------------------------------
function reference_context(){ static $ctx=null; if($ctx!==null) return $ctx; $ctx='';
    if(REFERENCE_URL==='') return $ctx;
    $r=wp_remote_get(REFERENCE_URL,['timeout'=>20,'redirection'=>3,'headers'=>['User-Agent'=>'Mozilla/5.0 (compatible; wc-master)']]);
    if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!=200) return $ctx;
    $html=(string)wp_remote_retrieve_body($r); $bits=[];
    if(preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is',$html,$mm)){
        foreach($mm[1] as $blk){ $j=json_decode(trim($blk),true); if(!is_array($j)) continue;
            $items=(isset($j['@graph'])&&is_array($j['@graph']))?$j['@graph']:[$j];
            foreach($items as $it){ if(!is_array($it)) continue; $ty=$it['@type']??''; $ty=is_array($ty)?implode(',',$ty):(string)$ty;
                if(stripos($ty,'Product')!==false){ $prices=[]; $avail='';
                    $offers=$it['offers']??[]; if(isset($offers['@type'])||isset($offers['price'])) $offers=[$offers];
                    foreach((array)$offers as $of){ if(!is_array($of)) continue; if(isset($of['price'])) $prices[]=(string)$of['price']; if(!empty($of['availability'])) $avail=(string)$of['availability']; }
                    $bits[]='REF PRODUCT: '.trim(wp_strip_all_tags((string)($it['name']??'')))
                        .($prices?' | price'.(count($prices)>1?'s (looks VARIABLE)':'').': '.implode(', ',array_slice($prices,0,6)):'')
                        .($avail?' | '.preg_replace('#.*/#','',$avail):'')
                        .(!empty($it['description'])?' | desc: '.trim(mb_substr(wp_strip_all_tags((string)$it['description']),0,280)):''); }
                if(stripos($ty,'BreadcrumbList')!==false && !empty($it['itemListElement'])){ $cs=[];
                    foreach((array)$it['itemListElement'] as $li){ $nm=$li['name']??($li['item']['name']??''); if($nm) $cs[]=trim(wp_strip_all_tags((string)$nm)); }
                    if($cs) $bits[]='REF CATEGORY PATH: '.implode(' > ',array_slice($cs,0,6)); } } } }
    if(!$bits){ if(preg_match('#<title>(.*?)</title>#is',$html,$tm)) $bits[]='REF PAGE: '.trim(wp_strip_all_tags($tm[1]));
        $bits[]='REF TEXT: '.trim(preg_replace('/\s+/',' ',mb_substr(wp_strip_all_tags($html),0,400))); }
    if($bits) $ctx="MARKET REFERENCE from a comparable store (use ONLY as a guide for price tier, unit style, description depth and category naming — write 100% ORIGINAL copy, never reproduce their text):\n".implode("\n",array_slice($bits,0,8))."\n";
    return $ctx; }

// ---------------------------------------------------------------------------
// Product prompt (dynamic — only requested fields)
// ---------------------------------------------------------------------------
function product_prompt($ctx,$need){ $cur=currency(); $keys=[]; $req='';
    if($need['short']){ $req.="- short_description: marketing HTML, ".words_phrase(SHORT_DESC_WORDS).".\n"; $keys[]='short_description'; }
    if($need['long']){ $req.="- long_description: valid HTML, ".words_phrase(LONG_DESC_WORDS).", with <h2> Overview, <h2> Key Features (a <ul>), <h2> Specifications (a small <table>), <h2> FAQ (3 <h3> question + <p> answer), closing CTA.\n"; $keys[]='long_description'; }
    if($need['meta']){ $req.="- meta_title (<=60 chars, end ' | ".brand()."'), meta_description (<=155 chars, focus keyword), focus_keyword.\n"; array_push($keys,'meta_title','meta_description','focus_keyword'); }
    if($need['tags']){ $req.="- tags: 3-5 short relevant tags.\n"; $keys[]='tags'; }
    if($need['price']){ if(PRODUCT_TYPE==='variable'){ $req.="- attribute: the measurement dimension with unit (e.g. 'Dosage (mg)', 'Volume (L)', 'Quantity'). variations: 2-5 {label,price} where label is a value in that unit (e.g. '10mg','5L','2') and price is a plain number in $cur.\n"; array_push($keys,'attribute','variations'); } else { $req.="- price: realistic AVERAGE MARKET PRICE, plain number in $cur.\n"; $keys[]='price'; } }
    if($need['unit']){ $req.="- unit: what ONE purchase includes, with measurement (e.g. 'per 10 mg vial', 'per 5 L container', 'each (1 unit)').\n"; $keys[]='unit'; }
    if($need['image']){ $req.="- image_subject: from the TITLE and description, describe this product's real physical form and packaging for a studio product photo with NO person, NO hands and NO face in the frame. If it is a PACKAGED good (food, drink, cosmetic, powder, liquid, supplement, etc.), name the exact package (e.g. 'a stand-up matte foil snack pouch', 'a clear glass bottle with a cap', 'a frosted cosmetic jar', 'a printed kraft carton') and say the label is MINIMAL with the large product name \"{$ctx['title']}\" as the ONLY text on it, and NO other writing at all: NO brand slogan, NO tagline, NO net weight/volume, NO ingredient list, NO directions, NO barcode, NO small print and NO secondary lines (the rest of the label is plain and empty, so no small filler text can be misspelled). If it is an UNPACKAGED item (hand tool, machine part, engine, furniture, electronics, raw hardware, etc.), describe the bare product with NO packaging and NO label. Keep it to one concise phrase.\n"; $keys[]='image_subject';
        $req.="- image_use: the product's core use or benefit as a punchy 1-3 word phrase for the FRONT of the pack (e.g. 'Pain Relief', 'Deep Sleep', 'Muscle Recovery', 'Daily Cleanser'). Plain words only.\n"; $keys[]='image_use'; }
    $sales=SALES_ORIENTED?"Sales-oriented: weave in natural buy/shop/for-sale phrasing and the focus keyword early.":"Informative and helpful.";
    return "You are an expert e-commerce SEO copywriter for ".brand().", selling ".STORE_NICHE.". $sales\n"
        ."Use this product's real context:\nTITLE: {$ctx['title']}\nCATEGORY: {$ctx['cat']}\nEXISTING SHORT: {$ctx['short']}\nEXISTING LONG: {$ctx['long']}\nCURRENCY: $cur\n"
        .reference_context()
        .voice_rules().compliance_clause().$req.html_quote_rule()
        ."Return ONE JSON object with exactly these keys: ".implode(', ',$keys)."."; }

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------
function find_or_create_cat($name){ $t=get_term_by('name',$name,'product_cat'); if($t&&!is_wp_error($t)) return (int)$t->term_id;
    $r=wp_insert_term($name,'product_cat'); return is_wp_error($r)?0:(int)$r['term_id']; }
function ai_category_names($titles){ $list=implode("\n",array_map(fn($t)=>'  - '.$t,array_slice($titles,0,400)));
    [$d]=ai_json("You are a merchandising expert for ".brand()." selling ".STORE_NICHE.".\nProducts:\n$list\n".reference_context()
        ."Propose ".AUTO_CATEGORY_COUNT." FLAT top-level e-commerce categories that group these by USE/type, using "
        ."SEO-friendly search-term names. Do NOT make one category per product. Return ONE JSON object: {\"categories\":[\"...\"]} — no comments, no trailing commas.");
    return is_array($d)&&!empty($d['categories'])?array_values(array_filter(array_map('trim',(array)$d['categories']))):[]; }
function ai_map_chunk($chunk,$cats){ $lines=''; foreach($chunk as $c){ $lines.="  {$c['id']} | {$c['title']} | ".excerpt($c['short'],80)."\n"; }
    $cl=implode(', ',$cats);
    [$d]=ai_json("Assign each product to exactly ONE category from this list: [$cl].\nProducts (id | title | note):\n$lines\n"
        ."Return ONE JSON object: {\"map\":[{\"id\":123,\"category\":\"Exact Category Name\"}]} — use the exact category names, no comments, no trailing commas.");
    $out=[]; if(is_array($d)) foreach(($d['map']??[]) as $m){ if(!empty($m['id'])&&!empty($m['category'])) $out[(int)$m['id']]=trim((string)$m['category']); } return $out; }

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------
function find_or_create_page($slug,$title){ $p=get_page_by_path($slug); if($p) return (int)$p->ID;
    return (int)wp_insert_post(['post_type'=>'page','post_name'=>$slug,'post_title'=>$title,'post_status'=>PUBLISH_STATE,'post_content'=>'']); }
function write_page_via_ai($slug,$title,$prompt){ $pid=find_or_create_page($slug,$title); if(!$pid) return;
    if(get_post_meta($pid,'_wcm_page_done',true)){ out("   [skip] $title (already done; RESET_PROGRESS to redo)",'#888'); return; }
    $cur=trim((string)get_post_field('post_content',$pid)); if($cur!=='' && !OVERWRITE_PAGES){ out("   [skip] $title already has content (OVERWRITE_PAGES=false)",'#888'); return; }
    [$d,$err]=ai_json($prompt); if(!$d||empty($d['content'])){ out("   [skip] $title — ".($err?:'no content'),'#f66'); return; }
    $c=dedash((string)$d['content']); if(REMOVE_FOREIGN_LINKS) $c=strip_foreign_links($c);   // strip off-site/competitor links from AI pages too (the one-shot REMOVE_FOREIGN_LINKS phase runs BEFORE these pages exist, so it never reaches them)
    $c=append_disclaimer(strip_future_internal_links($c,current_time('mysql')));   // a published page may only link to already-live pages/posts — strip any hallucinated link to a not-yet-published post
    wp_update_post(['ID'=>$pid,'post_content'=>$c]);
    if(!empty($d['meta_title'])) update_post_meta($pid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
    if(!empty($d['meta_description'])) update_post_meta($pid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
    if(!empty($d['focus_keyword'])) update_post_meta($pid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
    update_post_meta($pid,'_wcm_page_done',1); out("   [ok] $title",'#6f6'); }
// dynamic page prompt: a random angle + structure each time so pages don't read like the same template
function page_prompt($what,$extra=''){
    $angles=['Open with a short real-world scenario a buyer relates to.','Lead with the single most useful fact, then expand.','Use a warm, first-person brand voice.','Open with a promise, then prove it with specifics.','Use a question-then-answer rhythm.'];
    $structs=['3-5 <h2> sections of varied length.','a mix of short paragraphs and one <ul> list.','a couple of <h2> sections plus a short FAQ (<h3> + <p>).','narrative paragraphs plus a small <table> where it genuinely helps.'];
    $angle=$angles[mt_rand(0,count($angles)-1)]; $struct=$structs[mt_rand(0,count($structs)-1)];
    return "Write the '$what' page for ".brand().", a US-based online store selling ".STORE_NICHE.". "
    ."$extra\nMAKE IT DISTINCT — not a boilerplate template. Approach: $angle Structure: $struct Vary the headings and wording so it does not read like the store's other pages. Keep it a standard, professional, trustworthy company page, ".words_phrase('450-800').".\n"
    .company_facts()
    .voice_rules().compliance_clause()
    ."Valid HTML, proper heading hierarchy (one <h1>, then <h2>). Use SINGLE quotes for HTML attributes. "
    ."Return ONE valid JSON object: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"} — no comments, no trailing commas, single-line HTML value."; }

// --- Flatsome detection + single-column wrapper: keeps page content in a centered, constrained column
//     instead of running edge-to-edge. On Flatsome sites it uses the theme's section/row/col; elsewhere a max-width div.
function is_flatsome(){ static $f=null; if($f===null){ $t=function_exists('wp_get_theme')?wp_get_theme():null;
    $f=$t?(stripos((string)$t->get('Name'),'flatsome')!==false || stripos((string)$t->get_template(),'flatsome')!==false):false; } return $f; }
function page_wrap($html){ $html=(string)$html; if(trim($html)==='') return $html;
    if(is_flatsome()||HOMEPAGE_FORMAT==='flatsome') return "[section padding=\"60px\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n".$html."\n[/col]\n[/row]\n[/section]\n";
    return "<div style='max-width:820px;margin:0 auto;padding:40px 20px'>".$html."</div>"; }

// normalize intro_paragraphs: accept an array, OR a single string (split on blank lines / newlines so it still renders as paragraphs)
function intro_paras($v){ if(is_array($v)) return $v; $s=trim((string)$v); if($s==='') return [];
    $parts=preg_split('/\n\s*\n|\r\n\s*\r\n/',$s); if(count($parts)<2) $parts=preg_split('/\r\n|\n|\r/',$s);
    return array_values(array_filter(array_map('trim',$parts),fn($x)=>$x!=='')); }

// --- Flatsome homepage assembler (from the homepage builder) ---
function build_flatsome($f,$cats,$prods){ $shop=shop_url(); $btn=esc($f['cta_button']??'Shop Now'); $o='';
    // HERO — centered headline, lead line, primary CTA (text + button centered together)
    $o.="[section label=\"Hero\" padding=\"70px\" bg_color=\"#f7f7f9\"]\n[row h_align=\"center\"]\n[col span=\"10\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h1>".esc($f['hero_headline']??brand())."</h1>\n<p style='font-size:1.15em'>".esc($f['hero_intro']??'')."</p>\n[button text=\"$btn\" size=\"large\" link=\"".esc_url($shop)."\"]\n</div>\n[/col]\n[/row]\n[/section]\n";
    // INTRO — heading + 2-3 substantial paragraphs, centered narrow column so it reads like real editorial content
    $ip=intro_paras($f['intro_paragraphs']??null);
    if($ip){ $ptxt=''; foreach($ip as $para){ $pp=esc(is_array($para)?($para['text']??''):$para); if(trim($pp)!=='') $ptxt.="<p>$pp</p>\n"; }
        if($ptxt!==''){ $o.="[section label=\"Intro\" padding=\"45px\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n".(!empty($f['intro_heading'])?"<h2 style='text-align:center'>".esc($f['intro_heading'])."</h2>\n":'').$ptxt."[/col]\n[/row]\n[/section]\n"; } }
    // FEATURED PRODUCTS — moved up so the page leads with product visuals, not a wall of category links
    if($prods){ $ids=implode(',',array_map(fn($p)=>(int)$p['id'],$prods)); $o.="[section label=\"Featured\" padding=\"35px\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($f['featured_heading']??'Featured Products')."</h2>\n[ux_products ids=\"$ids\"]\n[/col]\n[/row]\n[/section]\n"; }
    // WHY US — trust points as equal cards
    $pts=is_array($f['why_us_points']??null)?$f['why_us_points']:[];
    if($pts){ $sp=count($pts)>=3?4:(count($pts)===2?6:12); $o.="[section label=\"Why Us\" padding=\"45px\" bg_color=\"#f7f7f9\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($f['why_us_heading']??'Why Choose Us')."</h2>\n[/col]\n[/row]\n[row]\n";
        foreach($pts as $pt){ $t=esc(is_array($pt)?($pt['title']??''):$pt); $dd=esc(is_array($pt)?($pt['text']??''):''); $o.="[col span=\"$sp\" span__sm=\"12\"]\n[featured_box]\n<h3>$t</h3>\n".($dd!==''?"<p>$dd</p>\n":'')."[/featured_box]\n[/col]\n"; }
        $o.="[/row]\n[/section]\n"; }
    // CATEGORIES — compact button grid (4 across on desktop, 2 on mobile) instead of one long bullet list
    if($cats){ $head=esc($f['categories_heading']??'Shop by Category');
        $o.="[section label=\"Categories\" padding=\"35px\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>$head</h2>\n[/col]\n[/row]\n[row]\n";
        foreach($cats as $c){ if(empty($c['url'])) continue; $o.="[col span=\"3\" span__sm=\"6\"]\n[button text=\"".esc($c['name'])."\" style=\"outline\" expand=\"true\" link=\"".esc_url($c['url'])."\"]\n[/col]\n"; }
        $o.="[/row]\n[/section]\n"; }
    // ABOUT
    if(!empty($f['about_text'])){ $o.="[section label=\"About\" padding=\"45px\" bg_color=\"#f7f7f9\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($f['about_heading']??'About Us')."</h2>\n<p>".esc($f['about_text'])."</p>\n[/col]\n[/row]\n[/section]\n"; }
    // FAQ
    if(is_array($f['faq']??null)&&$f['faq']){ $q="<h2 style='text-align:center'>Frequently Asked Questions</h2>\n"; foreach($f['faq'] as $qa){ $qq=esc($qa['q']??''); if($qq)$q.="<h3>$qq</h3>\n<p>".esc($qa['a']??'')."</p>\n"; } $o.="[section label=\"FAQ\" padding=\"45px\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n".$q."[/col]\n[/row]\n[/section]\n"; }
    // CLOSING CTA — full-width band
    if(!empty($f['closing_cta'])){ $o.="[section label=\"CTA\" padding=\"55px\" bg_color=\"#f7f7f9\"]\n[row h_align=\"center\"]\n[col span=\"10\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h3>".esc($f['closing_cta'])."</h3>\n[button text=\"$btn\" size=\"large\" link=\"".esc_url($shop)."\"]\n</div>\n[/col]\n[/row]\n[/section]\n"; }
    if(COMPLIANCE_MODE&&DISCLAIMER_HTML) $o.="[section padding=\"20px\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<small>".DISCLAIMER_HTML."</small>\n[/col]\n[/row]\n[/section]\n";
    return $o; }

function resolve_front(){ $f=(get_option('show_on_front')==='page')?(int)get_option('page_on_front'):0; if($f&&get_post($f)) return $f;
    $f=(int)wp_insert_post(['post_title'=>'Home','post_type'=>'page','post_status'=>'publish','post_content'=>'']); if($f){ update_option('show_on_front','page'); update_option('page_on_front',$f); } return $f; }

// ---------------------------------------------------------------------------
// RUN
// ---------------------------------------------------------------------------
out('== WC Master ==  provider='.AI_PROVIDER,'#6cf');
global $wpdb;   // make the DB handle explicit for every phase below (used in categories, products, stock, blog, cleanup)
// Index the whole catalog in ONE query. Title + short description come straight off the WP_Post rows —
// no per-product object loads. POSTMETA caching is OFF so we don't pull every product's meta into memory
// (heavy at thousands of products); meta is bulk-primed per batch below instead. TERM caching stays ON —
// it's cheap (just category/tag IDs) and makes the grouping + tag checks below cache hits. Ordered by ID
// so the batch offset lands on the same product on every refresh.
$P=[]; foreach(get_posts(['post_type'=>'product','post_status'=>PRODUCT_STATUSES,'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','update_post_meta_cache'=>false]) as $po){
    $P[$po->ID]=['title'=>$po->post_title,'short'=>$po->post_excerpt,'status'=>$po->post_status]; }
$ids=array_keys($P);
out('Found '.count($ids).' products');
if($ids) update_object_term_cache($ids,'product');   // ONE bulk load of every product's categories + tags, so the many get_the_terms() calls below (primary-category map, tag checks, category-safety reads) are cache hits instead of O(N) per-product queries

if (ENABLE_LAWFUL_USE_GUARD) { $hay='';   // lawful-use catalog guard — toggleable via the const above
    foreach($P as $d){ $hay.=strtolower($d['title']).' '; }
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ $hay.=strtolower($t->name).' '; }
    $hits=array_values(array_unique(array_filter($GUARD_TERMS,fn($t)=>strpos($hay,$t)!==false)));
    if($hits){ out('[ABORTED] Lawful-use guard: '.implode(', ',$hits),'#f66'); out('This tool is for lawful catalogs only.','#f66'); exit; } }

// ---- RESET / REPROCESS (fires ONCE per arming; independent of which phases are enabled) ------------
delete_option('wcm_offset');   // retire the old positional-offset resume
if(RESET_PROGRESS || REPROCESS_IDS){ if(!get_option('wcm_reset_done')){   // a saved marker stops it repeating on refresh
    if(RESET_PROGRESS){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wcm_done','_wcm_page_done','_wcm_img_done')"); $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key='_wcm_cat_done'"); delete_option('wcm_grouping_done'); delete_option('wcm_stock_done'); delete_option('wcm_foreign_done'); delete_option('wcm_branding_done'); delete_option('wcm_logo_att'); out("\nRESET_PROGRESS — progress wiped ONCE; reprocessing products, categories, grouping, stock, foreign-links, pages, images and branding. BLOG is left untouched (its done-flag, saved title list and schedule anchor are kept) so a reset can NEVER create a duplicate second batch of posts.",'#fa0'); }   // (previously also wiped wcm_blog_done/titles/start, which regenerated a fresh title list and doubled the blog)
    if(REPROCESS_IDS){ $rids=implode(',',array_map('intval',(array)REPROCESS_IDS)); if($rids!==''){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done' AND post_id IN ($rids)"); out("\nREPROCESS_IDS — redoing ".count((array)REPROCESS_IDS)." specific product(s) ONCE.",'#fa0'); } }
    update_option('wcm_reset_done',1,false); } }
else delete_option('wcm_reset_done');   // both off = re-arm for next time

// ---- PHASE: SEARCH VISIBILITY (runs EVERY load — cheap, and the #1 silent reason a submitted sitemap never indexes) ----
if(ENSURE_SEARCH_VISIBLE){ $bp=get_option('blog_public');
    if($bp==='0'||$bp===0){ update_option('blog_public',1); out("\n⚠️  SEARCH ENGINES WERE BLOCKED — your site had \"Discourage search engines\" ON, so nothing could index. FIXED: search visibility is now ON.",'#fa0'); }   // warn ONLY when it was explicitly blocked
    else { if((int)$bp!==1) update_option('blog_public',1); out("\n[ok] search visibility is ON (search engines allowed)",'#6f6'); } }   // missing/other -> set to 1 quietly (install default is already index; don't cry wolf)

// ---- PHASE: REPAIR POST LINKS (maintenance-only — no AI, no new posts) ------
// Re-validates internal links on every existing post against THAT post's own publish date, so any link pointing to a
// not-yet-published post (a live/future 404) is unwrapped, then tops the count back up with valid targets. Fixes posts
// that were written before the temporal link rule existed. Runs regardless of DO_BLOG so it can be used standalone.
if(REPAIR_POST_LINKS){ out("\n--- Repair post links (strip links to not-yet-published posts) ---",'#6cf');
    $rnow=current_time('mysql');
    $rposts=get_posts(['post_type'=>'post','post_status'=>array('publish','future'),'numberposts'=>-1,'orderby'=>'date','order'=>'ASC']);
    $rn=0; $rc=0; foreach($rposts as $rp){ $cut=(strcmp((string)$rp->post_date,$rnow)>0)?(string)$rp->post_date:$rnow; $html=(string)$rp->post_content;   // cutoff = max(post date, now): a LIVE post may link to anything live now; a SCHEDULED post is bound to its future date
        [$fixed]=blog_filter_links($html,$cut);
        if(strpos((string)$fixed,'<h2>Explore more</h2>')===false) $fixed=blog_topup_links($fixed,(int)BLOG_INTERNAL_LINKS,$cut);   // match the EXACT top-up heading (not the bare phrase, which could occur in prose) so we don't stack a second block, but still re-top-up posts that never had one
        if($fixed!==$html){ wp_update_post(['ID'=>$rp->ID,'post_content'=>$fixed]); $rc++; } $rn++; }
    out("   scanned $rn post(s); repaired links on $rc",'#6f6'); }

// ---- PHASE: CATEGORIES (single catalog pass) -------------------------------
$primary=[]; // pid => term_id
// grouping is built ONCE and flagged; on later runs/refreshes we skip it (the AI would otherwise pick different
// category names + mappings each time, so categories would keep changing). RESET_PROGRESS clears the flag to rebuild.
$cat_resume_skip = DO_CATEGORIES && get_option('wcm_grouping_done');
if ($cat_resume_skip) out("\n--- Categories --- (already grouped; skipping. Set RESET_PROGRESS=true to rebuild)",'#888');
if (DO_CATEGORIES && !$cat_resume_skip) {
    out("\n--- Categories ---",'#6cf');
    $cats = (CATEGORY_MODE==='manual') ? array_values(array_filter(array_map('trim',CATEGORY_LIST)))
                                       : ai_category_names(array_column($P,'title'));
    if(!$cats){ out('   [skip] no categories resolved','#fa0'); }
    else {
        out('   categories: '.implode(', ',$cats));
        $termid=[]; foreach($cats as $c){ $termid[$c]=find_or_create_cat($c); }
        $items=[]; foreach($P as $pid=>$d){ $items[]=['id'=>$pid,'title'=>$d['title'],'short'=>$d['short']]; }
        $map=[]; foreach(array_chunk($items,50) as $ch){ $map += ai_map_chunk($ch,$cats); }
        $done=0; foreach($P as $pid=>$d){ $cn=$map[$pid]??''; if($cn===''||empty($termid[$cn])) continue;
            wp_set_object_terms($pid,[(int)$termid[$cn]], 'product_cat', REPLACE_PRODUCT_CATEGORIES ? false : true);
            $primary[$pid]=(int)$termid[$cn]; $done++; }
        out("   grouped $done products into ".count($cats).' categories','#6f6');
        update_option('wcm_grouping_done',1,false);   // built once — don't re-group on refresh
    }
}
// fill primary term for products not just categorized (use existing first product_cat)
foreach($P as $pid=>$d){ if(isset($primary[$pid])) continue; $tt=get_the_terms($pid,'product_cat');
    $primary[$pid]=(is_array($tt)&&$tt)?(int)$tt[0]->term_id:0; }
// interlink groups by primary term
$groups=[]; foreach($primary as $pid=>$tid){ $groups[$tid][]=$pid; }
function related_of($pid,$primary,$groups,$P){ $out=[]; foreach(($groups[$primary[$pid]]??[]) as $o){ if($o==$pid) continue;
    if(($P[$o]['status']??'')!=='publish') continue;   // only link to publicly visible products (skip draft/pending/private)
    $out[]=['name'=>$P[$o]['title'],'url'=>site_link(get_permalink($o))]; if(count($out)>=INTERLINKS_PER_PRODUCT) break; } return $out; }

// ---- PHASE: PRODUCTS -------------------------------------------------------
$batched=false; $processed=0; $aborted=false; $products_complete=true; $left=0;   // default TRUE so a run that intentionally skips the product phase (pages-only / REPAIR_POST_LINKS / branding-only maintenance) still counts as complete and can self-delete + re-arm RESET; the product block below sets the REAL value when it runs
$want_product_ai = DO_SHORT_DESC||DO_LONG_DESC||DO_META||DO_TAGS||DO_PRICE;
if ($want_product_ai || DO_INTERLINKS || DO_IMAGE || REMOVE_FOREIGN_LINKS) {
    out("\n--- Products ---",'#6cf'); $tot=count($P); global $wpdb;
    // RESUME: each finished product carries a '_wcm_done' flag and is skipped. One query loads the whole done-set, so a
    // refresh continues exactly where it left off (even after a server timeout) and never re-touches a finished product
    // — so content is never duplicated. MAX_PRODUCTS_PER_RUN just caps how many NEW products to do per run (0 = all).
    $done=array_flip(array_intersect(array_map('intval',(array)$wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'")),$ids));   // intersect with the CURRENT catalog so stale flags (trashed/removed products) can't inflate the count and falsely mark the job complete
    $todo=[]; foreach($P as $pid=>$row){ if(!isset($done[$pid])){ $todo[]=$pid; if(MAX_PRODUCTS_PER_RUN>0 && count($todo)>=MAX_PRODUCTS_PER_RUN) break; } }
    out('   '.count($done).' already done · '.count($todo).' to do now · '.max(0,$tot-count($done)-count($todo)).' left after this run','#6cf');
    if($todo) update_meta_cache('post',$todo);
    $i=count($done); $fail=0; $handled=0;
    foreach($todo as $pid){ $i++; @set_time_limit(0); $p=wc_get_product($pid); if(!$p){ update_post_meta($pid,'_wcm_done',1); $handled++; continue; } $name=$p->get_name();
        $short=$p->get_short_description(); $long=$p->get_description(); $price=$p->get_regular_price();
        $has_var=$p->is_type('variable') && !empty($p->get_children());   // already has variations?
        $tid=$primary[$pid]; $tobj=$tid?get_term($tid):null; $catname=($tobj&&!is_wp_error($tobj))?$tobj->name:''; $cat_url=$tid?get_term_link($tid):''; if(is_wp_error($cat_url)) $cat_url=''; $cat_url=site_link($cat_url);
        $meta_empty = DO_META && !OVERWRITE_META && (!get_post_meta($pid,'rank_math_title',true)||!get_post_meta($pid,'rank_math_description',true)||!get_post_meta($pid,'rank_math_focus_keyword',true));
        $need=['short'=>DO_SHORT_DESC&&field_need($short,SHORT_DESC_MODE),
               'long'=>DO_LONG_DESC&&field_need($long,LONG_DESC_MODE),
               'meta'=>DO_META&&(OVERWRITE_META||$meta_empty),
               'tags'=>DO_TAGS&&(OVERWRITE_TAGS||!get_the_terms($pid,'product_tag')),   // get_the_terms hits the primed cache — no per-product query
               'price'=>DO_PRICE&&(OVERWRITE_PRICE||(PRODUCT_TYPE==='variable'?!$has_var:($price===''||$price===null))),
               'image'=>false,'unit'=>false];
        $will_image = DO_IMAGE && !get_post_meta($pid,'_wcm_img_done',true) && !(SKIP_IF_HAS_IMAGE && has_post_thumbnail($pid) && !get_post_meta($pid,'_wcm_logo_img',true));   // decide ONCE whether an image will really be generated. A logo PLACEHOLDER (_wcm_logo_img) does not count as a real image, so generation replaces it
        $need['unit']=($need['short']&&SHORT_DESC_INCLUDE_UNIT)||$need['price']||$will_image;
        $need['image']=$will_image; // only ask the AI for image_subject/image_use when an image will actually be made (saves tokens on products that already have one)
        $need_ai=$need['short']||$need['long']||$need['meta']||$need['tags']||$need['price'];
        if(!$need_ai && !DO_INTERLINKS && !DO_IMAGE && !REMOVE_FOREIGN_LINKS){ out("[$i/$tot] skip: $name",'#888'); update_post_meta($pid,'_wcm_done',1); $handled++; continue; }
        out("[$i/$tot] $name",'#ddd');
        $data=[]; if($need_ai){ [$data,$err]=ai_json(product_prompt(['title'=>$name,'cat'=>$catname,'short'=>excerpt($short),'long'=>excerpt($long,500)],$need));
            if(!$data){ out("   [skip] $err",'#f66');
                if(strpos((string)$err,'CREDIT')!==false){ out('   [STOP] AI credit/billing exhausted — halting so the rest of the catalog is not left half-done. Fix billing (or set AI_PROVIDER=\'gemini\'), then re-open the URL.','#f66'); $aborted=true; break; }
                if(++$fail>=3){ out('   [STOP] 3 AI calls failed in a row — halting so the catalog is not left half-updated. Check the API key/quota in the red messages above, then re-open the URL to resume from here.','#f66'); $aborted=true; break; }
                continue; }
            $fail=0; }

        // variable pricing first (may change type)
        $made_var=false;
        if($need['price'] && PRODUCT_TYPE==='variable' && !empty($data['variations']) && is_array($data['variations'])){
            $attr=trim((string)($data['attribute']??''))?:'Option'; $clean=[];
            foreach($data['variations'] as $v){ if(!is_array($v)) continue; $lb=trim((string)($v['label']??'')); $pr=round_price($v['price']??0); if($lb!==''&&$pr!=='') $clean[$lb]=$pr; }   // is_array guard: a stringy AI variation entry would otherwise throw on $v['label']
            if($clean && class_exists('WC_Product_Variable')){
                if($has_var) foreach($p->get_children() as $old) wp_delete_post($old,true);   // clear old variations so re-runs don't stack duplicates
                wp_set_object_terms($pid,'variable','product_type');
                $a=new WC_Product_Attribute(); $a->set_id(0); $a->set_name($attr); $a->set_options(array_keys($clean)); $a->set_visible(true); $a->set_variation(true);
                $vp=new WC_Product_Variable($pid); $vp->set_attributes([$a]); $vp->save(); $k=sanitize_title($attr);
                foreach($clean as $lb=>$pr){ $vn=new WC_Product_Variation(); $vn->set_parent_id($pid); $vn->set_attributes([$k=>$lb]); $vn->set_regular_price($pr); $vn->set_status('publish'); $vn->save(); }
                WC_Product_Variable::sync($pid); $made_var=true; } }
        if($made_var) $p=wc_get_product($pid);   // reload only when the type actually changed to variable

        $unit=trim((string)($data['unit']??'')); if($unit!=='') update_post_meta($pid,'_unit_of_sale',$unit);
        if(!empty($data['image_subject'])) update_post_meta($pid,'_image_subject',(string)$data['image_subject']);
        if(!empty($data['image_use'])) update_post_meta($pid,'_image_use',(string)$data['image_use']);   // middle label line (e.g. 'Pain Relief')

        $dirty=false;
        // short description (+ optional unit line)
        if($need['short']){ $ns=dedash(demote_h1((string)($data['short_description']??''))); if(SHORT_DESC_INCLUDE_UNIT&&$unit!==''&&stripos($ns,'sold as')===false) $ns.="\n<p><strong>Sold as:</strong> ".esc($unit).'.</p>';
            [$val,$ch]=apply_text($p->get_short_description(),$ns,SHORT_DESC_MODE); if($ch){ $p->set_short_description($val); $dirty=true; } }
        if(REMOVE_FOREIGN_LINKS){ $sd=$p->get_short_description(); $sdc=strip_foreign_links($sd); if($sdc!==$sd){ $p->set_short_description($sdc); $dirty=true; } }
        // long description (+ interlinks + disclaimer)
        $long0=$p->get_description(); $long_cur=$long0;
        if($need['long']){ $nl=dedash(demote_h1((string)($data['long_description']??''))); [$val,$ch]=apply_text($long_cur,$nl,LONG_DESC_MODE); if($ch){ $long_cur=$val; } }
        if(DO_INTERLINKS){ $long_cur=inject_links($long_cur,related_of($pid,$primary,$groups,$P),$cat_url,$catname); }
        $long_cur=append_disclaimer($long_cur);
        if(REMOVE_FOREIGN_LINKS) $long_cur=strip_foreign_links($long_cur);
        if($long_cur!==$long0){ $p->set_description($long_cur); $dirty=true; }

        if($need['price']&&!$made_var){ $pr=round_price($data['price']??0); if($pr!=='' && (OVERWRITE_PRICE||$p->get_regular_price()==='')){ $p->set_regular_price($pr); $dirty=true; } }
        if($dirty){ $kt=get_the_terms($pid,'product_cat'); $keep=is_array($kt)?wp_list_pluck($kt,'term_id'):[];   // SAFETY (cached read):
            if($keep && array_diff($keep,$p->get_category_ids())) $p->set_category_ids($keep);   // restore ONLY if the object dropped
            $p->save(); }                                                                        // its categories — never blank them

        if($need['meta']){ if(!empty($data['meta_title'])) update_post_meta($pid,'rank_math_title',mb_substr((string)$data['meta_title'],0,70));
            if(!empty($data['meta_description'])) update_post_meta($pid,'rank_math_description',mb_substr((string)$data['meta_description'],0,160));
            if(!empty($data['focus_keyword'])) update_post_meta($pid,'rank_math_focus_keyword',(string)$data['focus_keyword']); }
        if($need['tags']){ $tg=array_values(array_unique(array_filter(array_map('trim',(array)($data['tags']??[]))))); $tg=array_slice($tg,0,MAX_TAGS);
            if($tg){ $ex=[]; if(!OVERWRITE_TAGS){ $gt=get_the_terms($pid,'product_tag'); if(is_array($gt)) $ex=wp_list_pluck($gt,'name'); }
                wp_set_object_terms($pid,array_values(array_unique(array_merge($ex,$tg))),'product_tag',false); } }

        if($will_image){ [$u,$e]=ideogram_url(image_subject($pid,$name,$catname).'. '.image_style().'.');
            $use=trim((string)get_post_meta($pid,'_image_use',true)); if($use==='' && IMAGE_LABEL_USE_FALLBACK) $use=$catname;   // middle label line: AI 'use' phrase, else the category
            $label=['name'=>$name,'use'=>$use,'brand'=>brand()];
            if($u){ [$att,$e2]=attach_image($u,$pid,$name.' product image',$name,$label); if($att){ update_post_meta($pid,'_wcm_img_done',1); delete_post_meta($pid,'_wcm_logo_img'); out('   [image ok]','#6f6'); } else out("   [image] $e2",'#fa0'); } else out("   [image] $e",'#fa0'); }

        out('   [ok]'.($made_var?' + variations':''),'#6f6'); $processed++; $handled++;
        update_post_meta($pid,'_wcm_done',1);   // flag finished NOW so a server timeout keeps this product's progress
    }
    // completion: if every product is now flagged done, finish and clear the flags (so a fresh upload starts over).
    // Otherwise keep the file and wait for a refresh — failed products stay UNflagged and are retried; a timed-out
    // run simply resumes from the first product that isn't flagged yet.
    $left = max(0, $tot-count($done)-$handled);          // products still not finished (failed AI calls, or a future batch)
    $more_batches = (count($done)+count($todo)) < $tot;  // un-done products remain BEYOND this run's window (only when MAX caps it)
    $products_complete = (!$aborted && $left===0);
    if($products_complete) out("\n[done] all $tot products processed.",'#6cf');
    elseif($aborted) $batched=true;                                                  // stopped hard — don't build pages, keep file to retry
    elseif($more_batches){ $batched=true; out("\n[batch] $left product(s) left — refresh the URL to continue.",'#6cf'); }
    else out("\n[note] $left product(s) failed this run — the rest and the pages still build; re-open the URL to retry those.",'#fa0');   // last window: a bad product must NOT block the site build
}

// ---- PHASE: FORCE IN STOCK (all products + variations) ---------------------
// WooCommerce core, theme-independent — Flatsome only displays whatever status we set here.
if(!$batched && FORCE_IN_STOCK){ if(get_option('wcm_stock_done')) out("\n--- Force in stock --- (already done; RESET_PROGRESS to redo)",'#888'); else { out("\n--- Forcing stock status: In stock ---",'#6cf'); $sn=0;
    // Set BOTH manage-stock OFF and status IN STOCK on the SAME product object, then save once. Turning off
    // manage-stock is what makes it stick — otherwise WC re-derives "out of stock" from a 0 quantity. save()
    // also updates WC's product lookup table + clears caches so the shop reflects it. Only products/variations
    // not already in stock (or still self-managing stock) — O(items to fix).
    $fix=$wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
           LEFT JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_stock_status'
           LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_manage_stock'
          WHERE p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft')
            AND (s.meta_value IS NULL OR s.meta_value<>%s OR m.meta_value=%s)",
        'instock','yes'));
    foreach($fix as $pp){ $pr=wc_get_product($pp); if(!$pr) continue;
        if($pr->get_manage_stock()) $pr->set_manage_stock(false);           // stop WC re-deriving out-of-stock from qty 0
        $pr->set_stock_status('instock');
        $kt=get_the_terms($pp,'product_cat'); $keep=is_array($kt)?wp_list_pluck($kt,'term_id'):[];   // SAFETY: this save must never blank categories
        if($keep && array_diff($keep,$pr->get_category_ids())) $pr->set_category_ids($keep);
        $pr->save(); $sn++; }
    out("   set $sn products/variations in stock",'#6f6'); update_option('wcm_stock_done',1,false); } }

// ---- PHASE: REMOVE FOREIGN LINKS (categories + pages only — NOT blog posts, which keep their one authoritative .gov link) --------------
if(!$batched && REMOVE_FOREIGN_LINKS){ if(get_option('wcm_foreign_done')) out("\n--- Remove foreign links --- (already done; RESET_PROGRESS to redo)",'#888'); else { out("\n--- Removing foreign links ---",'#6cf'); $fn=0;
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ $d=(string)$t->description; $c=strip_foreign_links($d); if($c!==$d){ wp_update_term($t->term_id,'product_cat',['description'=>$c]); $fn++; } }
    foreach(get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']) as $pp){ $d=(string)get_post_field('post_content',$pp); $c=strip_foreign_links($d); if($c!==$d){ wp_update_post(['ID'=>$pp,'post_content'=>$c]); $fn++; } }   // pages only: blog posts curate their own links (one authoritative .gov/.edu outbound is kept on purpose), so this blanket sweep must not strip it
    out("   cleaned $fn category/page item(s) (product descriptions were cleaned in the product pass; blog posts keep their authoritative link)",'#6f6'); update_option('wcm_foreign_done',1,false); } }

// ---- PHASE: CATEGORY DESCRIPTIONS ------------------------------------------
if(!$batched && DO_CATEGORY_CONTENT){ out("\n--- Category descriptions ---",'#6cf');
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ if(strtolower($t->slug)==='uncategorized') continue;
        if(get_term_meta($t->term_id,'_wcm_cat_done',true)){ out("   [skip] {$t->name} (already done — set RESET_PROGRESS=true to redo)",'#888'); continue; }
        $has=trim((string)$t->description)!==''; if($has&&!OVERWRITE_CATEGORY_DESC){ out("   [skip] {$t->name}",'#888'); continue; }
        $plinks=[]; foreach(($groups[$t->term_id]??array_slice(get_posts(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','numberposts'=>8,'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id]]]),0,8)) as $pp){ if(get_post_status($pp)!=='publish') continue; $plinks[]=['name'=>get_the_title($pp),'url'=>site_link(get_permalink($pp))]; if(count($plinks)>=8) break; }   // publish-only: never surface a draft/pending product in the "Shop This Category" links
        [$d,$err]=ai_json("Write an SEO description, ".words_phrase('180-260').", for the product category \"{$t->name}\" at ".brand()." selling ".STORE_NICHE.". ".voice_rules().compliance_clause()."Open with the focus keyword; explain what it covers and why buy here; one <h2>. No invented links. ".html_quote_rule()."Return JSON: {\"description\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}");
        if(!$d||empty($d['description'])){ out("   [skip] {$t->name} — ".($err?:'no content'),'#f66'); continue; }
        $desc=dedash((string)$d['description']); if(REMOVE_FOREIGN_LINKS) $desc=strip_foreign_links($desc); $desc=strip_future_internal_links($desc,current_time('mysql')); $have=existing_hrefs($desc); $li='';   // strip off-site links (foreign phase never reaches category descriptions) + any link to a not-yet-published post
        foreach($plinks as $r){ if(!in_array($r['url'],$have,true)) $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; }
        if($li) $desc.="\n<h2>Shop This Category</h2>\n<ul>$li</ul>"; $desc=append_disclaimer($desc);
        $r=wp_update_term($t->term_id,'product_cat',['description'=>$desc]);
        if(!empty($d['meta_title'])) update_term_meta($t->term_id,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
        if(!empty($d['meta_description'])) update_term_meta($t->term_id,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
        if(!empty($d['focus_keyword'])) update_term_meta($t->term_id,'rank_math_focus_keyword',(string)$d['focus_keyword']);
        $svd=$wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d",$t->term_taxonomy_id));   // read STRAIGHT from DB (bypasses object cache)
        if(is_wp_error($r)) out("   [WRITE ERROR] {$t->name}: ".$r->get_error_message(),'#f66');
        else { update_term_meta($t->term_id,'_wcm_cat_done',1); out("   [ok] {$t->name} — wrote ".mb_strlen($desc)." chars, DB now holds ".mb_strlen((string)$svd),'#6f6'); } } }

// ---- PHASE: PAGES (after products) -----------------------------------------
$blog_incomplete=false;   // set true if the blog phase stops early (per-run cap, credit stop) so the file won't self-delete before all posts exist
if(!$batched){
    // featured products + category links — only built when the homepage is actually being written
    $topcats=[]; $feat=[];
    if(DO_HOMEPAGE){ foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>0]) as $t){ if(strtolower($t->slug)==='uncategorized') continue; $lk=get_term_link($t); if(!is_wp_error($lk)) $topcats[]=['name'=>$t->name,'url'=>site_link($lk)]; }
        foreach(array_keys($P) as $pid){ if(($P[$pid]['status']??'')!=='publish') continue;   // only feature publicly visible products
            $feat[]=['id'=>$pid,'name'=>$P[$pid]['title'],'url'=>site_link(get_permalink($pid))]; if(count($feat)>=6) break; } }

    if(DO_HOMEPAGE){ out("\n--- Homepage ---",'#6cf'); $fid=resolve_front();
        $cur=trim((string)get_post_field('post_content',$fid));
        if(get_post_meta($fid,'_wcm_page_done',true)){ out('   [skip] homepage (already done; RESET_PROGRESS to redo)','#888'); }
        elseif($cur!=='' && !OVERWRITE_HOMEPAGE){ out('   [skip] homepage has content (OVERWRITE_HOMEPAGE=false)','#888'); }
        else { $cl=''; foreach($topcats as $c){ $cl.='  - '.$c['name']."\n"; } $pl=''; foreach($feat as $f){ $pl.='  - '.$f['name']."\n"; }
            [$d,$err]=ai_json("Write HOMEPAGE copy for ".brand()." selling ".STORE_NICHE.". Tagline: \"".tagline()."\".\nCategories:\n$cl\nFeatured:\n$pl\n".voice_rules().compliance_clause()."Plain text fields only (no HTML). Make hero_intro a substantial 2-3 sentence lead. Provide intro_heading (a short section heading, not 'Welcome to') and intro_paragraphs (2-3 rich paragraphs of 60-90 words each that introduce the store, what it sells and why buy here — this is the main content shown directly under the homepage headline). Give 3-4 why_us_points and 3-4 faq. ".( "Return ONE valid JSON object, no comments/trailing commas, with keys: hero_headline, hero_intro, intro_heading, intro_paragraphs (array of paragraph strings), cta_button, categories_heading, why_us_heading, why_us_points (array of {title,text}), featured_heading, about_heading, about_text, faq (array of {q,a}), closing_cta, meta_title, meta_description, focus_keyword."));
            if(!$d||empty($d['hero_headline'])){ out('   [skip] homepage — '.($err?:'no content'),'#f66'); }
            else { if(HOMEPAGE_FORMAT==='flatsome') $content=build_flatsome($d,$topcats,$feat);
                else { $content='<h1>'.esc($d['hero_headline']).'</h1><p>'.esc($d['hero_intro']??'').'</p>';
                    $ip=intro_paras($d['intro_paragraphs']??null); if($ip){ if(!empty($d['intro_heading'])) $content.='<h2>'.esc($d['intro_heading']).'</h2>'; foreach($ip as $para){ $pp=esc(is_array($para)?($para['text']??''):$para); if(trim($pp)!=='') $content.='<p>'.$pp.'</p>'; } }
                    $li=''; foreach($topcats as $c){ $li.='<li><a href="'.esc_url($c['url']).'">'.esc($c['name']).'</a></li>'; } if($li)$content.='<h2>'.esc($d['categories_heading']??'Shop by Category').'</h2><ul>'.$li.'</ul>'; $content=append_disclaimer($content); }
                $content=dedash($content); $r=wp_update_post(['ID'=>$fid,'post_content'=>$content],true);
                $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$fid));   // verify STRAIGHT from DB
                if(is_wp_error($r)) out('   [WRITE ERROR] homepage: '.$r->get_error_message(),'#f66');
                else out("   homepage: wrote ".mb_strlen($content)." chars to page id $fid, DB now holds ".mb_strlen((string)$svd),'#6cf');
                if(!empty($d['meta_title'])) update_post_meta($fid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                if(!empty($d['meta_description'])) update_post_meta($fid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                if(!empty($d['focus_keyword'])) update_post_meta($fid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                update_post_meta($fid,'_wcm_page_done',1); out('   [ok] homepage','#6f6'); } } }

    if(DO_CONTACT){ out("\n--- Contact ---",'#6cf'); $pid=find_or_create_page('contact-us','Contact Us');
        $cur=trim((string)get_post_field('post_content',$pid));
        if(get_post_meta($pid,'_wcm_page_done',true)){ out('   [skip] contact (already done; RESET_PROGRESS to redo)','#888'); }
        elseif($cur!=='' && !OVERWRITE_PAGES){ out('   [skip] contact has content','#888'); }
        else { $bn=esc(brand()); $niche=esc(STORE_NICHE); $ph=esc(us_phone()); $ema=esc(site_email()); $loc=esc(CONTACT_LOCATION);
            $lead="Questions about our $niche, an existing order, or a bulk enquiry? Our team is glad to help, and we usually reply within one business day.";
            if(is_flatsome()){   // centered intro, then two clean cards: contact details + how we can help
                $c ="[section padding=\"60px\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h1>Contact $bn</h1>\n<p style='font-size:1.1em'>$lead</p>\n</div>\n[/col]\n[/row]\n";
                $c.="[row]\n[col span=\"6\" span__sm=\"12\"]\n[featured_box]\n<h3>Reach us</h3>\n<p><strong>Email:</strong> <a href='mailto:$ema'>$ema</a></p>\n<p><strong>Phone:</strong> $ph</p>\n<p><strong>Location:</strong> $loc</p>\n<p><strong>Support hours:</strong> Monday to Friday, 9:00 AM to 5:00 PM</p>\n[button text=\"Email Us\" link=\"mailto:$ema\"]\n[/featured_box]\n[/col]\n";
                $c.="[col span=\"6\" span__sm=\"12\"]\n[featured_box]\n<h3>How we can help</h3>\n<ul>\n<li>Product questions and recommendations</li>\n<li>Order status, shipping, and returns</li>\n<li>Bulk, wholesale, and business enquiries</li>\n<li>Feedback about your experience with $bn</li>\n</ul>\n<p>Prefer to write? Send a note any time and we'll get back to you quickly.</p>\n[/featured_box]\n[/col]\n[/row]\n[/section]\n";
            } else { $inner ="<h1>Contact $bn</h1>\n<p>$lead</p>\n";
                $inner.="<h3>Reach us</h3>\n<ul>\n<li><strong>Email:</strong> <a href='mailto:$ema'>$ema</a></li>\n<li><strong>Phone:</strong> $ph</li>\n<li><strong>Location:</strong> $loc</li>\n<li><strong>Support hours:</strong> Monday to Friday, 9:00 AM to 5:00 PM</li>\n</ul>\n";
                $inner.="<h3>How we can help</h3>\n<ul>\n<li>Product questions and recommendations</li>\n<li>Order status, shipping, and returns</li>\n<li>Bulk, wholesale, and business enquiries</li>\n</ul>\n";
                $c=page_wrap($inner); }   // non-Flatsome: clean single centered column
            $r=wp_update_post(['ID'=>$pid,'post_content'=>$c],true); $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$pid));
            if(is_wp_error($r)) out('   [WRITE ERROR] contact: '.$r->get_error_message(),'#f66');
            else { update_post_meta($pid,'_wcm_page_done',1); out("   [ok] contact (page id $pid) — wrote ".mb_strlen($c)." chars, DB now holds ".mb_strlen((string)$svd),'#6f6'); } } }

    $legal=[]; if(DO_PRIVACY)$legal[]=['privacy-policy','Privacy Policy','Cover, in a standard trustworthy way, what data is collected, how it is used, cookies, third parties, user rights (CCPA-aware), and how to reach us.'];
    if(DO_TERMS)$legal[]=['terms-and-conditions','Terms and Conditions','Cover use of the site, orders, pricing, intellectual property, limitation of liability and governing law (USA), as a standard fair company policy.'];
    if(DO_SHIPPING)$legal[]=['shipping-policy','Shipping Policy','State clearly that we ship WORLDWIDE — anywhere in the world, both within the USA and internationally. Do NOT quote any exact shipping prices or dollar amounts; instead say the exact shipping cost is calculated automatically and shown at checkout before payment. Cover order processing/handling times, delivery estimates, worldwide coverage, order tracking, and possible customs delays, all in a reassuring positive tone.'];
    if(DO_REFUND)$legal[]=['refund_returns','Refund and Returns Policy','Cover eligibility, timeframes, the return process, refunds, exchanges and any non-returnable items as a standard, fair, customer-friendly policy, and point buyers to contact support to start a return.'];
    if(DO_FAQ)$legal[]=['faq','FAQ','Answer 8-10 real buyer questions (ordering, worldwide shipping, delivery times, returns, payment security, product quality, contacting support). Keep EVERY answer positive and confident and show off what the store can do: we ship to anywhere in the world, orders are handled quickly, the exact shipping cost is shown at checkout, support replies promptly by email and phone, and checkout is secure. Phrase questions as natural long-tail keywords (<h3> question + <p> answer). Never say the store cannot do something.'];
    if(DO_ABOUT)$legal[]=['about-us','About Us','Tell the store\'s story, what makes it trustworthy, and why to buy here — specific and positive, not generic.'];
    foreach($legal as $L){ out("\n--- {$L[1]} ---",'#6cf'); write_page_via_ai($L[0],$L[1],page_prompt($L[1],$L[2])); }

    // ---- BLOG: post #1 live now, the rest auto-scheduled every BLOG_CADENCE_DAYS ----
    // Resume-safe: the title list + a fixed start date are saved ONCE, so each title keeps a
    // stable schedule slot no matter how many refreshes it takes. Already-created titles are
    // skipped (no duplicates). BLOG_PER_RUN caps writes per load so a 45-post job can't time out.
    if(DO_BLOG && BLOG_COUNT>0){
        // Flatsome blog layout — full-width posts (no category-widget sidebar shoving content). Verified theme-mod keys: blog_post_layout (single), blog_layout (archive).
        if(is_flatsome() && BLOG_LAYOUT!==''){ if(get_theme_mod('blog_post_layout')!==BLOG_LAYOUT || get_theme_mod('blog_layout')!==BLOG_LAYOUT){ set_theme_mod('blog_post_layout',BLOG_LAYOUT); set_theme_mod('blog_layout',BLOG_LAYOUT); out("   [ok] blog layout -> ".BLOG_LAYOUT,'#6f6'); } }
        // make sure posts actually list on the existing /blog page
        $bpg=get_page_by_path('blog'); if($bpg && (int)get_option('page_for_posts')!==(int)$bpg->ID){ update_option('page_for_posts',(int)$bpg->ID); out("   [ok] /blog set as the posts page",'#6f6'); }
        // Catch-up: publish any scheduled post whose time has already passed (WP-Cron missed it while the site was down / had no traffic). Runs every load, even after the batch is complete. One indexed query; loops only over the overdue few.
        $overdue=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='future' AND post_date_gmt<=%s",gmdate('Y-m-d H:i:s')));
        if($overdue){ $pn=0; foreach($overdue as $oid){ wp_publish_post((int)$oid); if(get_post_status((int)$oid)==='publish') $pn++; } if($pn) out("   [ok] published $pn overdue scheduled post(s) that WP-Cron had missed",'#6f6'); }
        if(get_option('wcm_blog_done')) out("\n--- Blog --- (already done; RESET_PROGRESS to redo)",'#888');
        else { out("\n--- Blog (".BLOG_COUNT." posts: #1 live, rest every ".max(1,(int)BLOG_CADENCE_DAYS)." day(s)) ---",'#6cf');
            $titles=json_decode((string)get_option('wcm_blog_titles'),true);   // reuse the saved list across resumes so schedule slots stay put
            if(!is_array($titles) || !$titles){
                [$td]=ai_json("Suggest exactly ".BLOG_COUNT." distinct, SEO-friendly blog article titles for ".brand()." selling ".STORE_NICHE.", aimed at buyers and search traffic. No numbering. Return JSON: {\"titles\":[\"...\"]} — no comments, no trailing commas.");
                $titles=(is_array($td)&&!empty($td['titles']))?array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x),(array)$td['titles'])))):[];
                $titles=array_slice($titles,0,BLOG_COUNT);
                if($titles) update_option('wcm_blog_titles',wp_json_encode($titles),false); }
            if(!$titles){ out('   [skip] blog — could not generate titles','#f66'); $blog_incomplete=true; }   // title call failed -> keep the file and retry next load; do NOT let $job_done self-delete with zero posts written
            else {
                $now=current_time('timestamp'); $base=(int)get_option('wcm_blog_start'); if($base<=0){ $base=$now; update_option('wcm_blog_start',$base,false); }   // fixed anchor date for the whole schedule
                $cad=max(1,(int)BLOG_CADENCE_DAYS); $cap=BLOG_PER_RUN>0?(int)BLOG_PER_RUN:PHP_INT_MAX;
                $cat_id=0; if(BLOG_CATEGORY!==''){ $bt=get_term_by('name',BLOG_CATEGORY,'category'); if($bt&&!is_wp_error($bt)) $cat_id=(int)$bt->term_id; else { $ins=wp_insert_term(BLOG_CATEGORY,'category'); if(!is_wp_error($ins)) $cat_id=(int)$ins['term_id']; } }
                $made=0; $fail=0;
                $existing=array_flip($wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_type='post' AND post_status<>'trash'"));   // ONE query for all existing post titles -> O(1) dedup per title instead of a full-table title scan on every title
                foreach($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wcm_blog_title'") as $bk){ $existing[$bk]=1; }   // primary done-marker: the EXACT original title we stamped on each created post. Immune to WP re-encoding the stored post_title (naked '&' -> '&amp;' etc.), which would otherwise miss the match and re-create the post
                foreach($titles as $idx=>$ti){ $ti=trim((string)$ti); if($ti==='') continue;
                    if(isset($existing[$ti])) continue;   // already created on a prior run — keeps its slot, don't touch
                    if($made>=$cap){ continue; }   // per-run cap reached: skip; the $uncreated coverage check below marks the run incomplete so the rest write next refresh
                    @set_time_limit(0);
                    $off=BLOG_FIRST_LIVE ? $idx*$cad : ($idx+1)*$cad; $when=$base+$off*86400; $due=($when<=$now);   // schedule slot FIRST — links are validated against WHEN THIS POST GOES LIVE, so it may link to any post that publishes at/before $dl
                    $status=$due?'publish':'future'; $dl=date('Y-m-d H:i:s',$when);
                    [$d,$err]=ai_json(blog_article_prompt($ti,$idx,$dl));
                    if(!$d||empty($d['content'])){ out("   [skip] $ti — ".($err?:'no content'),'#f66');
                        if(strpos((string)$err,'CREDIT')!==false){ out('   [STOP] AI credit/billing exhausted — refresh after fixing billing to resume.','#f66'); $blog_incomplete=true; break; }
                        if(++$fail>=3){ out('   [STOP] 3 blog calls failed in a row — refresh to resume from here.','#f66'); $blog_incomplete=true; break; } continue; }
                    $fail=0;
                    $html=demote_h1((string)$d['content']);              // theme already prints the title as the H1
                    $html=dedash($html);
                    [$html,$extk]=blog_filter_links($html,$dl);          // drop business/competitor links + any internal link not live by $dl; keep at most one authoritative source
                    $html=blog_topup_links($html,(int)BLOG_INTERNAL_LINKS,$dl);
                    $html=append_disclaimer($html);
                    $args=['post_type'=>'post','post_title'=>$ti,'post_status'=>$status,'post_content'=>$html,'post_date'=>$dl,'post_date_gmt'=>get_gmt_from_date($dl)];
                    if($cat_id) $args['post_category']=[$cat_id];
                    $post=wp_insert_post($args,true);
                    if(is_wp_error($post)){ out("   [skip] $ti — ".$post->get_error_message(),'#f66'); continue; }
                    update_post_meta($post,'_wcm_blog_title',$ti);   // stamp the EXACT original title as the done-marker so this post is never re-created on a later batch/refresh, regardless of how WP stored post_title
                    $lp=site_link(post_pretty_link($post)); if($lp&&!is_wp_error($lp)) blog_known_posts(['url'=>$lp,'name'=>$ti,'date'=>$dl]);   // register this post (PRETTY url even though it's scheduled) so LATER posts in this run can link back to it
                    if(!empty($d['meta_title'])) update_post_meta($post,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                    if(!empty($d['meta_description'])) update_post_meta($post,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                    if(!empty($d['focus_keyword'])) update_post_meta($post,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                    $made++; $existing[$ti]=1; $when_tag=$due?('live '.date('M j',$when)):('scheduled '.date('M j, Y',$when));
                    out("   [ok] $ti — $when_tag".($extk?' + authoritative link':''),'#6f6'); }
                if(!$blog_incomplete){
                    $uncreated=0; foreach($titles as $tt){ $tt=trim((string)$tt); if($tt!=='' && !isset($existing[$tt])) $uncreated++; }   // completion is measured by ACTUAL coverage of every title (capped OR failed), not just the per-run cap counter — so a title whose article call failed is NOT silently dropped and marked done
                    if($uncreated>0){ $blog_incomplete=true; out("   [batch] wrote $made now; $uncreated post(s) still to write (capped or failed) — refresh the URL to continue.",'#6cf'); }
                    else { update_option('wcm_blog_done',1,false); out("   [done] all ".count($titles)." blog posts created (".$made." this run).",'#6f6'); } } } }
    }
}

// ---- PHASE: BRANDING (site title + colors + logo) --------------------------
if(!$batched && DO_BRANDING){ if(get_option('wcm_branding_done')) out("\n--- Branding --- (already done; RESET_PROGRESS to redo)",'#888'); else { out("\n--- Branding (name, colors, logo) ---",'#6cf');
    $ov=BRANDING_OVERWRITE;
    // 1) Site title + tagline (core options)
    $title=BRAND_NAME;   // the site title IS the brand name — nothing extra to set
    if($title!=='' && ($ov || trim((string)get_option('blogname'))==='')){ update_option('blogname',$title); out("   [ok] site title -> $title",'#6f6'); }
    if(SITE_TAGLINE!=='' && ($ov || trim((string)get_option('blogdescription'))==='')){ update_option('blogdescription',SITE_TAGLINE); out("   [ok] tagline set",'#6f6'); }
    // 2) Color palette -> Flatsome theme mods (only where unset, unless overwrite)
    $pal=brand_palette(); out("   palette: {$pal['primary']} / {$pal['secondary']} / {$pal['accent']}",'#6cf');
    $setmod=function($key,$val) use($ov){ if($val==='') return false; if(!$ov && get_theme_mod($key)) return false; set_theme_mod($key,$val); return true; };
    $cn=0; if($setmod('color_primary',$pal['primary'])) $cn++; if($setmod('color_secondary',$pal['secondary'])) $cn++;
    if($setmod('color_success',$pal['accent'])) $cn++; if($setmod('color_links',$pal['primary'])) $cn++;
    out("   [ok] set $cn Flatsome color option(s)",'#6f6');
    // 3) Logo — provided URL (LOGO_URL) wins; else generate (if GENERATE_LOGO). Only if none set, unless overwrite.
    $have_logo = get_theme_mod('site_logo') || get_theme_mod('custom_logo');
    if($have_logo && !$ov){ out('   [skip] logo already set (BRANDING_OVERWRITE=false)','#888'); }
    elseif(LOGO_URL!==''){ $att=sideload_logo(LOGO_URL);
        if(!$att){ out('   [skip] logo — could not fetch LOGO_URL','#fa0'); }
        else { $u=wp_get_attachment_url($att); if($u) set_theme_mod('site_logo',$u); set_theme_mod('custom_logo',$att); update_option('wcm_logo_att',$att,false); out('   [ok] logo set from LOGO_URL','#6f6'); } }
    elseif(!GENERATE_LOGO){ out('   [skip] logo — GENERATE_LOGO is off and no LOGO_URL set','#888'); }
    else { [$lu,$lp]=generate_logo($pal);   // built by the text AI (SVG icon) + code wordmark, with a monogram fallback — no image API
        if(!$lu){ out("   [skip] logo — $lp",'#fa0'); }
        else { $att=media_handle_sideload(['name'=>'logo.png','tmp_name'=>$lp],0,brand().' logo');
            if(is_wp_error($att)){ @unlink($lp); out('   [skip] logo attach — '.$att->get_error_message(),'#fa0'); }
            else { $u=wp_get_attachment_url($att); if($u) set_theme_mod('site_logo',$u);   // Flatsome uses this URL directly as <img src>. Derive it from the STORED attachment — media_handle_sideload already consumed $lp, so $lu now points at a deleted file
                set_theme_mod('custom_logo',(int)$att);            // WordPress core custom-logo (attachment ID) as fallback
                update_post_meta($att,'_wp_attachment_image_alt',brand().' logo');
                update_option('wcm_logo_att',(int)$att,false);     // so USE_LOGO_AS_PRODUCT_IMAGE reuses this same attachment
                out('   [ok] logo generated + set','#6f6'); } } }
    update_option('wcm_branding_done',1,false); } }

// ---- PHASE: LOGO AS PRODUCT IMAGE (fill products that have NO featured image) ------
// No image API: point each imageless product's thumbnail at the ONE resolved logo attachment (reused, so no duplicate media).
// Runs after branding so a just-generated/URL logo is available. Idempotent + self-limiting (only products still missing an image).
if(!$batched && USE_LOGO_AS_PRODUCT_IMAGE){ out("\n--- Logo as product image ---",'#6cf');
    $logo_att=resolve_logo_attachment();
    if(!$logo_att){ out('   [skip] no logo available — set LOGO_URL, run branding, or have a theme logo first','#fa0'); }
    else { $miss=$wpdb->get_col(
        "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} t ON t.post_id=p.ID AND t.meta_key='_thumbnail_id'
          WHERE p.post_type='product' AND p.post_status NOT IN ('trash','auto-draft') AND (t.meta_value IS NULL OR t.meta_value='' OR t.meta_value='0')");   // O(products still missing an image)
        $n=0; foreach($miss as $pp){ $pp=(int)$pp; set_post_thumbnail($pp,$logo_att); update_post_meta($pp,'_wcm_logo_img',1); $n++; }   // flag it a PLACEHOLDER so a later DO_IMAGE run (with RESET) replaces it
        out("   set the logo as the image on $n product(s) that had none",'#6f6'); } }

// best-effort cache purge so new content/prices/stock show without a manual cache clear (each is a no-op if not installed)
if(function_exists('wp_cache_flush')) wp_cache_flush();          // object cache (Redis/Memcached)
do_action('litespeed_purge_all');                                // LiteSpeed (Hostinger default)
if(function_exists('rocket_clean_domain')) rocket_clean_domain();// WP Rocket
if(function_exists('w3tc_flush_all')) w3tc_flush_all();          // W3 Total Cache
if(function_exists('wpfc_clear_all_cache')) wpfc_clear_all_cache();// WP Fastest Cache
out("\nCleared caches (object + common page-cache plugins).",'#6cf');

out("\nDone. Products processed: $processed".($aborted?' — STOPPED EARLY (see red messages); re-open the URL to resume.':($batched?' (more to do — refresh to continue).':'.')),'#6cf');
$job_done = (!$batched && $left===0 && !$blog_incomplete);   // everything finished: products done AND no blog batch still pending
if($job_done && $products_complete){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'"); delete_option('wcm_reset_done'); }   // clear product resume flags + re-arm RESET only when the WHOLE job is done. Clearing while the blog is still batching across refreshes would wipe the done-flags and reprocess the ENTIRE catalog on every refresh
if($job_done && SELF_DELETE_WHEN_DONE){ if(@unlink(__FILE__)) out('This file deleted itself. ✅','#6f6'); else out('Could not auto-delete — delete this file manually.','#fa0'); }
elseif(!$job_done) out('>>> Not fully done — re-open the URL to finish, then delete this file. <<<','#fa0');
echo "</body>";
