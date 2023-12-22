<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\CoreBundle\Component\Utils;

enum ActionIcon: string
{
    // Add
    case ADD = 'plus-box';
    // Edit
    case EDIT = 'pencil';
    // Delete
    case DELETE = 'delete';
    // Configure
    case CONFIGURE = 'hammer-wrench';
    // Download
    case DOWNLOAD = 'download';
    // Download multiple items
    case DOWNLOAD_MULTIPLE = 'download-box';
    // Upload
    case UPLOAD = 'upload';
    // Go back one page
    case BACK = 'arrow-left-bold-box';
    // Assign groups of users to some resource
    case SUBSCRIBE_GROUP_USERS_TO_RESOURCE = 'account-multiple-plus';
    // Handle to move an element by drag & drop
    case MOVE_DRAG_DROP = 'cursor-move';
    // Move backward one page (learning paths)
    case MOVE_LP_BACKWARD = 'chevron-left';
    // Move forward one page (learning paths)
    case MOVE_LP_FORWARD = 'chevron-right';
    // Move something up
    case UP = 'arrow-up-bold';
    // Move something down or show some unfolded interface component
    case DOWN = 'arrow-down-bold';
    // Move something (from one folder to another) or unfold some interface component
    case MOVE = 'arrow-right-bold';
    // Preview some content
    case PREVIEW_CONTENT = 'magnify-plus-outline';
    // Import some kind of archive/packaged
    case IMPORT_ARCHIVE = 'archive-arrow-up';
    // Create a category
    case CREATE_CATEGORY = 'folder-multiple-plus';
    // Create a folder
    case CREATE_FOLDER = 'folder-plus';
    // Alert the user of something important/unexpected/undesired
    case ALERT = 'alert';
    // Inform of something completed
    case INFORM = 'checkbox-marked';
    // Crossed pencil to show the inability to edit for the current user
    case EDIT_OFF = 'pencil-off';
    // Visible state
    case VISIBLE = 'eye';
    // Invisible state
    case INVISIBLE = 'eye-off';
    // Hide from course homepage (unpublish)
    case UNPUBLISH_COURSE_TOOL = 'checkbox-multiple-blank';
    // Show on course homepage
    case PUBLISH_COURSE_TOOL = 'checkbox-multiple-blank-outline';
    // Disable multiple attempts (or show multiple attempts are currently enabled)
    case DISABLE_MULTIPLE_ATTEMPTS = 'sync';
    // Enable multiple attempts (or show multiple attempts are currently disabled)
    case ENABLE_MULTIPLE_ATTEMPTS = 'sync-circle';
    // Display mode 1
    case DISPLAY_MODE_1 = 'fullscreen';
    // Display mode 2
    case LP_DISPLAY_MODE_2 = 'fullscreen-exit';
    // Display mode 3
    case LP_DISPLAY_MODE_3 = 'overscan';
    // Display mode 4
    case LP_DISPLAY_MODE_4 = 'play-box-outline';
    // Equivalent to fullscreen-exit?
    case EXIT_LP_FULLSCREEN = 'fit-to-screen';
    // Enable debug
    case ENABLE_DEBUG = 'bug-check';
    // Disable debug
    case DISABLE_DEBUG = 'bug-outline';
    // Export in some type of archive/package
    case EXPORT_ARCHIVE = 'archive-arrow-down';
    // Copy content
    case COPY_CONTENT = 'text-box-plus';
    // Enable/Disable auto-launch of some content
    case AUTO_LAUNCH = 'rocket-launch';
    // Export to PDF
    case EXPORT_PDF = 'file-pdf-box';
    // CSV export
    case EXPORT_CSV = 'file-delimited-outline';
    // Export to Excel
    case EXPORT_SPREADSHEET = 'microsoft-excel';
    // Export to Document
    case EXPORT_DOC = 'microsoft-word';
    // Save the current form
    case FORM_SAVE = 'content-save';
    // Send a message
    case SEND_MESSAGE = 'send';
    // Add an attachment
    case ADD_ATTACHMENT = 'paperclip-plus';
    // ?
    //case CLOUD_UPLOAD = 'cloud-upload';
    // Three vertical dots to indicate the possibility to extend a menu/set of options
    case VERTICAL_DOTS = 'dots-vertical';
    // Information icon - Get more info
    case INFORMATION = 'information';
    // Login as
    case LOGIN_AS = 'account-key';
    // Take backup
    case TAKE_BACKUP = 'cloud-download';
    // Restore backup
    case RESTORE_BACKUP = 'cloud-upload';
    // Print
    case PRINT = 'printer';
    // See details/View details
    case VIEW_DETAILS = 'fast-forward-outline';
    // Clean/Reset
    case RESET = 'broom';
    // Add audio
    case ADD_AUDIO = 'music-note-plus';
    // Collapse/Contract
    case COLLAPSE = 'arrow-collapse-all';
    // Expand
    case EXPAND = 'arrow-expand-all';
    // Give a grade to some work
    case GRADE = 'checkbox-marked-circle-plus-outline';
    // Lock
    case LOCK = 'lock';
    // Unlock
    case UNLOCK = 'lock-open-variant';
    // Refresh/reload
    case REFRESH = 'file-document-refresh';
    // Add user
    case ADD_USER = 'account-plus';
}
