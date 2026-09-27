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
 *   - tags
 *   - internal interlinks (deterministic — no AI)
 *   - categories: create SEO use-based categories and group products into them
 *     (auto, or from your own list) + category descriptions with interlinks
 * PAGES (after products, so they read the finished catalog):
 *   - homepage (Flatsome UX Builder or HTML), contact, privacy, terms,
 *     shipping, refund/returns, FAQ, about, and N blog posts.
 *   - on Flatsome, the homepage/contact/info pages use a per-site design system (fonts/radius/card
 *     style/spacing, picked deterministically from the same seed as the homepage layout — see
 *     DYNAMIC_HOMEPAGE below) and a matching CSS block written into Customizer > Additional CSS
 *     between the markers "/* wcm-design:start *\/" and "/* wcm-design:end *\/" (re-running replaces
 *     that block only; any other CSS already there is preserved). Those pages use the Full Width
 *     ("page-blank.php") page template so the design isn't squeezed into the theme's boxed layout.
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

// ---- Language + auto-refresh ------------------------------------------------
const SITE_LANGUAGE = 'American English';   // content language for ALL AI copy, meta and keywords. Change to 'French', 'Spanish (Spain)', 'German', etc. Default American English.
const AUTO_REFRESH = true;                   // the output page reloads itself through each batch until the whole job is done, then self-deletes. Keep the browser tab open + laptop awake. No server-side scheduling.
const AUTO_REFRESH_SECONDS = 12;             // delay between auto-reloads (keeps AI request rate sane)
const AUTO_REFRESH_MAX_SECONDS = 20;   // when AUTO_REFRESH is on, do at most ~this many seconds of AI work per page-load, then pause and let the page auto-reload to continue. Keeps each load under the server/proxy timeout so the auto-refresh actually fires. Lower it if your host times out sooner.
const DECLUSTER_ANCHORS = true;    // split run-together CamelCase link/button labels into words (e.g. 'GoldenTeacherMushrooms' -> 'Golden Teacher Mushrooms'). ON by default. Set FALSE for chemical-name catalogs, where it would wrongly split names like '5-MeO-DMT'. Display only; never changes the stored product title.

// ---- AI provider -----------------------------------------------------------
const AI_PROVIDER = 'openai';                // 'gemini' (free) | 'claude' | 'openai' (ChatGPT)
const GEMINI_API_KEY = '';                   // https://aistudio.google.com/apikey
const GEMINI_MODEL   = 'gemini-2.0-flash';
const GEMINI_RPM     = 10;
const ANTHROPIC_API_KEY = '';                // https://console.anthropic.com  <-- paste your Claude key here
const CLAUDE_MODEL      = 'claude-haiku-4-5';  // fast + high rate limits, ideal for bulk. (Opus = 'claude-opus-4-8' if you want top quality)
const CLAUDE_RPM        = 0;                   // 0 = NO throttle (full speed, like the old script — fine for Haiku/Sonnet). Set ~5 ONLY if you use Opus and hit rate limits
const CLAUDE_MAX_TOKENS = 4096;              // max output tokens per call. Smaller = fewer rate-limit hits; RAISE it if long descriptions get cut off
const OPENAI_API_KEY = '';                   // https://platform.openai.com/api-keys  <-- paste your ChatGPT key here (AI_PROVIDER='openai'). Keep it out of any shared/committed copy of this file.
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
const DO_META         = true;   const OVERWRITE_META  = true;   // Rank Math meta
const DO_TAGS         = true;   const OVERWRITE_TAGS  = true;  // false = fill only if empty
const DO_PRICE        = true;   const OVERWRITE_PRICE = false;  // false = set only if empty
const PRODUCT_TYPE    = 'simple';               // 'simple' or 'variable' (AI proposes options)
const PRICE_ENDING    = '.99';                  // '' = whole number
const SHORT_DESC_INCLUDE_UNIT = false;           // add "Sold as: <unit>" to short desc?
const MAX_TAGS        = 3;

const DO_INTERLINKS   = true;   const INTERLINKS_PER_PRODUCT = 3;   // deterministic, no AI
const REMOVE_FOREIGN_LINKS = true; // strip links pointing to OTHER domains (keeps the anchor text)
const SITE_DOMAIN = '';             // your REAL domain WITH scheme, e.g. 'https://mysite.com' (include the subfolder if WordPress lives in one: 'https://mysite.com/shop'). Leave EMPTY to auto-detect — recommended when the site is already on its real domain
                                    // from the live site. Set it when running locally or on a temporary URL so
                                    // generated interlinks + foreign-link stripping use your real domain.

// ---- IMAGES ------------------------------------------------------------
const USE_LOGO_AS_PRODUCT_IMAGE = true;   // NO API needed: set the logo as the featured image for products that have NO image. AI product-image generation has been removed — this is the only way products get an image (besides manually uploading one)

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
const DYNAMIC_HOMEPAGE = true;   // (used by the homepage task) each site gets a distinct, SEO-safe homepage layout, design persona (fonts/radius/card style) and contact page layout, all picked deterministically from a per-site seed. false = every site gets the SAME fixed layout/persona/contact layout (colors still vary — that's controlled by DO_BRANDING/site_palette, not this)
const HOMEPAGE_IMAGES = true;    // (used by the homepage task) generate an AI banner + supporting images (needs OPENAI_API_KEY)
const HOMEPAGE_IMAGE_COUNT = 1;  // 1 banner + up to (this-1) supporting section images
const HOMEPAGE_IMAGE_MODEL    = 'auto';      // OpenAI image model: 'auto' = try gpt-image-1, fall back to dall-e-3 if the org isn't verified; or force 'gpt-image-1' / 'dall-e-3'
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
const BLOG_PER_RUN        = 15;       // max posts to WRITE per page-load, so a 45-post run can't time out — refresh to write the next batch. 0 = all at once
const BLOG_INTERNAL_LINKS = 5;       // minimum internal links per post (shop / categories / products / other posts). Guaranteed by a deterministic top-up
const BLOG_EXTERNAL_LINK  = true;    // add ONE outbound link to an authoritative .gov/.mil/.edu/.int or public info/legal source — never a business/competitor
const BLOG_EXTERNAL_URL   = '';      // OPTIONAL deterministic fallback: a niche-relevant AUTHORITATIVE url (e.g. a .gov/.edu page or a Wikipedia article). If the AI fails to add a surviving authoritative outbound link, THIS is appended as a 'Source:' line so every post has one. '' = rely on the AI only.
const BLOG_WORDS          = '900-1300';   // article length target
const BLOG_CATEGORY       = '';      // optional blog category name to file every post under ('' = none). Ignored when BLOG_AUTO_CATEGORIES is on.
const BLOG_AUTO_CATEGORIES = true;   // group posts into a SMALL fixed set of topical blog categories (AI picks the set ONCE) and file each post under the best fit. Tags are intentionally NOT added — single-use tags cause thin-content/index bloat and hurt SEO
const BLOG_CATEGORY_COUNT  = 7;      // HARD CAP on how many blog categories exist — the AI must reuse this set, never invent one per post
const BLOG_LAYOUT         = 'no-sidebar';   // Flatsome blog layout (verified theme keys). 'no-sidebar' = clean full-width posts (recommended, stops the category-widget sidebar pushing content) | 'right-sidebar' | 'left-sidebar' | '' = leave your current setting untouched
const BLOG_KEYWORDS_CSV   = 'keywords.csv';   // a SEMrush Keyword Magic Tool CSV export placed next to this file (WordPress root). Its keywords seed the blog titles. '' or file-absent = fall back to AI-suggested titles (today's behavior)
const BLOG_CORNERSTONE_COUNT = 3;   // how many of the posts are long "cornerstone" linkable-asset guides (comprehensive, built to attract backlinks). 0 = none
const BLOG_CORNERSTONE_WORDS = '1800-2500';   // target length for cornerstone posts (normal posts keep BLOG_WORDS)
const REPAIR_POST_LINKS   = false;   // MAINTENANCE MODE: re-scan every existing post and remove any internal link that points to a NOT-YET-PUBLISHED post (the 404 case), then re-top-up. No AI, no new posts. Turn every DO_*/FORCE_*/REMOVE_* off to run this alone. Set back to false after.
const OVERWRITE_PAGES = true;                 // legal/info pages: overwrite if they already have content

// ---- BRANDING (site identity: name, colors, fonts, logo — the "Appearance > Customize" bits) ----------------
const ENSURE_SEARCH_VISIBLE = true;   // force "Search engine visibility" ON (blog_public=1) every run. Restored/migrated/staging sites often silently carry the "Discourage search engines" flag, which noindexes the WHOLE site so nothing indexes no matter how many sitemaps you submit. Leave ON.
const DO_BRANDING       = true;   // set the site title, a color palette (from the logo's own colors, else an AI pick, else a deterministic fallback), the per-site design persona's fonts, and the logo (from LOGO_URL, if set). false = leave the theme's existing colors/fonts alone and derive page accents from whatever the theme already has set
const BRANDING_OVERWRITE= false;   // false = set each item (colors, fonts, logo, name) ONLY where the site hasn't been branded yet (safe). true = replace the theme's existing colors/fonts/logo/name every run
const SITE_TAGLINE      = '';      // '' = keep the current tagline  (the site TITLE is always BRAND_NAME — no separate setting)
const LOGO_URL          = '';      // OPTIONAL: paste a PUBLIC/live logo image URL (png/jpg) to USE as the logo AND as the color source (downloaded into the media library once). Leave empty to fall back to the theme's existing custom_logo/site_logo (if any) as the color source instead — no auto-generation. Also used by USE_LOGO_AS_PRODUCT_IMAGE

// ---- Compliance + anti-AI voice --------------------------------------------
const COMPLIANCE_MODE = false;
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
function lang_rule(){ return SITE_LANGUAGE==='' ? '' : 'Write all output text in '.SITE_LANGUAGE.': every heading, sentence, list item, meta title, meta description and focus keyword. Use natural, fluent, native '.SITE_LANGUAGE.' with correct spelling, grammar and punctuation. Make the wording and keywords SEO-optimized for searches performed in '.SITE_LANGUAGE.' (phrases a native '.SITE_LANGUAGE.' speaker would type into Google). '; }
function voice_rules(){ $b=implode(', ',BANNED_AI_WORDS);
    return "VOICE — STRICT: write like a real human brand copywriter; it must NOT read like AI. "
        . "NEVER use these words/phrases or close variants: $b. No hollow hype or filler; be concrete and "
        . "specific. Vary sentence length, use contractions, second person ('you'), active voice. Don't open "
        . "with 'Welcome to' or 'In the world of'; don't end with 'In conclusion'. Avoid tidy lists of three. "
        . "NEVER use em dashes or en dashes (the — or – characters) anywhere; use commas, periods, or parentheses instead.\n"
        . lang_rule(); }
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
// Branding: site colors (logo analysis, then AI, then a deterministic fallback — or, when
// DO_BRANDING is off, whatever the theme already renders) + manual logo (LOGO_URL)
// ---------------------------------------------------------------------------
function hex_ok($c){ return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/',trim($c)); }
function hsl_hex($h,$s,$l){ $c=(1-abs(2*$l-1))*$s; $x=$c*(1-abs(fmod($h/60,2)-1)); $m=$l-$c/2;
    if($h<60){$r=$c;$g=$x;$b=0;}elseif($h<120){$r=$x;$g=$c;$b=0;}elseif($h<180){$r=0;$g=$c;$b=$x;}
    elseif($h<240){$r=0;$g=$x;$b=$c;}elseif($h<300){$r=$x;$g=0;$b=$c;}else{$r=$c;$g=0;$b=$x;}
    $clamp=fn($v)=>max(0,min(255,(int)round($v*255)));   // guard float rounding at the 0/255 edges so sprintf always emits exactly 2 hex digits per channel
    return sprintf('#%02x%02x%02x',$clamp($r+$m),$clamp($g+$m),$clamp($b+$m)); }
// RGB (0-255 each) -> [hue 0-360, sat 0-1, light 0-1]
function rgb_hsl($r,$g,$b){ $r/=255; $g/=255; $b/=255; $mx=max($r,$g,$b); $mn=min($r,$g,$b); $l=($mx+$mn)/2;
    if($mx==$mn) return [0,0,$l];
    $d=$mx-$mn; $s=$l>0.5?$d/(2-$mx-$mn):$d/($mx+$mn);
    if($mx==$r) $h=fmod(($g-$b)/$d,6); elseif($mx==$g) $h=($b-$r)/$d+2; else $h=($r-$g)/$d+4;
    $h*=60; if($h<0) $h+=360; return [$h,$s,$l]; }
// hex -> [r,g,b] (0 on anything unparsable, never fatal)
function hex_rgb($hex){ $hex=ltrim((string)$hex,'#'); if(strlen($hex)!==6) return [0,0,0];
    return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))]; }
function hex_hsl($hex){ [$r,$g,$b]=hex_rgb($hex); return rgb_hsl($r,$g,$b); }
function hue_diff($a,$b){ $d=fmod(abs($a-$b),360); return $d>180?360-$d:$d; }
// WCAG relative luminance + contrast ratio (1-21), used to keep colors readable behind Flatsome's white button text
function rel_luminance($hex){ [$r,$g,$b]=hex_rgb($hex); $f=function($c){ $c/=255; return $c<=0.03928?$c/12.92:pow(($c+0.055)/1.055,2.4); };
    return 0.2126*$f($r)+0.7152*$f($g)+0.0722*$f($b); }
function contrast_ratio($a,$b){ $la=rel_luminance($a)+0.05; $lb=rel_luminance($b)+0.05; return $la>$lb?$la/$lb:$lb/$la; }
// darken (hue + roughly-saturation kept, saturation capped at 0.85 to avoid neon) until $hex reaches $min contrast against white —
// Flatsome renders WHITE text on primary/secondary buttons, so this is the one thing that must always hold
function contrast_fix($hex,$min){ if(!hex_ok($hex)) return $hex; [$h,$s,$l]=hex_hsl($hex); $s=min($s,0.85); $c=hsl_hex($h,$s,$l);
    for($i=0;$i<60 && $l>0 && contrast_ratio($c,'#ffffff')<$min; $i++){ $l=max(0,$l-0.02); $c=hsl_hex($h,$s,$l); }
    return $c; }
// apply the white-text-on-button readability rule to a {primary,secondary,accent} set — keeps other keys (e.g. 'source') untouched
function palette_readable($p){ if(isset($p['primary'])) $p['primary']=contrast_fix($p['primary'],4.5);
    if(isset($p['secondary'])) $p['secondary']=contrast_fix($p['secondary'],4.5);
    if(isset($p['accent'])) $p['accent']=contrast_fix($p['accent'],3.0); return $p; }
// near-black (dark bands/footers), near-white (alternating section backgrounds) and body-text neutral — all lightly tinted with the primary hue
function palette_shades($primary){ [$h,$s]=hex_hsl($primary);
    return [hsl_hex($h,min($s,0.30),0.10),hsl_hex($h,min($s,0.30),0.95),hsl_hex($h,min($s,0.12),0.16)]; }
// deterministic fallback palette derived from the brand name (no AI) so branding still works with no text key / failed call
function palette_fallback(){ $seed=crc32(strtolower(brand().'|'.STORE_NICHE)); $h=$seed%360;
    $prim=hsl_hex($h,0.55,0.42); $sec=hsl_hex(($h+150)%360,0.60,0.48); $acc=hsl_hex(($h+30)%360,0.65,0.45);
    return palette_readable(['primary'=>$prim,'secondary'=>$sec,'accent'=>$acc]); }
function brand_palette(){ static $p=null; if($p!==null) return $p; $p=palette_fallback(); $p['source']='fallback';
    [$d]=ai_json("Choose a professional website color palette for ".brand().", an online store selling ".STORE_NICHE.". "
        ."Pick colors that fit the feel of that niche (readable, not neon). Return ONE JSON object: "
        ."{\"primary\":\"#RRGGBB\",\"secondary\":\"#RRGGBB\",\"accent\":\"#RRGGBB\"} — full 6-digit hex, no comments.");
    if(is_array($d)){ $got=false; foreach(['primary','secondary','accent'] as $k){ if(hex_ok($d[$k]??null)){ $p[$k]=strtolower(trim($d[$k])); $got=true; } } if($got) $p['source']='ai'; }
    $p=palette_readable($p); return $p; }
