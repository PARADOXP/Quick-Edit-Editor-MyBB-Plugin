<?php
/**
 * Quick Edit Editor Plugin for MyBB
 * 
 * This plugin replaces the default quick edit textarea with MyBB's full-featured
 * BBCode editor (SCEditor) on the showthread page. Users can edit posts inline
 * with full access to all BBCode formatting options including:
 * - Bold, italic, underline, strikethrough
 * - Colors and fonts
 * - Links and images
 * - Lists (ordered and unordered)
 * - Quotes
 * - Code blocks
 * - YouTube videos
 * - And more...
 *
 * @package QuickEditEditor
 * @author PARAD0X
 * @version 1.0
 * @license GPL-3.0
 * @copyright Copyright 2024
 *
 * Compatible with MyBB 1.8.x
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Define plugin constants
define('QUICKEDIT_EDITOR_VERSION', '1.0');
define('QUICKEDIT_EDITOR_ROOT', MYBB_ROOT . 'inc/plugins/QuickEditEditor/');

// Load helper functions
require_once QUICKEDIT_EDITOR_ROOT . 'functions.php';

/**
 * Plugin information function
 */
function quickedit_editor_info()
{
    return array(
        "name"          => "Quick Edit Editor",
        "description"   => "Adds MyBB's Editor to quick edit boxes on the showthread page.",
        "website"       => "https://mybb.epizy.com",
        "author"        => "PARAD0X",
        "authorsite"    => "https://community.mybb.com/user-134311.html",
        "version"       => "1.1.0",
        "guid"          => "",
        "codename"      => "quickedit_editor",
        "compatibility" => "18*, 19*"
    );
}

// Register plugin hooks
$plugins->add_hook("showthread_end", "quickedit_editor_load");

/**
 * Main loader function - called on showthread page end
 */
function quickedit_editor_load()
{
    global $mybb, $headerinclude, $tid, $fid;
    
    // Check if plugin is enabled
    $enabled = isset($mybb->settings['quickedit_editor_enabled']) ? $mybb->settings['quickedit_editor_enabled'] : 1;
    if(!$enabled)
    {
        return;
    }

    // Check if user can view code buttons
    if($mybb->user['uid'] && $mybb->user['showcodebuttons'] == 0)
    {
        return;
    }
    
    // Check usergroup restriction
    $allowed_groups = isset($mybb->settings['quickedit_editor_usergroups']) ? $mybb->settings['quickedit_editor_usergroups'] : '';
    // -1 means all groups, empty means no groups selected (allow all as fallback)
    if($allowed_groups != '' && $allowed_groups != -1)
    {
        $allowed_groups_array = array_map('intval', explode(',', $allowed_groups));
        $user_groups = array($mybb->user['usergroup']);
        if(!empty($mybb->user['additionalgroups']))
        {
            $user_groups = array_merge($user_groups, explode(',', $mybb->user['additionalgroups']));
        }
        
        $has_access = false;
        foreach($user_groups as $g)
        {
            if(in_array((int)$g, $allowed_groups_array))
            {
                $has_access = true;
                break;
            }
        }
        
        if(!$has_access)
        {
            return;
        }
    }
    
    // Check forum restriction
    $allowed_forums = isset($mybb->settings['quickedit_editor_forums']) ? $mybb->settings['quickedit_editor_forums'] : '';
    // -1 means all forums, empty means no forums selected (allow all as fallback)
    if($allowed_forums != '' && $allowed_forums != -1 && !empty($fid))
    {
        $allowed_forums_array = array_map('intval', explode(',', $allowed_forums));
        if(!in_array((int)$fid, $allowed_forums_array))
        {
            return;
        }
    }

    // Generate editor configuration
    $quickedit_config = quickedit_editor_build_config();

    // Build the header injection
    $header_injection = quickedit_editor_build_header($quickedit_config);
    
    // Append to headerinclude
    $headerinclude .= $header_injection;
}

/**
 * Plugin installation function
 */
