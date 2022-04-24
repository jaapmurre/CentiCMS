<?php
/*
    Versions:

    1.0.2  -- 20190517 Jaap Murre -- Added iso-date-based release: if 20301201... is in the future the entry won't be in the sitemap or pages
    1.0.1  -- 20190517 Jaap Murre -- Added a line to the .htaccess to prevent direct access to .txt files (e.g., templates or index.txt files)
    1.0.0  -- 20180812 Jaap Murre -- Initial version

*/

$start_centicms = microtime(true);

$config = get_vars('config',True);
session_start();

//print_r($config); echo "<br />";

function get_var($varname,$defaultvalue='') {
    global $config;
    return $config == '{}' ? '' : (array_key_exists($varname,$config) ? $config[$varname] : $defaultvalue);
}

$theme = htmlspecialchars(@$_GET["standardsitetheme"]); // GET from url query parameter ?standardsitetheme=sketchy etc.
$theme = $theme ? $theme : get_var('sitetheme','shards'); // Or 'cerulean', or see https://bootswatch.com/ for all the options, e.g., materia, sketchy, or united
$sitename = get_var('sitename','CentiCMS');
$javascript = $theme == 'shards' ? get_var('javascript','<script src="https://unpkg.com/shards-ui@2.0.3/dist/js/shards.min.js"></script>') : get_var('javascript');
$stylesheet = $theme == 'shards' ? get_var('stylesheet','<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous"><link rel="stylesheet" href="https://unpkg.com/shards-ui@2.0.3/dist/css/shards.min.css">')
                                 : ($theme == 'default' ? '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">'
                                                        : get_var('stylesheet',"<link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootswatch/4.1.2/$theme/bootstrap.min.css'>"));

$rawsiteurl = strtok($_SERVER["SERVER_NAME"],'?'); // Get $siteurl from $_SERVER var and remove trailing queries
$rawsiteurl = trim($rawsiteurl,'/');
$siteurl = get_var("siteurl","//" . $rawsiteurl); // Allow override in config.com

$routeurl = strtok($_SERVER["REQUEST_URI"],'?'); // Get $siteurl from $_SERVER var and remove trailing queries
$routeurl = trim($routeurl,'/');
$route = explode("/",$routeurl);

if ($route[0] == get_var('remove_from_route')) {
    $route = array_slice($route,1); // Remove leading dir if specified in config.txt (TODO: does not yet work for subdir, subsubdir, etc.), useful if your site in is not in the root of your server public_html
}
if ($route && $route[0] == 'index.php') { // Remove 'index.php' if any, which will depend on your root .htaccess file
    $route = array_slice($route,1);
}

$languages = array_map('trim',explode(",",get_var('languages','en')));
if (count($route) && in_array($route[0],$languages)) {
    $selectedlanguage = array_shift($route);
} else {
    $selectedlanguage = $languages[0];
}

$defaultlanguage = $languages[0];

$localizedsiteurl = $siteurl . ($selectedlanguage != $defaultlanguage ? "/$selectedlanguage": ""); // e.g., example.com/nl

function is_visible($localpath) { // Only the root and directories starting with digit are shown in the nav menu by default

    $path = explode("/",$localpath);
    $last = trim(end($path));

//echo "$localpath --- $last " . $last[0] . " ---" . (ctype_alpha($last[0]) ? "" : "1") . "<br />";

    if ($last == "") {
        return "1"; // root is always visible
    } else {

        return ctype_alpha($last[0]) ? "" : "1";
    }
}

function strip_prefix(&$item) { $item = ltrim($item,"0..9 -_"); return $item; }

// Expects local content or vendor path. Removes trailing /, prefix 'content/' or 'vendor/*/', and prefix 0.9 - _ of each directory
function clean_path($localpath) {
    $localpath = preg_replace('{/$}', '',$localpath); // Remove trailing '/', if any
    $localpath_array = explode("/",$localpath);
    $localpath_array = count($localpath_array) > 0 ? (
//                            $localpath_array[0] == 'vendor' ? array_slice($localpath_array,2) : array_slice($localpath_array,1) ) : array();
                            $localpath_array[0] == 'vendor' ? array_slice($localpath_array,3) : array_slice($localpath_array,1) ) : array();

    array_walk($localpath_array,'strip_prefix');
    return implode("/",$localpath_array);
}

// Given either local path vendor/centicms_admin/999-admin/1-login or URL route admin/login, find say content/6-admin/1-login
// or if $find_in_vendor is True finds, say, vendor/centimms_admin/999-admin/1-login from admin/login
// Also maps vendor to content and vice versa: find_local_path("vendor/centicms_admin/admin",False) -> content/6-admin
// and find_local_path("content/admin",True) -> vendor/centicms_admin/admin/
// If the path does not exists '' is returned
function find_local_path($path,$find_in_vendor=False) {
    $clean = clean_path($path);
    $route = explode('/',$clean); // Convert path with content or vendor to clean URL-like route without content or vendor/*
    $dir = $find_in_vendor ? "vendor/*/content" : "content";
    $regdir = $find_in_vendor ? "vendor\/[^\/]+\/content" : "content";

    $localpath = '';
    if (!(count($route) == 1 && $route[0] == '')) { // When not searching in the root
        foreach ($route as $d) {
            $dir .= "/*$d"; // $dir matches content/*blog/*entry-2/
            $regdir .= "\/[0-9\-_ ]*$d"; // $regdir is a more restrictive, exact match not implementable in glob()
        }
    }

    $f = '';
    foreach (glob("$dir/") as $f) {
        if (preg_match("/$regdir/",$f)) {
            if (do_ignore($f)) {
                continue;
            }
            $localpath = $f;
            break;
        }
    }

    return $f;
}