// derive a brand palette from the LOGO's own ACTUAL colors (real image analysis, not just a dominant hue). Returns null when
// there is no logo, it can't be read, or it has neither a usable chromatic color nor a real dark tone (e.g. a blank/near-white
// image) — caller then falls back to brand_palette(). Otherwise returns {primary,secondary,accent,source}: source is 'logo'
// when the logo itself has one or more brand colors, or 'logo-mono' for a black/white logo (secondary/accent then come from
// brand_palette()). SVG is parsed as text (color tokens tallied, no image library needed, fill:/stop-color values weighted
// higher than incidental hex text); raster formats are grid-sampled via GD, or Imagick when GD isn't available — both optional,
// never required (returns null and lets the caller fall back).
function palette_from_logo(){
    try {
        $att=resolve_logo_attachment(); if(!$att) return null;
        $file=get_attached_file($att); if(!$file||!@file_exists($file)) return null;
        $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
        $tally=[]; // "r,g,b" => frequency
        if($ext==='svg'){
            $svg=@file_get_contents($file); if($svg===false||$svg==='') return null;
            $add=function($hex,$w=1) use(&$tally){ $hex=strtolower($hex); if(strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; if(strlen($hex)!==6) return;
                $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2)); $k="$r,$g,$b"; $tally[$k]=($tally[$k]??0)+$w; };
            if(preg_match_all('/#([0-9a-fA-F]{6})\b/',$svg,$m)) foreach($m[1] as $hex) $add($hex);
            if(preg_match_all('/#([0-9a-fA-F]{3})\b/',$svg,$m)) foreach($m[1] as $hex) $add($hex);
            if(preg_match_all('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i',$svg,$m,PREG_SET_ORDER)) foreach($m as $mm){ $k=((int)$mm[1]).','.((int)$mm[2]).','.((int)$mm[3]); $tally[$k]=($tally[$k]??0)+1; }
            if(preg_match_all('/(?:fill|stop-color)\s*[:=]\s*"?#([0-9a-fA-F]{3,6})/i',$svg,$m)) foreach($m[1] as $hex) $add($hex,2);   // an actual fill/gradient-stop color is more likely the real brand color than incidental hex text elsewhere in the file
        } elseif(in_array($ext,['png','jpg','jpeg','webp','gif'],true)){
            $bin=@file_get_contents($file); if($bin===false||$bin==='') return null;
            if(function_exists('imagecreatefromstring')){
                $im=@imagecreatefromstring($bin); if(!$im) return null;
                $w=@imagesx($im); $h=@imagesy($im);
                if(!$w||!$h){ imagedestroy($im); return null; }
                $step=max(1,(int)floor(min($w,$h)/40));   // grid sample, capped around ~1600 samples
                for($y=0;$y<$h;$y+=$step){ for($x=0;$x<$w;$x+=$step){
                    $idx=@imagecolorat($im,$x,$y); if($idx===false) continue;
                    $c=@imagecolorsforindex($im,$idx); if(!$c||$c['alpha']>100) continue;   // skip transparent pixels
                    $r=(int)(round($c['red']/16)*16); $g=(int)(round($c['green']/16)*16); $b=(int)(round($c['blue']/16)*16);   // quantize into buckets so near-identical colors tally together
                    $k="$r,$g,$b"; $tally[$k]=($tally[$k]??0)+1; } }
                imagedestroy($im);
            } elseif(class_exists('Imagick')){   // GD missing on this host — sample via Imagick instead
                try { $im=new \Imagick(); $im->readImageBlob($bin); $w=$im->getImageWidth(); $h=$im->getImageHeight(); if(!$w||!$h) return null;
                    $step=max(1,(int)floor(min($w,$h)/40));
                    for($y=0;$y<$h;$y+=$step){ for($x=0;$x<$w;$x+=$step){
                        $col=$im->getImagePixelColor($x,$y)->getColor(true);   // normalized 0-1 r/g/b/a
                        if(isset($col['a']) && $col['a']<0.4) continue;   // skip mostly-transparent pixels
                        $r=(int)(round($col['r']*255/16)*16); $g=(int)(round($col['g']*255/16)*16); $b=(int)(round($col['b']*255/16)*16);
                        $k="$r,$g,$b"; $tally[$k]=($tally[$k]??0)+1; } }
                    $im->clear();
                } catch (\Throwable $e) { return null; }
            } else return null;   // no image library on this host — skip, let brand_palette() handle it
        } else return null;
        if(!$tally) return null;
        arsort($tally);
        $chroma=[]; $darkest=null;   // $chroma: brand-usable colors, most prominent first (tally is already frequency-sorted); $darkest: first real dark pixel seen, for monochrome logos
        foreach($tally as $k=>$cnt){ [$r,$g,$b]=array_map(fn($v)=>max(0,min(255,(int)$v)),explode(',',$k)); [$hh,$ss,$ll]=rgb_hsl($r,$g,$b); $hex=sprintf('#%02x%02x%02x',$r,$g,$b);   // clamp: the /16*16 quantization above can round a 255 channel up to 256
            if($darkest===null && $ll<0.35) $darkest=['h'=>$hh,'s'=>$ss,'hex'=>$hex];
            if($ll>0.90||$ll<0.10||$ss<0.12) continue;   // skip near-white / near-black / near-gray (backgrounds, not brand color)
            $chroma[]=['h'=>$hh,'s'=>$ss,'l'=>$ll,'hex'=>$hex]; }
        if($chroma){   // the logo has real chromatic color(s) — keep them AS-IS (actual hue+sat+lightness, not just the hue)
            $primaryC=$chroma[0]; $primary=$primaryC['hex']; $ph=$primaryC['h'];
            $secondaryC=null; foreach($chroma as $i=>$c){ if($i===0) continue; if(hue_diff($ph,$c['h'])>=30){ $secondaryC=$c; break; } }   // a 2nd logo color, only if its hue is genuinely distinct
            $seed=crc32(site_host());   // per-site seed so sites without a 2nd logo color still vary from each other
            if($secondaryC){ $secondary=$secondaryC['hex']; $sh=$secondaryC['h']; }
            else { $off=[180,150,-150,30,-30][$seed%5]; $sh=fmod($ph+360+$off,360); $secondary=hsl_hex($sh,$primaryC['s'],$primaryC['l']); }   // complementary / split-complementary / analogous, deterministic per site
            $accentC=null; foreach($chroma as $c){ if($c===$primaryC||$c===$secondaryC) continue; if(hue_diff($ph,$c['h'])>=20 && hue_diff($sh,$c['h'])>=20){ $accentC=$c; break; } }
            if($accentC) $accent=$accentC['hex']; else { $off2=[60,-60,90,-90][intdiv($seed,5)%4]; $ah=fmod($ph+360+$off2,360); $accent=hsl_hex($ah,min(0.75,$primaryC['s']+0.05),max(0.35,min(0.55,$primaryC['l']))); }
            $pal=palette_readable(['primary'=>$primary,'secondary'=>$secondary,'accent'=>$accent]); $pal['source']='logo'; return $pal; }
        if($darkest!==null){   // monochrome logo (no chromatic pixel) but a real dark tone exists — use it, nudged to a rich charcoal
            $h=$darkest['s']>0.05?$darkest['h']:0; $primary=hsl_hex($h,max(0.04,min($darkest['s'],0.15)),0.16);
            $bp=brand_palette();   // secondary/accent from the niche-appropriate AI/fallback palette (already readability-checked)
            $pal=palette_readable(['primary'=>$primary,'secondary'=>$bp['secondary'],'accent'=>$bp['accent']]); $pal['source']='logo-mono'; return $pal; }
        return null;   // logo is blank/near-white -> no brand color or dark tone to extract
    } catch (\Throwable $e) { return null; }   // never fatal the branding phase — any failure just falls back to brand_palette()
}
// Flatsome's own shipped default color (class-flatsome-default.php) for the given key — used verbatim when DO_BRANDING is off
// and the theme mod isn't set yet, so the page accents match exactly what a virgin Flatsome install would show on its buttons.
function flatsome_default_color($which){
    if(class_exists('Flatsome_Default')){
        $const=['primary'=>'COLOR_PRIMARY','secondary'=>'COLOR_SECONDARY','accent'=>'COLOR_SUCCESS'][$which]??'';
        if($const!=='' && defined('Flatsome_Default::'.$const)){ $v=constant('Flatsome_Default::'.$const); if(hex_ok($v)) return strtolower($v); }
    }
    return ['primary'=>'#446084','secondary'=>'#d26e4b','accent'=>'#7a9c59'][$which] ?? '#446084';
}
// SINGLE SOURCE OF TRUTH for site colors, cached in option 'wcm_palette' (autoload false) so the Pages phase (which builds the
// homepage BEFORE Branding runs) and the Branding phase always see the same colors. Recomputed whenever there's no cache, the
// cache is malformed, the cached logo attachment id no longer matches resolve_logo_attachment(), DO_BRANDING/BRANDING_OVERWRITE
// changed since the cache was written, whether the theme already has its own color_primary changed, or the cached source was a
// failed-AI 'fallback' (a fallback is used for the current run but never cached, so the next run retries instead of being stuck
// on it forever). Keys: primary, secondary, accent, dark (near-black, for dark bands/footers), light (very light tint, for
// alternating section backgrounds), text (body text neutral), source ('logo'|'logo-mono'|'ai'|'fallback'|'theme'), logo (attachment id, 0 if none).
function site_palette(){
    try {
        $logo=resolve_logo_attachment();
        $theme_p_now=get_theme_mod('color_primary'); $theme_has_now=hex_ok($theme_p_now);
        $cache=get_option('wcm_palette');
        // theme_ok: valid whenever the theme currently has NO color_primary, OR its color_primary is exactly what WE cached
        // as 'primary' (i.e. the Branding phase wrote our own computed color back into the theme mod) — comparing against
        // the cached value itself, rather than a plain has/hasn't flag, means Branding writing the color right after we
        // compute it does NOT immediately invalidate the cache (which used to force a recompute next load that derived a
        // DIFFERENT accent and drifted from what was actually branded). A genuinely different owner-set color still misses.
        $theme_ok = !$theme_has_now || (is_array($cache) && strtolower($theme_p_now)===($cache['primary']??''));
        $cache_ok = is_array($cache) && hex_ok($cache['primary']??null) && hex_ok($cache['secondary']??null) && hex_ok($cache['accent']??null)
            && hex_ok($cache['dark']??null) && hex_ok($cache['light']??null) && hex_ok($cache['text']??null)
            && (int)($cache['logo']??-1)===(int)$logo
            && (bool)($cache['do_branding']??null)===DO_BRANDING
            && (bool)($cache['overwrite']??null)===BRANDING_OVERWRITE
            && $theme_ok
            && (($cache['source']??'')!=='fallback' || (int)($cache['retry_after']??0)>time());   // a failed-AI fallback is reused for a short TTL (so a page-load storm doesn't hammer the AI) then retried
        if($cache_ok) return $cache;
        if(!DO_BRANDING){   // branding is off entirely -> use exactly what Flatsome will render (no AI call, no logo analysis), so page accents match the theme's own buttons
            $ts=get_theme_mod('color_secondary'); $ta=get_theme_mod('color_success');
            $pal=palette_readable([
                'primary'=>$theme_has_now?strtolower($theme_p_now):flatsome_default_color('primary'),
                'secondary'=>hex_ok($ts)?strtolower($ts):flatsome_default_color('secondary'),
                'accent'=>hex_ok($ta)?strtolower($ta):flatsome_default_color('accent'),
            ]); $pal['source']='theme';
        } elseif(hex_ok($theme_p_now) && !BRANDING_OVERWRITE){   // theme already branded and we won't overwrite it — build the page palette around what Flatsome will actually show
            $theme_s=get_theme_mod('color_secondary'); $theme_s=hex_ok($theme_s)?$theme_s:$theme_p_now;
            $theme_acc=get_theme_mod('color_success');
            if(hex_ok($theme_acc)) $accent=strtolower($theme_acc);   // Flatsome's own accent color mod, when the theme has one, beats a derived guess
            else { [$th]=hex_hsl($theme_p_now); $accent=hsl_hex(fmod($th+30,360),0.65,0.45); }
            $pal=palette_readable(['primary'=>strtolower($theme_p_now),'secondary'=>strtolower($theme_s),'accent'=>$accent]); $pal['source']='theme';
        } else {
            $lp=palette_from_logo();
            if($lp) $pal=['primary'=>$lp['primary'],'secondary'=>$lp['secondary'],'accent'=>$lp['accent'],'source'=>$lp['source']??'logo'];
            else { $bp=brand_palette(); $pal=['primary'=>$bp['primary'],'secondary'=>$bp['secondary'],'accent'=>$bp['accent'],'source'=>$bp['source']??'ai']; }
        }
        [$dark,$light,$text]=palette_shades($pal['primary']);
        $pal['dark']=$dark; $pal['light']=$light; $pal['text']=$text; $pal['logo']=(int)$logo;
        $pal['do_branding']=DO_BRANDING; $pal['overwrite']=BRANDING_OVERWRITE;
        if($pal['source']==='fallback') $pal['retry_after']=time()+900;   // failed AI call -> short 15-minute retry window instead of hammering it on every load
        update_option('wcm_palette',$pal,false);
        return $pal;
    } catch (\Throwable $e) {   // never fatal — a safe neutral palette beats a broken page
        return ['primary'=>'#2c3e50','secondary'=>'#4a6fa5','accent'=>'#c0392b','dark'=>'#0d1117','light'=>'#f5f6f7','text'=>'#1c1c1c','source'=>'fallback','logo'=>0]; }
}
// download a logo image URL into the media library once; return attachment id (0 on failure)
function sideload_logo($url){ $url=trim((string)$url); if($url==='') return 0;
    $tmp=download_url($url,120); if(is_wp_error($tmp)) return 0;
    $ext=strtolower(pathinfo((string)parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION)); if(!in_array($ext,['png','jpg','jpeg','webp','gif','svg'],true)) $ext='png';
    $is_svg=($ext==='svg');   // accept the user's own SVG logo; WP blocks image/svg+xml by default, so temporarily allow it for THIS sideload only. A raster PNG/JPG is still preferable when used as a product image — SVG has no intrinsic thumbnail sizes.
    if($is_svg){ $addmime=function($m){ $m['svg']='image/svg+xml'; return $m; };
        $oktype=function($data,$file,$fn,$mimes){ if(preg_match('/\.svg$/i',(string)$fn)){ $data['ext']='svg'; $data['type']='image/svg+xml'; } return $data; };
        add_filter('upload_mimes',$addmime); add_filter('wp_check_filetype_and_ext',$oktype,10,4); }
    $att=media_handle_sideload(['name'=>'wcm-logo.'.$ext,'tmp_name'=>$tmp],0,brand().' logo');
    if($is_svg){ remove_filter('upload_mimes',$addmime); remove_filter('wp_check_filetype_and_ext',$oktype,10); }
    if(is_wp_error($att)){ @unlink($tmp); return 0; }
    update_post_meta($att,'_wp_attachment_image_alt',brand().' logo'); return (int)$att; }
// generate ONE image with OpenAI's image API and save it to a LOCAL temp file ready for media_handle_sideload.
// gpt-image-1 first (best), auto-falling back to dall-e-3 if the org isn't verified for gpt-image-1 (403). Both return
// base64 (dall-e-3 asked for b64_json), so there's one decode path. [tmpPath,'png',''] on success, or [0,'',err].
function openai_image_tmp($prompt){ if(OPENAI_API_KEY==='') return [0,'','OPENAI_API_KEY empty'];
    $spec=['gpt-image-1'=>['1536x1024',false],'dall-e-3'=>['1792x1024',true]];   // model => [landscape size, send response_format]
    $models=HOMEPAGE_IMAGE_MODEL==='auto'?['gpt-image-1','dall-e-3']:[HOMEPAGE_IMAGE_MODEL];
    $lasterr='';
    foreach($models as $model){ if(!isset($spec[$model])) continue; [$size,$rf]=$spec[$model];
        $payload=['model'=>$model,'prompt'=>$prompt,'size'=>$size,'n'=>1]; if($rf) $payload['response_format']='b64_json';
        $r=wp_remote_post('https://api.openai.com/v1/images/generations',['timeout'=>180,
            'headers'=>['Authorization'=>'Bearer '.OPENAI_API_KEY,'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload)]);
        if(is_wp_error($r)){ $lasterr=$r->get_error_message(); continue; }
        $code=wp_remote_retrieve_response_code($r); $j=json_decode(wp_remote_retrieve_body($r),true);
        if($code!=200){ $lasterr="OpenAI image HTTP $code ($model): ".($j['error']['message']??''); continue; }   // 403 = org not verified for gpt-image-1 -> fall through to dall-e-3
        $b64=$j['data'][0]['b64_json']??''; if($b64===''){ $lasterr="no image data ($model)"; continue; }
        $bin=base64_decode($b64,true); if($bin===false||$bin===''){ $lasterr="bad base64 ($model)"; continue; }
        $tmp=get_temp_dir().'wcm-oai-'.substr(md5($prompt.microtime()),0,10).'.png'; if(@file_put_contents($tmp,$bin)===false){ $lasterr='temp write failed'; continue; }
        return [$tmp,'png','']; }
    return [0,'',$lasterr?:'openai image failed']; }
// generate + sideload ONE AI homepage image (banner or supporting section), attached to 0 (not a product). Non-human, no text, marketing photograph.
// Uses OPENAI_API_KEY. [attId,''] on success; [0,''] on ANY failure — the homepage must always build fine without it.
function home_image($prompt,$alt){
    $full=trim((string)$prompt).'. Professional high-resolution marketing photograph, wide cinematic composition, clean modern lighting, realistic, no people, no text, no logos, no watermark.';
    [$tmp,$ext,$err]=openai_image_tmp($full); if(!$tmp) return [0,''];
    $att=media_handle_sideload(['name'=>'wcm-home-'.substr(md5($full.microtime()),0,8).'.'.$ext,'tmp_name'=>$tmp],0,$alt);
    if(is_wp_error($att)){ @unlink($tmp); return [0,'']; }
    update_post_meta($att,'_wp_attachment_image_alt',$alt); return [(int)$att,'']; }
// resolve ONE reusable logo attachment id (cached in wcm_logo_att): provided LOGO_URL > existing custom_logo > existing site_logo. 0 if none available.
function resolve_logo_attachment(){ static $id=null; if($id!==null) return $id;
    $cached=(int)get_option('wcm_logo_att'); if($cached && get_post($cached)) return $id=$cached;
    if(LOGO_URL!==''){ $a=sideload_logo(LOGO_URL); if($a){ update_option('wcm_logo_att',$a,false); return $id=$a; } }
    $cl=(int)get_theme_mod('custom_logo'); if($cl && get_post($cl)){ update_option('wcm_logo_att',$cl,false); return $id=$cl; }   // custom_logo is already an attachment id
    $sl=get_theme_mod('site_logo'); if(is_string($sl)&&$sl!==''){ $a=sideload_logo($sl); if($a){ update_option('wcm_logo_att',$a,false); return $id=$a; } }
    return $id=0; }

// ---------------------------------------------------------------------------
// Fixed UI strings (deterministic labels the tool hardcodes, not AI copy) —
// AI copy already localizes via lang_rule(); this covers the tool's own
// buttons/headings/labels. English base returned as-is (no AI call, so
// SITE_LANGUAGE='American English' stays byte-identical to before). Any other
// language is translated ONCE via a single ai_json() call and cached in the
// 'wcm_ui_lang' option (keyed to the exact SITE_LANGUAGE string), so auto-refresh
// reloads and reruns reuse the cached translation instead of re-billing.
function ui_strings(){ static $cache=null; if($cache!==null) return $cache;
    $base=[
        'shop_now'=>'Shop Now','featured_products'=>'Featured Products','why_choose_us'=>'Why Choose Us',
        'shop_by_category'=>'Shop by Category','about_us'=>'About Us','faq_heading'=>'Frequently Asked Questions',
        'how_it_works'=>'How It Works','related_products'=>'Related Products',
        'browse_more_in'=>'Browse more in','explore_more'=>'Explore more','shop_this_category'=>'Shop This Category',
        'contact'=>'Contact','reach_us'=>'Reach us','how_we_can_help'=>'How we can help','email'=>'Email','phone'=>'Phone',
        'location'=>'Location','support_hours'=>'Support hours','support_hours_value'=>'Monday to Friday, 9:00 AM to 5:00 PM',
        'email_us'=>'Email Us','product_questions'=>'Product questions and recommendations',
        'order_status'=>'Order status, shipping, and returns','bulk_enquiries'=>'Bulk, wholesale, and business enquiries',
        'contact_lead'=>'Questions about our %s, an existing order, or a bulk enquiry? Our team is glad to help, and we usually reply within one business day.',
        'contact_feedback'=>'Feedback about your experience with %s',
        'contact_write_prompt'=>"Prefer to write? Send a note any time and we'll get back to you quickly.",
        'view_all_products'=>'View All Products','browse_categories'=>'Browse Categories','new_arrivals'=>'New Arrivals',
        'send_a_message'=>'Send a Message','questions_contact_us'=>'Questions? Contact us',
        'still_have_questions'=>'Still have questions? Our team is happy to help.',
        'kicker_shop'=>'Shop','kicker_categories'=>'Browse',
    ];
    if(SITE_LANGUAGE==='' || stripos(SITE_LANGUAGE,'english')!==false) return $cache=$base;   // default/English: no AI call, byte-identical output
    $opt=get_option('wcm_ui_lang');
    $cached=(is_array($opt) && ($opt['lang']??'')===SITE_LANGUAGE && !empty($opt['strings']) && is_array($opt['strings'])) ? $opt['strings'] : [];
    if(!array_diff_key($base,$cached)) return $cache=array_merge($base,$cached);   // every base key already translated -> reuse, never re-bill on reload
    [$d]=ai_json("Translate the VALUES of this JSON object into ".SITE_LANGUAGE.", natural and idiomatic wording for "
        ."an e-commerce website's fixed UI text (buttons, headings, short labels). Keep each value concise, matching "
        ."the length/register of the original. Preserve any '%s' placeholder EXACTLY as '%s', in a natural position "
        ."for the sentence in ".SITE_LANGUAGE.". Return ONE JSON object with EXACTLY the same keys, translated values "
        ."only, no comments, no trailing commas: ".wp_json_encode($base));
    $usable=is_array($d) && !empty($d);   // a TOTAL failure (invalid JSON, empty response) must never be cached, or a non-English site would be locked into English forever
    $out=$cached;
    if($usable){ foreach($base as $k=>$v){ if(isset($d[$k]) && is_string($d[$k]) && trim($d[$k])!=='') $out[$k]=$d[$k]; }   // re-translating the FULL set self-heals any cache that's missing newer keys added later
        foreach($base as $k=>$v){ if(!isset($out[$k])) $out[$k]=$v; }   // a key the AI answered but DROPPED gets the English value PERMANENTLY (genuinely untranslatable is rare and this avoids re-billing it every load)
        update_option('wcm_ui_lang',['lang'=>SITE_LANGUAGE,'strings'=>$out],false);
    } else { foreach($base as $k=>$v){ if(!isset($out[$k])) $out[$k]=$v; } }   // total failure: render THIS page in English for whatever isn't already cached, but don't persist it, so the next load retries the AI
    return $cache=$out; }
function ui_t($key){ return ui_strings()[$key] ?? $key; }

// split run-together CamelCase label text into words for display/anchor use. Only touches strings with NO existing
// whitespace; preserves hyphens (so it never mangles an already-spaced or hyphenated name). Gated by DECLUSTER_ANCHORS.
function humanize_anchor($s){ $s=(string)$s; if(!DECLUSTER_ANCHORS || $s==='' || preg_match('/\s/',$s)) return $s;
    $t=str_replace('_',' ',$s);
    $t=preg_replace('/([a-z0-9])([A-Z])/','$1 $2',$t);        // camelCase boundary: 'aB' -> 'a B'
    $t=preg_replace('/([A-Z]+)([A-Z][a-z])/','$1 $2',$t);     // acronym boundary: 'HTMLParser' -> 'HTML Parser'
    $t=trim(preg_replace('/\s{2,}/',' ',$t));
    return $t===''?$s:$t; }

// ---------------------------------------------------------------------------
// Interlinks (deterministic)
// ---------------------------------------------------------------------------
function inject_links($html,$related,$cat_url,$cat_name){ $related=array_values(array_filter($related,fn($r)=>!empty($r['url'])));
    $have=existing_hrefs($html); $miss=array_filter($related,fn($r)=>!in_array($r['url'],$have,true)); $needcat=$cat_url&&!in_array($cat_url,$have,true);
    if(!$miss&&!$needcat) return $html; $li='';
    foreach($miss as $r){ $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; }   // only the links not already present — never duplicates
    $b=$li?"\n<h2>".esc(ui_t('related_products'))."</h2>\n<ul>$li</ul>":''; if($needcat) $b.="\n<p>".esc(ui_t('browse_more_in'))." <a href=\"".esc_url($cat_url).'">'.esc($cat_name).'</a>.</p>';
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
// Optional SEMrush Keyword Magic Tool CSV export (BLOG_KEYWORDS_CSV) placed next to this file — seeds blog titles from
// real search demand instead of AI-guessed topics. Dependency-free (fgetcsv only), tolerant of SEMrush's varying column
// wording/order, and NEVER fatal: '' / missing file / no keyword column / unparseable -> [] (caller falls back to AI titles).
// Returns rows ranked best-first: ['kw'=>,'vol'=>,'kd'=>,'intent'=>].
function read_keyword_csv(){ if(BLOG_KEYWORDS_CSV==='') return []; $path=__DIR__.'/'.BLOG_KEYWORDS_CSV; if(!@is_file($path)) return [];
    $fh=@fopen($path,'r'); if(!$fh) return [];
    $header=fgetcsv($fh,0,',','"',''); if(!is_array($header)){ fclose($fh); return []; }   // explicit args: silences the PHP 8.4 default-$escape deprecation and gives standard CSV parsing
    if(isset($header[0])) $header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$header[0]);   // strip a UTF-8 BOM SEMrush/Excel sometimes puts on the first cell
    $norm=array_map(fn($h)=>strtolower(trim((string)$h)),$header);
    $find=function($needles)use($norm){ foreach($needles as $n){ foreach($norm as $i=>$h){ if(strpos($h,$n)!==false) return $i; } } return -1; };   // match by NAME (case-insensitive, trimmed), not position — SEMrush export column order/wording varies
    $ci=$find(['keyword']); if($ci<0){ fclose($fh); return []; }   // no keyword column at all -> can't seed anything
    $vi=$find(['search volume','volume']); $ki=$find(['difficulty','kd']); $ii=$find(['intent']);
    $rows=[]; $seen=[];
    while(($r=fgetcsv($fh,0,',','"',''))!==false){ if(!isset($r[$ci])) continue; $kw=trim((string)$r[$ci]); if($kw==='') continue;
        $nk=preg_replace('/\s+/',' ',strtolower($kw)); if(isset($seen[$nk])) continue; $seen[$nk]=1;   // dedupe by normalized keyword
        $vol=$vi>=0?(int)preg_replace('/[^0-9]/','',(string)($r[$vi]??'')):0;
        $kd=($ki>=0 && trim((string)($r[$ki]??''))!=='')?(float)preg_replace('/[^0-9.]/','',(string)$r[$ki]):null;
        $intent=$ii>=0?strtolower(trim((string)($r[$ii]??''))):'';
        $rows[]=['kw'=>$kw,'vol'=>$vol,'kd'=>$kd,'intent'=>$intent]; }
    fclose($fh);
    usort($rows,function($a,$b){   // simple deterministic score: informational intent + higher volume wins, higher difficulty penalized
        $sa=$a['vol']*(strpos($a['intent'],'informational')!==false||strpos($a['intent'],'info')!==false?1.5:1)-($a['kd']??0)*20;
        $sb=$b['vol']*(strpos($b['intent'],'informational')!==false||strpos($b['intent'],'info')!==false?1.5:1)-($b['kd']??0)*20;
        return $sb<=>$sa; });
    return $rows; }
// The blog title cache doubles as the cornerstone flag store — read ONCE, memoized, so every blog_article_prompt() call is cheap.
function blog_cornerstones(){ static $c=null; if($c===null){ $c=json_decode((string)get_option('wcm_blog_cornerstones'),true); if(!is_array($c)) $c=[]; } return $c; }
// Always-live, non-blog link targets (shop, categories, products, contact, homepage). Memoized — these never depend on the schedule.
function blog_static_pool(){ static $p=null; if($p!==null) return $p; $raw=[];
    $raw[]=['url'=>shop_url(),'name'=>'our full catalog','money'=>1,'kind'=>'shop']; $raw[]=['url'=>home_url('/'),'name'=>brand(),'kind'=>'home'];
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'number'=>20]) as $t){ if(is_wp_error($t)||strtolower($t->slug)==='uncategorized') continue; $u=get_term_link($t); if(!is_wp_error($u)) $raw[]=['url'=>$u,'name'=>humanize_anchor($t->name),'money'=>1,'kind'=>'category']; }
    foreach(get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>30,'orderby'=>'ID','order'=>'ASC']) as $po){ $raw[]=['url'=>get_permalink($po),'name'=>humanize_anchor(get_the_title($po)),'money'=>1,'kind'=>'product']; }   // full objects prime the post cache -> get_permalink/get_the_title are cache hits
    $cp=get_page_by_path('contact-us'); if($cp) $raw[]=['url'=>get_permalink($cp->ID),'name'=>'contact us','kind'=>'page'];
    $seen=[]; $p=[]; foreach($raw as $r){ $u=site_link($r['url']); if(!$u||is_wp_error($u)||isset($seen[$u])) continue; $seen[$u]=1; $p[]=['url'=>$u,'name'=>$r['name'],'money'=>!empty($r['money']),'kind'=>$r['kind']??'page']; } return $p; }   // 'kind' = product|category|shop|home|page — lets the menu PRIORITISE products over categories/shop
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
    $static=blog_static_pool();
    $products=array_values(array_filter($static,fn($r)=>($r['kind']??'')==='product'));   // PRODUCTS = the priority (specific money pages that rank + convert)
    $cats=array_values(array_filter($static,fn($r)=>($r['kind']??'')==='category'));       // categories = 1 only
    $fallback=array_values(array_filter($static,fn($r)=>!in_array($r['kind']??'',['product','category'],true)));   // shop / home / contact — last resort
    $np=count($posts); $nprod=count($products); $ncat=count($cats); $k=min((int)BLOG_INTERNAL_LINKS+1,$np+count($static)); if($k<=0) return [];
    $out=[]; $seen=[]; $push=function($x)use(&$out,&$seen,$k){ if(count($out)>=$k||!is_array($x)||isset($seen[$x['url']])) return; $seen[$x['url']]=1; $out[]=['url'=>$x['url'],'name'=>$x['name']]; };
    $wantProd=min($nprod,max(2,$k-3));   // PRODUCTS get the majority — reserve ~3 slots for one category + up to two siblings
    for($i=0,$c=0;$i<$nprod && $c<$wantProd;$i++){ $b=count($out); $push($products[($idx*3+$i)%$nprod]); if(count($out)>$b) $c++; }   // rotate so different posts feature different products
    if($ncat) $push($cats[$idx%$ncat]);   // exactly ONE category (rotated by post index)
    for($i=$np-1,$c=0;$i>=0 && $c<2;$i--){ $b=count($out); $push($posts[$i]); if(count($out)>$b) $c++; }   // 1-2 newest sibling posts for topical clustering
    foreach($products as $x){ if(count($out)>=$k) break; $push($x); }                 // fill leftover slots with MORE products first,
    foreach($posts as $x){ if(count($out)>=$k) break; $push($x); }                     // then more siblings,
    foreach(array_merge($cats,$fallback) as $x){ if(count($out)>=$k) break; $push($x); }   // then extra categories / shop / home as a last resort
    return $out; }