function quickedit_editor_install()
{
    global $db, $cache;

    // Nothing to install - purely hook-based plugin
}

/**
 * Check if plugin is installed
 *
 * @return bool Always returns true (no installation needed)
 */
function quickedit_editor_is_installed()
{
    global $mybb;

    // Check if our setting exists
    if(isset($mybb->settings['quickedit_editor_enabled']))
    {
        return true;
    }

    return false;
}

/**
 * Plugin uninstallation function
 */
function quickedit_editor_uninstall()
{
    global $db, $cache;

    // Delete settings
    $db->delete_query("settings", "name LIKE 'quickedit_editor_%'");
    $db->delete_query("settinggroups", "name = 'quickedit_editor'");

    // Rebuild settings cache
    rebuild_settings();
}

/**
 * Plugin activation function
 */
function quickedit_editor_activate()
{
    global $db, $cache;

    // Create settings group
    $setting_group = array(
        'name' => 'quickedit_editor',
        'title' => 'Quick Edit Editor Settings',
        'description' => 'Configuration settings for the Quick Edit Editor plugin.',
        'disporder' => 100,
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    // Create settings
    $settings = array();

    $settings[] = array(
        'name' => 'quickedit_editor_enabled',
        'title' => 'Enable Quick Edit Editor',
        'description' => 'Turn on/off the SCEditor in quick edit boxes.',
        'optionscode' => 'yesno',
        'value' => 1,
        'disporder' => 1,
        'gid' => $gid
    );

    $settings[] = array(
        'name' => 'quickedit_editor_allow_mycode',
        'title' => 'Allow MyCode (BBCode)',
        'description' => 'Allow users to use MyCode (BBCode) formatting in the quick edit editor.',
        'optionscode' => 'yesno',
        'value' => 1,
        'disporder' => 2,
        'gid' => $gid
    );

    $settings[] = array(
        'name' => 'quickedit_editor_allow_smilies',
        'title' => 'Allow Smilies',
        'description' => 'Allow smilies to be used in the quick edit editor.',
        'optionscode' => 'yesno',
        'value' => 1,
        'disporder' => 3,
        'gid' => $gid
    );

    $settings[] = array(
        'name' => 'quickedit_editor_height',
        'title' => 'Editor Height',
        'description' => 'Set the height of the quick edit textarea (in pixels).',
        'optionscode' => 'numeric',
        'value' => 250,
        'disporder' => 4,
        'gid' => $gid
    );

    $settings[] = array(
        'name' => 'quickedit_editor_usergroups',
        'title' => 'Allowed Usergroups',
        'description' => 'Select which usergroups can use the Quick Edit Editor.',
        'optionscode' => 'groupselect',
        'value' => '2,3,4,6',
        'disporder' => 5,
        'gid' => $gid
    );

    $settings[] = array(
        'name' => 'quickedit_editor_forums',
        'title' => 'Allowed Forums',
        'description' => 'Select in which forums the Quick Edit Editor is available.',
        'optionscode' => 'forumselect',
        'value' => '-1',
        'disporder' => 6,
        'gid' => $gid
    );

    foreach($settings as $setting)
    {
        $db->insert_query("settings", $setting);
    }

    // Rebuild settings cache
    rebuild_settings();
}

/**
 * Build SCEditor configuration for quick edit
 *
 * @return array Configuration array for SCEditor
 */
function quickedit_editor_build_config()
{
    global $mybb, $theme, $lang, $smiliecache, $cache;

    // Initialize language if needed
    if(!isset($lang->editor_title_bold))
    {
        $lang->load("global");
    }

    // Check basic requirements - with fallbacks if settings don't exist
    $allowsmilies = isset($mybb->settings['quickedit_editor_allow_smilies']) ? $mybb->settings['quickedit_editor_allow_smilies'] : 1;
    $allowmycode = isset($mybb->settings['quickedit_editor_allow_mycode']) ? $mybb->settings['quickedit_editor_allow_mycode'] : 1;

    // Build language array
    $editor_language = "{}";
    if($lang->settings['rtl'])
    {
        $lang_directory = "rtl";
    }
    else
    {
        $lang_directory = "normal";
    }

    // Build smilie cache if needed
    $emoticons_enabled = "false";
    $dropdownsmilies = "";
    $moresmilies = "";
    $hiddensmilies = "";

    // Check smilieinserter settings with fallbacks
    $smilieinserter = isset($mybb->settings['smilieinserter']) ? $mybb->settings['smilieinserter'] : 1;
    $smilieinsertercols = isset($mybb->settings['smilieinsertercols']) ? $mybb->settings['smilieinsertercols'] : 4;
    $smilieinsertertot = isset($mybb->settings['smilieinsertertot']) ? $mybb->settings['smilieinsertertot'] : 15;

        if($allowsmilies && $smilieinserter && $smilieinsertercols)
    {
        $emoticons_enabled = "true";

        if(!$smiliecache)
        {
            if(!is_array($smiliecache))
            {
                $smiliecache = $cache->read("smilies");
            }
            // Pre-process smilie paths like MyBB does
            if(is_array($smiliecache))
            {
                foreach($smiliecache as $sid => $smilie)
                {
                    $smiliecache[$sid]['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
                }
            }
        }

        if(is_array($smiliecache) && !empty($smiliecache))
        {
            $i = 0;
            $dropdownsmilies_arr = array();
            $moresmilies_arr = array();
            $hiddensmilies_arr = array();

            foreach($smiliecache as $smilie)
            {
                if($smilie['showclickable'] != 0)
                {
                    $i++;
                }

                $finds = explode("\n", $smilie['find']);
                $first_find = $finds[0];

                // Escape for JavaScript - use same approach as MyBB build_mycode_inserter
                $find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($first_find));
                $image = htmlspecialchars_uni($mybb->asset_url . '/' . $smilie['image']);
                $image = str_replace(array('\\', '"'), array('\\\\', '\"'), $image);

                if(!$smilieinserter || !$smilieinsertercols || !$smilieinsertertot || !$smilie['showclickable'])
                {
                    $hiddensmilies_arr[$find] = $image;
                }
                elseif($i <= $smilieinsertertot)
                {
                    $dropdownsmilies_arr[$find] = $image;
                }
                else
                {
                    $moresmilies_arr[$find] = $image;
                }

                // Add additional find strings as hidden smilies
                for($j = 1; $j < count($finds); $j++)
                {
                    $find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($finds[$j]));
                    $hiddensmilies_arr[$find] = $image;
                }
            }

            // Convert to JSON strings for JavaScript
            $dropdownsmilies = json_encode($dropdownsmilies_arr);
            $moresmilies = json_encode($moresmilies_arr);
            $hiddensmilies = json_encode($hiddensmilies_arr);
        }
    }

    // Build toolbar based on settings
    $basic1 = $basic2 = $align = $font = $size = $color = $removeformat = $email = $link = $list = $code = $sourcemode = $emoticon = "";

    if($mybb->settings['allowbasicmycode'] == 1)
    {
        $basic1 = "bold,italic,underline,strike|";
        $basic2 = "horizontalrule,";
    }

    if($mybb->settings['allowalignmycode'] == 1)
    {
        $align = "left,center,right,justify|";
    }

    if($mybb->settings['allowfontmycode'] == 1)
    {
        $font = "font,";
    }

    if($mybb->settings['allowsizemycode'] == 1)
    {
        $size = "size,";
    }

    if($mybb->settings['allowcolormycode'] == 1)
    {
        $color = "color,";
    }

    if($mybb->settings['allowfontmycode'] == 1 || $mybb->settings['allowsizemycode'] == 1 || $mybb->settings['allowcolormycode'] == 1)
    {
        $removeformat = "removeformat|";
    }

    if($mybb->settings['allowlinkmycode'] == 1)
    {
        $email = "email,";
        $link = "link,unlink|";
    }

    if($mybb->settings['allowlistmycode'] == 1)
    {
        $list = "bulletlist,orderedlist|";
    }

    if($mybb->settings['allowcodemycode'] == 1)
    {
        $code = "code,php";
    }

    // Add emoticon button if enabled
    $emoticon = "";
    if($allowsmilies && $smilieinserter && $smilieinsertercols)
    {
        $emoticon = ",emoticon";
    }

    // Build toolbar string - use same format as MyBB's codebuttons
    $toolbar = "";
    if($allowmycode)
    {
        $toolbar = "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link}|video{$emoticon}|{$list}{$code}|quote|maximize,source";
    }
    else
    {
        $toolbar = "bold,italic,underline,strike|link,unlink|image|source";
    }

    // Get editor height
    $height = isset($mybb->settings['quickedit_editor_height']) ? (int)$mybb->settings['quickedit_editor_height'] : 250;
    if($height < 100)
    {
        $height = 250;
    }

    // Build configuration array
    $config = array(
        'theme' => $theme['editortheme'],
        'height' => $height,
        'toolbar' => $toolbar,
        'rtl' => $lang->settings['rtl'] ? true : false,
        'emoticonsEnabled' => $emoticons_enabled,
        'dropdownSmilies' => $dropdownsmilies,
        'moreSmilies' => $moresmilies,
        'hiddenSmilies' => $hiddensmilies,
        'allowMyCode' => $allowmycode ? true : false,
        'assetUrl' => $mybb->asset_url,
        'enablePasteFiltering' => true,
        'autoUpdate' => true,
        'format' => 'bbcode',
        'bbcodeTrim' => false,
        'plugins' => 'undo',
        'locale' => 'mybblang',
        'emoticonsCompat' => true
    );

    return $config;
}