function get_vendor_array() {
    $vendor_array = array();
    foreach (glob("vendor/*") as $d) {
        if (do_ignore($d)) {
            continue;
        } else {
            $vendor_array[] = implode("/",array_slice(explode("/",$d),1));
        }
    }
    return $vendor_array;
}

//print_r(get_vendor_array()); die();


// Returns parent of localpath without trailing /
function get_parent_path($localpath) {
    $localpath = trim(preg_replace('{/$}', '',$localpath)); // Remove trailing '/', if any, and trim
    if (!$localpath) {
        return '';
    }
    $path = explode('/',$localpath);
    if (($path[0] == 'content' && count($path) == 1) || ($path[0] == 'vendor' && count($path) == 2)) {
        return $localpath;
    }
    array_pop($path);
    $path = implode('/',$path);
    return $path;
}

//$reps = 0;

function find_template($path,$template,$skip_find_local_path=False,$stoprecursion=False,$type="template")
{
// global $reps; $reps += 1; if ($reps == 4) { die(); }

    if (!$skip_find_local_path) {
        $localpath = find_local_path($path,False);
    } else {
        $localpath = $path;
    }

//echo "1. $localpath <br />";

    if (!$template) // First look in content directory
    {
        $file_base = get_i18n_filename($localpath,$type,'txt'); // could be template.nl instead of just template

//echo "2.   $file_base <br />";

        if ($file_base) {
//echo @file_get_contents("$localpath/$file_base.txt");
            return @file_get_contents("$localpath/$file_base.txt");
        } else {
            $localpath = get_parent_path($localpath);
//echo "3a. $localpath <br />";
            $file_base = get_i18n_filename($localpath,"child$type",'txt');
//echo "3b.   $file_base <br />";

            if ($file_base) { // found childtemplate
                return @file_get_contents("$localpath/$file_base.txt");
            } else {
//echo "3c.   $path <br />";
                if (!$stoprecursion) {
                    return find_template(find_local_path($path,True),$template,True,True,$type); // Look in vendor directory
                }
//echo "3d. <br />";
            }
        }
    }
    else
    {
        foreach(glob("content/*",GLOB_ONLYDIR) as $child) // REFACTOR WITH ABOVE
        {
            if (do_ignore($child)) {
                continue;
            }

            $page = clean_path($child);
            if ($page == $template) // Found template directory
            {
                $file_base = get_i18n_filename($child,"$type",'txt'); // Finding possibly translated template

//echo "4.   $file_base <br />";

                if ($file_base) // If found
                {
                    return @file_get_contents("$child/$file_base.txt");
                }
            }
        }
    }


    $file_base = get_i18n_filename('content',"$type",'txt');

//echo "5.   $type -- $file_base <br />";

    if ($file_base) {
        return @file_get_contents("content/$file_base.txt"); // This file MUST be defined and is the default, if not it is an error
    } else {
        if ($type == 'template') {
            return '';
            //return '{"error": base64_encode("Template missing for $localpath, also in vendor content and root")}';
        } else {
            return '';
        }
    }
}

//find_template("content/contact",""); die();
// echo find_template("content",""); die();
//find_template("content/3-blog",""); die();
//find_template("vendor/centicms_admin/admin",""); die();

function get_page() {
    global $route;
    $localpath = convert_route_to_localpath($route,True); // Find localpath to index.txt
    if ($localpath) {
        return get_page_variables("$localpath",False);
    } else {
        return "{error: '" . base64_encode("URL Unknown") . "'}";
    }
}

function get_page_variables($localpath,$minimal=True) // $minimal=True does not get children and templates; $last is the final element of the route to determine whether this local path is the current route
{
    global $selectedlanguage;

    // Added 20190517 Jaap Murre
    $last = explode('/',$localpath);
    $last = end($last);
    $init = explode('-',$last);
    $init = $init[0];
    $init = intval($init);
    if ($init > intval(date("Ymd"))) {
        return null;
    }


    $localpath = trim(preg_replace('{/$}', '',$localpath)); // Remove trailing '/', if any, and trim

    $kv_array = read_vars($localpath,'index',False,array()); // $raw -> False

    $files = get_files($localpath);
    $kv_array[] = "'files': $files";
    $type64 = base64_encode('index');
    $kv_array[] = "'type': '$type64'";

    if (!$minimal) {
        $children = "[" . get_children($localpath) . "]";
        $kv_array[] = "'children': $children";
        $tmplt = find_template($localpath,array_key_exists('template',$kv_array) ? $kv_array['template'] : '');
        $tmplt = base64_encode($tmplt);
        $kv_array[] = "'template': '$tmplt'";
    }

    return "{" . implode(",",$kv_array) . "}";
}

function get_children($localpath="content") {
    global $route;
    $localpath = trim(preg_replace('{/$}', '',$localpath)); // Remove trailing '/', if any, and trim
    $pages = glob("$localpath/*",GLOB_ONLYDIR);
    $page_array = array();
    foreach ($pages as $page) {
        if (do_ignore($page)) {
            continue;
        }
        $page_array[] = get_page_variables($page,True);
    }
    return implode(",",$page_array);
}

function fixJSON($json) {
    $newJSON = '';

    $jsonLength = strlen($json);
    for ($i = 0; $i < $jsonLength; $i++) {
        if ($json[$i] == '"' || $json[$i] == "'") {
            $nextQuote = strpos($json, $json[$i], $i + 1);
            $quoteContent = substr($json, $i + 1, $nextQuote - $i - 1);
            $newJSON .= '"' . str_replace('"', "'", $quoteContent) . '"';
            $i = $nextQuote;
        } else {
            $newJSON .= $json[$i];
        }
    }

    return $newJSON;
}

function get(&$var, $default='') { return isset($var) ? $var : $default; }
function make_title($segment) { return ucwords(str_replace("_"," ",$segment)); }