// Deterministic SAFETY NET: if the model didn't weave the full mix in-context, top up ONLY the MISSING part of the target
// mix (products-heavy, exactly 1 category, up to 2 EARLIER articles). In-context model links are the SEO-best form; this
// bottom list just fills what's absent so a post is never left short of products / category / earlier-article links.
function blog_topup_links($html,$target,$cut){ $target=(int)$target; if($target<=0) return $html;
    $static=blog_static_pool();
    $products=array_values(array_filter($static,fn($r)=>($r['kind']??'')==='product'));
    $cats=array_values(array_filter($static,fn($r)=>($r['kind']??'')==='category'));
    $arts=[]; foreach(blog_known_posts() as $b){ if(strcmp((string)$b['date'],(string)$cut)<=0) $arts[]=['url'=>$b['url'],'name'=>$b['name'],'date'=>$b['date']]; } usort($arts,fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));   // EARLIER posts only, sorted NEWEST-first (usort, matching the menu) — never a not-yet-published one, so no 404
    $tArt=min($target>=6?2:1,count($arts)); $tCat=1; $tProd=max(1,$target-$tCat-$tArt);   // target mix: products-heavy, 1 category, up to 2 earlier articles
    $have=[]; foreach(existing_hrefs($html) as $u){ $have[blog_href_key($u)]=1; }
    $cnt=function($list)use($have){ $n=0; foreach($list as $r){ if(isset($have[blog_href_key($r['url'])])) $n++; } return $n; };
    $nP=max(0,$tProd-$cnt($products)); $nC=max(0,$tCat-$cnt($cats)); $nA=max(0,$tArt-$cnt($arts));
    if($nP+$nC+$nA<=0) return $html;   // the model already wove the full mix in-context (the ideal, strongest-SEO outcome) -> add nothing
    $li=''; $fill=function($list,$n)use(&$li,&$have){ foreach($list as $r){ if($n<=0) break; $k=blog_href_key($r['url']); if(isset($have[$k])) continue; $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; $have[$k]=1; $n--; } };
    $fill($products,$nP); $fill($cats,$nC); $fill($arts,$nA);
    return $li==='' ? $html : $html."\n<h2>".esc(ui_t('explore_more'))."</h2>\n<ul>$li</ul>"; }
function blog_article_prompt($ti,$idx,$cut){ $links=''; foreach(blog_link_menu($idx,$cut) as $m){ $links.='  - '.$m['name'].': '.$m['url']."\n"; }
    $is_cs=in_array($ti,blog_cornerstones(),true);   // this post's title is one of the first BLOG_CORNERSTONE_COUNT cached in wcm_blog_cornerstones
    $words=$is_cs?BLOG_CORNERSTONE_WORDS:BLOG_WORDS;
    $csclause=$is_cs?"CORNERSTONE ARTICLE — this is a long-form linkable-asset guide, not a normal post: give genuinely comprehensive, definitive coverage of the topic. Use a clear logical structure with MULTIPLE <h2> sections and supporting <h3> subsections. Include at least one comparison or summary <table>. Include a THOROUGH FAQ (more than the usual 2-3 questions). Go deep with concrete specifics and step-by-step detail. Give it an original angle that makes it worth citing and linking to. NEVER invent statistics, studies, testimonials or data — build the depth from genuine domain knowledge, never fabricated numbers.\n":'';
    $ext = BLOG_EXTERNAL_LINK
        ? "OUTBOUND LINK (REQUIRED — EVERY article must have exactly ONE): add ONE in-context outbound link to an authoritative NON-commercial source. Best: a .gov/.mil/.edu/.int page (nih.gov, pubmed.ncbi.nlm.nih.gov, fda.gov, cdc.gov, clinicaltrials.gov, medlineplus.gov, who.int). If no government/edu page fits, link the most relevant en.wikipedia.org article — there is almost ALWAYS a relevant Wikipedia article, so use it rather than skipping. NEVER link to a store, brand, blog, competitor or any commercial (.com/.org business) site — those are automatically stripped out and vanish. Do NOT skip this link.\n"
        : "Do not add any outbound external links.\n";
    $catclause=''; $catkey='';
    if(BLOG_AUTO_CATEGORIES){ $bc=json_decode((string)get_option('wcm_blog_cats'),true); if(is_array($bc)&&$bc){ $catclause="CATEGORY: pick EXACTLY ONE category from this list that best fits the article — do NOT invent a new one: ".implode(', ',$bc).".\n"; $catkey=",\"category\":\"exactly one from the list above\""; } }
    return "Write a ".$words." word SEO blog article titled \"$ti\" for ".brand().", which sells ".STORE_NICHE.". Write for real buyers and to rank in Google. "
        .voice_rules().compliance_clause().$csclause
        ."SEO — follow ALL of this: choose ONE primary keyword this article targets (the core topic of the title). Put that keyword in the <h1>, in the FIRST sentence of the intro, in at least one <h2>, and naturally 3-5 more times in the body (no keyword stuffing). Return that EXACT phrase as focus_keyword. meta_title: 55-60 chars, LEADS with the primary keyword, compelling to click. meta_description: 150-160 chars, includes the keyword and a reason to click. Answer real buyer questions and cover the topic thoroughly so it earns the ranking.\n"
        ."STRUCTURE: valid HTML only — ONE <h1> (the title), then <h2>/<h3> sections, short scannable paragraphs, at least one <ul> list, and a short FAQ of 2-3 <h3> questions with answers.\n".$catclause
        ."INTERNAL LINKS (IMPORTANT): do NOT skip any type. Weave links INSIDE your sentences (in-context, with descriptive keyword anchor text, NOT a list at the end, NEVER 'click here'). Anchor text must be natural, correctly-spaced words (never run together, never camelCase, never a slug or the raw URL). Use ONLY the EXACT URLs below. Include, in this order of priority: 3 links to relevant PRODUCTS, 1 link to a relevant CATEGORY, and 2 links to our EARLIER blog posts from the list (link to a blog post ONLY if one is listed below; the earliest articles simply have fewer to link to). Products first; at most ONE category; never more blog posts than products:\n".($links?:"  (none available yet)\n")
        .$ext.html_quote_rule()
        ."Return JSON: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"".$catkey."}"; }

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
function product_prompt($ctx,$need,$links=[]){ $cur=currency(); $keys=[]; $req='';
    if($need['short']){ $req.="- short_description: marketing HTML, ".words_phrase(SHORT_DESC_WORDS).".\n"; $keys[]='short_description'; }
    if($need['long']){ $req.="- long_description: valid HTML, ".words_phrase(LONG_DESC_WORDS).", with <h2> Overview, <h2> Key Features (a <ul>), <h2> Specifications (a small <table>), <h2> FAQ (3 <h3> question + <p> answer), closing CTA.\n"; $keys[]='long_description'; }
    if($need['meta']){ $req.="- meta_title (<=60 chars, end ' | ".brand()."'), meta_description (<=155 chars, focus keyword), focus_keyword.\n"; array_push($keys,'meta_title','meta_description','focus_keyword'); }
    if($need['tags']){ $req.="- tags: 3-5 short relevant tags.\n"; $keys[]='tags'; }
    if($need['price']){ if(PRODUCT_TYPE==='variable'){ $req.="- attribute: the measurement dimension with unit (e.g. 'Dosage (mg)', 'Volume (L)', 'Quantity'). variations: 2-5 {label,price} where label is a value in that unit (e.g. '10mg','5L','2') and price is a plain number in $cur.\n"; array_push($keys,'attribute','variations'); } else { $req.="- price: realistic AVERAGE MARKET PRICE, plain number in $cur.\n"; $keys[]='price'; } }
    if($need['unit']){ $req.="- unit: what ONE purchase includes, with measurement (e.g. 'per 10 mg vial', 'per 5 L container', 'each (1 unit)').\n"; $keys[]='unit'; }
    $sales=SALES_ORIENTED?"Sales-oriented: weave in natural buy/shop/for-sale phrasing and the focus keyword early.":"Informative and helpful.";
    $linkclause='';
    if(DO_INTERLINKS && !empty($need['long']) && $links){ $ltxt=''; foreach($links as $l){ $ltxt.='  - '.$l['name'].': '.$l['url']."\n"; }
        $linkclause="INTERNAL LINKS: weave 2-3 of these internal links INTO the sentences of the long_description (in-context, not a list at the end, never 'click here'), using ONLY these EXACT URLs. The anchor text must be natural, correctly-spaced words that describe the destination (e.g. 'our whey protein isolate'). It must never run together, never use camelCase, and never be a slug or the raw URL:\n$ltxt"; }
    return "You are an expert e-commerce SEO copywriter for ".brand().", selling ".STORE_NICHE.". $sales\n"
        ."Use this product's real context:\nTITLE: {$ctx['title']}\nCATEGORY: {$ctx['cat']}\nEXISTING SHORT: {$ctx['short']}\nEXISTING LONG: {$ctx['long']}\nCURRENCY: $cur\n"
        .reference_context()
        .voice_rules().compliance_clause().$req.$linkclause.html_quote_rule()
        ."Return ONE JSON object with exactly these keys: ".implode(', ',$keys)."."; }

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------
function find_or_create_cat($name){ $t=get_term_by('name',$name,'product_cat'); if($t&&!is_wp_error($t)) return (int)$t->term_id;
    $r=wp_insert_term($name,'product_cat'); return is_wp_error($r)?0:(int)$r['term_id']; }
function ai_category_names($titles){ $list=implode("\n",array_map(fn($t)=>'  - '.$t,array_slice($titles,0,400)));
    [$d]=ai_json("You are a merchandising expert for ".brand()." selling ".STORE_NICHE.".\nProducts:\n$list\n".reference_context()
        ."Propose ".AUTO_CATEGORY_COUNT." FLAT top-level e-commerce categories that group these by USE/type, using "
        ."SEO-friendly search-term names. Do NOT make one category per product. ".lang_rule()."Return ONE JSON object: {\"categories\":[\"...\"]} — no comments, no trailing commas.");
    return is_array($d)&&!empty($d['categories'])?array_values(array_filter(array_map('trim',(array)$d['categories']))):[]; }
function ai_map_chunk($chunk,$cats){ $lines=''; foreach($chunk as $c){ $lines.="  {$c['id']} | {$c['title']} | ".excerpt($c['short'],80)."\n"; }
    $cl=implode(', ',$cats);
    [$d]=ai_json("Assign each product to exactly ONE category from this list: [$cl].\nProducts (id | title | note):\n$lines\n"
        ."Return ONE JSON object: {\"map\":[{\"id\":123,\"category\":\"Exact Category Name\"}]} — use the exact category names, no comments, no trailing commas.");
    $out=[]; if(is_array($d)) foreach(($d['map']??[]) as $m){ if(!empty($m['id'])&&!empty($m['category'])) $out[(int)$m['id']]=trim((string)$m['category']); } return $out; }