/**
 * Build the header HTML to inject SCEditor configuration
 * Uses MyBB's codebuttons template to automatically include all plugin modifications
 *
 * @param array $config SCEditor configuration
 * @return string HTML/JS to inject in header
 */
function quickedit_editor_build_header($config)
{
    global $db, $mybb, $theme, $lang, $cache, $smiliecache;
    
    // Load required files for template evaluation
    if(!isset($lang->editor_title_bold))
    {
        $lang->load("global");
    }
    
    // Get smilie cache if not already loaded
    if(!$smiliecache)
    {
        $smiliecache = $cache->read("smilies");
    }
    
    // Build smilies if enabled - respect plugin's allow_smilies setting
    $emoticons_enabled = "false";
    $dropdownsmilies = "";
    $moresmilies = "";
    $hiddensmilies = "";
    
    $allow_smilies = isset($mybb->settings['quickedit_editor_allow_smilies']) ? $mybb->settings['quickedit_editor_allow_smilies'] : 1;
    $smilieinserter = isset($mybb->settings['smilieinserter']) ? $mybb->settings['smilieinserter'] : 1;
    $smilieinsertercols = isset($mybb->settings['smilieinsertercols']) ? $mybb->settings['smilieinsertercols'] : 4;
    $smilieinsertertot = isset($mybb->settings['smilieinsertertot']) ? $mybb->settings['smilieinsertertot'] : 15;
    
    if($allow_smilies && $smilieinserter && $smilieinsertercols && $smilieinsertertot)
    {
        $emoticons_enabled = "true";
        
        if(is_array($smiliecache))
        {
            $i = 0;
            foreach($smiliecache as $smilie)
            {
                if($smilie['showclickable'] != 0)
                {
                    $i++;
                }
                
                $smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
                
                $finds = explode("\n", $smilie['find']);
                $smilie['find'] = $finds[0];
                
                $find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($smilie['find']));
                $image = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
                $image = str_replace(array('\\', '"'), array('\\\\', '\"'), $image);
                
                if(!$smilieinserter || !$smilieinsertercols || !$smilieinsertertot || !$smilie['showclickable'])
                {
                    $hiddensmilies .= '"'.$find.'": "'.$image.'",';
                }
                elseif($i <= $smilieinsertertot)
                {
                    $dropdownsmilies .= '"'.$find.'": "'.$image.'",';
                }
                else
                {
                    $moresmilies .= '"'.$find.'": "'.$image.'",';
                }
                
                for($j = 1; $j < count($finds); $j++)
                {
                    $find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($finds[$j]));
                    $hiddensmilies .= '"'.$find.'": "'.$image.'",';
                }
            }
        }
    }
    
    // Build toolbar based on settings (same as MyBB)
    $basic1 = $basic2 = $align = $font = $size = $color = $removeformat = $email = $link = $list = $code = $emoticon = "";
    
    if($mybb->settings['allowbasicmycode'] == 1)
    {
        $basic1 = "bold,italic,underline,strike|";
        $basic2 = "horizontalrule,";
    }
    // Check if mycode is allowed - respect plugin setting
    $allow_mycode = isset($mybb->settings['quickedit_editor_allow_mycode']) ? $mybb->settings['quickedit_editor_allow_mycode'] : 1;
    
    if($allow_mycode == 1)
    {
        if(!empty($mybb->settings['allowbulkmycode']))
        {
            $basic1 = "bold,italic,underline,strike|";
        }
        if(!empty($mybb->settings['allowalignmycode']))
        {
            $align = "left,center,right,justify|";
        }
        if(!empty($mybb->settings['allowfontmycode']))
        {
            $font = "font,";
        }
        if(!empty($mybb->settings['allowsizemycode']))
        {
            $size = "size,";
        }
        if(!empty($mybb->settings['allowcolormycode']))
        {
            $color = "color,";
        }
        if(!empty($mybb->settings['allowfontmycode']) || !empty($mybb->settings['allowsizemycode']) || !empty($mybb->settings['allowcolormycode']))
        {
            $removeformat = "removeformat|";
        }
        if(!empty($mybb->settings['allowlinkmycode']))
        {
            $email = "email,";
            $link = "link,unlink|";
        }
        if(!empty($mybb->settings['allowlistmycode']))
        {
            $list = "bulletlist,orderedlist|";
        }
        if(!empty($mybb->settings['allowcodemycode']))
        {
            $code = "code,php";
        }
        if($emoticons_enabled == "true")
        {
            $emoticon = ",emoticon";
        }
        
        $toolbar = "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link}|video{$emoticon}|{$list}{$code}|quote|maximize,source";
    }
    else
    {
        // If mycode is disabled, use minimal toolbar
        $toolbar = "bold,italic,underline,strike|link,unlink|source";
    }
    
    // Get editor height
    $height = isset($mybb->settings['quickedit_editor_height']) ? (int)$mybb->settings['quickedit_editor_height'] : 250;
    if($height < 100) $height = 250;
    
    // ---------------------------------------------------------------
    // Fetch the live codebuttons template from the database.
    // Strategy: check the theme-specific override first (this is what
    // find_replace_templatesets() modifies), then fall back to the
    // MyBB master template (sid = -2).  Whatever any plugin adds to
    // codebuttons will be in the theme-specific row, so we always
    // prefer that one.
    // ---------------------------------------------------------------
    $template = '';

    // 1) Theme-specific version (plugins write here via find_replace_templatesets)
    if(!empty($theme['templateset']))
    {
        $query  = $db->simple_select('templates', 'template',
            "title='codebuttons' AND sid='".(int)$theme['templateset']."'");
        $result = $db->fetch_array($query);
        if($result && !empty($result['template']))
        {
            $template = $result['template'];
        }
    }

    // 2) MyBB master template (sid = -2) — used when no theme override exists
    if(empty($template))
    {
        $query  = $db->simple_select('templates', 'template', "title='codebuttons' AND sid='-2'");
        $result = $db->fetch_array($query);
        if($result && !empty($result['template']))
        {
            $template = $result['template'];
        }
    }

    // 3) Absolute last resort — the SCEditor files could not be located at all
    if(empty($template))
    {
        return quickedit_editor_build_fallback_header($config, $toolbar, $emoticons_enabled, $dropdownsmilies, $moresmilies, $hiddensmilies, $height);
    }

    
    // Resolve MyBB template variable placeholders into real URLs.
    // We pre-build the placeholder strings as variable values so PHP
    // cannot expand them during compilation — they stay as the exact
    // literal text that appears in the raw database template.

    $asset_url   = $mybb->asset_url;
    $editortheme = $theme['editortheme'];
    $imgdir      = $theme['imgdir'];
    // Build placeholder strings via concatenation so PHP never tries to
    // expand them as variables during compilation.
    $dollar            = '$';
    $ph_asset_url      = '{' . $dollar . 'mybb->asset_url}';
    $ph_editortheme_sq = '{' . $dollar . "theme['editortheme']}";
    $ph_imgdir_sq      = '{' . $dollar . "theme['imgdir']}";
    $template = str_replace($ph_asset_url,      $asset_url,   $template);
    $template = str_replace($ph_editortheme_sq, $editortheme, $template);
    $template = str_replace($ph_imgdir_sq,      $imgdir,      $template);

    $header = "<!-- Quick Edit Editor: live assets from codebuttons -->\n";

    // Extract ALL <link ...> tags — we capture the full tag so we
    // output it exactly as it appears (preserving rel, media, etc.)
    preg_match_all('/<link\b[^>]+>/i', $template, $css_matches);
    $css_tags = isset($css_matches[0]) ? $css_matches[0] : array();

    // Extract ALL external <script src="..."></script> tags.
    // Inline <script> blocks are intentionally skipped — we build
    // our own quickedit_opt_editor config object below.
    preg_match_all('/<script\b[^>]+\bsrc="([^"]+)"[^>]*>\s*<\/script>/i',
        $template, $js_matches, PREG_SET_ORDER);
    
    // Add CSS link tags (deduplicated by href value)
    $added_css = array();
    foreach($css_tags as $link_tag)
    {
        if(preg_match('/\bhref="([^"]+)"/i', $link_tag, $m))
        {
            if(!in_array($m[1], $added_css))
            {
                $added_css[] = $m[1];
                $header .= $link_tag . "\n";
            }
        }
    }

    // Add external JS script tags (deduplicated by src value)
    $added_js = array();
    foreach($js_matches as $match)
    {
        $src = $match[1];
        if(!in_array($src, $added_js))
        {
            $added_js[] = $src;
            $header .= $match[0] . "\n";
        }
    }
    
    // Add our config and plugin JS
    $header .= quickedit_editor_build_fallback_header($config, $toolbar, $emoticons_enabled, $dropdownsmilies, $moresmilies, $hiddensmilies, $height);
    
    $header .= "\n<!-- /Quick Edit Editor Plugin -->";
    
    return $header;
}

