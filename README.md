# Quick Edit Editor — MyBB Plugin

Adds MyBB's SCEditor to quick edit boxes on the showthread page.

MyBB's inline quick edit is a plain textarea. This plugin replaces it with the same SCEditor used by the full post editor: toolbar, smilies, MyCode buttons, source mode, and maximise — without leaving the thread. Posts are still fetched and saved through MyBB's existing `xmlhttp.php` quick-edit endpoint, so forum permissions, edit time limits, post keys, and the post data handler all apply as normal.

## Features

- **SCEditor in quick edit** — clicking *Quick Edit* opens a full editor in place of the post body.
- **Reuses MyBB's live `codebuttons` template** — the stylesheet and external scripts are read from the current theme's `codebuttons` row (falling back to the master template), so SCEditor assets and third-party additions to that template carry over.
- **AJAX fetch** — loads the raw post message from `xmlhttp.php?action=edit_post&do=get_post`.
- **AJAX save** — submits through `xmlhttp.php?action=edit_post&do=update_post` with the post key, message, and optional edit reason.
- **Moderation-aware** — handles `moderation_post` / `moderation_thread` responses and follows the returned URL.
- **Edited-by refresh** — updates the post's edited-by block after a successful save.
- **Syntax highlighting refresh** — calls `MyBB.SyntaxHighlighter.highlight()` when available.
- **Multiple editors** — keeps an instance map per post ID and restores the original `MyBBEditor` reference when the last quick edit closes.
- **Cancel restores the original post** from the temp clone MyBB's stock quick edit creates.
- **Public JS API** — `window.QuickEditSCEditor` exposes `init`, `destroy`, `destroyAll`, `getEditor`, `reinitialize`, `onEditorInit`, `onEditorReady`, `getDefaultToolbar`, and `getDefaultConfig`.
- **Permission gates** — master switch, `showcodebuttons`, allowed usergroups (primary plus additional), and allowed forums.
- **Configurable** — editor height, MyCode toolbar on/off, and smilies on/off.

## Requirements

| Requirement | Version / note |
| --- | --- |
| MyBB | 1.8.x (the plugin also declares `19*` compatibility) |
| PHP | 7.4+ (works on PHP 8.x) |
| SCEditor | Stock MyBB assets under `jscripts/sceditor/` |
| jQuery | Stock MyBB front-end jQuery |
| PluginLibrary | **Not required** — this plugin does not use PluginLibrary |

## Installation

1. Extract the repository into your MyBB root (the directory containing `index.php`).
2. **Admin CP → Configuration → Plugins** → **Quick Edit Editor** → **Install**, then **Activate**.
3. **Configuration → Settings → Quick Edit Editor Settings** to set the height, MyCode/smilie toggles, usergroups, and forums.
4. Open a thread and use *Quick Edit* on a post you are allowed to edit.

No template edits, core edits, database tables, or file permissions are needed. The plugin is entirely hook-based.

## Repository layout

```
inc/plugins/quickedit_editor.php                       # hooks, settings, config, header builder
inc/plugins/QuickEditEditor/functions.php              # helper functions (mostly unused, see Notes)
jscripts/quickedit_editor/quickedit_editor.js          # click handler, AJAX form, SCEditor init
inc/languages/english/quickedit_editor.lang.php        # phrases (not loaded; see Notes)
README.md
LICENSE                                                # GPL-3.0
```

The JavaScript file is deliberately outside `inc/plugins/` and must be uploaded to `jscripts/quickedit_editor/`. There is no `settings.json`, no `Templates/` folder, no CSS file, and no admin module.

## Settings (6)

| Setting | Default | Description |
| --- | --- | --- |
| Enable Quick Edit Editor | `1` | Master switch. |
| Allow MyCode (BBCode) | `1` | Build the full MyCode toolbar. When off, a minimal bold/italic/underline/link/source toolbar is used. |
| Allow Smilies | `1` | Enable the emoticon button and smilie data. |
| Editor Height | `250` | Height in pixels; values below `100` fall back to `250`. |
| Allowed Usergroups | `2,3,4,6` | Primary and additional groups that load the editor. |
| Allowed Forums | `-1` (all) | Forums where the editor loads. |

Stored as `quickedit_editor_<key>`. The settings group is created directly with `$db->insert_query()` on activation — there is no `settings.json` and no PluginLibrary call.

## How it works