// ---------------------------------------------------------------------------
// Design system: per-site persona (typography/radius/cards/spacing) + the CSS
// that renders it. Colors always come from site_palette() (logo-driven, cached
// in 'wcm_palette') — this only adds the LOOK on top, so 10 fresh installs of
// the same niche don't render an identical homepage/contact page.
// ---------------------------------------------------------------------------
// 7 tasteful, restrained personas. Only fonts from Flatsome's own font list are used.
function design_personas(){ return [
    ['key'=>'clean_commerce','heading_font'=>'Poppins','heading_weight'=>'600','body_font'=>'Open Sans','body_weight'=>'400',
        'case'=>'none','radius_btn'=>8,'radius_card'=>10,'card'=>'elevated','space'=>'normal','eyebrow'=>'pill','mask'=>'','hero'=>'A'],
    ['key'=>'editorial_serif','heading_font'=>'Playfair Display','heading_weight'=>'600','body_font'=>'Lora','body_weight'=>'400',
        'case'=>'none','radius_btn'=>2,'radius_card'=>2,'card'=>'hairline','space'=>'relaxed','eyebrow'=>'line','mask'=>'angled','hero'=>'D'],
    ['key'=>'soft_modern','heading_font'=>'Quicksand','heading_weight'=>'700','body_font'=>'Nunito Sans','body_weight'=>'400',
        'case'=>'none','radius_btn'=>999,'radius_card'=>18,'card'=>'tinted','space'=>'normal','eyebrow'=>'pill','mask'=>'arrow-large','hero'=>'A'],
    ['key'=>'bold_retail','heading_font'=>'Oswald','heading_weight'=>'600','body_font'=>'Work Sans','body_weight'=>'400',
        'case'=>'upper','radius_btn'=>3,'radius_card'=>4,'card'=>'elevated','space'=>'tight','eyebrow'=>'block','mask'=>'arrow','hero'=>'C'],
    ['key'=>'classic_trust','heading_font'=>'Merriweather','heading_weight'=>'700','body_font'=>'Source Sans Pro','body_weight'=>'400',
        'case'=>'none','radius_btn'=>6,'radius_card'=>6,'card'=>'hairline','space'=>'normal','eyebrow'=>'line','mask'=>'','hero'=>'D'],
    ['key'=>'premium_luxe','heading_font'=>'Cormorant Garamond','heading_weight'=>'600','body_font'=>'EB Garamond','body_weight'=>'400',
        'case'=>'upper','radius_btn'=>2,'radius_card'=>2,'card'=>'hairline','space'=>'relaxed','eyebrow'=>'line','mask'=>'angled-large','hero'=>'B'],
    ['key'=>'technical','heading_font'=>'Rubik','heading_weight'=>'600','body_font'=>'Barlow','body_weight'=>'400',
        'case'=>'none','radius_btn'=>6,'radius_card'=>8,'card'=>'hairline','space'=>'tight','eyebrow'=>'pill','mask'=>'angled-right','hero'=>'C'],
]; }   // 'mask' is only ever 'angled*'/'arrow*' (fixed-pixel clip-path corner notches, safe at any content height) or '' — never
// 'circle' (a percentage clip-path: on a tall narrow mobile section with long hero text it clips most of the content away)
// SINGLE SOURCE OF TRUTH for the site's persona — same per-site seed as build_flatsome (wcm_home_seed XOR crc32(site_host())),
// so it's stable across re-runs of the SAME site but differs site to site. DYNAMIC_HOMEPAGE=false -> always persona 0 / variant 0
// (static mode = one fixed look, same on every site, matching build_flatsome's own on/off semantics).
function site_design(){ static $d=null; if($d!==null) return $d;
    $personas=design_personas();
    $seed=(int)get_option('wcm_home_seed'); if($seed<1){ $seed=mt_rand(1,2000000000); update_option('wcm_home_seed',$seed,false); }
    $eff=DYNAMIC_HOMEPAGE?(($seed^(int)crc32(site_host()))&0x7fffffff):0;
    $pi=DYNAMIC_HOMEPAGE?($eff%count($personas)):0;
    $p=$personas[$pi]; $p['idx']=$pi; $p['variant']=DYNAMIC_HOMEPAGE?(intdiv($eff,count($personas))%6):0;
    return $d=$p; }
// pick one of $n options deterministically from the site's design variant (stable per site, varies site to site in dynamic mode)
function design_variant($n){ $n=(int)$n; if($n<=0) return 0; return site_design()['variant']%$n; }
// make AI/translated text safe INSIDE a shortcode attribute value: esc() first (HTML-entity the usual suspects), then
// entity-escape square brackets too, since a literal '[' or ']' inside an attribute (e.g. a button's text="..." or an
// accordion item's title="...") can be misread as the start/end of a nested shortcode and break the whole tag.
function esc_attr_sc($s){ return str_replace(['[',']'],['&#91;','&#93;'],esc((string)$s)); }
// small line-art icons for CSS mask-image (never raw SVG in post content — KSES strips that; this lives in a theme-mod CSS block, which isn't filtered)
function wcm_icon_datauri($name){
    $paths=[
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M4 6l8 7 8-7"/>',
        'phone'=>'<path d="M5 4c-1 0-1.5.7-1.4 1.6C4.4 13 10.9 19.6 18.4 20.4c.9.1 1.6-.4 1.6-1.4v-2.6l-4-1.6-1.7 1.7c-2-1-4-3-5-5L11 9 9.4 5 5 4z"/>',
        'pin'=>'<path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
        'check'=>'<path d="M4 12l5 5L20 6"/>',
    ];
    $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name]??$paths['check']).'</svg>';
    return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg); }
// build the persona/palette-driven CSS block (target under ~14KB). SCOPED entirely under ".wcm" (the class every generated
// section carries) so shop/product/checkout/blog pages — which never carry that class — are never touched, and NO
// font-family rule is emitted anywhere: fonts come ONLY from the type_* theme mods Flatsome itself applies (see
// wcm_apply_design()); forcing a font-family here would switch headings to a font Flatsome never actually loaded.
// Never throws — a design failure must never stop pages writing.
function wcm_design_css(){
    try {
        $pal=site_palette(); $d=site_design();
        $rb=max(0,(int)$d['radius_btn']); $rc=max(0,(int)$d['radius_card']);
        $space=['tight'=>'40px','normal'=>'60px','relaxed'=>'84px'][$d['space']]??'60px';
        $case=$d['case']==='upper'?'uppercase':'none'; $track=$d['case']==='upper'?'.04em':'normal';
        $hw=esc($d['heading_weight']);
        if($d['card']==='elevated') $card_css="background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.08);border:none;";
        elseif($d['card']==='tinted') $card_css="background:var(--wcm-light,#f5f6f7);box-shadow:none;border:none;";
        else $card_css="background:#fff;box-shadow:none;border:1px solid rgba(0,0,0,.10);";
        if($d['eyebrow']==='pill') $eyebrow_css="display:inline-block;background:var(--wcm-light,#f5f6f7);color:var(--wcm-primary,#446084);padding:6px 16px;border-radius:999px;";
        elseif($d['eyebrow']==='block') $eyebrow_css="display:inline-block;background:var(--wcm-dark,#111827);color:#fff;padding:5px 12px;border-radius:2px;";
        else $eyebrow_css="display:inline-block;border-bottom:2px solid var(--wcm-primary,#446084);padding-bottom:4px;color:var(--wcm-primary,#446084);";
        $icMail=wcm_icon_datauri('mail'); $icPhone=wcm_icon_datauri('phone'); $icPin=wcm_icon_datauri('pin'); $icClock=wcm_icon_datauri('clock'); $icCheck=wcm_icon_datauri('check');
        $css = ".wcm{--wcm-primary:{$pal['primary']};--wcm-secondary:{$pal['secondary']};--wcm-accent:{$pal['accent']};--wcm-dark:{$pal['dark']};--wcm-light:{$pal['light']};--wcm-text:{$pal['text']};--wcm-radius-btn:{$rb}px;--wcm-radius-card:{$rc}px;--wcm-space:$space;max-width:100%;}\n"
            .".wcm h1,.wcm h2,.wcm h3,.wcm h4,.wcm h5,.wcm h6{font-weight:$hw;text-transform:$case;letter-spacing:$track;}\n"
            .".wcm .button{border-radius:var(--wcm-radius-btn,8px)!important;}\n"
            .".wcm .product-small .box-image{border-radius:var(--wcm-radius-card,10px);overflow:hidden;}\n"
            .".wcm .product-small .box-image img{transition:transform .35s ease;}\n"
            .".wcm .product-small:hover .box-image img{transform:scale(1.05);}\n"
            .".wcm .wcm-hero{position:relative;}\n"
            .".wcm.wcm-hero-banner .overlay{background:linear-gradient(100deg,rgba(0,0,0,.8),rgba(0,0,0,.18))!important;}\n"
            .".wcm .wcm-eyebrow{font-size:.8em;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;$eyebrow_css}\n"
            .".wcm .wcm-lead{font-size:1.15em;line-height:1.6;}\n"
            .".wcm .wcm-hero-ctas{display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:inherit;margin-top:22px;}\n"
            .".wcm .wcm-hero-strip{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:34px;}\n"
            .".wcm .wcm-hero-strip img{width:74px;height:74px;object-fit:cover;border-radius:var(--wcm-radius-card,10px);box-shadow:0 8px 20px rgba(0,0,0,.25);}\n"
            .".wcm .wcm-trust{display:flex;flex-wrap:wrap;justify-content:center;gap:14px 32px;padding:20px 0;}\n"
            .".wcm .wcm-trust-item{display:flex;align-items:center;gap:8px;font-size:.92em;font-weight:600;color:var(--wcm-text,#1c1c1c);}\n"
            .".wcm .wcm-trust-item:before{content:'';width:16px;height:16px;flex:0 0 16px;background-color:var(--wcm-primary,#446084);-webkit-mask:url('$icCheck') center/contain no-repeat;mask:url('$icCheck') center/contain no-repeat;}\n"
            .".wcm .wcm-cat-tile{display:block;text-align:center;padding:28px 14px;border-radius:var(--wcm-radius-card,10px);background:var(--wcm-light,#f5f6f7);font-weight:$hw;text-transform:$case;text-decoration:none;color:var(--wcm-text,#1c1c1c);transition:transform .2s ease,box-shadow .2s ease;}\n"
            .".wcm .wcm-cat-tile:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(0,0,0,.10);color:var(--wcm-primary,#446084);}\n"
            .".wcm .wcm-card{border-radius:var(--wcm-radius-card,10px);padding:30px 26px;$card_css}\n"
            .".wcm .wcm-faq-card{margin-bottom:16px;}\n"
            .".wcm .wcm-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}\n"
            .".wcm .wcm-steps{counter-reset:wcmstep;}\n"
            .".wcm .wcm-step-num{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:var(--wcm-primary,#446084);color:#fff;font-weight:700;margin-bottom:14px;}\n"
            .".wcm .wcm-cta-band{text-align:center;}\n"
            .".wcm .wcm-contact-card{border-radius:var(--wcm-radius-card,10px);padding:28px 24px;background:#fff;box-shadow:0 14px 40px rgba(0,0,0,.10);}\n"
            .".wcm .wcm-contact-icon{width:26px;height:26px;display:inline-block;background-color:var(--wcm-primary,#446084);margin-bottom:10px;}\n"
            .".wcm .wcm-contact-icon-mail{-webkit-mask:url('$icMail') center/contain no-repeat;mask:url('$icMail') center/contain no-repeat;}\n"
            .".wcm .wcm-contact-icon-phone{-webkit-mask:url('$icPhone') center/contain no-repeat;mask:url('$icPhone') center/contain no-repeat;}\n"
            .".wcm .wcm-contact-icon-pin{-webkit-mask:url('$icPin') center/contain no-repeat;mask:url('$icPin') center/contain no-repeat;}\n"
            .".wcm .wcm-contact-icon-clock{-webkit-mask:url('$icClock') center/contain no-repeat;mask:url('$icClock') center/contain no-repeat;}\n"
            .".wcm.wcm-contact-hero{background:linear-gradient(135deg,var(--wcm-primary,#446084),var(--wcm-dark,#111827));color:#fff;padding-bottom:120px;}\n"
            .".wcm.wcm-contact-hero h1,.wcm.wcm-contact-hero p{color:#fff;}\n"
            .".wcm .wcm-contact-cards-overlap{margin-top:-86px;position:relative;z-index:2;}\n"
            .".wcm .wcm-contact-split-dark{background:var(--wcm-dark,#111827);color:#fff;border-radius:var(--wcm-radius-card,10px);padding:38px;}\n"
            .".wcm .wcm-contact-split-dark a,.wcm .wcm-contact-split-dark h1,.wcm .wcm-contact-split-dark h2,.wcm .wcm-contact-split-dark h3,.wcm .wcm-contact-split-dark h4,.wcm .wcm-contact-split-dark p,.wcm .wcm-contact-split-dark strong{color:#fff;}\n"
            .".wcm .wcm-contact-split-dark a{text-decoration:underline;}\n"
            .".wcm .wcm-contact-tile{border:1px solid rgba(0,0,0,.12);border-radius:var(--wcm-radius-card,10px);padding:24px;text-align:center;}\n"
            .".wcm .wcm-contact-chip{display:inline-block;padding:7px 16px;margin:4px;border:1px solid rgba(0,0,0,.15);border-radius:999px;font-size:.85em;}\n"
            .".wcm .wcm-prose{max-width:760px;margin:0 auto;line-height:1.75;}\n"
            .".wcm .wcm-prose h2{margin-top:1.5em;}\n"
            .".wcm .wcm-cta-aside{background:var(--wcm-light,#f5f6f7);border-radius:var(--wcm-radius-card,10px);padding:26px;text-align:center;margin-top:44px;}\n"
            .".wcm a:focus-visible,.wcm button:focus-visible,.wcm .button:focus-visible{outline:2px solid var(--wcm-accent,#c0392b);outline-offset:2px;}\n"
            ."@media (prefers-reduced-motion: reduce){.wcm *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;}}\n"
            ."@media (max-width:480px){.wcm .wcm-hero h1{font-size:1.6em;}.wcm .wcm-trust{gap:10px 20px;}.wcm .wcm-contact-cards-overlap{margin-top:-50px;}.wcm .wcm-hero-ctas{justify-content:center;}}\n";
        return $css;
    } catch (\Throwable $e) { return ''; } }   // never fatal — a design failure must never stop pages from being written
// write the design CSS into the Flatsome theme mod (theme mods are NOT KSES-filtered, unlike post content), preserving any
// other CSS the owner already has there. Idempotent: re-running replaces the marked block instead of duplicating it, and
// repairs an orphaned start marker (owner hand-edited the CSS and lost the end marker) instead of appending a 2nd block.
// Also sets the persona's fonts as Flatsome type_* theme mods (gated exactly like the rest of branding).
function wcm_apply_design(){
    if(!is_flatsome()) return;
    try {
        $css=wcm_design_css(); if($css===''){ return; }
        $cur=(string)get_theme_mod('html_custom_css');
        $block="/* wcm-design:start */\n".$css."/* wcm-design:end */";
        $startPos=strpos($cur,'/* wcm-design:start */');
        if($startPos===false){ $new=rtrim($cur)."\n\n".$block; }   // no existing block -> append
        else { $endPos=strpos($cur,'/* wcm-design:end */',$startPos);
            $new=($endPos!==false)
                ? preg_replace_callback('/\/\* wcm-design:start \*\/.*?\/\* wcm-design:end \*\//s',function() use($block){ return $block; },$cur,1)
                : rtrim(str_replace('/* wcm-design:start */','',$cur))."\n\n".$block; }   // orphaned start marker (no matching end) -> remove just that stray marker text, keep ALL owner CSS before AND after it, then append a fresh complete block
        if($new===null) return;   // regex failure -> leave the owner's existing CSS untouched rather than write a broken value
        set_theme_mod('html_custom_css',$new);
        if(DO_BRANDING && (BRANDING_OVERWRITE || !get_theme_mod('type_headings'))){
            $d=site_design();
            set_theme_mod('type_headings',['font-family'=>$d['heading_font'],'variant'=>$d['heading_weight']]);
            set_theme_mod('type_texts',['font-family'=>$d['body_font'],'variant'=>$d['body_weight']]);
            set_theme_mod('type_nav',['font-family'=>$d['heading_font'],'variant'=>$d['heading_weight']]);
            set_theme_mod('type_alt',['font-family'=>$d['heading_font'],'variant'=>$d['heading_weight']]);
        }
    } catch (\Throwable $e) { /* a design failure must never stop pages from being written */ } }

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------
function find_or_create_page($slug,$title){ $p=get_page_by_path($slug); if($p) return (int)$p->ID;
    return (int)wp_insert_post(['post_type'=>'page','post_name'=>$slug,'post_title'=>$title,'post_status'=>PUBLISH_STATE,'post_content'=>'']); }
// true when a page with this id/content would actually be (re)written this run — i.e. it isn't already marked done, and
// either it has no content yet or overwriting is allowed. Mirrors the done/overwrite checks each page block makes itself.
function wcm_page_pending($pid,$content,$overwrite){ if(!$pid) return true;
    if(get_post_meta($pid,'_wcm_page_done',true)) return false;
    return trim((string)$content)==='' || $overwrite; }
// clear the page template ONLY when it's still our own 'page-blank.php' value — never touch an owner-chosen template
// (a non-Flatsome theme's own full-width template, or e.g. page-transparent-header.php on a Flatsome html-format page).
function wcm_clear_our_template($pid){ if($pid && get_post_meta($pid,'_wp_page_template',true)==='page-blank.php') delete_post_meta($pid,'_wp_page_template'); }
// Flatsome info/legal pages: move the content's own <h1> (or $title, if it has none) into a page-hero band, put the rest
// in a readable prose column, and add a small "questions? contact us" aside. Never fatal — falls back to the plain $body.
function wcm_page_hero_wrap($html,$title){
    try {
        $h1=''; $body=$html;
        if(preg_match('/<h1[^>]*>(.*?)<\/h1>/is',$html,$m)){ $h1=trim(wp_strip_all_tags($m[1])); $body=preg_replace('/<h1[^>]*>.*?<\/h1>/is','',$html,1); }
        if($h1==='') $h1=$title;
        $body=demote_h1($body);   // guard: any OTHER <h1> left in the body (the prompt asks for only one) becomes an <h2>
        $contact=get_page_by_path('contact-us'); $curl=($contact && $contact->post_status==='publish')?get_permalink($contact->ID):'';   // never link to a not-yet-published contact page (would 404)
        $varLight='var(--wcm-light,#f5f6f7)';
        $aside=$curl?"[section padding=\"0px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n<div class='wcm-cta-aside'>\n<h3>".esc(ui_t('questions_contact_us'))."</h3>\n<p>".esc(ui_t('still_have_questions'))."</p>\n[button text=\"".esc_attr_sc(ui_t('contact'))."\" link=\"".esc_url($curl)."\"]\n</div>\n[/col]\n[/row]\n[/section]\n":'';
        // the band's own bg_color spans the FULL section width edge to edge (same technique the homepage hero uses); a nested
        // div background would only fill the boxed row/col container, rendering as a small tinted box, not a full-bleed band.
        return "[section label=\"Page Hero\" padding=\"70px\" bg_color=\"$varLight\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"10\" span__sm=\"12\"]\n<h1 style='text-align:center;margin:0'>".esc($h1)."</h1>\n[/col]\n[/row]\n[/section]\n"
            ."[section padding=\"55px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\" class=\"wcm-prose\"]\n".$body."\n[/col]\n[/row]\n[/section]\n".$aside;
    } catch (\Throwable $e) { return $html; } }