function get_sitemap_vars(&$page,$localpath,$path,$d,$cleaned_local_segment)
{
    global $siteurl, $route, $defaultlanguage, $selectedlanguage;

    $localpath = $d ? "$localpath/$d" : $localpath;

    $vars = get_vars('index',True,"$localpath",False);
    $segment = get($vars['segment'],clean_path("content/$d")); // clean_path() removes content/ and then 'cleans' $d, which is the default
    $page['segment'] = base64_encode($segment);


    $get_files = get($vars['always_get_files'],'');

    if ($get_files && $get_files != 'false') {
        $fj = fixJSON(get_files($localpath));
        $page['files'] = json_decode($fj);
    }

    $fields = array_map('trim',explode(",",get_var('extra_sitemap_fields','').",tags"));
    foreach ($fields as $f) {
        $page[$f] = base64_encode(get($vars[$f]));
    }

    $page['title'] = base64_encode(get($vars['title'],make_title($segment)));
    $localpagepath = $localpath;
    $page["localpath"] = base64_encode($localpagepath);
    $fullpath = "$localpagepath/index.txt";
    $page["fullpath"] = base64_encode($fullpath);
    $page["isvisible"] = base64_encode(is_visible($localpagepath));
    $pagepath = $path ? "$path/$segment" : $segment;
    $page["path"] = base64_encode("$pagepath");
    $page["cleaned_local_segment"] = $cleaned_local_segment; // E.g., record 001-projects-1 as projects-1, even though segment may be say 'penske_file'
    $page["iscurrent"] = base64_encode(implode('/',$route) == $path);
    $page["url"] = base64_encode("$siteurl" . ($selectedlanguage != $defaultlanguage ?  "/$selectedlanguage" : "") . ($pagepath ? "/$pagepath" : ""));


    $page["vendors"] = array();
    $vendors = get_vendor_array();
    foreach ($vendors as $vendor) {
        $t = trim(find_template($localpath,"",False,False,"vendortemplate_$vendor"));
        if ($t) {
            $page["vendors"][$vendor] = array('rawtext' => base64_encode($t));
            $vars = preg_split("/\s*--(-)+\s*/",$t);
            foreach ($vars as $var) {
                $key_value = explode(":",$var,2);
                if (count($key_value) < 2) {
                    continue;
                }
                $key = strtolower(trim($key_value[0]));
                $key = str_replace("-", "_", $key);
                $key = str_replace(" ", "_", $key);
                $value = base64_encode(trim($key_value[1]));
                $page["vendors"][$vendor][$key] = $value;
//                $page["vendors"][$vendor][] = "'$key': '$value'";
            }
        }
    }

    return $pagepath;
}

function sort_by_localpath($a,$b) {
    return strcmp(base64_decode($a['localpath']),base64_decode($b['localpath']));
}

function get_sitemap_children($localpath,$path,&$sitemap,$parent)
{
    if (!file_exists($localpath)) {
        return array();
    }

    foreach (scandir($localpath) as $d) {
        if (is_dir("$localpath/$d") && $d != '..' & $d != '.') {

            // Added 20190517 Jaap Murre
            $init = explode('-',$d);
            $init = $init[0];
            $init = intval($init);
            if ($init > intval(date("Ymd"))) {
                continue;
            }

            if (do_ignore("$localpath/$d")) {
                continue;
            }

            $cleaned_local_segment = base64_encode(clean_path("content/$d"));

            $found_existing = False; // Check whether the directory has already been found earlier, in a vendor area
            if ($parent) {
                if (array_key_exists('children', $parent)) {

                    for ($i = 0; $i < count($parent['children']); $i++) {
                        if ($parent['children'][$i]['cleaned_local_segment'] === $cleaned_local_segment) {
                            $found_existing = True;
                            $page = &$parent['children'][$i];
                            if (!array_key_exists('children',$page)){
                                $page['children'] = array();
                            }
                            break;
                        }
                    }
                } else {
                    for ($i = 0; $i < count($parent); $i++) {
                        if ($parent[$i]['cleaned_local_segment'] === $cleaned_local_segment) {
                            $found_existing = True;
                            $page = &$parent[$i];
                            if (!array_key_exists('children',$page)){
                                $page['children'] = array();
                            }
                            break;
                        }
                    }
                }
            }

            if (!$found_existing) {
                $sitemap[] = array();
                $c = count($sitemap)-1;
                $page = &$sitemap[$c];
                $sitemap[$c]['children'] = array();
            }

            $pagepath = get_sitemap_vars($page,$localpath,$path,$d,$cleaned_local_segment); // $page is passed by reference
            get_sitemap_children("$localpath/$d",$pagepath,$page['children'],$page);
            usort($page['children'],"sort_by_localpath"); // $page['chidren'] array is passed by reference
        }
    }

    return $sitemap;
}

function get_sitemap($localpath="content",$path="",&$sitemap=array(),$skip_vendor=False) // Must pass by reference with &, else a complete copy is passed
{
    $localpath = preg_replace('{/$}', '',$localpath); // Remove trailing '/', if any


//echo "get_sitemap: $localpath $skip_vendor <br />";

    if (do_ignore($localpath)) {
        return $sitemap;
    }

//    if ($localpath='content') {
//        $sitemap['children'] = array();
//    }

    if (!$skip_vendor){ // First check all vendor directories for files and add these
        $dir = "vendor/*/content";
        $regdir = "vendor\/[^\/]+\/content";

        foreach (glob("$dir/") as $f) { // Find local path for route
            if (preg_match("/$regdir/",$f)) {
                if (do_ignore($f)) {
                    continue;
                }
//echo "Mapping: $f <br />";
                $sitemap = get_sitemap($f,"",$sitemap,True);
            }
        }
    }

//echo "FINISHED MAPPING VENDORS <br /><br/>";

    get_sitemap_vars($sitemap,$localpath,$path,"",""); // $page is passed by reference
    get_sitemap_children($localpath,$path,$sitemap['children'],$sitemap);

    if ($sitemap['children']) {
        usort($sitemap['children'],"sort_by_localpath"); // $page['chidren'] array is passed by reference
    }

    return $sitemap;
}

