<?php
/**
 * Quick Edit Editor - Helper Functions
 *
 * @package QuickEditEditor
 * @author Claude
 * @version 1.0
 */

// Disallow direct access
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Check if quick edit SCEditor should be enabled for current user
 *
 * @return bool True if enabled, false otherwise
 */
function quickedit_editor_is_enabled()
{
    global $mybb;

    // Check plugin enabled setting
    if(!$mybb->settings['quickedit_editor_enabled'])
    {
        return false;
    }

    // Check if user can see code buttons
    if($mybb->user['uid'] && $mybb->user['showcodebuttons'] == 0)
    {
        return false;
    }

    // Check if BBCode inserter is enabled globally
    if($mybb->settings['bbcodeinserter'] == 0)
    {
        return false;
    }

    return true;
}

/**
 * Get the current forum's allowsmilies setting for quick edit context
 * Since quick edit doesn't have direct forum context in the same way,
 * we need to determine from the post
 *
 * @param int $pid Post ID
 * @return bool True if smilies allowed
 */
function quickedit_editor_get_forum_smilie_setting($pid = 0)
{
    global $db, $mybb;

    // If no PID, use default
    if(!$pid)
    {
        return true;
    }

    // Get forum from post
    $query = $db->simple_select("posts p LEFT JOIN " . TABLE_PREFIX . "forums f ON (p.fid = f.fid)", "f.allowsmilies", "p.pid = '" . (int)$pid . "'");
    $forum = $db->fetch_array($query);

    if($forum && $forum['allowsmilies'] == 1)
    {
        return true;
    }

    return false;
}

/**
 * Build the SCEditor toolbar configuration string
 * This mirrors the logic in build_mycode_inserter() but is tailored for quick edit
 *
 * @return string Toolbar configuration
 */
function quickedit_editor_build_toolbar()
{
    global $mybb;

    $basic1 = $basic2 = $align = $font = $size = $color = $removeformat = $email = $link = $list = $code = "";

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
        $code = "code,php,";
    }

    // Emoticon button will be added conditionally
    $emoticon = "";
    if($mybb->settings['smilieinserter'] && $mybb->settings['smilieinsertercols'])
    {
        $emoticon = "emoticon";
    }

    // Build the toolbar string
    $toolbar = "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link}|video{$emoticon}|{$list}{$code}quote|maximize,source";

    return $toolbar;
}

/**
 * Build smilie/emoticon configuration for SCEditor
 *
 * @return array Emoticon configuration array
 */
function quickedit_editor_build_emoticons()
{
    global $mybb, $theme, $cache, $smiliecache;

    $emoticons = array(
        'dropdown' => array(),
        'more' => array(),
        'hidden' => array()
    );

    // Check if smilies are enabled
    if(!$mybb->settings['smilieinserter'] || !$mybb->settings['smilieinsertercols'])
    {
        return $emoticons;
    }

    // Load smilie cache if not loaded
    if(!is_array($smiliecache))
    {
        $smiliecache = $cache->read("smilies");
    }

    if(!is_array($smiliecache))
    {
        return $emoticons;
    }

    $i = 0;
    foreach($smiliecache as $smilie)
    {
        // Build image URL
        $image = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
        $image = $mybb->asset_url . '/' . $image;

        if($smilie['showclickable'] != 0)
        {
            $i++;
            if($i <= $mybb->settings['smilieinsertertot'])
            {
                $emoticons['dropdown'][$smilie['find']] = $image;
            }
            else
            {
                $emoticons['more'][$smilie['find']] = $image;
            }
        }
        else
        {
            $emoticons['hidden'][$smilie['find']] = $image;
        }
    }

    return $emoticons;
}

/**
 * Escape string for safe inclusion in JavaScript
 *
 * @param string $string String to escape
 * @return string Escaped string
 */
function quickedit_editor_js_escape($string)
{
    $string = str_replace("\\", "\\\\", $string);
    $string = str_replace("'", "\\'", $string);
    $string = str_replace('"', '\\"', $string);
    $string = str_replace("\n", "\\n", $string);
    $string = str_replace("\r", "\\r", $string);
    $string = str_replace("\t", "\\t", $string);

    return $string;
}

/**
 * Get the editor language strings for SCEditor
 * This builds the mybblang object used by SCEditor
 *
 * @return string JavaScript object as string
 */
