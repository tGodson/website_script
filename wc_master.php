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
const SECRET = 'change-me-letters-and-numbers-only';   // ?key=THIS  (no # & % symbols)

// ---- AI provider -----------------------------------------------------------
const AI_PROVIDER = 'claude';                // 'gemini' (free) or 'claude'
const GEMINI_API_KEY = '';                   // https://aistudio.google.com/apikey
const GEMINI_MODEL   = 'gemini-2.0-flash';
const GEMINI_RPM     = 10;
const ANTHROPIC_API_KEY = '';                // https://console.anthropic.com  <-- paste your Claude key here
const CLAUDE_MODEL      = 'claude-haiku-4-5';  // fast + high rate limits, ideal for bulk. (Opus = 'claude-opus-4-8' if you want top quality)
const CLAUDE_RPM        = 0;                   // 0 = NO throttle (full speed, like the old script — fine for Haiku/Sonnet). Set ~5 ONLY if you use Opus and hit rate limits
const CLAUDE_MAX_TOKENS = 4096;              // max output tokens per call. Smaller = fewer rate-limit hits; RAISE it if long descriptions get cut off

// ---- Store identity --------------------------------------------------------
const BRAND_NAME = '';                        // '' = site title
const TAGLINE    = '';                        // '' = site tagline
const STORE_NICHE = 'general consumer products';   // <-- EDIT: what the store sells
const SALES_ORIENTED = true;
const REFERENCE_URL = '';   // OPTIONAL: one URL of a comparable store's product or shop page. If set, the script reads its
                            // price tier, unit style (simple/variable), description depth and category naming as GUIDANCE for
                            // the AI (which still writes 100% original copy). Leave '' to skip. Best on sites with JSON-LD product data.

// ---- PRODUCT operations: toggle each ON/OFF --------------------------------
const DO_SHORT_DESC   = true;   const OVERWRITE_SHORT = false;  // false = append after existing
const DO_LONG_DESC    = true;   const OVERWRITE_LONG  = false;  // false = append after existing
const SHORT_DESC_WORDS    = 130;   // target words for the short description (edit per project)
const LONG_DESC_MIN_WORDS = 650;   // minimum words for the long description (edit per project)
const DO_META         = true;   const OVERWRITE_META  = true;   // Rank Math meta
const DO_TAGS         = true;   const OVERWRITE_TAGS  = false;  // false = fill only if empty
const DO_PRICE        = true;   const OVERWRITE_PRICE = false;  // false = set only if empty
const PRODUCT_TYPE    = 'simple';               // 'simple' or 'variable' (AI proposes options)
const PRICE_ENDING    = '.99';                  // '' = whole number
const SHORT_DESC_INCLUDE_UNIT = true;           // add "Sold as: <unit>" to short desc?
const MAX_TAGS        = 5;

const DO_INTERLINKS   = true;   const INTERLINKS_PER_PRODUCT = 3;   // deterministic, no AI
const REMOVE_FOREIGN_LINKS = true; // strip links pointing to OTHER domains (keeps the anchor text)
const SITE_DOMAIN = '';             // your REAL domain, e.g. 'https://mysite.com'. Leave EMPTY to auto-detect
                                    // from the live site. Set it when running locally or on a temporary URL so
                                    // generated interlinks + foreign-link stripping use your real domain.

// ---- IMAGES (Ideogram) -----------------------------------------------------
const DO_IMAGE            = false;
const SKIP_IF_HAS_IMAGE   = true;
const IDEOGRAM_API_KEY    = '';
const IDEOGRAM_MODEL      = 'V_2';
const IDEOGRAM_ASPECT     = 'ASPECT_1_1';
const IMAGE_STYLE = 'clean professional product photograph of a single isolated product, centered on a '
                  . 'pure white background, studio softbox lighting, sharp focus, high detail, no people, '
                  . 'no human faces, no hands, no text';
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
const DO_BLOG       = false;   const BLOG_COUNT = 3;
const OVERWRITE_PAGES = true;                 // legal/info pages: overwrite if they already have content

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
const FORCE_IN_STOCK        = false;           // set every product + variation status to "In stock" (and keep it there)
const SELF_DELETE_WHEN_DONE = true;
const PRODUCT_STATUSES = ['publish', 'draft', 'pending', 'private'];
const PUBLISH_STATE = 'publish';               // pages/blog status (you chose publish)
const REPLACE_PRODUCT_CATEGORIES = false;      // ONLY used by DO_CATEGORIES grouping. false = ADD the grouped category and
                                               // KEEP the product's existing categories (safe). true = REMOVE existing
                                               // categories and replace them (destructive — this is what wiped categories).