//$sitemap = get_sitemap();
//die();
//print_r($sitemap); die();
/*
$start = microtime(true);
$sitemap = get_sitemap();
$secs = microtime(true) - $start;
echo "time to generate a sitemap = " . sprintf("%fl",$secs) . "s<br />";
die();
*/

function hash_dir($directory) {
    if (!is_dir($directory)) {
        return false;
    }
    $files = array();
    $dir = dir($directory);
    while (false !== ($file = $dir->read())) {
        if ($file != '.' and $file != '..') {
            if (is_dir($directory . '/' . $file)) {
                $files[] = hash_dir($directory . '/' . $file);
            }
            else {
                $files[] = sha1($directory . '/' . $file);
                $files[] = sha1(filemtime($directory . '/' . $file));
            }
        }
    }
    $dir->close();
    return sha1(implode('',$files));
}

function do_rebuild() {
    if (!is_dir('cache')) {
        mkdir('cache', 0777, true);
//        file_put_contents('cache/.htaccess',"deny from all"); // Prevent external access via Internet
    }
    $m = sha1(hash_dir('content') . hash_dir('vendor'));
    if (!file_exists("cache/_hash.txt") || !file_exists("cache/sitemaps.js") || (file_get_contents("cache/_hash.txt") !== $m)) {
        file_put_contents("cache/_hash.txt",$m); // Save hash
        return True;
    } else {
        return False; // Nothing has changed
    }
}

function build_sitemaps() {
    global $selectedlanguage, $languages;
    $sel_lang = $selectedlanguage;    // Create a single JSON for ALL sitemaps and cache selected language in data.sitemap
    $ts = array();
    foreach ($languages as $lang) {
        $selectedlanguage = $lang; // Temporarily change selected language
        $ts[$lang] = get_sitemap(); // Get translated sitemap
    }
    $selectedlanguage = $sel_lang;
    return $ts;
}

if (get_var('cache_sitemaps') && get_var('cache_sitemaps') != "false") {
    if (do_rebuild()) {
        file_put_contents("cache/sitemaps.js","__SITEMAPS__ = " . json_encode(build_sitemaps()) . ";");
    }
} else {
    $sitemaps = json_encode(build_sitemaps()); // Always rebuild when not caching
}

function read_vars($localpath,$type,$raw,$kv_array,$do_localize=True)
{
    $file_base = $do_localize ? get_i18n_filename($localpath,$type,'txt') : $type;
    $file = @file_get_contents("$localpath/$file_base.txt");

    // Added 20190517 Jaap Murre
    $last = explode('/',$localpath);
    $last = end($last);
    $init = explode('-',$last);
    $init = $init[0];
    $init = intval($init);
    if ($init > intval(date("Ymd"))) {
        return $kv_array;
    }

//    if (trim($file) != "") {
    if (True) {
        $vars = preg_split("/\s*--(-)+\s*/",$file);
        foreach ($vars as $var) {
            $key_value = explode(":",$var,2);
            if (count($key_value) < 2) {
                continue;
            }
            $key = strtolower(trim($key_value[0]));
            $key = str_replace("-", "_", $key);
            $key = str_replace(" ", "_", $key);
            $value = trim($key_value[1]);
            if ($raw) {
                $kv_array[$key] = array_key_exists($key, $kv_array) &&
                                      in_array($key,array('javascript','stylesheet','vendor_stylesheet','style','metatags')) ?  // Space-delimited fields, concatenate
                                      $kv_array[$key] . ' ' . $value
                                    : (  array_key_exists($key, $kv_array) && in_array($key,array('extra_sitemap_fields')) ? // Comma-delimited fields, concatenate
                                         $kv_array[$key] . ',' . $value
                                       : $value ); // New key value or replace (overwrite) existing value: content value 'wins' from vendor
            } else {
                $value = base64_encode($value);
                $kv_array[] = "'$key': '$value'";
            }
        }

        if ($raw) { // Record type and fullpath
            $kv_array['type'] = $type;
            $kv_array['fullpath'] = "$localpath/$type.txt";
            $kv_array['fullpath'] = "$localpath";
            $kv_array['rawtext'] = trim($file);
        } else {
            $type64 = base64_encode($type);
            $fullpath64 = base64_encode("$localpath/$type.txt");
            $localpath64 = base64_encode("$localpath");
            $rawtext64 = base64_encode(trim($file));
            $kv_array[] = "'type': '$type64'";
            $kv_array[] = "'fullpath': '$fullpath64'";
            $kv_array[] = "'localpath': '$localpath64'";
            $kv_array[] = "'rawtext': '$rawtext64'";
        }
    }

    return $kv_array;
}

//print_r(read_vars('content/test','testing.pdf.nl',False,array())); die();

function do_ignore($localpath) {
    $initial_underscore = preg_match('/\/_/',$localpath);
    return $initial_underscore;
}

function get_vars($type,$raw = False,$localpath_content="content",$check_vendors=True,$do_localize=True)
{
    $paths = array($localpath_content);

    if ($check_vendors)
    {
//        foreach (glob(dirname(__File__) . "/vendor/*/content/$type.txt") as $lpath) {
        foreach (glob(dirname(__File__) . "/vendor/*/content",GLOB_ONLYDIR) as $lpath) {
//            $lpath = dirname($lpath);
            if (do_ignore($lpath)) {
                continue;
            }
            if (get_i18n_filename($lpath,$type,'txt')) {
                $paths[] = $lpath;
            }
        }
    }

    $kv_array = array();

    foreach ($paths as $localpath) {
        $kv_array = read_vars($localpath,$type,$raw,$kv_array,$do_localize);
    }

    if ($raw) {
        return $kv_array;
    } else {
        return "{" . implode(",",$kv_array) . "}";
    }
}