function quickedit_editor_get_language_strings()
{
    global $lang;

    // Load global lang file if needed
    if(!isset($lang->editor_title_bold))
    {
        $lang->load("global");
    }

    // Build language array for SCEditor
    $strings = array(
        'Bold' => isset($lang->editor_title_bold) ? $lang->editor_title_bold : 'Bold',
        'Italic' => isset($lang->editor_title_italic) ? $lang->editor_title_italic : 'Italic',
        'Underline' => isset($lang->editor_title_underline) ? $lang->editor_title_underline : 'Underline',
        'Strikethrough' => isset($lang->editor_title_strikethrough) ? $lang->editor_title_strikethrough : 'Strikethrough',
        'Subscript' => isset($lang->editor_title_subscript) ? $lang->editor_title_subscript : 'Subscript',
        'Superscript' => isset($lang->editor_title_superscript) ? $lang->editor_title_superscript : 'Superscript',
        'Align left' => isset($lang->editor_title_left) ? $lang->editor_title_left : 'Align left',
        'Center' => isset($lang->editor_title_center) ? $lang->editor_title_center : 'Center',
        'Align right' => isset($lang->editor_title_right) ? $lang->editor_title_right : 'Align right',
        'Justify' => isset($lang->editor_title_justify) ? $lang->editor_title_justify : 'Justify',
        'Font Name' => isset($lang->editor_font) ? $lang->editor_font : 'Font Name',
        'Font Size' => isset($lang->editor_size) ? $lang->editor_size : 'Font Size',
        'Font Color' => isset($lang->editor_color) ? $lang->editor_color : 'Font Color',
        'Remove Formatting' => isset($lang->editor_title_removeformatting) ? $lang->editor_title_removeformatting : 'Remove Formatting',
        'Cut' => isset($lang->editor_cut) ? $lang->editor_cut : 'Cut',
        'Your browser does not allow the cut command. Please use the keyboard shortcut Ctrl/Cmd-X' => isset($lang->editor_cutnosupport) ? $lang->editor_cutnosupport : 'Your browser does not allow the cut command. Please use the keyboard shortcut Ctrl/Cmd-X',
        'Copy' => isset($lang->editor_copy) ? $lang->editor_copy : 'Copy',
        'Your browser does not allow the copy command. Please use the keyboard shortcut Ctrl/Cmd-C' => isset($lang->editor_copynosupport) ? $lang->editor_copynosupport : 'Your browser does not allow the copy command. Please use the keyboard shortcut Ctrl/Cmd-C',
        'Paste' => isset($lang->editor_paste) ? $lang->editor_paste : 'Paste',
        'Your browser does not allow the paste command. Please use the keyboard shortcut Ctrl/Cmd-V' => isset($lang->editor_pastenosupport) ? $lang->editor_pastenosupport : 'Your browser does not allow the paste command. Please use the keyboard shortcut Ctrl/Cmd-V',
        'Paste Text' => isset($lang->editor_pastetext) ? $lang->editor_pastetext : 'Paste Text',
        'Paste as plain text' => isset($lang->editor_pastetext) ? $lang->editor_pastetext : 'Paste as plain text',
        'Bullet list' => isset($lang->editor_title_bulletlist) ? $lang->editor_title_bulletlist : 'Bullet list',
        'Numbered list' => isset($lang->editor_title_orderedlist) ? $lang->editor_title_orderedlist : 'Numbered list',
        'Undo' => isset($lang->editor_title_undo) ? $lang->editor_title_undo : 'Undo',
        'Redo' => isset($lang->editor_title_redo) ? $lang->editor_title_redo : 'Redo',
        'Insert/Edit Link' => isset($lang->editor_title_hyperlink) ? $lang->editor_title_hyperlink : 'Insert/Edit Link',
        'Unlink' => isset($lang->editor_title_unlink) ? $lang->editor_title_unlink : 'Unlink',
        'Insert Image' => isset($lang->editor_title_insertimage) ? $lang->editor_title_insertimage : 'Insert Image',
        'Email' => isset($lang->editor_title_email) ? $lang->editor_title_email : 'Email',
        'Insert an email' => isset($lang->editor_title_email) ? $lang->editor_title_email : 'Insert an email',
        'Horizontal Rule' => isset($lang->editor_title_inserthorizontalrule) ? $lang->editor_title_inserthorizontalrule : 'Horizontal Rule',
        'Code' => isset($lang->editor_title_code) ? $lang->editor_title_code : 'Code',
        'Insert PHP Code' => isset($lang->editor_title_php) ? $lang->editor_title_php : 'Insert PHP Code',
        'Quote' => isset($lang->editor_title_quote) ? $lang->editor_title_quote : 'Quote',
        'Insert a Quote' => isset($lang->editor_title_quote) ? $lang->editor_title_quote : 'Insert a Quote',
        'Video' => isset($lang->editor_title_video) ? $lang->editor_title_video : 'Video',
        'Width (optional):' => isset($lang->editor_video_width) ? $lang->editor_video_width : 'Width (optional):',
        'Height (optional):' => isset($lang->editor_video_height) ? $lang->editor_video_height : 'Height (optional):',
        'Insert a video' => isset($lang->editor_title_video) ? $lang->editor_title_video : 'Insert a video',
        'More' => isset($lang->more_smilies) ? $lang->more_smilies : 'More',
        'Insert an emoticon' => isset($lang->editor_title_emoticon) ? $lang->editor_title_emoticon : 'Insert an emoticon',
        'Video Type:' => isset($lang->editor_video_type) ? $lang->editor_video_type : 'Video Type:',
        'Insert' => isset($lang->insert) ? $lang->insert : 'Insert',
        'Cancel' => isset($lang->cancel) ? $lang->cancel : 'Cancel',
        'Insert a Link' => isset($lang->editor_title_hyperlink) ? $lang->editor_title_hyperlink : 'Insert a Link',
        'Enter the image URL:' => isset($lang->editor_enter_image_url) ? $lang->editor_enter_image_url : 'Enter the image URL:',
        'Date/Time' => 'Date/Time',
        'Insert current date/time' => 'Insert current date/time',
        'Print' => 'Print',
        'Maximize' => isset($lang->editor_maximize) ? $lang->editor_maximize : 'Maximize',
        'Source' => isset($lang->editor_viewsource) ? $lang->editor_viewsource : 'Source',
        'Enter URL:' => isset($lang->editor_url) ? $lang->editor_url : 'Enter URL:',
        'Enter the link URL:' => isset($lang->editor_url) ? $lang->editor_url : 'Enter the link URL:',
        'Describe the link URL here...' => isset($lang->editor_enter_url) ? $lang->editor_enter_url : 'Describe the link URL here...',
        'Description (optional):' => isset($lang->editor_description) ? $lang->editor_description : 'Description (optional):',
        'Enter the email address:' => isset($lang->editor_enter_email) ? $lang->editor_enter_email : 'Enter the email address:',
        'Enter the shown text:' => isset($lang->editor_enter_text) ? $lang->editor_enter_text : 'Enter the shown text:',
        'Enter the list item. Click ok or press enter to add another item. Click cancel or press escape when done.' => isset($lang->editor_enter_list_item) ? $lang->editor_enter_list_item : 'Enter the list item. Click ok or press enter to add another item. Click cancel or press escape when done.',
        'Enter the video URL:' => isset($lang->editor_video_url) ? $lang->editor_video_url : 'Enter the video URL:',
        'Video URL:' => isset($lang->editor_video_url) ? $lang->editor_video_url : 'Video URL:',
        'Right-to-Left' => 'Right-to-Left',
        'Left-to-Right' => 'Left-to-Right',
        'Preformatted Text' => 'Preformatted Text',
        'View source' => isset($lang->editor_viewsource) ? $lang->editor_viewsource : 'View source',
        'Drop files here' => 'Drop files here',
        'Or click to browse...' => 'Or click to browse...',
        'Enter the YouTube video URL:' => isset($lang->editor_video_url) ? $lang->editor_video_url : 'Enter the YouTube video URL:',
    );

    // Convert to JavaScript object format
    $js = "var mybblang = {\n";
    $first = true;
    foreach($strings as $key => $value)
    {
        if(!$first)
        {
            $js .= ",\n";
        }
        $js .= "    '" . quickedit_editor_js_escape($key) . "': '" . quickedit_editor_js_escape($value) . "'";
        $first = false;
    }
    $js .= "\n};";

    return $js;
}