/**
 * Fallback header if codebuttons template can't be loaded, or for injecting config/plugin JS
 */
function quickedit_editor_build_fallback_header($config, $toolbar, $emoticons_enabled, $dropdownsmilies, $moresmilies, $hiddensmilies, $height)
{
    global $mybb, $lang, $theme;
    
    $rtl = $lang->settings['rtl'] ? 'true' : 'false';
    
    $header = '
<script type="text/javascript">
var partialmode = ' . ($mybb->settings['partialmode'] ? 'true' : 'false') . ',
quickedit_opt_editor = {
    plugins: "undo",
    format: "bbcode",
    bbcodeTrim: false,
    style: "'.$mybb->asset_url.'/jscripts/sceditor/themes/'.$theme['editortheme'].'",
    rtl: '.$rtl.',
    locale: "mybblang",
    width: "100%",
    height: '.$height.',
    enablePasteFiltering: true,
    autoUpdate: true,
    emoticonsEnabled: '.$emoticons_enabled.',
    emoticons: {
        dropdown: {'.$dropdownsmilies.'},
        more: {'.$moresmilies.'},
        hidden: {'.$hiddensmilies.'}
    },
    emoticonsCompat: true,
    toolbar: "'.$toolbar.'"
};
</script>
<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/quickedit_editor/quickedit_editor.js?ver=1.0"></script>
';

    return $header;
}