function write_page_via_ai($slug,$title,$prompt){ global $pages_incomplete,$progress; $pid=find_or_create_page($slug,$title); if(!$pid) return;
    if(get_post_meta($pid,'_wcm_page_done',true)){ out("   [skip] $title (already done; RESET_PROGRESS to redo)",'#888'); return; }
    $cur=trim((string)get_post_field('post_content',$pid)); if($cur!=='' && !OVERWRITE_PAGES){ out("   [skip] $title already has content (OVERWRITE_PAGES=false)",'#888'); return; }
    [$d,$err]=ai_json($prompt); if(!$d||empty($d['content'])){ out("   [skip] $title — ".($err?:'no content'),'#f66'); $pages_incomplete=true; return; }
    $c=dedash((string)$d['content']); if(REMOVE_FOREIGN_LINKS) $c=strip_foreign_links($c);   // strip off-site/competitor links from AI pages too (the one-shot REMOVE_FOREIGN_LINKS phase runs BEFORE these pages exist, so it never reaches them)
    $c=append_disclaimer(strip_future_internal_links($c,current_time('mysql')));   // a published page may only link to already-live pages/posts — strip any hallucinated link to a not-yet-published post
    if(is_flatsome()) $c=wcm_page_hero_wrap($c,$title);
    wp_update_post(['ID'=>$pid,'post_content'=>$c]);
    if(!empty($d['meta_title'])) update_post_meta($pid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
    if(!empty($d['meta_description'])) update_post_meta($pid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
    if(!empty($d['focus_keyword'])) update_post_meta($pid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
    if(is_flatsome()) update_post_meta($pid,'_wp_page_template','page-blank.php'); else wcm_clear_our_template($pid);   // full width so the page-hero band isn't squeezed into the theme's narrow default container; only clear OUR OWN stale value, never an owner-chosen template
    update_post_meta($pid,'_wcm_page_done',1); $progress=true; out("   [ok] $title",'#6f6'); }
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

function published_product_count(){ static $n=null; if($n===null){ $c=wp_count_posts('product'); $n=$c?(int)($c->publish??0):0; } return $n; }
// span (and, when it matters, a tablet span__md override) for a row of equal cards, chosen by ITEM COUNT so there's never
// an orphan card wrapping alone onto its own row: 4 items -> 4 across desktop / 2x2 tablet, 3 -> 3 across, 2 -> 2 across.
function wcm_card_span($n){ $n=(int)$n;
    if($n>=4) return ['3','6']; if($n===3) return ['4','']; if($n===2) return ['6','']; return ['12','']; }
// --- Flatsome homepage assembler ---
// $images = ['banner'=>attId, 'sections'=>[attId,...]] — optional AI homepage images; defaults to [] so nothing breaks when unset.
function build_flatsome($f,$cats,$prods,$images=[]){ $shop=shop_url(); $btn=esc_attr_sc($f['cta_button']??ui_t('shop_now')); $o='';
    $bannerId=(int)($images['banner']??0);
    $secQueue=is_array($images['sections']??null)?array_values(array_filter(array_map('intval',$images['sections']))):[];
    $design=site_design(); $mask=(string)$design['mask'];
    $varLight='var(--wcm-light,#f5f6f7)'; $varDark='var(--wcm-dark,#111827)';   // fallback so a section is never white/transparent-on-transparent if the CSS block failed to write

    // PER-SITE SEED — stable across re-runs of the SAME site, but different site to site, so 10 fresh installs don't look identical.
    // CLONE-SAFE: the effective seed the LCG runs on XORs the stored seed with a hash of THIS host, so a database cloned onto a
    // new domain (same stored wcm_home_seed row) still lands on a different layout, while the SAME site stays stable on re-runs.
    // Self-contained LCG (never touches global mt_rand state). Only takes effect in dynamic mode.
    $dynamic=DYNAMIC_HOMEPAGE;
    $seed=(int)get_option('wcm_home_seed'); if($seed<1){ $seed=mt_rand(1,2000000000); update_option('wcm_home_seed',$seed,false); }
    $eff=$dynamic?($seed^(int)crc32(site_host())):$seed;
    $rng=function() use(&$eff){ $eff=($eff*1103515245+12345)&0x7fffffff; return $eff; };
    $pick=function($a) use($rng){ return $a[$rng()%count($a)]; };

    $h1=esc($f['hero_headline']??brand()); $introTxt=esc($f['hero_intro']??'');
    $eyebrowRaw=$f['hero_eyebrow']??''; $eyebrow=trim((string)(is_array($eyebrowRaw)?($eyebrowRaw['title']??$eyebrowRaw['text']??''):$eyebrowRaw));
    $eyebrowHtml=$eyebrow!==''?"<span class='wcm-eyebrow'>".esc($eyebrow)."</span><br>":'';
    $catsValid=array_values(array_filter((array)$cats,fn($c)=>!empty($c['url'])));   // same emptiness test the categories block itself uses below
    if($catsValid){ $secUrl='#wcm-categories'; $secLabel=esc_attr_sc(ui_t('browse_categories')); }
    else { $secUrl=$shop; $secLabel=esc_attr_sc(ui_t('view_all_products')); }
    $primaryBtn="[button text=\"$btn\" size=\"large\" link=\"".esc_url($shop)."\"]";
    $secondaryBtn="[button text=\"$secLabel\" size=\"large\" style=\"outline\" link=\"".esc_url($secUrl)."\"]";
    $ctas="<div class='wcm-hero-ctas'>$primaryBtn$secondaryBtn</div>";

    // TRUST strip — a few short reassurance points right under the hero. Optional trust_points field, else benefits titles
    // (kept OUT of the main body so it's never rendered twice), else why_us_points titles as a last resort.
    $pick_titles=function($arr) { $out=[]; if(is_array($arr)) foreach($arr as $t){ $t=trim((string)(is_array($t)?($t['title']??$t['text']??''):$t)); if($t!=='') $out[]=$t; } return $out; };
    $trust=$pick_titles($f['trust_points']??null);
    if(!$trust) $trust=$pick_titles($f['benefits']??null);
    if(!$trust) $trust=$pick_titles($f['why_us_points']??null);
    $trust=array_slice($trust,0,4);
    $trustHtml=''; if($trust){ $items=''; foreach($trust as $t) $items.="<span class='wcm-trust-item'>".esc($t)."</span>";
        $trustHtml="[section padding=\"0px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"12\"]\n<div class='wcm-trust'>$items</div>\n[/col]\n[/row]\n[/section]\n"; }

    // HERO — always FIRST, always the page's ONLY <h1>. 4 variants, each with a graceful no-image fallback, and B/C/D all
    // use a paid AI banner image when one exists so it's never generated and left unused:
    //  A) split text (left) + image (right) on a light tint — needs a supporting or banner image
    //  B) full-bleed banner image with a strong dark overlay + left-aligned text — needs a banner image
    //  C) centered band, banner as a dark-overlaid background when one exists (else a flat dark band), optional strip of product thumbnails beneath — always available
    //  D) editorial: large heading beside the intro, banner (or a supporting image) as a wide image below — always available
    $heroOpts=['C','D']; if($bannerId) $heroOpts[]='B'; if($bannerId||$secQueue) $heroOpts[]='A';
    if($dynamic){ if(in_array($design['hero'],$heroOpts,true)){ $heroOpts[]=$design['hero']; $heroOpts[]=$design['hero']; } $heroVariant=$pick($heroOpts); }
    else $heroVariant=$bannerId?'B':'C';   // static mode: one fixed layout, the same on every site — but still use a paid banner image when one exists

    if($heroVariant==='A'){
        $himg=$secQueue?(int)array_shift($secQueue):$bannerId;
        $o.="[section label=\"Hero\" padding=\"70px\" bg_color=\"$varLight\" class=\"wcm\"]\n[row h_align=\"center\" v_align=\"middle\"]\n[col span=\"7\" span__sm=\"12\"]\n<div class='wcm-hero'>{$eyebrowHtml}<h1>$h1</h1>\n<p class='wcm-lead'>$introTxt</p>\n$ctas\n</div>\n[/col]\n[col span=\"5\" span__sm=\"12\"]\n[ux_image id=\"$himg\"]\n[/col]\n[/row]\n[/section]\n";
    } elseif($heroVariant==='B'){
        $o.="[ux_banner height=\"580px\" height__sm=\"460px\" class=\"wcm wcm-hero-banner\" bg=\"$bannerId\" bg_overlay=\"rgba(0,0,0,0.6)\"]\n[text_box width=\"55\" width__sm=\"88\" position_x=\"10\" position_y=\"50\" text_align=\"left\" text_color=\"light\"]\n<div class='wcm-hero'>{$eyebrowHtml}<h1 style='font-size:2.6em;line-height:1.1'>$h1</h1>\n<p class='wcm-lead'>$introTxt</p>\n$ctas\n</div>\n[/text_box]\n[/ux_banner]\n";
    } elseif($heroVariant==='D'){
        $wimg=$bannerId?:($secQueue?(int)array_shift($secQueue):0);
        $o.="[section label=\"Hero\" padding=\"70px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"6\" span__sm=\"12\"]\n<div class='wcm-hero'>{$eyebrowHtml}<h1 style='font-size:2.4em;line-height:1.15'>$h1</h1>\n</div>\n[/col]\n[col span=\"6\" span__sm=\"12\"]\n<p class='wcm-lead'>$introTxt</p>\n$ctas\n[/col]\n[/row]\n".($wimg?"[row]\n[col span__sm=\"12\"]\n[ux_image id=\"$wimg\"]\n[/col]\n[/row]\n":'')."[/section]\n";
    } else {   // C — centered band, banner as background when available, optional product-thumbnail strip
        $stripImgs=''; foreach(array_slice((array)$prods,0,5) as $pr){ $pid=(int)($pr['id']??0); if(!$pid) continue; $img=get_the_post_thumbnail_url($pid,'thumbnail'); if(!$img) continue; $stripImgs.='<a href="'.esc_url($pr['url']).'"><img src="'.esc_url($img).'" alt="'.esc($pr['name']).'" loading="lazy"></a>'; }
        $maskAttr=$mask!==''?" mask=\"$mask\"":'';
        $bgAttr=$bannerId?" bg=\"$bannerId\" bg_overlay=\"rgba(0,0,0,0.6)\" bg_size=\"original\"":" bg_color=\"$varDark\"";
        $o.="[section label=\"Hero\" padding=\"110px\"$bgAttr dark=\"true\"$maskAttr class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<div class='wcm-hero' style='text-align:center'>{$eyebrowHtml}<h1>$h1</h1>\n<p class='wcm-lead'>$introTxt</p>\n$ctas\n".($stripImgs?"<div class='wcm-hero-strip'>$stripImgs</div>":'')."</div>\n[/col]\n[/row]\n[/section]\n";
    }
    $o.=$trustHtml;

    // style choices for the reorderable middle blocks — only picked (and only vary the layout) when DYNAMIC_HOMEPAGE is on;
    // when off, each block keeps its ORIGINAL hardcoded padding/background so the static path stays a single fixed layout.
    $pad=$dynamic?$pick(['45px','55px','60px']):null;
    $catspan=$dynamic?$pick(['3','4']):'3';
    $whyusStyle=$dynamic?$pick([1,2]):1;   // 1=cards 2=clean centered stacked list, no boxes
    $featCols=$dynamic?$pick(['3','4']):null;   // [ux_products] grid columns; null = theme default
    $faqVariant=($design['idx']+$design['variant'])%3;   // decorrelated from the contact-page layout (which uses design_variant(3) directly)
    $static_pad=['intro'=>'45px','featured'=>'35px','whyus'=>'45px','categories'=>'35px','about'=>'45px','faq'=>'45px','process'=>'45px','newarrivals'=>'35px'];
    $static_bg =['intro'=>'','featured'=>'','whyus'=>" bg_color=\"$varLight\"",'categories'=>'','about'=>" bg_color=\"$varLight\"",'faq'=>'','process'=>'','newarrivals'=>''];
    $band_white=true;   // dynamic mode only: toggled after each RENDERED band so adjacent bands alternate white / tint
    $style=function($key) use($dynamic,$pad,&$band_white,$static_pad,$static_bg,$varLight){
        if($dynamic){ $p=$pad; $bg=$band_white?'':" bg_color=\"$varLight\""; $band_white=!$band_white; return [$p,$bg]; }
        return [$static_pad[$key]??'45px',$static_bg[$key]??'']; };
    $kicker=function($key) { $t=trim((string)ui_t($key)); return $t!==''?"<div style='text-align:center'><span class='wcm-eyebrow'>".esc($t)."</span></div>\n":''; };

    // BLOCKS — each middle section built as an independent string, keyed by name, so the assembly order below can vary per site.
    $blocks=[];
    $blocks['intro']=function() use($f,$style){ $ip=intro_paras($f['intro_paragraphs']??null); if(!$ip) return '';
        $ptxt=''; foreach($ip as $para){ $pp=esc(is_array($para)?($para['text']??''):$para); if(trim($pp)!=='') $ptxt.="<p>$pp</p>\n"; }
        if($ptxt==='') return ''; [$p,$bg]=$style('intro');
        return "[section label=\"Intro\" padding=\"$p\"$bg class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\" class=\"wcm-prose\"]\n".(!empty($f['intro_heading'])?"<h2 style='text-align:center'>".esc($f['intro_heading'])."</h2>\n":'').$ptxt."[/col]\n[/row]\n[/section]\n"; };
    $blocks['featured']=function() use($f,$prods,$style,$featCols,$shop,$kicker){ if(!$prods) return '';
        $ids=implode(',',array_map(fn($p)=>(int)$p['id'],$prods)); [$p,$bg]=$style('featured'); $cols=$featCols?" columns=\"$featCols\"":'';
        return "[section label=\"Featured\" padding=\"$p\"$bg class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n".$kicker('kicker_shop')."<h2 style='text-align:center'>".esc($f['featured_heading']??ui_t('featured_products'))."</h2>\n[ux_products ids=\"$ids\"$cols animate=\"fadeInUp\"]\n<p style='text-align:center'><a href=\"".esc_url($shop)."\">".esc(ui_t('view_all_products'))."</a></p>\n[/col]\n[/row]\n[/section]\n"; };
    $blocks['newarrivals']=function() use($style){ if(published_product_count()<8) return '';   // date-only: a "Best Sellers" claim would be false on a store with few or no sales yet
        [$p,$bg]=$style('newarrivals'); $head=ui_t('new_arrivals');
        return "[section label=\"".esc_attr_sc($head)."\" padding=\"$p\"$bg class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($head)."</h2>\n[ux_products type=\"row\" orderby=\"date\" products=\"4\" columns=\"4\" animate=\"fadeInUp\"]\n[/col]\n[/row]\n[/section]\n"; };
    $blocks['whyus']=function() use($f,$style,$whyusStyle){ $pts=is_array($f['why_us_points']??null)?array_values(array_filter($f['why_us_points'],fn($pt)=>trim((string)(is_array($pt)?($pt['title']??''):$pt))!=='')):[]; if(!$pts) return '';
        [$p,$bg]=$style('whyus'); $head="[section label=\"Why Us\" padding=\"$p\"$bg class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($f['why_us_heading']??ui_t('why_choose_us'))."</h2>\n[/col]\n[/row]\n";
        if($whyusStyle===2){   // variant 2: clean centered stacked list, one column, no boxes
            $body="[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n";
            foreach($pts as $pt){ $t=esc(is_array($pt)?($pt['title']??''):$pt); $dd=esc(is_array($pt)?($pt['text']??''):''); $body.="<h3 style='text-align:center'>$t</h3>\n".($dd!==''?"<p style='text-align:center'>$dd</p>\n":''); }
            return $head.$body."[/col]\n[/row]\n[/section]\n"; }
        [$sp,$spMd]=wcm_card_span(count($pts)); $mdAttr=$spMd!==''?" span__md=\"$spMd\"":''; $body="[row]\n";
        foreach($pts as $pt){ $t=esc(is_array($pt)?($pt['title']??''):$pt); $dd=esc(is_array($pt)?($pt['text']??''):''); $body.="[col span=\"$sp\"$mdAttr span__sm=\"12\" animate=\"fadeInUp\"]\n<div class='wcm-card'>\n<h3>$t</h3>\n".($dd!==''?"<p>$dd</p>\n":'')."</div>\n[/col]\n"; }
        return $head.$body."[/row]\n[/section]\n"; };
    $blocks['categories']=function() use($f,$catsValid,$style,$catspan,$design,$kicker){ if(!$catsValid) return '';
        $head=esc($f['categories_heading']??ui_t('shop_by_category')); [$p,$bg]=$style('categories');
        $withThumb=array_filter($catsValid,fn($c)=>!empty($c['id'])&&get_term_meta((int)$c['id'],'thumbnail_id',true));
        $majority=count($withThumb)>=ceil(count($catsValid)/2);
        $o2="[section label=\"Categories\" _id=\"wcm-categories\" padding=\"$p\"$bg class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n".$kicker('kicker_categories')."<h2 style='text-align:center'>$head</h2>\n[/col]\n[/row]\n";
        if($majority && array_filter($catsValid,fn($c)=>!empty($c['id']))){
            $ids=implode(',',array_map(fn($c)=>(int)$c['id'],array_filter($catsValid,fn($c)=>!empty($c['id']))));
            $ccols=count($catsValid)>=4?'4':(string)max(2,count($catsValid));
            $cstyle=$design['card']==='elevated'?'overlay':($design['card']==='tinted'?'badge':'push');
            $radPct=min(8,(int)round(((int)$design['radius_card'])/2)); $radAttr=$radPct>0?" image_radius=\"$radPct\"":'';   // ux_product_categories applies image_radius as a PERCENT — omit it entirely rather than ever emit "0" (Flatsome's own style-tag helper throws a visible PHP warning for an empty('0') attribute value)
            $o2.="[row]\n[col span__sm=\"12\"]\n[ux_product_categories ids=\"$ids\" type=\"row\" columns=\"$ccols\" style=\"$cstyle\" image_height=\"210px\"$radAttr]\n[/col]\n[/row]\n";
        } else {
            $o2.="[row]\n"; foreach($catsValid as $c){ $o2.="[col span=\"$catspan\" span__sm=\"6\"]\n<a class='wcm-cat-tile' href=\"".esc_url($c['url'])."\">".esc(humanize_anchor($c['name']))."</a>\n[/col]\n"; } $o2.="[/row]\n";
        }
        return $o2."[/section]\n"; };
    $blocks['about']=function() use($f,$style,&$secQueue,$mask){ if(empty($f['about_text'])) return '';
        [$p,$bg]=$style('about'); $aid=$secQueue?(int)$secQueue[0]:0; $maskAttr='';
        if($aid){ array_shift($secQueue); $bg=" bg=\"$aid\" bg_overlay=\"rgba(0,0,0,0.45)\" bg_size=\"original\" dark=\"true\""; $maskAttr=$mask!==''?" mask=\"$mask\"":''; }
        return "[section label=\"About\" padding=\"$p\"$bg$maskAttr class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"8\" span__sm=\"12\"]\n<h2 style='text-align:center'>".esc($f['about_heading']??ui_t('about_us'))."</h2>\n<p class='wcm-lead' style='text-align:center'>".esc($f['about_text'])."</p>\n[/col]\n[/row]\n[/section]\n"; };
    // FAQ — visible markup (never an accordion: every question/answer must sit in the DOM as plain text for SEO), one of
    // 3 tasteful variants per site: two-column Q&A grid, bordered stacked cards, or a heading-left/answers-right split.
    $blocks['faq']=function() use($f,$style,$faqVariant){ $qas=is_array($f['faq']??null)?array_values(array_filter($f['faq'],fn($q)=>trim((string)(is_array($q)?($q['q']??''):''))!=='')):[]; if(!$qas) return '';
        $head=esc($f['faq_heading']??ui_t('faq_heading')); [$p,$bg]=$style('faq');
        if($faqVariant===1){   // bordered stacked cards
            $o2="[section label=\"FAQ\" padding=\"$p\"$bg class=\"wcm wcm-faq\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<h2 style='text-align:center'>$head</h2>\n";
            foreach($qas as $qa){ $o2.="<div class='wcm-card wcm-faq-card'>\n<h3>".esc($qa['q'])."</h3>\n<p>".esc($qa['a']??'')."</p>\n</div>\n"; }
            return $o2."[/col]\n[/row]\n[/section]\n"; }
        if($faqVariant===2){   // heading left, answers right
            $o2="[section label=\"FAQ\" padding=\"$p\"$bg class=\"wcm wcm-faq\"]\n[row]\n[col span=\"4\" span__sm=\"12\"]\n<h2>$head</h2>\n[/col]\n[col span=\"8\" span__sm=\"12\"]\n";
            foreach($qas as $qa){ $o2.="<h3>".esc($qa['q'])."</h3>\n<p>".esc($qa['a']??'')."</p>\n"; }
            return $o2."[/col]\n[/row]\n[/section]\n"; }
        $o2="[section label=\"FAQ\" padding=\"$p\"$bg class=\"wcm wcm-faq\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>$head</h2>\n[/col]\n[/row]\n[row]\n";   // default: two-column Q&A grid
        foreach($qas as $qa){ $o2.="[col span=\"6\" span__sm=\"12\"]\n<h3>".esc($qa['q'])."</h3>\n<p>".esc($qa['a']??'')."</p>\n[/col]\n"; }
        return $o2."[/row]\n[/section]\n"; };
    // OPTIONAL EXTRA SECTIONS (dynamic mode only, picked below) — additive content, never replace the core blocks above.
    // Each skips cleanly (returns '') when its AI field/data is empty, so an empty block is never emitted. 'benefits' has no
    // section of its own (it already feeds the trust strip above) and 'assurance' is folded into the closing CTA band below,
    // so no two sections on the page ever repeat the same card-grid/CTA look.
    $blocks['process']=function() use($f,$style){ $ps=is_array($f['process_steps']??null)?array_values(array_filter($f['process_steps'],fn($s)=>trim((string)(is_array($s)?($s['title']??''):$s))!=='')):[]; if(!$ps) return '';
        [$p,$bg]=$style('process');
        $o2="[section label=\"Process\" padding=\"$p\"$bg class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n<h2 style='text-align:center'>".esc(ui_t('how_it_works'))."</h2>\n[/col]\n[/row]\n[row class=\"wcm-steps\"]\n";
        $i=0; foreach($ps as $s){ $i++; $t=esc(is_array($s)?($s['title']??''):$s); $dd=esc(is_array($s)?($s['text']??''):''); $o2.="[col span=\"4\" span__sm=\"12\" animate=\"fadeInUp\"]\n<div style='text-align:center'>\n<span class='wcm-step-num'>$i</span>\n<h3>$t</h3>\n".($dd!==''?"<p>$dd</p>\n":'')."</div>\n[/col]\n"; }
        return $o2."[/row]\n[/section]\n"; };

    // ORDER — fixed (today's order) when DYNAMIC_HOMEPAGE is off; one of six curated, SEO-safe orders when on. Empty blocks are skipped either way.
    $orders=[
        ['intro','featured','whyus','categories','about','faq'],
        ['intro','categories','featured','whyus','faq','about'],
        ['featured','intro','categories','about','whyus','faq'],
        ['intro','whyus','featured','faq','categories','about'],
        ['intro','featured','categories','faq','whyus','about'],
        ['categories','intro','featured','whyus','about','faq'],
    ];
    $order=$dynamic?$pick($orders):$orders[0];

    $pieces=[]; foreach($order as $key){ $s=$blocks[$key](); if($s!=='') $pieces[]=$s; }

    // OPTIONAL EXTRA SECTIONS — seed picks 0-1 (only from those with real content) and slots it into a random spot among
    // the core middle blocks above: always after the hero/trust strip (already rendered), always before the closing CTA.
    if($dynamic){
        $optAvail=[];
        if(is_array($f['process_steps']??null) && array_filter($f['process_steps'],fn($s)=>trim((string)(is_array($s)?($s['title']??''):$s))!=='')) $optAvail[]='process';
        if(published_product_count()>=8) $optAvail[]='newarrivals';
        if($optAvail){ $k=$pick($optAvail); $s=$blocks[$k](); if($s!==''){ $pos=$rng()%(count($pieces)+1); array_splice($pieces,$pos,0,[$s]); } }
    } else { $s=$blocks['newarrivals'](); if($s!=='') $pieces[]=$s; }   // static mode: still show it (fixed position) when the catalogue supports it

    // a second supporting image (not used by About or a split-hero) runs as a standalone full-width band roughly halfway down the page
    if(!empty($secQueue)){ $simg=(int)array_shift($secQueue);
        $showcase="[section label=\"Showcase\" padding=\"0px\" class=\"wcm\"]\n[row]\n[col span__sm=\"12\"]\n[ux_image id=\"$simg\"]\n[/col]\n[/row]\n[/section]\n";
        array_splice($pieces,intdiv(count($pieces),2),0,[$showcase]); }
    foreach($pieces as $piece) $o.=$piece;

    // CLOSING CTA — full-width band, ALWAYS last. closing_cta and assurance are merged into ONE band (never two CTA-style
    // bands back to back): the assurance sentence, when present, sits as a sub-line under the closing headline/button.
    $closing=trim((string)($f['closing_cta']??'')); $assurance=trim((string)($f['assurance']??''));
    if($closing!=='' || $assurance!==''){
        $sub=$assurance!==''?"<p style='font-size:1.05em'>".esc($assurance)."</p>\n":'';
        $o.="[section label=\"CTA\" padding=\"55px\" bg_color=\"$varLight\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"10\" span__sm=\"12\"]\n<div class='wcm-cta-band'>\n".($closing!==''?"<h3>".esc($closing)."</h3>\n":'').$sub."[button text=\"$btn\" size=\"large\" link=\"".esc_url($shop)."\"]\n</div>\n[/col]\n[/row]\n[/section]\n"; }
    if(COMPLIANCE_MODE&&DISCLAIMER_HTML) $o.="[section padding=\"20px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<small>".DISCLAIMER_HTML."</small>\n[/col]\n[/row]\n[/section]\n";
    return $o; }
// contact form to embed: prefer a published CF7 form whose title mentions "contact" (case-insensitive); otherwise the
// OLDEST published form (CF7 ships a default "Contact form 1" — the newest form could just as easily be a newsletter
// sign-up or some other unrelated form the owner added later).
function contact_cf7_shortcode(){ if(!shortcode_exists('contact-form-7')) return '';
    $forms=get_posts(['post_type'=>'wpcf7_contact_form','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
    if(!$forms) return '';
    foreach($forms as $frm){ if(stripos((string)$frm->post_title,'contact')!==false) return '[contact-form-7 id="'.(int)$frm->ID.'"]'; }
    return '[contact-form-7 id="'.(int)$forms[0]->ID.'"]'; }
// Flatsome contact page — 3 per-site layouts (picked from the same seed as the homepage design, so it's stable per site,
// varies site to site). CF7 is embedded when active; otherwise a styled mailto card stands in for the message area.
// Every layout follows H1 -> H2 -> H3 (a "Reach us"/"Get in touch" H2 sits above the contact cards; H3 is only ever used
// for a card/panel heading nested under that H2).
function build_contact_flatsome($ttl,$lead,$bn,$ema,$ph,$loc,$hoursVal){
    $reach=esc(ui_t('reach_us')); $help=esc(ui_t('how_we_can_help'));
    $lEmail=esc(ui_t('email')); $lPhone=esc(ui_t('phone')); $lLoc=esc(ui_t('location')); $lHours=esc(ui_t('support_hours'));
    $emailUs=esc_attr_sc(ui_t('email_us')); $sendMsg=esc(ui_t('send_a_message'));
    $q1=esc(ui_t('product_questions')); $q2=esc(ui_t('order_status')); $q3=esc(ui_t('bulk_enquiries'));
    $q4=str_replace('%s',$bn,esc(ui_t('contact_feedback')));
    $tel=preg_replace('/[^0-9+]/','',(string)$ph); $hasTel=$tel!=='' && preg_replace('/[^0-9]/','',$tel)!=='';
    $phoneHtml=$hasTel?"<a href='tel:$tel'>$ph</a>":$ph;   // no digits in the configured phone -> show plain text instead of a dead tel: link
    $cf7=contact_cf7_shortcode();
    $msgHtml=$cf7!=='' ? $cf7 : ("<p>".esc(ui_t('contact_write_prompt'))."</p>\n[button text=\"$emailUs\" link=\"mailto:$ema\"]");
    $layout=design_variant(3);   // 0=A gradient hero + overlapping cards, 1=B split dark panel, 2=C minimal centered tiles + chips

    $cardMail="<div class='wcm-contact-card'><span class='wcm-contact-icon wcm-contact-icon-mail'></span><h3>$lEmail</h3><p><a href='mailto:$ema'>$ema</a></p></div>";
    $cardPhone="<div class='wcm-contact-card'><span class='wcm-contact-icon wcm-contact-icon-phone'></span><h3>$lPhone</h3><p>$phoneHtml</p></div>";
    $cardLoc="<div class='wcm-contact-card'><span class='wcm-contact-icon wcm-contact-icon-pin'></span><h3>$lLoc</h3><p>$loc</p></div>";
    $cardHours="<div class='wcm-contact-card'><span class='wcm-contact-icon wcm-contact-icon-clock'></span><h3>$lHours</h3><p>$hoursVal</p></div>";
    $cards=[$cardMail,$cardPhone,$cardLoc,$cardHours];

    if($layout===0){
        $c ="[section padding=\"80px\" class=\"wcm wcm-contact-hero\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h1>$ttl</h1>\n<p class='wcm-lead'>$lead</p>\n</div>\n[/col]\n[/row]\n[/section]\n";
        $c.="[section padding=\"0px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"12\"]\n<h2 class='wcm-visually-hidden' style='text-align:center'>$reach</h2>\n[/col]\n[/row]\n[row h_align=\"center\" class=\"wcm-contact-cards-overlap\"]\n";
        foreach($cards as $card){ $c.="[col span=\"3\" span__sm=\"6\"]\n$card\n[/col]\n"; }
        $c.="[/row]\n[/section]\n";
        $c.="[section padding=\"55px\" class=\"wcm\"]\n[row]\n[col span=\"6\" span__sm=\"12\"]\n<h2>$help</h2>\n<ul>\n<li>$q1</li>\n<li>$q2</li>\n<li>$q3</li>\n<li>$q4</li>\n</ul>\n[/col]\n[col span=\"6\" span__sm=\"12\"]\n<div class='wcm-card'>\n<h3>$sendMsg</h3>\n$msgHtml\n</div>\n[/col]\n[/row]\n[/section]\n";
    } elseif($layout===1){
        $c ="[section padding=\"70px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"10\" span__sm=\"12\"]\n<h1 style='text-align:center'>$ttl</h1>\n<p class='wcm-lead' style='text-align:center'>$lead</p>\n[/col]\n[/row]\n";
        $c.="[row h_align=\"center\"]\n[col span=\"5\" span__sm=\"12\"]\n<div class='wcm-contact-split-dark dark'>\n<h2>$reach</h2>\n<p><strong>$lEmail:</strong> <a href='mailto:$ema'>$ema</a></p>\n<p><strong>$lPhone:</strong> $phoneHtml</p>\n<p><strong>$lLoc:</strong> $loc</p>\n<p><strong>$lHours:</strong> $hoursVal</p>\n</div>\n[/col]\n";
        $c.="[col span=\"7\" span__sm=\"12\"]\n<div class='wcm-card'>\n<h2>$sendMsg</h2>\n$msgHtml\n</div>\n[/col]\n[/row]\n[/section]\n";
    } else {
        $c ="[section padding=\"70px\" class=\"wcm\"]\n[row h_align=\"center\"]\n[col span=\"9\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h1>$ttl</h1>\n<p class='wcm-lead'>$lead</p>\n<p><span class='wcm-contact-chip'>$q1</span><span class='wcm-contact-chip'>$q2</span><span class='wcm-contact-chip'>$q3</span></p>\n</div>\n[/col]\n[/row]\n";
        $c.="[row h_align=\"center\"]\n[col span=\"12\"]\n<h2 style='text-align:center'>$reach</h2>\n[/col]\n[/row]\n[row h_align=\"center\"]\n"; foreach($cards as $card){ $tile=str_replace('wcm-contact-card','wcm-contact-tile',$card); $c.="[col span=\"3\" span__sm=\"6\"]\n$tile\n[/col]\n"; } $c.="[/row]\n";
        $c.="[row h_align=\"center\"]\n[col span=\"7\" span__sm=\"12\"]\n<div style='text-align:center'>\n<h2>$sendMsg</h2>\n$msgHtml\n</div>\n[/col]\n[/row]\n[/section]\n";
    }
    return $c; }
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
    if(RESET_PROGRESS){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wcm_done','_wcm_page_done')"); $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key='_wcm_cat_done'"); delete_option('wcm_grouping_done'); delete_option('wcm_stock_done'); delete_option('wcm_foreign_done'); delete_option('wcm_branding_done'); delete_option('wcm_logo_att'); delete_option('wcm_palette'); delete_option('wcm_noprog'); out("\nRESET_PROGRESS — progress wiped ONCE; reprocessing products, categories, grouping, stock, foreign-links, pages and branding. BLOG is left untouched (its done-flag, saved title list and schedule anchor are kept) so a reset can NEVER create a duplicate second batch of posts.",'#fa0'); }   // (previously also wiped wcm_blog_done/titles/start, which regenerated a fresh title list and doubled the blog)
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
        if(strpos((string)$fixed,'<h2>'.esc(ui_t('explore_more')).'</h2>')===false) $fixed=blog_topup_links($fixed,(int)BLOG_INTERNAL_LINKS,$cut);   // match the EXACT top-up heading (not the bare phrase, which could occur in prose) so we don't stack a second block, but still re-top-up posts that never had one
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
function related_of($pid,$primary,$groups,$P){ $out=[]; $seen=[$pid=>1];
    foreach(($groups[$primary[$pid]]??[]) as $o){ if(isset($seen[$o])||($P[$o]['status']??'')!=='publish') continue;   // same-category siblings first (most relevant)
        $seen[$o]=1; $out[]=['name'=>humanize_anchor($P[$o]['title']),'url'=>site_link(get_permalink($o))]; if(count($out)>=INTERLINKS_PER_PRODUCT) return $out; }
    $keys=array_keys($P); $nk=count($keys); $start=$nk?($pid%$nk):0;   // FALLBACK rotates its start by product id so link equity spreads across the catalog instead of piling onto the first few products
    for($j=0;$j<$nk && count($out)<INTERLINKS_PER_PRODUCT;$j++){ $o=$keys[($start+$j)%$nk]; if(isset($seen[$o])||($P[$o]['status']??'')!=='publish') continue;   // too few same-category siblings -> fill from other published products so EVERY product still gets interlinks
        $seen[$o]=1; $out[]=['name'=>humanize_anchor($P[$o]['title']),'url'=>site_link(get_permalink($o))]; }
    return $out; }

// ---- PHASE: PRODUCTS -------------------------------------------------------
$batched=false; $processed=0; $aborted=false; $products_complete=true; $left=0; $hard_stop=false; $progress=false; $blog_incomplete=false; $pages_incomplete=false; $pages_paused=false;   // $blog_incomplete/$pages_incomplete init HERE (before the category + pages phases) so a failure in ANY of them keeps the file from self-deleting; default TRUE so a run that intentionally skips the product phase (pages-only / REPAIR_POST_LINKS / branding-only maintenance) still counts as complete and can self-delete + re-arm RESET; the product block below sets the REAL value when it runs. $hard_stop = a runaway-guard: true on AI billing/credit exhaustion or repeated failures, so AUTO_REFRESH stops reloading instead of hammering the AI. $progress = did this run make REAL forward progress (used by the no-progress streak below)
$want_product_ai = DO_SHORT_DESC||DO_LONG_DESC||DO_META||DO_TAGS||DO_PRICE;
if ($want_product_ai || DO_INTERLINKS || REMOVE_FOREIGN_LINKS) {
    out("\n--- Products ---",'#6cf'); $tot=count($P); global $wpdb;
    // RESUME: each finished product carries a '_wcm_done' flag and is skipped. One query loads the whole done-set, so a
    // refresh continues exactly where it left off (even after a server timeout) and never re-touches a finished product
    // — so content is never duplicated. MAX_PRODUCTS_PER_RUN just caps how many NEW products to do per run (0 = all).
    $done=array_flip(array_intersect(array_map('intval',(array)$wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'")),$ids));   // intersect with the CURRENT catalog so stale flags (trashed/removed products) can't inflate the count and falsely mark the job complete
    $todo=[]; foreach($P as $pid=>$row){ if(!isset($done[$pid])){ $todo[]=$pid; if(MAX_PRODUCTS_PER_RUN>0 && count($todo)>=MAX_PRODUCTS_PER_RUN) break; } }
    out('   '.count($done).' already done · '.count($todo).' to do now · '.max(0,$tot-count($done)-count($todo)).' left after this run','#6cf');
    if($todo) update_meta_cache('post',$todo);
    $i=count($done); $fail=0; $handled=0; $rt0=time(); $timeboxed=false;
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
               'unit'=>false];
        $need['unit']=($need['short']&&SHORT_DESC_INCLUDE_UNIT)||$need['price'];
        $need_ai=$need['short']||$need['long']||$need['meta']||$need['tags']||$need['price'];
        if(!$need_ai && !DO_INTERLINKS && !REMOVE_FOREIGN_LINKS){ out("[$i/$tot] skip: $name",'#888'); update_post_meta($pid,'_wcm_done',1); $handled++; continue; }
        out("[$i/$tot] $name",'#ddd');
        $link_menu=[]; if(DO_INTERLINKS){ $link_menu=array_slice(related_of($pid,$primary,$groups,$P),0,4); if($cat_url) $link_menu[]=['url'=>$cat_url,'name'=>$catname]; }   // a few EXACT related-product + category URLs the prompt may weave in-context; inject_links() below still runs as a deterministic fallback
        $data=[]; if($need_ai){ [$data,$err]=ai_json(product_prompt(['title'=>$name,'cat'=>$catname,'short'=>excerpt($short),'long'=>excerpt($long,500)],$need,$link_menu));
            if(!$data){ out("   [skip] $err",'#f66');
                if(strpos((string)$err,'CREDIT')!==false){ out('   [STOP] AI credit/billing exhausted — halting so the rest of the catalog is not left half-done. Fix billing (or set AI_PROVIDER=\'gemini\'), then re-open the URL.','#f66'); $aborted=true; $hard_stop=true; break; }
                if(++$fail>=3){ out('   [STOP] 3 AI calls failed in a row — halting so the catalog is not left half-updated. Check the API key/quota in the red messages above, then re-open the URL to resume from here.','#f66'); $aborted=true; $hard_stop=true; break; }
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

        out('   [ok]'.($made_var?' + variations':''),'#6f6'); $processed++; $handled++;
        update_post_meta($pid,'_wcm_done',1);   // flag finished NOW so a server timeout keeps this product's progress
        if(AUTO_REFRESH && (time()-$rt0)>=AUTO_REFRESH_MAX_SECONDS){ $timeboxed=true; break; }   // keep this load short so the end-of-page auto-refresh reliably fires under the proxy timeout; every product so far is already flagged done, so resuming next load loses no work
    }
    if($handled>0) $progress=true;   // a product was resolved this run — forward progress for the no-progress streak
    // completion: if every product is now flagged done, finish and clear the flags (so a fresh upload starts over).
    // Otherwise keep the file and wait for a refresh — failed products stay UNflagged and are retried; a timed-out
    // run simply resumes from the first product that isn't flagged yet.
    $left = max(0, $tot-count($done)-$handled);          // products still not finished (failed AI calls, or a future batch)
    $more_batches = (count($done)+count($todo)) < $tot;  // un-done products remain BEYOND this run's window (only when MAX caps it)
    $products_complete = (!$aborted && $left===0);
    if($products_complete) out("\n[done] all $tot products processed.",'#6cf');
    elseif($aborted) $batched=true;                                                  // stopped hard — don't build pages, keep file to retry
    elseif($timeboxed){ $batched=true; out("\n[auto] paused after ".AUTO_REFRESH_MAX_SECONDS."s to keep the load short — $left product(s) left; auto-continuing on reload.",'#6cf'); }   // load stayed short so the auto-refresh at the bottom of the page actually fires under the proxy timeout; downstream phases skipped this load via $batched
    elseif($more_batches){ $batched=true; out("\n[batch] $left product(s) left — refresh the URL to continue.",'#6cf'); }
    else out("\n[note] $left product(s) failed this run — the rest and the pages still build; re-open the URL to retry those.",'#fa0');   // last window: a bad product must NOT block the site build
}

// ---- PHASE: FORCE IN STOCK (all products + variations) ---------------------
// WooCommerce core, theme-independent — Flatsome only displays whatever status we set here.
// This is AI-free maintenance, so it runs on EVERY load INDEPENDENTLY of the product/AI phase: an empty API
// key or a mid-batch product run must never suppress it (previously it was gated behind !$batched, so a failed
// AI key that aborted the product loop also silently disabled stock). It is also NOT guarded by a one-shot
// done-flag, so products added on a LATER upload get fixed too. The selector returns only items that still need
// a fix, so a fully-stocked catalog costs one cheap indexed query and zero writes.
if(FORCE_IN_STOCK){ out("\n--- Forcing stock status: In stock ---",'#6cf'); $sn=0; $vparents=[];
    // Set BOTH manage-stock OFF and status IN STOCK on the SAME product object, then save once. Turning off
    // manage-stock is what makes it stick — otherwise WC re-derives "out of stock" from a 0 quantity. save()
    // also rebuilds this product's WC lookup-table row + clears caches so the shop reflects it. Only
    // products/variations not already in stock (or still self-managing stock) — O(items to fix).
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
        $pr->save(); $sn++;
        $par=(int)$pr->get_parent_id(); if($par) $vparents[$par]=1; elseif($pr->is_type('variable')) $vparents[$pp]=1; }   // note affected variable parents
    // Re-derive each affected variable parent from its now-in-stock children instead of relying only on WC's
    // deferred shutdown sync (some hosts finish the request before shutdown handlers fire).
    if($vparents && class_exists('WC_Product_Variable')) foreach(array_keys($vparents) as $vp) WC_Product_Variable::sync($vp);
    // MIGRATION FIX: rows whose _stock_status postmeta ALREADY says 'instock' are skipped by the query above, so
    // their WC product-lookup row is never rebuilt. After a DB restore/import that lookup row can be stale
    // ('outofstock') while postmeta is correct — and the shop catalog (+ "hide out of stock items", sorting,
    // [products] queries) reads the lookup table, NOT postmeta, so those items stay hidden/out of stock. One
    // targeted query corrects any such desync. Guarded by a table-exists check (older WC has no lookup table).
    $lut=$wpdb->prefix.'wc_product_meta_lookup';
    if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$lut))===$lut)
        $wpdb->query("UPDATE {$lut} l JOIN {$wpdb->postmeta} pm ON pm.post_id=l.product_id AND pm.meta_key='_stock_status' SET l.stock_status='instock' WHERE pm.meta_value='instock' AND l.stock_status<>'instock'");
    out("   set $sn products/variations in stock",'#6f6'); }

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
        $plinks=[]; foreach(($groups[$t->term_id]??array_slice(get_posts(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','numberposts'=>8,'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id]]]),0,8)) as $pp){ if(get_post_status($pp)!=='publish') continue; $plinks[]=['name'=>humanize_anchor(get_the_title($pp)),'url'=>site_link(get_permalink($pp))]; if(count($plinks)>=8) break; }   // publish-only: never surface a draft/pending product in the "Shop This Category" links
        $plink_ctx=array_slice($plinks,0,4); $plinktxt=''; foreach($plink_ctx as $r){ $plinktxt.='  - '.$r['name'].': '.$r['url']."\n"; }
        $linkclause=$plinktxt?"INTERNAL LINKS: weave 2-3 of these internal links INTO your sentences (in-context, not a list at the end, never 'click here'), using ONLY these EXACT URLs. Anchor text must be natural, correctly-spaced descriptive words, never run together, never camelCase, never a slug or the raw URL:\n$plinktxt":"No invented links.";
        [$d,$err]=ai_json("Write an SEO description, ".words_phrase('180-260').", for the product category \"{$t->name}\" at ".brand()." selling ".STORE_NICHE.". ".voice_rules().compliance_clause()."Open with the focus keyword; explain what it covers and why buy here; one <h2>. $linkclause ".html_quote_rule()."Return JSON: {\"description\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}");
        if(!$d||empty($d['description'])){ out("   [skip] {$t->name} — ".($err?:'no content'),'#f66'); $pages_incomplete=true; continue; }   // don't self-delete with a category still description-less; retry on reload
        $desc=dedash((string)$d['description']); if(REMOVE_FOREIGN_LINKS) $desc=strip_foreign_links($desc); $desc=strip_future_internal_links($desc,current_time('mysql')); $have=existing_hrefs($desc); $li='';   // strip off-site links (foreign phase never reaches category descriptions) + any link to a not-yet-published post
        foreach($plinks as $r){ if(!in_array($r['url'],$have,true)) $li.='<li><a href="'.esc_url($r['url']).'">'.esc($r['name']).'</a></li>'; }
        if($li) $desc.="\n<h2>".esc(ui_t('shop_this_category'))."</h2>\n<ul>$li</ul>"; $desc=append_disclaimer($desc);
        $r=wp_update_term($t->term_id,'product_cat',['description'=>$desc]);
        if(!empty($d['meta_title'])) update_term_meta($t->term_id,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
        if(!empty($d['meta_description'])) update_term_meta($t->term_id,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
        if(!empty($d['focus_keyword'])) update_term_meta($t->term_id,'rank_math_focus_keyword',(string)$d['focus_keyword']);
        $svd=$wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d",$t->term_taxonomy_id));   // read STRAIGHT from DB (bypasses object cache)
        if(is_wp_error($r)){ out("   [WRITE ERROR] {$t->name}: ".$r->get_error_message(),'#f66'); $pages_incomplete=true; }
        else { update_term_meta($t->term_id,'_wcm_cat_done',1); $progress=true; out("   [ok] {$t->name} — wrote ".mb_strlen($desc)." chars, DB now holds ".mb_strlen((string)$svd),'#6f6'); } } }

// ---- PHASE: PAGES (after products) -----------------------------------------
if(!$batched){
    $pg0=time();   // time-box the whole pages phase (homepage + contact + legal + gates blog/branding) like the product/blog loops, so a long load doesn't exceed the proxy timeout and kill the auto-refresh
    // only touch the design CSS/fonts when a Flatsome page is actually going to be (re)written this run — never on every
    // load (e.g. once everything is done and only the blog is still batching, or when every DO_* page flag is off)
    if(is_flatsome()){
        $will_write_page=false;
        if(DO_HOMEPAGE && HOMEPAGE_FORMAT==='flatsome'){ $fid0=resolve_front(); if(wcm_page_pending($fid0,get_post_field('post_content',$fid0),OVERWRITE_HOMEPAGE)) $will_write_page=true; }
        if(!$will_write_page && DO_CONTACT){ $c0=get_page_by_path('contact-us'); if(wcm_page_pending($c0?$c0->ID:0,$c0?$c0->post_content:'',OVERWRITE_PAGES)) $will_write_page=true; }   // get_page_by_path (not find_or_create_page): a missing page counts as pending WITHOUT creating an empty published page here — if the homepage write below then pauses the budget, no orphan "Contact Us" page is left behind
        if(!$will_write_page){ foreach(['privacy-policy'=>DO_PRIVACY,'terms-and-conditions'=>DO_TERMS,'shipping-policy'=>DO_SHIPPING,'refund_returns'=>DO_REFUND,'faq'=>DO_FAQ,'about-us'=>DO_ABOUT] as $slug0=>$flag0){
            if(!$flag0) continue; $p0=get_page_by_path($slug0);
            if(wcm_page_pending($p0?$p0->ID:0,$p0?$p0->post_content:'',OVERWRITE_PAGES)){ $will_write_page=true; break; } } }
        if($will_write_page) wcm_apply_design();
    }
    // featured products + category links — only built when the homepage is actually being written
    $topcats=[]; $feat=[];
    if(DO_HOMEPAGE){ foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>0]) as $t){ if(strtolower($t->slug)==='uncategorized') continue; $lk=get_term_link($t); if(!is_wp_error($lk)) $topcats[]=['id'=>(int)$t->term_id,'name'=>$t->name,'url'=>site_link($lk)]; }
        foreach(array_keys($P) as $pid){ if(($P[$pid]['status']??'')!=='publish') continue;   // only feature publicly visible products
            $feat[]=['id'=>$pid,'name'=>$P[$pid]['title'],'url'=>site_link(get_permalink($pid))]; if(count($feat)>=6) break; } }

    if(DO_HOMEPAGE){ out("\n--- Homepage ---",'#6cf'); $fid=resolve_front();
        $cur=trim((string)get_post_field('post_content',$fid));
        if(get_post_meta($fid,'_wcm_page_done',true)){ out('   [skip] homepage (already done; RESET_PROGRESS to redo)','#888'); }
        elseif($cur!=='' && !OVERWRITE_HOMEPAGE){ out('   [skip] homepage has content (OVERWRITE_HOMEPAGE=false)','#888'); }
        else { $cl=''; foreach($topcats as $c){ $cl.='  - '.$c['name']."\n"; } $pl=''; foreach($feat as $f){ $pl.='  - '.$f['name']."\n"; }
            [$d,$err]=ai_json("Write HOMEPAGE copy for ".brand()." selling ".STORE_NICHE.". Tagline: \"".tagline()."\".\nCategories:\n$cl\nFeatured:\n$pl\n".voice_rules().compliance_clause()."Plain text fields only (no HTML). Make hero_intro a substantial 2-3 sentence lead. Provide intro_heading (a short section heading, not 'Welcome to') and intro_paragraphs (2-3 rich paragraphs of 60-90 words each that introduce the store, what it sells and why buy here; this is the main content shown directly under the homepage headline). Give 3-4 why_us_points, 3-4 faq and faq_heading (a short heading for the FAQ section, e.g. localized 'Frequently Asked Questions'). Also provide benefits_heading (a short heading for a features band, e.g. 'Why Shop With Us') and benefits: 3-4 {title,text} value props for a features band (e.g. selection, worldwide shipping, secure checkout, responsive support). Provide process_steps: exactly 3 {title,text} steps describing how ordering works (e.g. browse, order, receive). Provide assurance: one short reassuring sentence about shopping here. Do NOT invent customer testimonials, reviews, ratings, star counts, specific statistics, percentages, awards, certifications, or years-in-business. Stick to generic benefit categories you can safely claim (e.g. selection, worldwide shipping, secure checkout, responsive support), and avoid any specific claim you cannot back up. Also provide hero_eyebrow: a short tagline of 3-6 words shown above the main headline (not a repeat of the headline). Provide trust_points: an array of 3-4 very short reassurance phrases (2-5 words each, e.g. 'Worldwide shipping', 'Secure checkout') shown in a thin strip under the hero. Never claim anything is free unless it genuinely is. Also provide banner_image_prompt: one sentence describing a professional wide banner scene that represents what this store sells or its industry (an environmental or product scene, no people, no text). Provide section_image_prompts: an array of up to 2 short scene descriptions for supporting sections, using the same rules (no people, no text). ".( "Return ONE valid JSON object, no comments/trailing commas, with keys: hero_headline, hero_intro, hero_eyebrow, trust_points (array of strings), intro_heading, intro_paragraphs (array of paragraph strings), cta_button, categories_heading, why_us_heading, why_us_points (array of {title,text}), featured_heading, about_heading, about_text, faq (array of {q,a}), faq_heading, closing_cta, benefits_heading, benefits (array of {title,text}), process_steps (array of {title,text}), assurance, banner_image_prompt, section_image_prompts (array of strings), meta_title, meta_description, focus_keyword."));
            if(!$d||empty($d['hero_headline'])){ out('   [skip] homepage — '.($err?:'no content'),'#f66'); $pages_incomplete=true; }
            else { $images=['banner'=>0,'sections'=>[]];
                if(HOMEPAGE_IMAGES && OPENAI_API_KEY!==''){   // banner + up to (HOMEPAGE_IMAGE_COUNT-1) supporting images — cached in options so an auto-refresh / re-run never re-generates (never re-bills) once made
                    $bp=trim((string)($d['banner_image_prompt']??'')); if($bp==='') $bp='A professional wide banner scene representing '.STORE_NICHE.', showing its products and setting, no people, no text.';
                    $batt=(int)get_option('wcm_home_banner_att');
                    if(!($batt && get_post($batt))){ [$batt,]=home_image($bp,brand().' '.STORE_NICHE.' banner'); if($batt) update_option('wcm_home_banner_att',$batt,false); else $batt=0; }
                    $images['banner']=$batt;
                    $need_n=max(0,HOMEPAGE_IMAGE_COUNT-1);
                    if($need_n>0){ $sids=get_option('wcm_home_imgs',[]); $sids=is_array($sids)?array_values(array_filter(array_map('intval',$sids),fn($i)=>$i && get_post($i))):[];
                        $sp=is_array($d['section_image_prompts']??null)?array_values(array_filter(array_map('trim',$d['section_image_prompts']))):[];
                        if(!$sp) $sp=['A professional scene representing '.STORE_NICHE.', focused on the products and setting, no people, no text.','A professional close-up scene representing '.STORE_NICHE.', in a tidy, well-lit setting, no people, no text.'];
                        for($si=0; count($sids)<$need_n && $si<count($sp); $si++){ [$sa,]=home_image($sp[$si],brand().' '.STORE_NICHE.' section '.($si+1)); if($sa) $sids[]=$sa; }
                        $sids=array_slice($sids,0,$need_n); if($sids) update_option('wcm_home_imgs',$sids,false); $images['sections']=$sids; } }
                if(HOMEPAGE_FORMAT==='flatsome') $content=build_flatsome($d,$topcats,$feat,$images);
                else { $content='<h1>'.esc($d['hero_headline']).'</h1><p>'.esc($d['hero_intro']??'').'</p>';
                    $ip=intro_paras($d['intro_paragraphs']??null); if($ip){ if(!empty($d['intro_heading'])) $content.='<h2>'.esc($d['intro_heading']).'</h2>'; foreach($ip as $para){ $pp=esc(is_array($para)?($para['text']??''):$para); if(trim($pp)!=='') $content.='<p>'.$pp.'</p>'; } }
                    $li=''; foreach($topcats as $c){ $li.='<li><a href="'.esc_url($c['url']).'">'.esc($c['name']).'</a></li>'; } if($li)$content.='<h2>'.esc($d['categories_heading']??ui_t('shop_by_category')).'</h2><ul>'.$li.'</ul>'; $content=append_disclaimer($content); }
                $content=dedash($content); $r=wp_update_post(['ID'=>$fid,'post_content'=>$content],true);
                $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$fid));   // verify STRAIGHT from DB
                if(is_wp_error($r)) out('   [WRITE ERROR] homepage: '.$r->get_error_message(),'#f66');
                else out("   homepage: wrote ".mb_strlen($content)." chars to page id $fid, DB now holds ".mb_strlen((string)$svd),'#6cf');
                if(!empty($d['meta_title'])) update_post_meta($fid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                if(!empty($d['meta_description'])) update_post_meta($fid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                if(!empty($d['focus_keyword'])) update_post_meta($fid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                if(is_flatsome() && HOMEPAGE_FORMAT==='flatsome') update_post_meta($fid,'_wp_page_template','page-blank.php'); else wcm_clear_our_template($fid);   // full width so the design isn't squeezed into the theme's narrow default container; only clear OUR OWN stale value (never an owner-chosen template, e.g. page-transparent-header.php on an html-format front page)
                update_post_meta($fid,'_wcm_page_done',1); $progress=true; out('   [ok] homepage','#6f6'); } } }
    if(AUTO_REFRESH && (time()-$pg0)>=AUTO_REFRESH_MAX_SECONDS){ $pages_paused=true; $pages_incomplete=true; }   // homepage alone (AI copy + images) can eat the whole budget — pause here so the auto-refresh fires

    if(DO_CONTACT && !$pages_paused){ out("\n--- Contact ---",'#6cf'); $pid=find_or_create_page('contact-us','Contact Us');
        $cur=trim((string)get_post_field('post_content',$pid));
        if(get_post_meta($pid,'_wcm_page_done',true)){ out('   [skip] contact (already done; RESET_PROGRESS to redo)','#888'); }
        elseif($cur!=='' && !OVERWRITE_PAGES){ out('   [skip] contact has content','#888'); }
        else { $bn=esc(brand()); $niche=esc(STORE_NICHE); $ph=esc(us_phone()); $ema=esc(site_email()); $loc=esc(CONTACT_LOCATION);
            $lead=str_replace('%s',$niche,esc(ui_t('contact_lead')));   // str_replace (not sprintf): a translated string with a stray % or missing/extra %s must never fatal the page
            $ttl=esc(ui_t('contact')).' '.$bn; $reach=esc(ui_t('reach_us')); $help=esc(ui_t('how_we_can_help'));
            $lEmail=esc(ui_t('email')); $lPhone=esc(ui_t('phone')); $lLoc=esc(ui_t('location')); $lHours=esc(ui_t('support_hours'));
            $hoursVal=esc(ui_t('support_hours_value')); $emailUs=esc(ui_t('email_us'));
            $q1=esc(ui_t('product_questions')); $q2=esc(ui_t('order_status')); $q3=esc(ui_t('bulk_enquiries'));
            if(is_flatsome()){ $c=build_contact_flatsome($ttl,$lead,$bn,$ema,$ph,$loc,$hoursVal); }
            else { $inner ="<h1>$ttl</h1>\n<p>$lead</p>\n";
                $inner.="<h3>$reach</h3>\n<ul>\n<li><strong>$lEmail:</strong> <a href='mailto:$ema'>$ema</a></li>\n<li><strong>$lPhone:</strong> $ph</li>\n<li><strong>$lLoc:</strong> $loc</li>\n<li><strong>$lHours:</strong> $hoursVal</li>\n</ul>\n";
                $inner.="<h3>$help</h3>\n<ul>\n<li>$q1</li>\n<li>$q2</li>\n<li>$q3</li>\n</ul>\n";
                $c=page_wrap($inner); }   // non-Flatsome: clean single centered column
            $r=wp_update_post(['ID'=>$pid,'post_content'=>$c],true); $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$pid));
            if(is_wp_error($r)){ out('   [WRITE ERROR] contact: '.$r->get_error_message(),'#f66'); $pages_incomplete=true; }
            else { if(is_flatsome()) update_post_meta($pid,'_wp_page_template','page-blank.php'); else wcm_clear_our_template($pid);
                update_post_meta($pid,'_wcm_page_done',1); $progress=true; out("   [ok] contact (page id $pid) — wrote ".mb_strlen($c)." chars, DB now holds ".mb_strlen((string)$svd),'#6f6'); } } }
    if(AUTO_REFRESH && (time()-$pg0)>=AUTO_REFRESH_MAX_SECONDS){ $pages_paused=true; $pages_incomplete=true; }

    $legal=[]; if(DO_PRIVACY)$legal[]=['privacy-policy','Privacy Policy','Cover, in a standard trustworthy way, what data is collected, how it is used, cookies, third parties, user rights (CCPA-aware), and how to reach us.'];
    if(DO_TERMS)$legal[]=['terms-and-conditions','Terms and Conditions','Cover use of the site, orders, pricing, intellectual property, limitation of liability and governing law (USA), as a standard fair company policy.'];
    if(DO_SHIPPING)$legal[]=['shipping-policy','Shipping Policy','State clearly that we ship WORLDWIDE — anywhere in the world, both within the USA and internationally. Do NOT quote any exact shipping prices or dollar amounts; instead say the exact shipping cost is calculated automatically and shown at checkout before payment. Cover order processing/handling times, delivery estimates, worldwide coverage, order tracking, and possible customs delays, all in a reassuring positive tone.'];
    if(DO_REFUND)$legal[]=['refund_returns','Refund and Returns Policy','Cover eligibility, timeframes, the return process, refunds, exchanges and any non-returnable items as a standard, fair, customer-friendly policy, and point buyers to contact support to start a return.'];
    if(DO_FAQ)$legal[]=['faq','FAQ','Answer 8-10 real buyer questions (ordering, worldwide shipping, delivery times, returns, payment security, product quality, contacting support). Keep EVERY answer positive and confident and show off what the store can do: we ship to anywhere in the world, orders are handled quickly, the exact shipping cost is shown at checkout, support replies promptly by email and phone, and checkout is secure. Phrase questions as natural long-tail keywords (<h3> question + <p> answer). Never say the store cannot do something.'];
    if(DO_ABOUT)$legal[]=['about-us','About Us','Tell the store\'s story, what makes it trustworthy, and why to buy here — specific and positive, not generic.'];
    foreach($legal as $L){ if($pages_paused){ $pages_incomplete=true; break; }
        out("\n--- {$L[1]} ---",'#6cf'); write_page_via_ai($L[0],$L[1],page_prompt($L[1],$L[2]));
        if(AUTO_REFRESH && (time()-$pg0)>=AUTO_REFRESH_MAX_SECONDS){ $pages_paused=true; $pages_incomplete=true; } }   // set BOTH: if the LAST legal page trips the budget the foreach ends WITHOUT the top-of-loop guard running, so $job_done must already be kept false here (else the file would self-delete with blog/branding/logo unrun)

    // ---- BLOG: post #1 live now, the rest auto-scheduled every BLOG_CADENCE_DAYS ----
    // Resume-safe: the title list + a fixed start date are saved ONCE, so each title keeps a
    // stable schedule slot no matter how many refreshes it takes. Already-created titles are
    // skipped (no duplicates). BLOG_PER_RUN caps writes per load so a 45-post job can't time out.
    if(DO_BLOG && BLOG_COUNT>0 && !$pages_paused){
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
                $kwrows=read_keyword_csv();   // SEMrush export, ranked best-first; [] when BLOG_KEYWORDS_CSV is '' / missing / unparseable -> falls through to the AI-guessed titles below (today's behavior)
                if($kwrows){
                    $sel=array_slice($kwrows,0,BLOG_COUNT); usort($sel,fn($a,$b)=>$b['vol']<=>$a['vol']);   // top BLOG_COUNT keywords by rank, then highest SEARCH VOLUME first so post #1/live-now targets the strongest demand
                    $kws=array_map(fn($r)=>$r['kw'],$sel); $kwlist=''; foreach($kws as $i=>$k) $kwlist.=($i+1).". $k\n";
                    [$td]=ai_json("Here is an ORDERED list of ".count($kws)." real search keywords (from a SEMrush export) for ".brand()." selling ".STORE_NICHE.":\n$kwlist"
                        ."Write exactly ".count($kws)." blog article titles, ONE per keyword, in the SAME ORDER. Each title must FRONT-LOAD and target its exact keyword above (real search demand drives the topic), be specific, compelling and click-worthy, and be distinct from every other title. No numbering. ".lang_rule()."Return JSON: {\"titles\":[\"...\"]} — no comments, no trailing commas, same order and count as the keyword list.");
                    $gen=(is_array($td)&&!empty($td['titles']))?array_values((array)$td['titles']):[];
                    $titles=[]; foreach($kws as $i=>$k){ $t=trim((string)($gen[$i]??'')); $titles[]=$t!==''?$t:$k; }   // AI returned fewer/misaligned titles -> use the raw keyword for that slot, never crash
                } else {
                    [$td]=ai_json("Suggest exactly ".BLOG_COUNT." blog article titles for ".brand()." selling ".STORE_NICHE.". Each title must target a DISTINCT long-tail search keyword with NO overlap between titles (avoid keyword cannibalization). Front-load the keyword and keep titles specific and compelling. Use a HEALTHY MIX for topical authority: mostly informational/educational topics (how-to, guides, explainers, common buyer questions — these rank and earn links most easily) PLUS some commercial-intent topics (comparisons, 'best', 'how to choose', buying guides). Every topic must be relevant to the niche so it can link naturally to our products. No numbering. ".lang_rule()."Return JSON: {\"titles\":[\"...\"]} — no comments, no trailing commas.");
                    $titles=(is_array($td)&&!empty($td['titles']))?array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x),(array)$td['titles'])))):[];
                }
                $titles=array_slice(array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x),(array)$titles)))),0,BLOG_COUNT);   // dedupe+clean BOTH paths (the CSV path could otherwise emit two identical AI titles, silently dropping a post)
                if($titles){ update_option('wcm_blog_titles',wp_json_encode($titles),false);
                    update_option('wcm_blog_cornerstones',wp_json_encode(array_slice($titles,0,max(0,(int)BLOG_CORNERSTONE_COUNT))),false); } }   // first N = the highest-volume topics when CSV-seeded; written ONCE alongside the title cache so cornerstone status stays stable across resumes
            if(!$titles){ out('   [skip] blog — could not generate titles','#f66'); $blog_incomplete=true; }   // title call failed -> keep the file and retry next load; do NOT let $job_done self-delete with zero posts written
            else {
                $now=current_time('timestamp'); $base=(int)get_option('wcm_blog_start'); if($base<=0){ $base=$now; update_option('wcm_blog_start',$base,false); }   // fixed anchor date for the whole schedule
                $cad=max(1,(int)BLOG_CADENCE_DAYS); $cap=BLOG_PER_RUN>0?(int)BLOG_PER_RUN:PHP_INT_MAX;
                $cat_id=0; if(BLOG_CATEGORY!==''){ $bt=get_term_by('name',BLOG_CATEGORY,'category'); if($bt&&!is_wp_error($bt)) $cat_id=(int)$bt->term_id; else { $ins=wp_insert_term(BLOG_CATEGORY,'category'); if(!is_wp_error($ins)) $cat_id=(int)$ins['term_id']; } }
                $blogcat_ids=[];   // BLOG_AUTO_CATEGORIES: a SMALL fixed set of topical blog categories, built ONCE (capped at BLOG_CATEGORY_COUNT) and reused; each post is filed under the best fit. No tags (they cause thin-content bloat).
                if(BLOG_AUTO_CATEGORIES){ $blogcats=json_decode((string)get_option('wcm_blog_cats'),true);
                    if(!is_array($blogcats)||!$blogcats){ [$bc]=ai_json("Suggest between 3 and ".max(3,(int)BLOG_CATEGORY_COUNT)." BROAD, reusable blog category names for ".brand()."'s blog about ".STORE_NICHE.". Each must be broad enough to hold MANY articles — never one category per article. ".lang_rule()."Return JSON: {\"categories\":[\"...\"]} — no comments, no trailing commas.");
                        $blogcats=(is_array($bc)&&!empty($bc['categories']))?array_slice(array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x),(array)$bc['categories'])))),0,max(1,(int)BLOG_CATEGORY_COUNT)):[];   // max(1,...) so a misconfigured 0 can't empty the set and re-fire the AI call every refresh
                        if($blogcats) update_option('wcm_blog_cats',wp_json_encode($blogcats),false); }
                    foreach((array)$blogcats as $cn){ $cn=trim((string)$cn); if($cn==='') continue; $bt=get_term_by('name',$cn,'category'); if($bt&&!is_wp_error($bt)) $blogcat_ids[$cn]=(int)$bt->term_id; else { $ins=wp_insert_term($cn,'category'); if(!is_wp_error($ins)) $blogcat_ids[$cn]=(int)$ins['term_id']; } }
                    if($blogcat_ids) out('   blog categories ('.count($blogcat_ids).'): '.implode(', ',array_keys($blogcat_ids)),'#6cf'); }
                $made=0; $fail=0;
                $existing=array_flip($wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_type='post' AND post_status<>'trash'"));   // ONE query for all existing post titles -> O(1) dedup per title instead of a full-table title scan on every title
                foreach($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wcm_blog_title'") as $bk){ $existing[$bk]=1; }   // primary done-marker: the EXACT original title we stamped on each created post. Immune to WP re-encoding the stored post_title (naked '&' -> '&amp;' etc.), which would otherwise miss the match and re-create the post
                $bt0=time();
                foreach($titles as $idx=>$ti){ $ti=trim((string)$ti); if($ti==='') continue;
                    if(isset($existing[$ti])) continue;   // already created on a prior run — keeps its slot, don't touch
                    if($made>=$cap){ continue; }   // per-run cap reached: skip; the $uncreated coverage check below marks the run incomplete so the rest write next refresh
                    @set_time_limit(0);
                    $off=BLOG_FIRST_LIVE ? $idx*$cad : ($idx+1)*$cad; $when=$base+$off*86400; $due=($when<=$now);   // schedule slot FIRST — links are validated against WHEN THIS POST GOES LIVE, so it may link to any post that publishes at/before $dl
                    $status=$due?'publish':'future'; $dl=date('Y-m-d H:i:s',$when);
                    [$d,$err]=ai_json(blog_article_prompt($ti,$idx,$dl));
                    if(!$d||empty($d['content'])){ out("   [skip] $ti — ".($err?:'no content'),'#f66');
                        if(strpos((string)$err,'CREDIT')!==false){ out('   [STOP] AI credit/billing exhausted — refresh after fixing billing to resume.','#f66'); $blog_incomplete=true; $hard_stop=true; break; }
                        if(++$fail>=3){ out('   [STOP] 3 blog calls failed in a row — refresh to resume from here.','#f66'); $blog_incomplete=true; $hard_stop=true; break; } continue; }
                    $fail=0;
                    $html=demote_h1((string)$d['content']);              // theme already prints the title as the H1
                    $html=dedash($html);
                    [$html,$extk]=blog_filter_links($html,$dl);          // drop business/competitor links + any internal link not live by $dl; keep at most one authoritative source
                    $html=blog_topup_links($html,(int)BLOG_INTERNAL_LINKS,$dl);
                    if(BLOG_EXTERNAL_LINK && !$extk && BLOG_EXTERNAL_URL!==''){ $eh=preg_replace('/^www\./','',(string)parse_url(BLOG_EXTERNAL_URL,PHP_URL_HOST)); $html.="\n<p><em>Source: <a href=\"".esc_url(BLOG_EXTERNAL_URL)."\">".esc($eh?:'authoritative source')."</a></em></p>"; $extk=true; }   // deterministic external: only when the AI's authoritative outbound was missing/stripped
                    $html=append_disclaimer($html);
                    $args=['post_type'=>'post','post_title'=>$ti,'post_status'=>$status,'post_content'=>$html,'post_date'=>$dl,'post_date_gmt'=>get_gmt_from_date($dl)];
                    $post_cat=0;
                    if(BLOG_AUTO_CATEGORIES && $blogcat_ids){ $pc=strtolower(trim((string)($d['category']??''))); foreach($blogcat_ids as $nm=>$tid){ if(strtolower($nm)===$pc){ $post_cat=$tid; break; } } if(!$post_cat) $post_cat=reset($blogcat_ids); }   // file under the AI-chosen category from the fixed set; fall back to the first if its pick isn't in the set (so it can NEVER create a new category)
                    elseif($cat_id) $post_cat=$cat_id;
                    if($post_cat) $args['post_category']=[$post_cat];
                    $post=wp_insert_post($args,true);
                    if(is_wp_error($post)){ out("   [skip] $ti — ".$post->get_error_message(),'#f66'); continue; }
                    update_post_meta($post,'_wcm_blog_title',$ti);   // stamp the EXACT original title as the done-marker so this post is never re-created on a later batch/refresh, regardless of how WP stored post_title
                    $lp=site_link(post_pretty_link($post)); if($lp&&!is_wp_error($lp)) blog_known_posts(['url'=>$lp,'name'=>$ti,'date'=>$dl]);   // register this post (PRETTY url even though it's scheduled) so LATER posts in this run can link back to it
                    if(!empty($d['meta_title'])) update_post_meta($post,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                    if(!empty($d['meta_description'])) update_post_meta($post,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                    if(!empty($d['focus_keyword'])) update_post_meta($post,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                    update_post_meta($post,'rank_math_rich_snippet','article'); update_post_meta($post,'rank_math_snippet_article_type','BlogPosting');   // emit Article/BlogPosting schema (rich results) — the SAFE meta way; never write rank_math_schema_* as a JSON string (that fatals blog pages)
                    $made++; $existing[$ti]=1; $when_tag=$due?('live '.date('M j',$when)):('scheduled '.date('M j, Y',$when));
                    out("   [ok] $ti — $when_tag".($extk?' + authoritative link':''),'#6f6');
                    if(AUTO_REFRESH && (time()-$bt0)>=AUTO_REFRESH_MAX_SECONDS){ $blog_incomplete=true; break; }   // keep this load short so the end-of-page auto-refresh reliably fires under the proxy timeout; every post so far is already stamped, so resuming next load loses no work
                }
                if($made>0) $progress=true;   // posts were written this run — forward progress for the no-progress streak
                if(!$blog_incomplete){
                    $uncreated=0; foreach($titles as $tt){ $tt=trim((string)$tt); if($tt!=='' && !isset($existing[$tt])) $uncreated++; }   // completion is measured by ACTUAL coverage of every title (capped OR failed), not just the per-run cap counter — so a title whose article call failed is NOT silently dropped and marked done
                    if($uncreated>0){ $blog_incomplete=true; out("   [batch] wrote $made now; $uncreated post(s) still to write (capped or failed) — refresh the URL to continue.",'#6cf'); }
                    else { update_option('wcm_blog_done',1,false); out("   [done] all ".count($titles)." blog posts created (".$made." this run).",'#6f6'); } } } }
    }
}

// ---- PHASE: BRANDING (site title + colors + logo) --------------------------
if(!$batched && DO_BRANDING && !$pages_paused){ if(get_option('wcm_branding_done')) out("\n--- Branding --- (already done; RESET_PROGRESS to redo)",'#888'); else { out("\n--- Branding (name, colors, logo) ---",'#6cf');
    $ov=BRANDING_OVERWRITE;
    // 1) Site title + tagline (core options)
    $title=BRAND_NAME;   // the site title IS the brand name — nothing extra to set
    if($title!=='' && ($ov || trim((string)get_option('blogname'))==='')){ update_option('blogname',$title); out("   [ok] site title -> $title",'#6f6'); }
    if(SITE_TAGLINE!=='' && ($ov || trim((string)get_option('blogdescription'))==='')){ update_option('blogdescription',SITE_TAGLINE); out("   [ok] tagline set",'#6f6'); }
    // 2) Color palette -> Flatsome theme mods (only where unset, unless overwrite) — site_palette() is the single source of
    // truth (cached in wcm_palette) so the homepage, built earlier in the Pages phase, and this phase always agree on colors.
    $pal=site_palette();
    out("   palette ({$pal['source']}): primary {$pal['primary']} / secondary {$pal['secondary']} / accent {$pal['accent']}",'#6cf');
    $setmod=function($key,$val) use($ov){ if($val==='') return false; if(!$ov && get_theme_mod($key)) return false; set_theme_mod($key,$val); return true; };
    $cn=0; if($setmod('color_primary',$pal['primary'])) $cn++; if($setmod('color_secondary',$pal['secondary'])) $cn++;
    if($setmod('color_success',$pal['accent'])) $cn++; if($setmod('color_links',$pal['primary'])) $cn++;
    if($setmod('color_links_hover',$pal['dark'])) $cn++;
    out("   [ok] set $cn Flatsome color option(s)",'#6f6');
    // 3) Logo — provided URL (LOGO_URL) only. Only if none set, unless overwrite.
    $have_logo = get_theme_mod('site_logo') || get_theme_mod('custom_logo');
    if($have_logo && !$ov){ out('   [skip] logo already set (BRANDING_OVERWRITE=false)','#888'); }
    elseif(LOGO_URL!==''){ $att=sideload_logo(LOGO_URL);
        if(!$att){ out('   [skip] logo — could not fetch LOGO_URL','#fa0'); }
        else { $u=wp_get_attachment_url($att); if($u) set_theme_mod('site_logo',$u); set_theme_mod('custom_logo',$att); update_option('wcm_logo_att',$att,false); out('   [ok] logo set from LOGO_URL','#6f6'); } }
    else { out('   [skip] logo — set LOGO_URL to use one (auto-generation removed)','#888'); }
    update_option('wcm_branding_done',1,false); } }

// ---- PHASE: LOGO AS PRODUCT IMAGE (fill products that have NO featured image) ------
// No image API: point each imageless product's thumbnail at the ONE resolved logo attachment (reused, so no duplicate media).
// Runs after branding so a just-generated/URL logo is available. Idempotent + self-limiting (only products still missing an image).
if(!$batched && USE_LOGO_AS_PRODUCT_IMAGE && !$pages_paused){ out("\n--- Logo as product image ---",'#6cf');
    $logo_att=resolve_logo_attachment();
    if(!$logo_att){ out('   [skip] no logo available — set LOGO_URL, run branding, or have a theme logo first','#fa0'); }
    else { $miss=$wpdb->get_col(
        "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} t ON t.post_id=p.ID AND t.meta_key='_thumbnail_id'
          WHERE p.post_type='product' AND p.post_status NOT IN ('trash','auto-draft') AND (t.meta_value IS NULL OR t.meta_value='' OR t.meta_value='0')");   // O(products still missing an image)
        $n=0; foreach($miss as $pp){ $pp=(int)$pp; set_post_thumbnail($pp,$logo_att); $n++; }
        out("   set the logo as the image on $n product(s) that had none",'#6f6'); } }

// best-effort cache purge so new content/prices/stock show without a manual cache clear (each is a no-op if not installed)
if(function_exists('wp_cache_flush')) wp_cache_flush();          // object cache (Redis/Memcached)
do_action('litespeed_purge_all');                                // LiteSpeed (Hostinger default)
if(function_exists('rocket_clean_domain')) rocket_clean_domain();// WP Rocket
if(function_exists('w3tc_flush_all')) w3tc_flush_all();          // W3 Total Cache
if(function_exists('wpfc_clear_all_cache')) wpfc_clear_all_cache();// WP Fastest Cache
out("\nCleared caches (object + common page-cache plugins).",'#6cf');

out("\nDone. Products processed: $processed".($aborted?' — STOPPED EARLY (see red messages); re-open the URL to resume.':($batched?' (more to do — refresh to continue).':'.')),'#6cf');
$job_done = (!$batched && $left===0 && !$blog_incomplete && !$pages_incomplete);   // everything finished: products done, no blog batch still pending, no page generation failure left unwritten
if($job_done){ delete_option('wcm_noprog'); }
elseif($progress){ update_option('wcm_noprog',0,false); }
else { $np=(int)get_option('wcm_noprog')+1; update_option('wcm_noprog',$np,false); if($np>=2) $hard_stop=true; }   // no forward progress twice in a row (e.g. a dead API key returning 401, or a tiny catalog that never hits the 3-in-a-row abort) -> pause instead of reloading forever
if($job_done && $products_complete){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'"); delete_option('wcm_reset_done'); }   // clear product resume flags + re-arm RESET only when the WHOLE job is done. Clearing while the blog is still batching across refreshes would wipe the done-flags and reprocess the ENTIRE catalog on every refresh
if($job_done && SELF_DELETE_WHEN_DONE){ if(@unlink(__FILE__)) out('This file deleted itself. ✅','#6f6'); else out('Could not auto-delete — delete this file manually.','#fa0'); }
elseif(!$job_done) out('>>> Not fully done — re-open the URL to finish, then delete this file. <<<','#fa0');
// AUTO_REFRESH: the browser reloads itself (?key preserved) to run the next batch — NOT a hard stop and NOT already done. Paused on a hard stop so it never hammers the AI.
if(AUTO_REFRESH && !$job_done && !$hard_stop){ $sec=max(3,(int)AUTO_REFRESH_SECONDS);
    out("Auto-continuing in {$sec}s. Keep this tab open. It stops and self-deletes when everything is finished.",'#6cf');
    echo "<meta http-equiv=\"refresh\" content=\"{$sec}\">";                        // fires even in a background/inactive tab (not subject to JS timer throttling); bare content=N reloads current URL, preserving ?key
    echo "<script>setTimeout(function(){location.reload();},".($sec*1000).");</script>"; }
elseif(AUTO_REFRESH && !$job_done && $hard_stop){ out('Auto-refresh paused: a hard stop occurred (AI billing, API key, or repeated failures). Fix the issue, then re-open the URL to resume.','#fa0'); }
echo "</body>";