function get_last_segment($localpath) { // path as string
    $localpath = preg_replace('{/$}', '',$localpath); // Remove trailing '/', if any
    $p = explode('/',$localpath);
    $last = $p[count($p)-1];            // Get last segment
    return clean_path("content/$last");   // clean path removes 'content/' again
}

function segment_matches_dir($localpath,$route_segment) {
    $vars = read_vars($localpath,'index',True,array()); // See whether 'segment' was defined in (localized) index.txt
    if (array_key_exists('segment', $vars)) {
        if ($vars['segment'] == $route_segment) {
            return True;
        }
    }
    return get_last_segment($localpath) == $route_segment;
}

function find_localpath($route,$localpath) { // $localpath is starting glob string, either 'content' or 'vendor/*'
    foreach ($route as $r) { // First try to find a local (content) segment-match for the route
        $success = False;
        foreach (glob("$localpath/*") as $f) {
            if (segment_matches_dir($f,$r)) {
                if (do_ignore($f)) {
                    continue;
                }
                $localpath = $f;
                $success = True;
                break;
            }
        }
        if (!$success) {
            $localpath = '';
            break;
        }
    }
    return $localpath;
}

function convert_route_to_localpath($route,$find_first_with_index=False)
{ // Convert route to first matching local path, using (localized) segment defs in index.txt and 'cleaned' dir names
    $content_localpath = find_localpath($route,'content');

    if ($content_localpath && $find_first_with_index) {
        $file_base = get_i18n_filename($content_localpath,'index','txt');
        if (file_exists("$content_localpath/$file_base.txt")) {
            return $content_localpath;
        }
    } elseif ($content_localpath) {
        return $content_localpath;
    }

    $vendor_localpath = find_localpath($route,'vendor/*/content');

    if ($vendor_localpath) {
        return $vendor_localpath;
    } else {
        return $content_localpath; // This is the case with empty content directories and no vendor ones either
    }
}

function get_i18n_filename($localpath,$file_base,$file_ext)
{
    global $selectedlanguage;
    $filename = file_exists("$localpath/$file_base.$selectedlanguage.$file_ext") ? $file_base . ".$selectedlanguage" :
                     (file_exists("$localpath/$file_base.$file_ext") ? $file_base : '');

//echo "i18n($localpath,$file_base,$file_ext) =  $selectedlanguage -> $filename <br />";

    return $filename;
}

$filetypes = array('image'=>get_var('image','jpg,jpeg,gif,png,tif,tiff,svg,bmp'),
                   'document'=>get_var('document','pdf,doc,docx,rtf,ppt,pptx,xls,xlsx,csv,md'),
                   'video'=>get_var('video','mov,mkv,avi,ogg,ogv,webm,flv,swf,mp4,mv4'),
                   'audio'=>get_var('audio','mp3,m4a,wav,aiff,midi'));

function get_files($localpath)
{
    global $config, $language, $languages, $siteurl, $filetypes;

    $files = array();

    if ($config != '{}') {
        foreach ($config as $k => $v) {
            if (explode("_",$k)[0] == 'filetype') { // user-defined file type in config.txt, e.g., filetype_math_files: sav, spv, mat, r
                $filetype = explode("_",$k,2)[1];
                $filetypes[$filetype] = $v;
            }
        }
    }

    foreach (glob("$localpath/*.*") as $f) {
        $path = array_slice(explode("/",$f),1);
        $file = end($path);
        $file_base_raw = explode(".",$file,2)[0];
        $fc = explode(".",$file);
        $file_ext = end($fc);

        if ($file_ext == 'txt') {
            continue;
        }

        if (!in_array($file_base_raw,explode(",","config,index,partials,site,template"))) {

            $file_base = get_i18n_filename($localpath,$file_base_raw,$file_ext);

            $file = "$file_base.$file_ext";

            if ($file_ext != 'txt') {
                $files[$file] = array('name' => "'" . base64_encode($file) . "'" );
                $files[$file]['url'] = "'" . base64_encode("$siteurl/$localpath/$file") . "'";
                $files[$file]['caption'] = get_vars("$file_base_raw.$file_ext",False,$localpath);
            }

            foreach ($filetypes as $k => $v){
                if (in_array($file_ext,array_map('trim',explode(",",$v)))) {
                    $files[$file]['type'] = "'" . base64_encode($k) . "'";
                    break;
                }
            }

            if (!array_key_exists('type',$files[$file]))
            {
                $files[$file]['type'] = "'" . base64_encode("unknown") . "'";
            }
        }
    }

    $r = array();

    foreach ($files as $k => $v) {
        $r[] = "'$k': {'name': $v[name], 'url': $v[url], 'type': $v[type], 'caption': $v[caption]}";
    }

    return '{' . implode(",",$r) . "}";
}


function get_file_in_query()
{
    if (isset($_GET['file'])) {
        $file = $_GET['file'];
        $localpath = dirname($file);
        $last = get_last_segment($file);
        $base_ext = explode(".",$last);
        $extension = array_pop($base_ext);
        $type = implode(".",$base_ext);

        if ($extension == 'txt') {

//echo "Reading '$type' file at '$localpath' <br />";

            if ($type == 'index') {
                return get_page_variables($localpath,False);
            } else {
//                if (in_array($type,array('config','site','translation','partials','template','childtemplate'))) {
                    return get_vars($type,False,$localpath,False,False);
//                }
            }
        }
    }
}

//echo get_file_in_query(); die();


