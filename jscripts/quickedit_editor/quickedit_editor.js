/**
 * Quick Edit Editor Plugin - JavaScript
 * Handles initialization of SCEditor on quick edit forms
 *
 * @package QuickEditEditor
 * @version 1.0
 */

// Quick Edit Editor - loaded


(function($) {
    "use strict";

    // Store references to SCEditor instances
    var editorInstances = {};
    var originalMyBBEditor = null;

    /**
     * Initialize Quick Edit Editor
     */
    function initQuickEditSCEditor() {
        // Check if SCEditor is available
        if (typeof $.fn.sceditor === 'undefined') {
            return;
        }

        // Check if quickedit_opt_editor is defined
        if (typeof quickedit_opt_editor === 'undefined') {
            return;
        }

        // Use event delegation for dynamically created buttons
        $(document).on('click.quickedit-sceditor', '.quick_edit_button', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var buttonId = $button.attr('id');

            if (!buttonId) {
                return;
            }

            var pid = buttonId.replace('quick_edit_post_', '');

            if (!pid) {
                return;
            }

            // Close any popup menus first
            $('.popup_menu').hide();

            handleQuickEditClick(pid, $button);

            return false;
        });
    }

    /**
     * Handle quick edit button click
     *
     * @param {string} pid Post ID
     * @param {jQuery} $button The clicked button
     */
    function handleQuickEditClick(pid, $button) {
        // Close any popup menus
        $('.popup_menu').hide();

        // Show loading spinner in the post content area
        var $postContent = $('#pid_' + pid);

        if ($postContent.length === 0) {
            return;
        }

        // Store original content for cancel - store as temp element like MyBB does
        if ($('#pid_' + pid + '_temp').length === 0) {
            $postContent.clone().attr('id', 'pid_' + pid + '_temp').appendTo($postContent.parent()).hide();
        }

        // Get spinner image path
        var spinnerImage = rootpath + '/images/spinner.gif';
        var savingPost = window.lang && window.lang.saving_post ? window.lang.saving_post : 'Loading...';

        $postContent.html('<img src="' + spinnerImage + '" alt="" /> ' + savingPost);

        // Get post key
        var myPostKey = window.my_post_key || '';

        // Make AJAX request to get the raw post content (not the parsed HTML)
        $.ajax({
            url: 'xmlhttp.php?action=edit_post&do=get_post&pid=' + pid + '&my_post_key=' + myPostKey,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // Create our custom edit form with SCEditor
                createQuickEditForm(pid, response, $postContent);
            },
            error: function(xhr, status, error) {
                // Restore original content on error
                var $tempContent = $('#pid_' + pid + '_temp');
                if ($tempContent.length) {
                    $postContent.html($tempContent.html());
                    $tempContent.remove();
                }
                alert('Error loading quick edit form. Please try again.');
            }
        });
    }

    /**
     * Create the quick edit form with SCEditor
     *
     * @param {string} pid Post ID
     * @param {string} rawMessage The raw post message
     * @param {jQuery} $postContent The post content container
     */
    function createQuickEditForm(pid, rawMessage, $postContent) {
        var myPostKey = window.my_post_key || '';
        
        // Build the edit form HTML
        var formHtml = '<form id="quickedit_form_' + pid + '" class="quickedit-form">';
        formHtml += '<input type="hidden" name="my_post_key" value="' + myPostKey + '" />';
        formHtml += '<input type="hidden" name="pid" value="' + pid + '" />';
        formHtml += '<textarea id="quickedit_' + pid + '" name="value" rows="12">' + escapeHtml(rawMessage) + '</textarea>';
        
        // Add edit reason if enabled - show directly
        if (window.allowEditReason == 1) {
            formHtml += '<div class="quick_edit_edit_reason" style="margin: 8px 0;">';
            formHtml += '<label for="editreason_' + pid + '">' + (window.lang && window.lang.edit_reason ? window.lang.edit_reason : 'Edit Reason') + ':</label><br />';
            formHtml += '<input type="text" name="editreason" id="editreason_' + pid + '" value="" style="width: 100%; max-width: 400px;" />';
            formHtml += '</div>';
        }
        
        formHtml += '<div class="quickedit-buttons" style="margin-top: 10px;">';
        formHtml += '<input type="submit" value="' + (window.lang && window.lang.save_changes ? window.lang.save_changes : 'Save Changes') + '" class="quick_edit_submit" style="padding: 3px 8px; cursor: pointer; font-family: Tahoma, Verdana, Arial, Sans-Serif; font-size: 13px; background: #eee url(' + rootpath + '/images/buttons_bg.png) repeat-x; border: 1px solid #bbb; color: #333; outline: 0;" />';
        formHtml += '<input type="button" value="' + (window.lang && window.lang.cancel_edit ? window.lang.cancel_edit : 'Cancel') + '" class="quick_edit_cancel" style="padding: 3px 8px; cursor: pointer; font-family: Tahoma, Verdana, Arial, Sans-Serif; font-size: 13px; background: #eee url(' + rootpath + '/images/buttons_bg.png) repeat-x; border: 1px solid #bbb; color: #333; outline: 0;" />';
        formHtml += '</div>';
        formHtml += '</form>';
        
        // Insert the form
        $postContent.html(formHtml);
        
        // Initialize SCEditor - like MyBB does
        initializeSCEditorOnQuickEdit(pid);
    }

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} text Text to escape
     * @return {string} Escaped text
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize SCEditor on the quick edit textarea
     * Uses the same pattern as MyBB's codebuttons
     *
     * @param {string} pid Post ID
     */
    function initializeSCEditorOnQuickEdit(pid) {
        var textareaId = 'quickedit_' + pid;
        var $textarea = $('#' + textareaId);

        if ($textarea.length === 0) {
            return;
        }

        // Check if editor is already initialized
        if (editorInstances[pid]) {
            return;
        }

        // Check if SCEditor is available
        if (typeof $.fn.sceditor === 'undefined') {
            return;
        }

        // Destroy any existing SCEditor instance on this element
        try {
            var existingInstance = $textarea.sceditor('instance');
            if (existingInstance) {
                existingInstance.destroy();
            }
        } catch (e) {
            // Ignore destruction errors
        }

        // Create the editor - using the same pattern as MyBB
        try {
            // Create a copy of the config for this specific editor
            var editorConfig = $.extend({}, quickedit_opt_editor);
            
            // Allow plugins to modify the editor config
            if (typeof window.QuickEditSCEditor !== 'undefined' && typeof window.QuickEditSCEditor.onEditorInit === 'function') {
                window.QuickEditSCEditor.onEditorInit(pid, editorConfig);
            }
            
            // Initialize SCEditor
            $textarea.sceditor(editorConfig);

            // Get the editor instance
            var editor = $textarea.sceditor('instance');

            if (!editor) {
                return;
            }

            // Store the instance
            editorInstances[pid] = editor;

            // Save original MyBBEditor if not already saved
            if (originalMyBBEditor === null && typeof window.MyBBEditor !== 'undefined') {
                originalMyBBEditor = window.MyBBEditor;
            }

            // Set MyBBEditor global for compatibility with plugins
            window.MyBBEditor = editor;

            // Manually initialize color picker if function exists and dependencies are loaded
            if (typeof initSpectrumColorPicker === 'function') {
                try {
                    initSpectrumColorPicker(editor);
                } catch(e) {
                    // Color picker init error
                }
            }

            // Add styling
            styleEditorContainer(pid, editorConfig.height);

            // Bind form events
            bindFormEvents(pid, editor);

            // Focus the editor
            editor.focus();

            // Trigger custom event
            $(document).trigger('quickedit_sceditor_initialized', [pid, editor]);

            // Allow plugins to act on editor ready
            if (typeof window.QuickEditSCEditor !== 'undefined' && typeof window.QuickEditSCEditor.onEditorReady === 'function') {
                window.QuickEditSCEditor.onEditorReady(pid, editor);
            }

            // Store reference similar to MyBBEditor for compatibility
            window.MyBBQuickEditEditor = editor;
        } catch (e) {
            // Error creating editor
        }
    }

    /**
     * Style the editor container
     *
     * @param {string} pid Post ID
     * @param {number} height Editor height
     */
    function styleEditorContainer(pid, height) {
        var $container = $('#pid_' + pid);
        var $editorContainer = $container.find('.sceditor-container');

        if ($editorContainer.length) {
            $editorContainer.css({
                'width': '100%',
                'margin-bottom': '10px'
            });
        }
    }

    /**
     * Bind form events
     *
     * @param {string} pid Post ID
     * @param {object} editor SCEditor instance
     */
    function bindFormEvents(pid, editor) {
        var formId = 'quickedit_form_' + pid;
        var $form = $('#' + formId);
        var textareaId = 'quickedit_' + pid;
        var $textarea = $('#' + textareaId);

        if ($form.length === 0) {
            return;
        }

        // Handle form submission
        $form.off('submit.quickedit-sceditor').on('submit.quickedit-sceditor', function(e) {
            e.preventDefault();
            
            // Update the textarea with editor content
            editor.updateOriginal();
            
            // Get the message value
            var message = $textarea.val();
            
            // Get edit reason if present
            var editReason = '';
            var $editReasonField = $form.find('input[name="editreason"]');
            if ($editReasonField.length) {
                editReason = $editReasonField.val();
            }
            
            // Show saving indicator
            var $postContent = $('#pid_' + pid);
            var spinnerImage = rootpath + '/images/spinner.gif';
            var savingPost = window.lang && window.lang.saving_post ? window.lang.saving_post : 'Saving post...';

            $postContent.html('<img src="' + spinnerImage + '" alt="" /> ' + savingPost);

            // Submit via AJAX
            $.ajax({
                url: 'xmlhttp.php?action=edit_post&do=update_post&pid=' + pid + '&my_post_key=' + window.my_post_key,
                type: 'POST',
                data: {
                    value: message,
                    editreason: editReason,
                    my_post_key: window.my_post_key
                },
                dataType: 'json',
                success: function(response) {
                    // Destroy the editor first
                    destroyEditor(pid);
                    
                    // Check for errors
                    if (response.errors) {
                        alert('Error saving post: ' + response.errors.join(', '));
                        createQuickEditForm(pid, message, $postContent);
                        return;
                    }
                    
                    // Check for moderation actions
                    if (response.moderation_post) {
                        alert(response.moderation_post);
                        if (response.url) {
                            window.location.href = response.url;
                        } else {
                            window.location.reload();
                        }
                        return;
                    }
                    
                    if (response.moderation_thread) {
                        alert(response.moderation_thread);
                        if (response.url) {
                            window.location.href = response.url;
                        }
                        return;
                    }
                    
                    // Success - update the post content
                    if (response.message) {
                        $postContent.html(response.message);
                    }
                    
                    // Update edited by message
                    if (response.editedmsg) {
                        $('#edited_by_' + pid).html(response.editedmsg);
                    }
                    
                    // Remove temp element
                    $('#pid_' + pid + '_temp').remove();
                    
                    // Re-highlight syntax if MyCode is available
                    if (typeof MyBB !== 'undefined' && MyBB.SyntaxHighlighter) {
                        MyBB.SyntaxHighlighter.highlight();
                    }
                },
                error: function(xhr, status, error) {
                    createQuickEditForm(pid, message, $postContent);
                    alert('Error saving post. Please try again.');
                }
            });

            return false;
        });

        // Handle cancel button
        var $cancelButton = $form.find('.quick_edit_cancel');
        $cancelButton.off('click.quickedit-sceditor').on('click.quickedit-sceditor', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Destroy the editor
            destroyEditor(pid);

            // Restore original post content from temp element
            var $tempContent = $('#pid_' + pid + '_temp');
            if ($tempContent.length) {
                var $postContent = $('#pid_' + pid);
                $postContent.html($tempContent.html());
                $tempContent.remove();
            } else {
                window.location.reload();
            }

            return false;
        });
    }

    /**
     * Destroy SCEditor instance
     *
     * @param {string} pid Post ID
     */
    function destroyEditor(pid) {
        if (editorInstances[pid]) {
            try {
                editorInstances[pid].destroy();
            } catch (e) {
                // Ignore destruction errors
            }
            delete editorInstances[pid];

            // Restore original MyBBEditor if this was the last quick edit
            var hasMoreEditors = false;
            for (var key in editorInstances) {
                if (editorInstances.hasOwnProperty(key)) {
                    hasMoreEditors = true;
                    break;
                }
            }

            if (!hasMoreEditors && originalMyBBEditor !== null) {
                window.MyBBEditor = originalMyBBEditor;
                originalMyBBEditor = null;
            } else if (!hasMoreEditors) {
                // No original to restore, just clear it
                window.MyBBEditor = null;
            }
        }
    }

    /**
     * Destroy all editor instances
     */
    function destroyAllEditors() {
        for (var pid in editorInstances) {
            if (editorInstances.hasOwnProperty(pid)) {
                destroyEditor(pid);
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initQuickEditSCEditor();
    });

    // Expose public API for other plugins to use
    window.QuickEditSCEditor = {
        // Initialize the plugin
        init: initQuickEditSCEditor,
        // Destroy editor for a specific post
        destroy: destroyEditor,
        // Destroy all editors
        destroyAll: destroyAllEditors,
        // Get editor instance for a post
        getEditor: function(pid) {
            return editorInstances[pid] || null;
        },
        // Reinitialize editor for a post
        reinitialize: function(pid) {
            destroyEditor(pid);
            initializeSCEditorOnQuickEdit(pid);
        },
        // Hook called when editor is being initialized (other plugins can modify config)
        onEditorInit: null,
        // Hook called when editor is fully initialized
        onEditorReady: null,
        // Get the default toolbar config
        getDefaultToolbar: function() {
            return quickedit_opt_editor ? quickedit_opt_editor.toolbar : '';
        },
        // Get the default editor config
        getDefaultConfig: function() {
            return quickedit_opt_editor ? $.extend({}, quickedit_opt_editor) : null;
        }
    };

})(jQuery);