const APPEND_MARKER = '<span class="wcm-added"></span>';  // guards "append" so it only happens once. A <span> survives WordPress kses; an HTML comment gets stripped when saving unauthenticated
const APPEND_SIG    = 'wcm-added';             // stable substring to detect the marker (matches the new span AND the old comment — backward-compatible)
const ENABLE_LAWFUL_USE_GUARD = true;

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
echo "<!doctype html><meta charset='utf-8'><title>WC Master</title>";
echo "<body style='font:14px/1.5 monospace;background:#111;color:#ddd;padding:20px'>", str_repeat(' ', 4096);
function out($m,$c='#ddd'){ echo "<div style='color:$c'>".esc_html($m)."</div>\n"; flush(); }
function esc($s){ return esc_html((string) $s); }
function brand(){ return BRAND_NAME !== '' ? BRAND_NAME : get_bloginfo('name'); }
function tagline(){ return TAGLINE !== '' ? TAGLINE : get_bloginfo('description'); }
function currency(){ return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD'; }
function shop_url(){ $u = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : ''; return $u ?: home_url('/'); }

// ---------------------------------------------------------------------------
// AI stack (Gemini / Claude) — throttle, billing-aware errors, tolerant parser
// ---------------------------------------------------------------------------
function ai_throttle(){ static $last=0.0; $rpm=(AI_PROVIDER==='gemini')?GEMINI_RPM:CLAUDE_RPM; $iv=$rpm>0?60.0/$rpm:0.0;   // rpm=0 => no throttle (full speed)
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
        [$t,$err]=(AI_PROVIDER==='gemini')?ai_text_gemini($prompt):ai_text_claude($prompt);
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
function round_price($v){ $v=(float)$v; if($v<=0) return ''; return (strlen(PRICE_ENDING)&&PRICE_ENDING[0]==='.')?((int)$v).PRICE_ENDING:(string)round($v); }
function excerpt($html,$n=280){ return trim(mb_substr(wp_strip_all_tags((string)$html),0,$n)); }
function existing_hrefs($html){ preg_match_all('/href=["\']([^"\']+)["\']/',(string)$html,$m); return $m[1]; }
// --- domain handling: use SITE_DOMAIN when set, otherwise the live site's own URL ---
function site_base(){ static $b=null; if($b===null){ $b=SITE_DOMAIN!==''?rtrim(SITE_DOMAIN,'/'):rtrim((string)home_url(),'/'); } return $b; }
function site_host(){ static $h=null; if($h===null){ $h=preg_replace('/^www\./','',strtolower((string)parse_url(site_base(),PHP_URL_HOST))); } return $h; }
function site_link($u){ if(SITE_DOMAIN===''||!$u||!is_string($u)) return $u; $h=rtrim((string)home_url(),'/'); $b=rtrim(SITE_DOMAIN,'/'); if($h===$b) return $u; return strpos($u,$h)===0?$b.substr($u,strlen($h)):$u; }
// --- foreign-link stripping: keep this site's links, unwrap links to other domains ---
function is_foreign_url($url){ $url=trim((string)$url); if($url==='') return false;
    if($url[0]==='#') return false;                                   // in-page anchor
    if(strncmp($url,'//',2)!==0 && $url[0]==='/') return false;        // root-relative = internal
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    if(in_array($scheme,['mailto','tel','javascript'],true)) return false;
    $host=parse_url($url,PHP_URL_HOST); if(!$host) return false;       // relative (no host) = internal
    return preg_replace('/^www\./','',strtolower($host))!==site_host(); }
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
/** apply text with append-once / overwrite / fill. Returns [value, changed]. */
function apply_text($cur,$new,$overwrite){ $cur=(string)$cur; $new=(string)$new; if($new==='') return [$cur,false];
    if(trim($cur)==='') return [$new,true];
    if($overwrite) return [$new,true];
    if(strpos($cur,APPEND_SIG)!==false) return [$cur,false];
    return [$cur."\n".APPEND_MARKER."\n".$new,true]; }

// ---------------------------------------------------------------------------
// Images (Ideogram + optional GD/Imagick watermark)
// ---------------------------------------------------------------------------
function ideogram_url($prompt){ if(IDEOGRAM_API_KEY==='') return [null,'IDEOGRAM_API_KEY empty'];
    for($a=0;$a<4;$a++){ $r=wp_remote_post('https://api.ideogram.ai/generate',['timeout'=>120,
        'headers'=>['Api-Key'=>IDEOGRAM_API_KEY,'Content-Type'=>'application/json'],
        'body'=>wp_json_encode(['image_request'=>['prompt'=>$prompt,'model'=>IDEOGRAM_MODEL,'magic_prompt_option'=>'OFF','style_type'=>'REALISTIC','aspect_ratio'=>IDEOGRAM_ASPECT]])]);
        if(is_wp_error($r)) return [null,$r->get_error_message()];
        $code=wp_remote_retrieve_response_code($r); if($code==429){ sleep(30*($a+1)); continue; }
        if($code!=200) return [null,"Ideogram $code"]; $u=(json_decode(wp_remote_retrieve_body($r),true)['data'][0]['url']??'');
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
function attach_image($url,$pid,$alt,$name){ $tmp=download_url($url,120); if(is_wp_error($tmp)) return [0,$tmp->get_error_message()];
    $ext=strtolower(pathinfo((string)parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION)); if(!in_array($ext,['png','jpg','jpeg','webp','gif'],true)) $ext='png';
    if(WATERMARK_LOGO!=='' && watermark($tmp)) $ext='png';
    $att=media_handle_sideload(['name'=>sanitize_title($name).'-'.$pid.'.'.$ext,'tmp_name'=>$tmp],$pid,$name);
    if(is_wp_error($att)){ @unlink($tmp); return [0,$att->get_error_message()]; }
    set_post_thumbnail($pid,$att); update_post_meta($att,'_wp_attachment_image_alt',$alt); return [$att,'']; }
function form_hint($h){ foreach(['vial'=>'a single labeled glass vial','ampoule'=>'a sealed glass ampoule','syringe'=>'a prefilled medical syringe','capsule'=>'an amber pill bottle of capsules','tablet'=>'a blister strip of tablets','powder'=>'a sealed jar of powder','sachet'=>'a sealed sachet','pouch'=>'a resealable pouch','tube'=>'a labeled tube','jar'=>'a labeled jar','engine'=>'a complete automotive engine','bottle'=>'a labeled bottle','liter'=>'a labeled liquid container'] as $k=>$v){ if(strpos($h,$k)!==false) return $v; } return ''; }
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
    if($need['short']){ $req.="- short_description: marketing HTML, ~".SHORT_DESC_WORDS." words.\n"; $keys[]='short_description'; }
    if($need['long']){ $req.="- long_description: valid HTML, ".LONG_DESC_MIN_WORDS."+ words, with <h2> Overview, <h2> Key Features (a <ul>), <h2> Specifications (a small <table>), <h2> FAQ (3 <h3> question + <p> answer), closing CTA.\n"; $keys[]='long_description'; }
    if($need['meta']){ $req.="- meta_title (<=60 chars, end ' | ".brand()."'), meta_description (<=155 chars, focus keyword), focus_keyword.\n"; array_push($keys,'meta_title','meta_description','focus_keyword'); }
    if($need['tags']){ $req.="- tags: 3-5 short relevant tags.\n"; $keys[]='tags'; }
    if($need['price']){ if(PRODUCT_TYPE==='variable'){ $req.="- attribute: the measurement dimension with unit (e.g. 'Dosage (mg)', 'Volume (L)', 'Quantity'). variations: 2-5 {label,price} where label is a value in that unit (e.g. '10mg','5L','2') and price is a plain number in $cur.\n"; array_push($keys,'attribute','variations'); } else { $req.="- price: realistic AVERAGE MARKET PRICE, plain number in $cur.\n"; $keys[]='price'; } }
    if($need['unit']){ $req.="- unit: what ONE purchase includes, with measurement (e.g. 'per 10 mg vial', 'per 5 L container', 'each (1 unit)').\n"; $keys[]='unit'; }
    if($need['image']){ $req.="- image_subject: literal physical form/packaging for a product photo, matching the unit (e.g. 'a single 10 mg amber glass vial', 'a complete automotive engine').\n"; $keys[]='image_subject'; }
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
    $cur=trim((string)get_post_field('post_content',$pid)); if($cur!=='' && !OVERWRITE_PAGES){ out("   [skip] $title already has content (OVERWRITE_PAGES=false)",'#888'); return; }
    [$d,$err]=ai_json($prompt); if(!$d||empty($d['content'])){ out("   [skip] $title — ".($err?:'no content'),'#f66'); return; }
    $c=append_disclaimer(dedash((string)$d['content']));
    wp_update_post(['ID'=>$pid,'post_content'=>$c]);
    if(!empty($d['meta_title'])) update_post_meta($pid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
    if(!empty($d['meta_description'])) update_post_meta($pid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
    if(!empty($d['focus_keyword'])) update_post_meta($pid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
    out("   [ok] $title",'#6f6'); }
function page_prompt($what,$extra=''){ return "Write the '$what' page for ".brand().", a US-based online store selling ".STORE_NICHE.". "
    ."$extra\n".voice_rules().compliance_clause()
    ."Valid HTML, proper heading hierarchy (one <h1>, then <h2>). Use SINGLE quotes for HTML attributes. "
    ."Return ONE valid JSON object: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"} — no comments, no trailing commas, single-line HTML value."; }

// --- Flatsome homepage assembler (from the homepage builder) ---
function build_flatsome($f,$cats,$prods){ $shop=shop_url(); $btn=esc($f['cta_button']??'Shop Now'); $o='';
    $o.="[section label=\"Hero\" padding=\"60px\"]\n[row]\n[col span__sm=\"12\"]\n<h1>".esc($f['hero_headline']??brand())."</h1>\n<p>".esc($f['hero_intro']??'')."</p>\n[button text=\"$btn\" link=\"".esc_url($shop)."\"]\n[/col]\n[/row]\n[/section]\n";
    if($cats){ $li=''; foreach($cats as $c){ if(!empty($c['url'])) $li.='<li><a href="'.esc_url($c['url']).'">'.esc($c['name']).'</a></li>'; }
        $o.="[section label=\"Categories\"]\n[row]\n[col span__sm=\"12\"]\n<h2>".esc($f['categories_heading']??'Shop by Category')."</h2>\n<ul>$li</ul>\n[/col]\n[/row]\n[/section]\n"; }
    $pts=is_array($f['why_us_points']??null)?$f['why_us_points']:[];
    if($pts){ $sp=count($pts)>=3?4:(count($pts)===2?6:12); $o.="[section label=\"Why Us\"]\n[row]\n[col span__sm=\"12\"]\n<h2>".esc($f['why_us_heading']??'Why Choose Us')."</h2>\n[/col]\n[/row]\n[row]\n";
        foreach($pts as $pt){ $t=esc(is_array($pt)?($pt['title']??''):$pt); $dd=esc(is_array($pt)?($pt['text']??''):''); $o.="[col span=\"$sp\" span__sm=\"12\"]\n[featured_box]\n<h3>$t</h3>\n".($dd!==''?"<p>$dd</p>\n":'')."[/featured_box]\n[/col]\n"; }
        $o.="[/row]\n[/section]\n"; }
    if($prods){ $ids=implode(',',array_map(fn($p)=>(int)$p['id'],$prods)); $o.="[section label=\"Featured\"]\n[row]\n[col span__sm=\"12\"]\n<h2>".esc($f['featured_heading']??'Featured Products')."</h2>\n[ux_products ids=\"$ids\"]\n[/col]\n[/row]\n[/section]\n"; }
    if(!empty($f['about_text'])){ $o.="[section label=\"About\"]\n[row]\n[col span__sm=\"12\"]\n<h2>".esc($f['about_heading']??'About Us')."</h2>\n<p>".esc($f['about_text'])."</p>\n[/col]\n[/row]\n[/section]\n"; }
    if(is_array($f['faq']??null)&&$f['faq']){ $q="<h2>Frequently Asked Questions</h2>\n"; foreach($f['faq'] as $qa){ $qq=esc($qa['q']??''); if($qq)$q.="<h3>$qq</h3>\n<p>".esc($qa['a']??'')."</p>\n"; } $o.="[section label=\"FAQ\"]\n[row]\n[col span__sm=\"12\"]\n".$q."[/col]\n[/row]\n[/section]\n"; }
    if(!empty($f['closing_cta'])){ $o.="[section label=\"CTA\"]\n[row]\n[col span__sm=\"12\"]\n<p>".esc($f['closing_cta'])."</p>\n[button text=\"$btn\" link=\"".esc_url($shop)."\"]\n[/col]\n[/row]\n[/section]\n"; }
    if(COMPLIANCE_MODE&&DISCLAIMER_HTML) $o.="[section]\n[row]\n[col span__sm=\"12\"]\n".DISCLAIMER_HTML."\n[/col]\n[/row]\n[/section]\n";
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
    $P[$po->ID]=['title'=>$po->post_title,'short'=>$po->post_excerpt]; }
$ids=array_keys($P);
out('Found '.count($ids).' products');

if (ENABLE_LAWFUL_USE_GUARD) { $hay='';
    foreach($P as $d){ $hay.=strtolower($d['title']).' '; }
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ $hay.=strtolower($t->name).' '; }
    $hits=array_values(array_unique(array_filter($GUARD_TERMS,fn($t)=>strpos($hay,$t)!==false)));
    if($hits){ out('[ABORTED] Lawful-use guard: '.implode(', ',$hits),'#f66'); out('This tool is for lawful catalogs only.','#f66'); exit; } }

// ---- RESET / REPROCESS (fires ONCE per arming; independent of which phases are enabled) ------------
delete_option('wcm_offset');   // retire the old positional-offset resume
if(RESET_PROGRESS || REPROCESS_IDS){ if(!get_option('wcm_reset_done')){   // a saved marker stops it repeating on refresh
    if(RESET_PROGRESS){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'"); $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key='_wcm_cat_done'"); out("\nRESET_PROGRESS — progress wiped ONCE; reprocessing every product + category.",'#fa0'); }
    if(REPROCESS_IDS){ $rids=implode(',',array_map('intval',(array)REPROCESS_IDS)); if($rids!==''){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done' AND post_id IN ($rids)"); out("\nREPROCESS_IDS — redoing ".count((array)REPROCESS_IDS)." specific product(s) ONCE.",'#fa0'); } }
    update_option('wcm_reset_done',1,false); } }
else delete_option('wcm_reset_done');   // both off = re-arm for next time

// ---- PHASE: CATEGORIES (single catalog pass) -------------------------------
$primary=[]; // pid => term_id
// on a batch resume (some products already flagged done) categories were built on the first pass — don't redo the AI mapping
$cat_resume_skip = DO_CATEGORIES && !RESET_PROGRESS && MAX_PRODUCTS_PER_RUN>0
    && (int)$wpdb->get_var("SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done' LIMIT 1")>0;
if ($cat_resume_skip) out("\n--- Categories --- (already built on the first batch; skipping)",'#888');
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
    }
}
// fill primary term for products not just categorized (use existing first product_cat)
foreach($P as $pid=>$d){ if(isset($primary[$pid])) continue; $tt=get_the_terms($pid,'product_cat');
    $primary[$pid]=(is_array($tt)&&$tt)?(int)$tt[0]->term_id:0; }
// interlink groups by primary term
$groups=[]; foreach($primary as $pid=>$tid){ $groups[$tid][]=$pid; }
function related_of($pid,$primary,$groups,$P){ $out=[]; foreach(($groups[$primary[$pid]]??[]) as $o){ if($o==$pid) continue;
    $out[]=['name'=>$P[$o]['title'],'url'=>site_link(get_permalink($o))]; if(count($out)>=INTERLINKS_PER_PRODUCT) break; } return $out; }

// ---- PHASE: PRODUCTS -------------------------------------------------------
$batched=false; $processed=0; $aborted=false; $products_complete=false; $left=0;
$want_product_ai = DO_SHORT_DESC||DO_LONG_DESC||DO_META||DO_TAGS||DO_PRICE;
if ($want_product_ai || DO_INTERLINKS || DO_IMAGE || REMOVE_FOREIGN_LINKS) {
    out("\n--- Products ---",'#6cf'); $tot=count($P); global $wpdb;
    // RESUME: each finished product carries a '_wcm_done' flag and is skipped. One query loads the whole done-set, so a
    // refresh continues exactly where it left off (even after a server timeout) and never re-touches a finished product
    // — so content is never duplicated. MAX_PRODUCTS_PER_RUN just caps how many NEW products to do per run (0 = all).
    $done=array_flip((array)$wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'"));
    $todo=[]; foreach($P as $pid=>$row){ if(!isset($done[$pid])){ $todo[]=$pid; if(MAX_PRODUCTS_PER_RUN>0 && count($todo)>=MAX_PRODUCTS_PER_RUN) break; } }
    out('   '.count($done).' already done · '.count($todo).' to do now · '.max(0,$tot-count($done)-count($todo)).' left after this run','#6cf');
    if($todo) update_meta_cache('post',$todo);
    $i=count($done); $fail=0; $handled=0;
    foreach($todo as $pid){ $i++; @set_time_limit(0); $p=wc_get_product($pid); if(!$p){ update_post_meta($pid,'_wcm_done',1); $handled++; continue; } $name=$p->get_name();
        $short=$p->get_short_description(); $long=$p->get_description(); $price=$p->get_regular_price();
        $has_var=$p->is_type('variable') && !empty($p->get_children());   // already has variations?
        $tid=$primary[$pid]; $tobj=$tid?get_term($tid):null; $catname=($tobj&&!is_wp_error($tobj))?$tobj->name:''; $cat_url=$tid?get_term_link($tid):''; if(is_wp_error($cat_url)) $cat_url=''; $cat_url=site_link($cat_url);
        $meta_empty = DO_META && !OVERWRITE_META && (!get_post_meta($pid,'rank_math_title',true)||!get_post_meta($pid,'rank_math_description',true)||!get_post_meta($pid,'rank_math_focus_keyword',true));
        $need=['short'=>DO_SHORT_DESC&&(OVERWRITE_SHORT||trim($short)===''||strpos($short,APPEND_SIG)===false),
               'long'=>DO_LONG_DESC&&(OVERWRITE_LONG||trim($long)===''||strpos($long,APPEND_SIG)===false),
               'meta'=>DO_META&&(OVERWRITE_META||$meta_empty),
               'tags'=>DO_TAGS&&(OVERWRITE_TAGS||!get_the_terms($pid,'product_tag')),   // get_the_terms hits the primed cache — no per-product query
               'price'=>DO_PRICE&&(OVERWRITE_PRICE||(PRODUCT_TYPE==='variable'?!$has_var:($price===''||$price===null))),
               'image'=>false,'unit'=>false];
        $need['unit']=($need['short']&&SHORT_DESC_INCLUDE_UNIT)||$need['price']||DO_IMAGE;
        $need['image']=DO_IMAGE; // request image_subject when generating
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
            foreach($data['variations'] as $v){ $lb=trim((string)($v['label']??'')); $pr=round_price($v['price']??0); if($lb!==''&&$pr!=='') $clean[$lb]=$pr; }
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

        $dirty=false;
        // short description (+ optional unit line)
        if($need['short']){ $ns=dedash(demote_h1((string)($data['short_description']??''))); if(SHORT_DESC_INCLUDE_UNIT&&$unit!==''&&stripos($ns,'sold as')===false) $ns.="\n<p><strong>Sold as:</strong> ".esc($unit).'.</p>';
            [$val,$ch]=apply_text($p->get_short_description(),$ns,OVERWRITE_SHORT); if($ch){ $p->set_short_description($val); $dirty=true; } }
        if(REMOVE_FOREIGN_LINKS){ $sd=$p->get_short_description(); $sdc=strip_foreign_links($sd); if($sdc!==$sd){ $p->set_short_description($sdc); $dirty=true; } }
        // long description (+ interlinks + disclaimer)
        $long0=$p->get_description(); $long_cur=$long0;
        if($need['long']){ $nl=dedash(demote_h1((string)($data['long_description']??''))); [$val,$ch]=apply_text($long_cur,$nl,OVERWRITE_LONG); if($ch){ $long_cur=$val; } }
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

        if(DO_IMAGE && !(SKIP_IF_HAS_IMAGE&&has_post_thumbnail($pid))){ [$u,$e]=ideogram_url(image_subject($pid,$name,$catname).'. '.IMAGE_STYLE.'.');
            if($u){ [$att,$e2]=attach_image($u,$pid,$name.' product image',$name); if($att) out('   [image ok]','#6f6'); else out("   [image] $e2",'#fa0'); } else out("   [image] $e",'#fa0'); }

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
if(!$batched && FORCE_IN_STOCK){ out("\n--- Forcing stock status: In stock ---",'#6cf'); $sn=0;
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
    out("   set $sn products/variations in stock",'#6f6'); }

// ---- PHASE: REMOVE FOREIGN LINKS (categories + pages + posts) --------------
if(!$batched && REMOVE_FOREIGN_LINKS){ out("\n--- Removing foreign links ---",'#6cf'); $fn=0;
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ $d=(string)$t->description; $c=strip_foreign_links($d); if($c!==$d){ wp_update_term($t->term_id,'product_cat',['description'=>$c]); $fn++; } }
    foreach(get_posts(['post_type'=>['page','post'],'post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']) as $pp){ $d=(string)get_post_field('post_content',$pp); $c=strip_foreign_links($d); if($c!==$d){ wp_update_post(['ID'=>$pp,'post_content'=>$c]); $fn++; } }
    out("   cleaned $fn category/page/post items (product descriptions were cleaned in the product pass)",'#6f6'); }

// ---- PHASE: CATEGORY DESCRIPTIONS ------------------------------------------
if(!$batched && DO_CATEGORY_CONTENT){ out("\n--- Category descriptions ---",'#6cf');
    foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t){ if(strtolower($t->slug)==='uncategorized') continue;
        if(get_term_meta($t->term_id,'_wcm_cat_done',true)){ out("   [skip] {$t->name} (already done — set RESET_PROGRESS=true to redo)",'#888'); continue; }
        $has=trim((string)$t->description)!==''; if($has&&!OVERWRITE_CATEGORY_DESC){ out("   [skip] {$t->name}",'#888'); continue; }
        $plinks=[]; foreach(($groups[$t->term_id]??array_slice(get_posts(['post_type'=>'product','fields'=>'ids','numberposts'=>8,'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id]]]),0,8)) as $pp){ $plinks[]=['name'=>get_the_title($pp),'url'=>site_link(get_permalink($pp))]; if(count($plinks)>=8) break; }
        [$d,$err]=ai_json("Write an SEO description (~200 words) for the product category \"{$t->name}\" at ".brand()." selling ".STORE_NICHE.". ".voice_rules().compliance_clause()."Open with the focus keyword; explain what it covers and why buy here; one <h2>. No invented links. ".html_quote_rule()."Return JSON: {\"description\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}");
        if(!$d||empty($d['description'])){ out("   [skip] {$t->name} — ".($err?:'no content'),'#f66'); continue; }
        $desc=dedash((string)$d['description']); $have=existing_hrefs($desc); $li='';
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
if(!$batched){
    // featured products + category links for homepage
    $topcats=[]; foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>0]) as $t){ if(strtolower($t->slug)==='uncategorized') continue; $lk=get_term_link($t); if(!is_wp_error($lk)) $topcats[]=['name'=>$t->name,'url'=>site_link($lk)]; }
    $feat=[]; foreach(array_slice(array_keys($P),0,6) as $pid){ $feat[]=['id'=>$pid,'name'=>$P[$pid]['title'],'url'=>site_link(get_permalink($pid))]; }

    if(DO_HOMEPAGE){ out("\n--- Homepage ---",'#6cf'); $fid=resolve_front();
        $cur=trim((string)get_post_field('post_content',$fid));
        if($cur!=='' && !OVERWRITE_HOMEPAGE){ out('   [skip] homepage has content (OVERWRITE_HOMEPAGE=false)','#888'); }
        else { $cl=''; foreach($topcats as $c){ $cl.='  - '.$c['name']."\n"; } $pl=''; foreach($feat as $f){ $pl.='  - '.$f['name']."\n"; }
            [$d,$err]=ai_json("Write HOMEPAGE copy for ".brand()." selling ".STORE_NICHE.". Tagline: \"".tagline()."\".\nCategories:\n$cl\nFeatured:\n$pl\n".voice_rules().compliance_clause()."Plain text fields only (no HTML). Give 3-4 why_us_points and 3-4 faq. ".( "Return ONE valid JSON object, no comments/trailing commas, with keys: hero_headline, hero_intro, cta_button, categories_heading, why_us_heading, why_us_points (array of {title,text}), featured_heading, about_heading, about_text, faq (array of {q,a}), closing_cta, meta_title, meta_description, focus_keyword."));
            if(!$d||empty($d['hero_headline'])){ out('   [skip] homepage — '.($err?:'no content'),'#f66'); }
            else { if(HOMEPAGE_FORMAT==='flatsome') $content=build_flatsome($d,$topcats,$feat);
                else { $content='<h1>'.esc($d['hero_headline']).'</h1><p>'.esc($d['hero_intro']??'').'</p>'; $li=''; foreach($topcats as $c){ $li.='<li><a href="'.esc_url($c['url']).'">'.esc($c['name']).'</a></li>'; } if($li)$content.='<h2>'.esc($d['categories_heading']??'Shop by Category').'</h2><ul>'.$li.'</ul>'; $content=append_disclaimer($content); }
                $content=dedash($content); $r=wp_update_post(['ID'=>$fid,'post_content'=>$content],true);
                $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$fid));   // verify STRAIGHT from DB
                if(is_wp_error($r)) out('   [WRITE ERROR] homepage: '.$r->get_error_message(),'#f66');
                else out("   homepage: wrote ".mb_strlen($content)." chars to page id $fid, DB now holds ".mb_strlen((string)$svd),'#6cf');
                if(!empty($d['meta_title'])) update_post_meta($fid,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                if(!empty($d['meta_description'])) update_post_meta($fid,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                if(!empty($d['focus_keyword'])) update_post_meta($fid,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                out('   [ok] homepage','#6f6'); } } }

    if(DO_CONTACT){ out("\n--- Contact ---",'#6cf'); $pid=find_or_create_page('contact-us','Contact Us');
        $cur=trim((string)get_post_field('post_content',$pid));
        if($cur!=='' && !OVERWRITE_PAGES){ out('   [skip] contact has content','#888'); }
        else { $c='<h1>Contact '.esc(brand()).'</h1>'; $c.='<p>Have a question about our '.esc(STORE_NICHE).'? Reach us and we\'ll help.</p><ul>';
            if(CONTACT_PHONE) $c.='<li><strong>Phone:</strong> '.esc(CONTACT_PHONE).'</li>';
            if(CONTACT_EMAIL) $c.='<li><strong>Email:</strong> '.esc(CONTACT_EMAIL).'</li>';
            $c.='<li><strong>Location:</strong> '.esc(CONTACT_LOCATION).'</li></ul>';
            $r=wp_update_post(['ID'=>$pid,'post_content'=>$c],true); $svd=$wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$pid));
            if(is_wp_error($r)) out('   [WRITE ERROR] contact: '.$r->get_error_message(),'#f66');
            else out("   [ok] contact (page id $pid) — wrote ".mb_strlen($c)." chars, DB now holds ".mb_strlen((string)$svd),'#6f6'); } }

    $legal=[]; if(DO_PRIVACY)$legal[]=['privacy-policy','Privacy Policy','Cover data collection, use, cookies, third parties, user rights (CCPA-aware), and contact.'];
    if(DO_TERMS)$legal[]=['terms-and-conditions','Terms and Conditions','Cover use of the site, orders, pricing, IP, limitation of liability, governing law (USA).'];
    if(DO_SHIPPING)$legal[]=['shipping-policy','Shipping Policy','Cover processing times, US domestic + international shipping, costs, tracking, delays.'];
    if(DO_REFUND)$legal[]=['refund_returns','Refund and Returns Policy','Cover eligibility, timeframes, process, refunds, exchanges, non-returnable items.'];
    if(DO_FAQ)$legal[]=['faq','FAQ','Answer 8-10 real buyer questions about the products, ordering, shipping, and returns, phrased as long-tail keywords (<h3> question + <p> answer).'];
    if(DO_ABOUT)$legal[]=['about-us','About Us','Tell the store\'s story, what makes it trustworthy, and why to buy here — specific, not generic.'];
    foreach($legal as $L){ out("\n--- {$L[1]} ---",'#6cf'); write_page_via_ai($L[0],$L[1],page_prompt($L[1],$L[2])); }

    if(DO_BLOG && BLOG_COUNT>0){ out("\n--- Blog (".BLOG_COUNT." posts) ---",'#6cf');
        [$td]=ai_json("Suggest ".BLOG_COUNT." distinct, SEO-friendly blog article titles for ".brand()." selling ".STORE_NICHE.", aimed at buyers and search traffic. Return JSON: {\"titles\":[\"...\"]} — no comments, no trailing commas.");
        $titles=is_array($td)&&!empty($td['titles'])?array_slice(array_values((array)$td['titles']),0,BLOG_COUNT):[];
        foreach($titles as $ti){ $ti=trim((string)$ti); if($ti==='') continue;
            if((int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status<>'trash' AND post_title=%s LIMIT 1",$ti))){ out("   [skip] $ti (post already exists)",'#888'); continue; }   // don't duplicate on a re-run
            [$d,$err]=ai_json("Write a 900-1200 word SEO blog article titled \"$ti\" for ".brand()." selling ".STORE_NICHE.", written to help buyers and rank in search. ".voice_rules().compliance_clause()."Valid HTML, one <h1>, then <h2>/<h3>. ".html_quote_rule()."Return JSON: {\"content\":\"<html>\",\"meta_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}");
            if(!$d||empty($d['content'])){ out("   [skip] $ti — ".($err?:'no content'),'#f66'); continue; }
            $post=wp_insert_post(['post_type'=>'post','post_title'=>$ti,'post_status'=>PUBLISH_STATE,'post_content'=>append_disclaimer(dedash((string)$d['content']))]);
            if($post){ if(!empty($d['meta_title'])) update_post_meta($post,'rank_math_title',mb_substr((string)$d['meta_title'],0,70));
                if(!empty($d['meta_description'])) update_post_meta($post,'rank_math_description',mb_substr((string)$d['meta_description'],0,160));
                if(!empty($d['focus_keyword'])) update_post_meta($post,'rank_math_focus_keyword',(string)$d['focus_keyword']);
                out("   [ok] $ti",'#6f6'); } } }
}

// best-effort cache purge so new content/prices/stock show without a manual cache clear (each is a no-op if not installed)
if(function_exists('wp_cache_flush')) wp_cache_flush();          // object cache (Redis/Memcached)
do_action('litespeed_purge_all');                                // LiteSpeed (Hostinger default)
if(function_exists('rocket_clean_domain')) rocket_clean_domain();// WP Rocket
if(function_exists('w3tc_flush_all')) w3tc_flush_all();          // W3 Total Cache
if(function_exists('wpfc_clear_all_cache')) wpfc_clear_all_cache();// WP Fastest Cache
out("\nCleared caches (object + common page-cache plugins).",'#6cf');

out("\nDone. Products processed: $processed".($aborted?' — STOPPED EARLY (see red messages); re-open the URL to resume.':($batched?' (more to do — refresh to continue).':'.')),'#6cf');
if($products_complete){ $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wcm_done'"); delete_option('wcm_reset_done'); }   // whole job done — clear resume flags + re-arm RESET_PROGRESS
if(!$batched && $left===0 && SELF_DELETE_WHEN_DONE){ if(@unlink(__FILE__)) out('This file deleted itself. ✅','#6f6'); else out('Could not auto-delete — delete this file manually.','#fa0'); }
else out('>>> Not fully done — re-open the URL to finish, then delete this file. <<<','#fa0');
echo "</body>";