?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">

    <script>window.centi_cms_js_page_generation_time_start = performance.now(); </script>

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo get_var('meta_description'); ?>">
    <meta name="author" content="<?php echo get_var('meta_author'); ?>">
    <meta name="keywords" content="<?php echo get_var('meta_keywords'); ?>">
    <?php echo get_var('metatags'); ?>


    <?php
        if (get_var('cache_sitemaps') && get_var('cache_sitemaps') != "false") {
            $m = file_get_contents("cache/_hash.txt"); // Add hash to force refresh cache on new build
            echo "<script src='$siteurl/cache/sitemaps.js?$m'></script>";
        }
    ?>

    <?php
        $icons = get_var('icons');
        if ($icons == 'material') {
            echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/material-design-iconic-font/2.2.0/css/material-design-iconic-font.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">';
        } else if ($icons != 'none') {
            echo '<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">';
        }
    ?>

    <?php echo get_var('style',''); ?>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

    <!-- TODO: Allow config.txt to override handlebars and marked below and bootstrap above for other libraries -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.0.11/handlebars.min.js" integrity="sha256-+JMHsXRyeTsws/tzbIh5YHQxRdKCuNjmvNcTFtY6DLc=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/0.4.0/marked.min.js" integrity="sha256-JaznSM5IwXQK1AyjtxCTklRL05ar/8zo+oty8fS+AMc=" crossorigin="anonymous"></script>
    <?php if (get_var('yaml') != 'false') {
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/yamljs/0.3.0/yaml.min.js" integrity="sha256-fDg/uZo3GBErK1nydRVMJ6iZcDtOq2u3tWJtql0odec=" crossorigin="anonymous"></script>';
    } ?>
    <?php if (get_var('mathjax') != 'false') {
              echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.5/MathJax.js?config=TeX-MML-AM_CHTML' async></script>";
    } ?>
    <?php if (get_var('highlight') != 'false') {
              echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/' .
                    get_var('highlight_style','github') . '.min.css" />';
              echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js" integrity="sha256-/BfiIkHlHoVihZdc6TFuj7MmJ0TWcWsMXkeDFwhi0zw=" crossorigin="anonymous"></script>';
              echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/languages/handlebars.min.js" integrity="sha256-gIKchkDdEmNiSdlIe2VJHNVFcM90eMFzTPJbdLsY9Ak=" crossorigin="anonymous"></script>';
              // See https://cdnjs.com/libraries/highlight.js/ for other options
              echo "<script>hljs.initHighlightingOnLoad();</script>";
    } ?>
    <?php if (get_var('moment') != 'false') {
              echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/moment.min.js" integrity="sha256-CutOzxCRucUsn6C6TcEYsauvvYilEniTXldPa6/wu0k=" crossorigin="anonymous"></script>';
              if ($selectedlanguage != 'en') {
                  echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/locale/$selectedlanguage.js'></script>";
              }
    } ?>

    <?php echo $javascript; ?>

    <?php echo $stylesheet; ?>
    <?php echo get_var('vendor_stylesheet'); ?>

    <script>

        (function() { // local module

            function decode(arr) {
                var i, k;

                if (!arr) {
                    return {};
                }

                if ($.type(arr) === 'array') {
                    for (var i = 0; i < arr.length; i++) {
                        arr[i] = decode(arr[i]);
                    }
                } else if ($.type(arr) === 'object') {
                    for (k in arr) {
                        if (arr[k] != '') {
                            arr[k] = decode(arr[k]);
                        }
                    }
                } else {
                    arr = atob(arr);
                }
                return arr;
            }

            window.data = {}; // data is deliberately made global for easy use in scripts

            <?php
                if (!(get_var('cache_sitemaps') && get_var('cache_sitemaps') != "false")) {
                    echo "__SITEMAPS__ = $sitemaps;"; // non-cached sitemaps are in $sitemaps variable
                } // (Cached files are downloaded above from link and put into the same variable __SITEMAPS__)
            ?>

            data.translated_sitemaps = decode(__SITEMAPS__);
            data.selectedlanguage = '<?php echo $selectedlanguage; ?>';
            data.defaultlanguage = '<?php echo $defaultlanguage; ?>';
            data.languages = <?php echo json_encode($languages); ?>;
            data.translation = decode(<?php echo get_vars("translation"); ?>);
            data.sitemap = data.translated_sitemaps[data.selectedlanguage];
            data.pages = data.sitemap.children;
            data.page = decode(<?php echo get_page(); ?>);
            data.page.translated_page_in_sitemap = {};
            data.site = decode(<?php echo get_vars("site"); ?>);
            data.filetypes = <?php echo json_encode($filetypes); ?>;
            data.theme = '<?php echo $theme; ?>';
            data.sitename = '<?php echo $sitename; ?>';
            data.siteurl = '<?php echo $siteurl; ?>';
            data.localizedsiteurl = '<?php echo $localizedsiteurl; ?>';
            data.route = '<?php echo implode("/",$route); ?>';
            data.query = <?php echo json_encode($_GET); ?>;
            data.session = <?php echo json_encode(isset($_SESSION) ? $_SESSION : array()); ?>;
            data.stats = {};
            data.file_in_query = decode(<?php echo get_file_in_query(); ?>); // Page variables if the query contains ?file=<localpath>/<type>.txt

            function process_pages(children,parent,process_language) {
                var i, child;

                for (i = 0; i < children.length; i++) {
                    child = children[i];
                    child.parent = parent;
                    child.index = i;
                    child.previous = i == 0 ? '' : children[i-1];
                    child.next = i < children.length-1 ? children[i+1] : '';
                    child.siblings = children;

                    if (child.path == data.route && !process_language) {
                        child.iscurrent = "1";
                        data.page.page_in_sitemap = child;
                        data.page.index = i; // Copy some useful properties to the (current) data.page
                        for (k in {'isvisible':1,'title':1,'url':1,'path':1,'localpath':1,'next':1,'previous':1,'siblings':1}) {
                            data.page[k] = child[k];
                        }
                    } else {
                        child.iscurrent = "";
                    }

                    if (child.fullpath == data.file_in_query['fullpath'] && !process_language) {
                        data.file_in_query.page_in_sitemap = child;
                        data.file_in_query.index = i; // Copy some useful properties to the (current) data.page
                        for (k in {'isvisible':1,'title':1,'url':1,'path':1,'localpath':1,'next':1,'previous':1,'siblings':1}) {
                            data.file_in_query[k] = child[k];
                        }
                    }

                    if (process_language) {
                        if (data.page.path == child.path) {
                            data.page.translated_page_in_sitemap[process_language] = child; // TODO: MUST REFER TO URL ???
                        }
                    }

                    if (child.children && child.children.length) {
                        process_pages(child.children,child,process_language);
                    }
                }
            }

            process_pages([data.sitemap]);

            for (var i = 0, c = data.page ? data.page.children||[] : []; i < c.length; i++) {
                c[i].parent = data.page;
                var k;
                if (data.page.page_in_sitemap) {
                    for (k in {'isvisible':1,'title':1,'url':1,'path':1,'localpath':1,'next':1,'previous':1,'siblings':1}) {
                        if (data.page.children[i] && data.page.page_in_sitemap.children[i]) {
                            data.page.children[i][k] = data.page.page_in_sitemap.children[i][k];
                        }
                    }
                }
            }

            for (i = 0; i < data.languages.length; i++) { // Main goal: assign translated paths to data.page.translated_paths
                var lang = data.languages[i];
                if (lang != data.selectedlanguage) {
                    process_pages([data.translated_sitemaps[lang]],undefined,lang);

                    if (data.route == "") { // Process the root
                        data.page.translated_page_in_sitemap[lang] = {url: data.siteurl + (lang != data.defaultlanguage ? '/' + lang : "/") };
                    }
                }
            }

            console.log("data: ",data);

            // PARTIALS
            var partials = decode(<?php echo get_vars("partials"); ?>);
            for (var k in partials) {
                Handlebars.registerPartial(k,partials[k])
            }

            // HELPERS
            Handlebars.registerHelper('markdown',function(s,is_inline) { // Markdown helper with marked.js --- Use of Handlebars within Markdown is supported
                s = Handlebars.compile(s||'')(data);
                if (is_inline == 'inline') { // Second, optional argument to prevent wrapping in <p>
                    return marked.inlineLexer(s||'',[]);
                } else {
                    return marked(s||'');
                }
            });
            Handlebars.registerHelper('yaml',function(s) { // YAML helper with yaml.js --- Use of Handlebars within YAML is supported
                s = Handlebars.compile(s||'')(data);
                return YAML.parse(s||'');
            });

            // i18n helpers
            Handlebars.registerHelper('_',function(s) { // Translate helper {{_ 'slogan'}}, if 'nl' -> "1% grootte, ongelofelijke kracht"
                if (!data.translation || !data.translation[s]) { // Defined in content/translation.nl.txt etc.
                    console.error("Translation helper: '" + s + "' has no translation in language '" + data.selectedlanguage + "'. (Hint: Did you think to put the word in quotes?)");
                    return s;
                }
                return data.translation[s];
            });
            Handlebars.registerHelper('dot_language',function() { // If selectedlanguage == 'nl' -> '.nl', if defaultlanguage -> ''
                return data.selectedlanguage === data.defaultlanguage ? '' : "." + data.selectedlanguage;
            });
            Handlebars.registerHelper('slash_language',function() { // If selectedlanguage == 'nl' -> '/nl', if defaultlanguage -> ''
                return data.selectedlanguage === data.defaultlanguage ? '' : "/" + data.selectedlanguage;
            });
            Handlebars.registerHelper('moment',function(fun,s,arg1,arg2) { // Access display functions in moment; s, arg1 and arg2 are optional arguments
                arg2 = $.type(arg2) == 'object' ? undefined : arg2; // If arg2 is missing, it will contain the 'options' object
                arg1 = $.type(arg1) == 'object' ? undefined : arg1; // idem
                s = $.type(s) == 'object' ? undefined : s||undefined;          // idem
                return moment(s)[fun](arg1,arg2);
            });

            function get_ext(file) { // Get the extension or file_base minust .txt and possible language like .nl
                var f = file.split("."),
                    x = f[f.length-2]; // cat.jpg.txt -> jpg
                if ($.inArray(x,data.languages) > -1) {
                    x = f[f.length-3]; // cat.jpg.nl.txt -> jpg
                }
                return x;
            }

            function get_filetype(ext) { // Using the data.filetypes map, derive filetype, e.g., ext = jpg -> image (also maps user filetypes)
                var k, t, i;
                for (k in data.filetypes) {
                    t = data.filetypes[k].split(",");
                    for (i = 0; i < t.length; i++) {
                        if (ext == $.trim(t[i])) {
                            return k;
                        }
                    }
                }
                return ext; // If no match, ext is its own filetype, e.g., index or config
            }

            function get_last_segment(path) { // content/test/jantje -> jantje
                var p = path.split("/");
                return p[p.length-1];
            }

            function get_page_from_fullpath(fullpath) { // Using the full path retrieve the page in the sitemap
                if (!fullpath) { return undefined; }
                var p = fullpath.split('/'), page, i, c;
                page = data.sitemap;
                for (i = 0; i < p.length-1; i++) {
                    for (c = 0; c < page.children.length; c++) {
                        if (p[i] == get_last_segment(page.children[c].localpath)) {
                            page = page.children[c];
                            break;
                        }
                    }
                }
                return page;
            }

            function get_page_in_sitemap_from_fullpath(fullpath) { // TODO: integrate with get_page_from_fullpath
                var p = fullpath.split('/'), page, i, c;
                page = data.sitemap;
                for (i = 1; i < p.length; i++) { // Skip 'content' root
                    for (c = 0; c < page.children.length; c++) {
                        if (p[i] == get_last_segment(page.children[c].localpath)) {
                            page = page.children[c];
                            break;
                        }
                    }
                }
                return page;
            }

            Handlebars.registerHelper('getinsitemap',function(fullpath,prop) { // Given 'content/2-doc/1-start' and 'children' get the children of this path's page in sitemap
                return get_page_in_sitemap_from_fullpath(fullpath)[prop];
            });

            Handlebars.registerHelper('getvendorfields',function(fullpath,vendor) { // Given 'test/cat.jpg.txt' and 'centicms_admin' get vendor image caption fields as object

                var page = get_page_from_fullpath(fullpath);

                if (!page || !page.vendors[vendor]) {
                    return [{type: 'error', label: 'Error', title: "Missing file or template", text: "No file or admin template found"}];
                }

                var path_arr = fullpath.split('/'),
                    file = path_arr[path_arr.length-1],
                    file_ext = get_ext(file),
                    filetype = get_filetype(file_ext),
                    s = Handlebars.compile(page.vendors[vendor]["fields"] || '')(data),
                    fields = YAML.parse(s||'');
                return fields[filetype];
            });

            Handlebars.registerHelper('getbreadcrumb',function() {
                var b = [{segment: "/",url: data.sitemap.url,title: "{{_ 'home'}}"}],
                    route = data.page.path.split('/'),
                    children = data.sitemap.children, i, c;
                for (i = 0; i < route.length; i++) {
                    for (c = 0; c < children.length; c++) {
                        if (route[i] == children[c].segment) {
                            b.push({segment: children[c].segment, url: children[c].url, title: children[c]['title'] });
                            children = children[c].children;
                            break;
                        }
                    }
                }
                return b;
            });

            // General, handy helpers
            Handlebars.registerHelper("math", function(lvalue, operator, rvalue, options) {
                lvalue = parseFloat(lvalue);
                rvalue = parseFloat(rvalue);
                return {
                    "+": lvalue + rvalue,
                    "-": lvalue - rvalue,
                    "*": lvalue * rvalue,
                    "/": lvalue / rvalue,
                    "%": lvalue % rvalue
                }[operator];
            });
            function check(v1, operator, v2) {
                switch (operator) {
                    case '==': return v1 == v2;
                    case '===': return v1 === v2;
                    case '!=': return v1 != v2;
                    case '!==': return v1 !== v2;
                    case '<': return v1 < v2;
                    case '<=': return v1 <= v2;
                    case '>': return v1 > v2;
                    case '>=': return v1 >= v2;
                    case '&&': return v1 && v2;
                    case '||': return v1 || v2;
                    default: return false;
                }
            }
            Handlebars.registerHelper('compare', function (v1, operator, v2, options) {
                return check(v1, operator, v2) ? options.fn(this) : options.inverse(this);
            });
            Handlebars.registerHelper('split', function (s,sep) { // 'a,b,c' -> ['a','b','c']. If sep == "/", 'a/b/c' -> ['a','b','c'].
                sep = $.type(sep) == 'object' ? ',' : sep; // If sep is missing, it will contain the 'options' object and we will use ','
                return $.map((s||'').split(sep), $.trim);
            });
            Handlebars.registerHelper('lookupProp', function (obj, key, prop) { // TODO: Review this one and the next cf. 'with' statement
               return obj[key] && obj[key][prop];
            });
            Handlebars.registerHelper('lookupPropRef', function (obj, key, prop) { // {text: 'hi'} {field: 'text'} 'field' -> 'hi'
               return (key && prop && key[prop] && obj && obj[key[prop]]) || "";
            });
            Handlebars.registerHelper ('truncate', function (str, len) {
                if (str && str.length > len && str.length > 0) {
                    var new_str = str + " ";
                    new_str = str.substr (0, len);
                    new_str = str.substr (0, new_str.lastIndexOf(" "));
                    new_str = (new_str.length > 0) ? new_str : str.substr (0, len);
                    return new_str +' ...';
                }
                return str;
            });
            Handlebars.registerHelper('getquery', function (key, options) { // if query is ?a=b&c=d then getquery 'c' -> 'd'
                key = key.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + key + '=([^&#]*)');
                var results = regex.exec(window.location.search);
                return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
            });
            Handlebars.registerHelper('contains', function (needle, haystack, options) { // needle ='a', haystack = ' a , b,c' -> true
                return $.inArray(needle,$.map((haystack||'').split(","), $.trim)) > -1 ;
            });
            Handlebars.registerHelper('getsetfrompages', function (pages, field, options) { // fields in pages: 'a,b', 'a,c', ... -> 'a,b,c'
                var i, j, c = pages, set = [], values;
                for (i = 0; i < c.length; i++) {
                    values = $.map(((c[i] && $.type(c[i][field]) == 'string' && c[i][field]) || '').split(","), $.trim);
                    for (j = 0; j < values.length; j++) {
                        if (set.indexOf(values[j]) < 0 && values[j]) {
                            set.push(values[j]);
                        }
                    }
                }
                return set;
            });

            $(document).ready(function() {
                var templateScript = Handlebars.compile(data.page.template||""); // TODO: Make 404 missing page.template template (URL Unknown)

                if (data.page.error) {
                    $(document.body).html(data.translation.error_url_unknown || "<h3 style='margin-left:3em;'>Error: " + data.page.error + "</h3>");
                } else {
                    if (templateScript(data)) {
                        $(document.body).html(templateScript(data));
                    }
                }
                $('body').css('padding-top',$('.navbar').outerHeight(true));
                <?php if (get_var('highlight') != 'false'){echo "$('pre code').each(function(i, block) { hljs.highlightBlock(block); });";} ?>

                window.data.stats.client_side_generation_time = performance.now() - window.centi_cms_js_page_generation_time_start;

            });

            data.stats.server_side_generation_time = <?php echo microtime(true) - $start_centicms; ?>;

        }());

    </script>

  </head>
  <body>Loading...</body>
</html>