- `showthread_end` runs `quickedit_editor_load()`.
- The loader checks the master switch, `showcodebuttons` (for logged-in users), the usergroup list (including additional groups), and the forum list.
- `quickedit_editor_build_config()` assembles toolbar, smilie, RTL, theme, and height data.
- `quickedit_editor_build_header()` reads the `codebuttons` template row for the current theme (`sid = templateset`), falls back to the master row (`sid = -2`), and extracts every `<link>` and external `<script src>` tag. It resolves `{$mybb->asset_url}`, `{$theme['editortheme']}`, and `{$theme['imgdir']}` in those tags.
- The header then appends an inline `quickedit_opt_editor` object and loads `jscripts/quickedit_editor/quickedit_editor.js`.
- On the client, a delegated click handler on `.quick_edit_button` fetches the raw message, builds a form with a textarea, initialises SCEditor, and binds submit/cancel handlers.
- Submit sends the message and edit reason to `xmlhttp.php`, replaces the post body with the parsed response, refreshes the edited-by block, and destroys the editor instance.

## Notes and known limitations

- **Activation is not idempotent.** `quickedit_editor_activate()` always inserts a new `quickedit_editor` setting group and six new settings. Deactivate then activate again (or activate after an upgrade) creates duplicate groups and duplicate setting names. The last duplicate wins in the settings cache; the extras clutter the ACP and are only removed by uninstall. There is no `settings.json` or PluginLibrary guard to prevent this.
- **There is no `_deactivate()` function.** Deactivation relies on MyBB no longer calling the hook. The settings group and settings remain, which is what makes the duplicate-on-reactivate problem possible.
- **The language file is never loaded.** Nothing calls `$lang->load('quickedit_editor')`, and the keys in the file are named `quickedit_sceditor_*` while the plugin uses `quickedit_editor_*`. All setting labels are hardcoded English in PHP, and there is no Admin CP language file. The shipped phrase file is effectively dead.
- **The locale is probably missing.** The editor config sets `locale: "mybblang"`, but `mybblang` is defined by MyBB's `build_mycode_inserter()` inside the `codebuttons` template. The showthread page does not build that editor by default, and the plugin deliberately ignores the template's inline scripts, so the locale object may never exist. `quickedit_editor_get_language_strings()` builds a replacement but is never called. SCEditor may fall back to its default English strings.
- **Fallback header has no SCEditor assets.** If the `codebuttons` template cannot be found in either the theme or the master set, `quickedit_editor_build_fallback_header()` emits only the config and plugin JS — no SCEditor stylesheet or script. The editor then silently fails to initialise.
- **Only three template placeholders are resolved.** Any other placeholder inside a `<link>`/`<script>` added to `codebuttons` by another plugin (for example `{$mybb->settings['bburl']}`) is left as literal text and the asset fails to load.
- **Stock and plugin quick-edit handlers both run.** MyBB's `jscripts/thread.js` binds a direct click handler to `.quick_edit_button`; this plugin adds a delegated handler on `document`. The stock handler transforms the post first, then the plugin fetches and replaces the content. It usually ends with the plugin's form, but the interaction is fragile and depends on handler order.
- **No `bbcodeinserter` check at load time.** `quickedit_editor_is_enabled()` in `functions.php` checks the global BBCode inserter setting but is never called. The editor loads even when the board has BBCode buttons disabled.
- **No SCEditor existence check in PHP.** If the stock `jscripts/sceditor/` assets are missing, the page still emits the config and plugin JS; the JS returns early when `$.fn.sceditor` is undefined, leaving the stock textarea quick edit in place.
- **Toolbar and config are built twice.** `quickedit_editor_build_config()` returns a config array that `quickedit_editor_build_header()` ignores; the header rebuilds the toolbar and smilies with slightly different logic. Several helpers in `functions.php` (`is_enabled`, `get_forum_smilie_setting`, `build_toolbar`, `build_emoticons`, `get_language_strings`) are dead code.
- **Version strings disagree.** `quickedit_editor_info()` reports `1.1.0`, while `QUICKEDIT_EDITOR_VERSION` and the JS cache-buster say `1.0`, and the file header says `@version 1.0`.
- **The JavaScript builds HTML with string concatenation** and uses inline `style` attributes for the form and buttons. There are no registered templates or stylesheet assets, so the quick-edit form cannot be themed from the ACP.
- **Usergroup/forum filters are fail-open.** An empty usergroup or forum setting is treated as “allow all”, not “allow none”. `-1` also means all.
- **Saving still goes through MyBB's normal post handler.** The editor does not bypass edit permissions, edit time limits, post moderation, or the post key check; those are enforced in `xmlhttp.php` before the data handler runs.

## Author

**PARAD0X** — [MyBB Community profile](https://community.mybb.com/user-134311.html)

- Website: https://mybb.epizy.com
- Version: 1.1.0

## License

GPL-3.0 — see [LICENSE](LICENSE).
