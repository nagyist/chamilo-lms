<?php
/* For licensing terms, see /license.txt */

/**
 * These files are a complete rework of the forum. The database structure is
 * based on phpBB but all the code is rewritten. A lot of new functionalities
 * are added:
 * - forum categories and forums can be sorted up or down, locked or made invisible
 * - consistent and integrated forum administration
 * - forum options:     are students allowed to edit their post?
 *                         moderation of posts (approval)
 *                         reply only forums (students cannot create new threads)
 *                         multiple forums per group
 * - sticky messages
 * - new view option: nested view
 * - quoting a message
 *
 * @package chamilo.forum
 *
 * @todo several functions have to be moved to the itemmanager library
 * @todo displaying icons => display library
 * @todo complete the missing phpdoc the correct order should be
 */

use ChamiloSession as Session;
use Doctrine\Common\Collections\Criteria;
use Chamilo\CourseBundle\Entity\CForumPost;

define('FORUM_NEW_POST', 0);

get_notifications_of_user();

$htmlHeadXtra[] = api_get_jquery_libraries_js(array('jquery-ui', 'jquery-upload'));
$htmlHeadXtra[] = '<script>

function check_unzip() {
    if (document.upload.unzip.checked){
        document.upload.if_exists[0].disabled=true;
        document.upload.if_exists[1].checked=true;
        document.upload.if_exists[2].disabled=true;
    } else {
        document.upload.if_exists[0].checked=true;
        document.upload.if_exists[0].disabled=false;
        document.upload.if_exists[2].disabled=false;
    }
}
function setFocus() {
    $("#title_file").focus();
}
</script>';
// The next javascript script is to manage ajax upload file
$htmlHeadXtra[] = api_get_jquery_libraries_js(array('jquery-ui', 'jquery-upload'));

// Recover Thread ID, will be used to generate delete attachment URL to do ajax
$threadId = isset($_REQUEST['thread']) ? intval($_REQUEST['thread']) : 0;
$forumId = isset($_REQUEST['forum']) ? intval($_REQUEST['forum']) : 0;

// The next javascript script is to delete file by ajax
$htmlHeadXtra[] = '<script>
$(function () {
    $(document).on("click", ".deleteLink", function(e) {
        e.preventDefault();
        e.stopPropagation();
        var l = $(this);
        var id = l.closest("tr").attr("id");
        var filename = l.closest("tr").find(".attachFilename").html();
        if (confirm("' . get_lang('AreYouSureToDeleteJS') . '", filename)) {
            $.ajax({
                type: "POST",
                url: "'.api_get_path(WEB_AJAX_PATH) . 'forum.ajax.php?'.api_get_cidreq().'&a=delete_file&attachId=" + id +"&thread='.$threadId .'&forum='.$forumId .'",
                dataType: "json",
                success: function(data) {
                    if (data.error == false) {
                        l.closest("tr").remove();
                        if ($(".files td").length < 1) {
                            $(".files").closest(".control-group").hide();
                        }
                    }
                }
            })
        }
    });
});
</script>';

/**
 * This function handles all the forum and forum categories actions. This is a wrapper for the
 * forum and forum categories. All this code code could go into the section where this function is
 * called but this make the code there cleaner.
 * @param int $lp_id Learning path Id
 *
 * @return void
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @author Juan Carlos Raña Trabado (return to lp_id)
 * @version may 2011, Chamilo 1.8.8
 */
function handle_forum_and_forumcategories($lp_id = null)
{
    $action_forum_cat = isset($_GET['action']) ? $_GET['action'] : '';
    $get_content = isset($_GET['content']) ? $_GET['content'] : '';
    $post_submit_cat = isset($_POST['SubmitForumCategory']) ? true : false;
    $post_submit_forum = isset($_POST['SubmitForum']) ? true : false;
    $get_id = isset($_GET['id']) ? intval($_GET['id']) : '';
    $forum_categories_list = get_forum_categories();

    //Verify if forum category exists
    if (empty($forum_categories_list)) {
        $get_content = 'forumcategory';
    }

    // Adding a forum category
    if (($action_forum_cat == 'add' && $get_content == 'forumcategory') || $post_submit_cat) {
        show_add_forumcategory_form(array(), $lp_id); //$lp_id when is called from learning path
    }

    // Adding a forum
    if ((($action_forum_cat == 'add' || $action_forum_cat == 'edit') && $get_content == 'forum') || $post_submit_forum) {
        if ($action_forum_cat == 'edit' && $get_id || $post_submit_forum) {
            $inputvalues = get_forums($get_id);
        } else {
            $inputvalues = array();
        }
        show_add_forum_form($inputvalues, $lp_id);
    }

    // Edit a forum category
    if (($action_forum_cat == 'edit' && $get_content == 'forumcategory') || (isset($_POST['SubmitEditForumCategory'])) ? true : false) {
        $forum_category = get_forum_categories($get_id);
        show_edit_forumcategory_form($forum_category);
    }

    // Delete a forum category
    if ($action_forum_cat == 'delete') {
        $id_forum = intval($get_id);
        $list_threads = get_threads($id_forum);

        for ($i = 0; $i < count($list_threads); $i++) {
            deleteForumCategoryThread('thread', $list_threads[$i]['thread_id']);
            $link_info = GradebookUtils::isResourceInCourseGradebook(
                api_get_course_id(),
                5,
                intval($list_threads[$i]['thread_id']),
                api_get_session_id()
            );
            if ($link_info !== false) {
                GradebookUtils::remove_resource_from_course_gradebook($link_info['id']);
            }
        }
        $return_message = deleteForumCategoryThread($get_content, $get_id);
        Display::display_confirmation_message($return_message, false);
    }

    // Change visibility of a forum or a forum category.
    if ($action_forum_cat == 'invisible' || $action_forum_cat == 'visible') {
        $return_message = change_visibility($get_content, $get_id, $action_forum_cat);
        Display::display_confirmation_message($return_message, false);
    }
    // Change lock status of a forum or a forum category.
    if ($action_forum_cat == 'lock' || $action_forum_cat == 'unlock') {
        $return_message = change_lock_status($get_content, $get_id, $action_forum_cat);
        Display::display_confirmation_message($return_message, false);
    }
    // Move a forum or a forum category.
    if ($action_forum_cat == 'move' && isset($_GET['direction'])) {
        $return_message = move_up_down($get_content, $_GET['direction'], $get_id);
        Display::display_confirmation_message($return_message, false);
    }
}

/**
 * This function displays the form that is used to add a forum category.
 *
 * @param  array $inputvalues (deprecated, set to null when calling)
 * @param  int $lp_id Learning path ID
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @author Juan Carlos Raña Trabado (return to lp_id)
 * @version may 2011, Chamilo 1.8.8
 */
function show_add_forumcategory_form($inputvalues = array(), $lp_id)
{
    // Initialize the object.
    $form = new FormValidator('forumcategory', 'post', 'index.php?' . api_get_cidreq());
    // hidden field if from learning path

    $form->addElement('hidden', 'lp_id', $lp_id);
    // Setting the form elements.
    $form->addElement('header', get_lang('AddForumCategory'));
    $form->addElement('text', 'forum_category_title', get_lang('Title'), array('autofocus'));
    $form->addElement(
        'html_editor',
        'forum_category_comment',
        get_lang('Description'),
        null,
        array('ToolbarSet' => 'Forum', 'Width' => '98%', 'Height' => '200')
    );
    $form->addButtonCreate(get_lang('CreateCategory'), 'SubmitForumCategory');

    // Setting the rules.
    $form->addRule('forum_category_title', get_lang('ThisFieldIsRequired'), 'required');

    // The validation or display
    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $values = $form->exportValues();
            store_forumcategory($values);
        }
        Security::clear_token();
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(array('sec_token' => $token));
        $form->display();
    }
}

/**
 * This function displays the form that is used to add a forum category.
 *
 * @param array $inputvalues
 * @param int $lp_id
 * @return void HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @author Juan Carlos Raña Trabado (return to lp_id)
 *
 * @version may 2011, Chamilo 1.8.8
 */
function show_add_forum_form($inputvalues = array(), $lp_id)
{
    $_course = api_get_course_info();
    $form = new FormValidator('forumcategory', 'post', 'index.php?' . api_get_cidreq());

    // The header for the form
    if (!empty($inputvalues)) {
        $form_title = get_lang('EditForum');
    } else {
        $form_title = get_lang('AddForum');
    }
    $session_header = api_get_session_name();
    $form->addElement('header', $form_title.$session_header);

    // We have a hidden field if we are editing.
    if (!empty($inputvalues) && is_array($inputvalues)) {
        $my_forum_id = isset($inputvalues['forum_id']) ? $inputvalues['forum_id'] : null;
        $form->addElement('hidden', 'forum_id', $my_forum_id);
    }
    $lp_id = intval($lp_id);

    // hidden field if from learning path
    $form->addElement('hidden', 'lp_id', $lp_id);

    // The title of the forum
    $form->addElement('text', 'forum_title', get_lang('Title'), array('autofocus'));

    // The comment of the forum.
    $form->addElement(
        'html_editor',
        'forum_comment',
        get_lang('Description'),
        null,
        array('ToolbarSet' => 'Forum', 'Width' => '98%', 'Height' => '200')
    );

    // Dropdown list: Forum categories
    $forum_categories = get_forum_categories();
    foreach ($forum_categories as $key => $value) {
        $forum_categories_titles[$value['cat_id']] = $value['cat_title'];
    }
    $form->addElement('select', 'forum_category', get_lang('InForumCategory'), $forum_categories_titles);
    $form->applyFilter('forum_category', 'html_filter');

    if ($_course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD) {
        // This is for horizontal
        $group = array();
        $group[] = $form->createElement('radio', 'allow_anonymous', null, get_lang('Yes'), 1);
        $group[] = $form->createElement('radio', 'allow_anonymous', null, get_lang('No'), 0);
        $form->addGroup($group, 'allow_anonymous_group', get_lang('AllowAnonymousPosts'));
    }

    $form->addButtonAdvancedSettings('advanced_params');
    $form->addElement('html', '<div id="advanced_params_options" style="display:none">');

    $form->addDateTimePicker(
        'start_time',
        array(get_lang('ForumStartDate'), get_lang('ForumStartDateComment')),
        array('id' => 'start_time')
    );

    $form->addDateTimePicker(
        'end_time',
        array(get_lang('ForumEndDate'), get_lang('ForumEndDateComment')),
        array('id' => 'end_time')
    );

    $form->addRule(
        array('start_time', 'end_time'),
        get_lang('StartDateMustBeBeforeTheEndDate'),
        'compare_datetime_text',
        '< allow_empty'
    );

    $group = array();
    $group[] = $form->createElement('radio', 'moderated', null, get_lang('Yes'), 1);
    $group[] = $form->createElement('radio', 'moderated', null, get_lang('No'), 0);
    $form->addGroup($group, 'moderated', get_lang('ModeratedForum'));

    $group = array();
    $group[] = $form->createElement('radio', 'students_can_edit', null, get_lang('Yes'), 1);
    $group[] = $form->createElement('radio', 'students_can_edit', null, get_lang('No'), 0);
    $form->addGroup($group, 'students_can_edit_group', get_lang('StudentsCanEdit'));

    $group = array();
    $group[] = $form->createElement('radio', 'approval_direct', null, get_lang('Approval'), 1);
    $group[] = $form->createElement('radio', 'approval_direct', null, get_lang('Direct'), 0);

    $group = array();
    $group[] = $form->createElement('radio', 'allow_attachments', null, get_lang('Yes'), 1);
    $group[] = $form->createElement('radio', 'allow_attachments', null, get_lang('No'), 0);

    $group = array();
    $group[] = $form->createElement('radio', 'allow_new_threads', null, get_lang('Yes'), 1);
    $group[] = $form->createElement('radio', 'allow_new_threads', null, get_lang('No'), 0);
    $form->addGroup($group, 'allow_new_threads_group', get_lang('AllowNewThreads'));

    $group = array();
    $group[] = $form->createElement('radio', 'default_view_type', null, get_lang('Flat'), 'flat');
    $group[] = $form->createElement('radio', 'default_view_type', null, get_lang('Threaded'), 'threaded');
    $group[] = $form->createElement('radio', 'default_view_type', null, get_lang('Nested'), 'nested');
    $form->addGroup($group, 'default_view_type_group', get_lang('DefaultViewType'));

    // Drop down list: Groups
    $groups = GroupManager::get_group_list();
    $groups_titles[0] = get_lang('NotAGroupForum');
    foreach ($groups as $key => $value) {
        $groups_titles[$value['id']] = $value['name'];
    }
    $form->addElement('select', 'group_forum', get_lang('ForGroup'), $groups_titles);

    // Public or private group forum
    $group = array();
    $group[] = $form->createElement('radio', 'public_private_group_forum', null, get_lang('Public'), 'public');
    $group[] = $form->createElement('radio', 'public_private_group_forum', null, get_lang('Private'), 'private');
    $form->addGroup($group, 'public_private_group_forum_group', get_lang('PublicPrivateGroupForum'));

    // Forum image
    $form->addProgress();
    if (!empty($inputvalues['forum_image'])) {
        $baseImagePath = api_get_course_path() . '/upload/forum/images/' . $inputvalues['forum_image'];
        $image_path = api_get_path(WEB_COURSE_PATH) . $baseImagePath;
        $sysImagePath = api_get_path(SYS_COURSE_PATH) . $baseImagePath;

        if (file_exists($sysImagePath)) {
            $show_preview_image = Display::img($image_path, null, ['class' => 'img-responsive']);
            $form->addElement('label', get_lang('PreviewImage'), $show_preview_image);
            $form->addElement('checkbox', 'remove_picture', null, get_lang('DelImage'));
        }
    }
    $forum_image = isset($inputvalues['forum_image']) ? $inputvalues['forum_image'] : '';
    $form->addElement('file', 'picture', ($forum_image != '' ? get_lang('UpdateImage') : get_lang('AddImage')));
    $form->addRule('picture', get_lang('OnlyImagesAllowed'), 'filetype', array('jpg', 'jpeg', 'png', 'gif'));
    $form->addElement('html', '</div>');

    // The OK button
    if (isset($_GET['id']) && $_GET['action'] == 'edit') {
        $form->addButtonUpdate(get_lang('ModifyForum'), 'SubmitForum');
    } else {
        $form->addButtonCreate(get_lang('CreateForum'), 'SubmitForum');
    }

    // setting the rules
    $form->addRule('forum_title', get_lang('ThisFieldIsRequired'), 'required');
    $form->addRule('forum_category', get_lang('ThisFieldIsRequired'), 'required');

    $defaultSettingAllowNewThreads = api_get_default_tool_setting('forum', 'allow_new_threads', 0);

    // Settings the defaults
    if (empty($inputvalues) || !is_array($inputvalues)) {
        $defaults['moderated']['moderated'] = 0;
        $defaults['allow_anonymous_group']['allow_anonymous'] = 0;
        $defaults['students_can_edit_group']['students_can_edit'] = 0;
        $defaults['approval_direct_group']['approval_direct'] = 0;
        $defaults['allow_attachments_group']['allow_attachments'] = 1;
        $defaults['allow_new_threads_group']['allow_new_threads'] = $defaultSettingAllowNewThreads;
        $defaults['default_view_type_group']['default_view_type'] = api_get_setting('default_forum_view');
        $defaults['public_private_group_forum_group']['public_private_group_forum'] = 'public';
        if (isset($_GET['forumcategory'])) {
            $defaults['forum_category'] = Security::remove_XSS($_GET['forumcategory']);
        }
    } else {   // the default values when editing = the data in the table
        $defaults['forum_id'] = isset($inputvalues['forum_id']) ? $inputvalues['forum_id'] : null;
        $defaults['forum_title'] = prepare4display(isset($inputvalues['forum_title']) ? $inputvalues['forum_title'] : null);
        $defaults['forum_comment'] = prepare4display(isset($inputvalues['forum_comment']) ? $inputvalues['forum_comment'] : null);
        $defaults['start_time'] = isset($inputvalues['start_time']) ? api_get_local_time($inputvalues['start_time']) : null;
        $defaults['end_time'] = isset($inputvalues['end_time']) ? api_get_local_time($inputvalues['end_time']) : null;
        $defaults['moderated']['moderated'] = isset($inputvalues['moderated']) ? $inputvalues['moderated'] : 0;
        $defaults['forum_category'] = isset($inputvalues['forum_category']) ? $inputvalues['forum_category'] : null;
        $defaults['allow_anonymous_group']['allow_anonymous'] = isset($inputvalues['allow_anonymous']) ? $inputvalues['allow_anonymous'] : null;
        $defaults['students_can_edit_group']['students_can_edit'] = isset($inputvalues['allow_edit']) ? $inputvalues['allow_edit'] : null;
        $defaults['approval_direct_group']['approval_direct'] = isset($inputvalues['approval_direct_post']) ? $inputvalues['approval_direct_post'] : null;
        $defaults['allow_attachments_group']['allow_attachments'] = isset($inputvalues['allow_attachments']) ? $inputvalues['allow_attachments'] : null;
        $defaults['allow_new_threads_group']['allow_new_threads'] = isset($inputvalues['allow_new_threads']) ? $inputvalues['allow_new_threads'] : $defaultSettingAllowNewThreads;
        $defaults['default_view_type_group']['default_view_type'] = isset($inputvalues['default_view']) ? $inputvalues['default_view'] : null;
        $defaults['public_private_group_forum_group']['public_private_group_forum'] = isset($inputvalues['forum_group_public_private']) ? $inputvalues['forum_group_public_private'] : null;
        $defaults['group_forum'] = isset($inputvalues['forum_of_group']) ? $inputvalues['forum_of_group'] : null;
    }
    $form->setDefaults($defaults);
    // Validation or display
    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $values = $form->exportValues();
            $return_message = store_forum($values);
            Display :: display_confirmation_message($return_message);
        }
        Security::clear_token();
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(array('sec_token' => $token));
        $form->display();
    }
}

/**
 * This function deletes the forum image if exists
 *
 * @param int forum id
 * @return boolean true if success
 * @author Julio Montoya <gugli100@gmail.com>
 * @version february 2006, dokeos 1.8
 */
function delete_forum_image($forum_id)
{
    $table_forums = Database::get_course_table(TABLE_FORUM);
    $course_id = api_get_course_int_id();
    $forum_id = intval($forum_id);

    $sql = "SELECT forum_image FROM $table_forums
            WHERE forum_id = '".$forum_id."' AND c_id = $course_id";
    $result = Database::query($sql);
    $row = Database::fetch_array($result);
    if ($row['forum_image'] != '') {
        $del_file = api_get_path(SYS_COURSE_PATH).api_get_course_path().'/upload/forum/images/'.$row['forum_image'];

        return @unlink($del_file);
    } else {
        return false;
    }
}

/**
 * This function displays the form that is used to edit a forum category.
 * This is more or less a copy from the show_add_forumcategory_form function with the only difference that is uses
 * some default values. I tried to have both in one function but this gave problems with the handle_forum_and_forumcategories function
 * (storing was done twice)
 *
 * @param array
 * @return void HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function show_edit_forumcategory_form($inputvalues = array())
{
    $categoryId = $inputvalues['cat_id'];
    $form = new FormValidator('forumcategory', 'post', 'index.php?'.api_get_cidreq().'&id='.$categoryId);

    // Setting the form elements.
    $form->addElement('header', '', get_lang('EditForumCategory'));
    $form->addElement('hidden', 'forum_category_id');
    $form->addElement('text', 'forum_category_title', get_lang('Title'));

    $form->addElement(
        'html_editor',
        'forum_category_comment',
        get_lang('Comment'),
        null,
        array('ToolbarSet' => 'Forum', 'Width' => '98%', 'Height' => '200')
    );

    $form->addButtonUpdate(get_lang('ModifyCategory'), 'SubmitEditForumCategory');

    // Setting the default values.
    $defaultvalues['forum_category_id'] = $inputvalues['cat_id'];
    $defaultvalues['forum_category_title'] = $inputvalues['cat_title'];
    $defaultvalues['forum_category_comment'] = $inputvalues['cat_comment'];
    $form->setDefaults($defaultvalues);

    // Setting the rules.
    $form->addRule('forum_category_title', get_lang('ThisFieldIsRequired'), 'required');

    // Validation or display
    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $values = $form->exportValues();
            store_forumcategory($values);
        }
        Security::clear_token();
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(array('sec_token' => $token));
        $form->display();
    }
}

/**
 * This function stores the forum category in the database.
 * The new category is added to the end.
 *
 * @param array $values
 * @param array $courseInfo
 * @param bool $showMessage
 * @return void HMTL language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_forumcategory($values, $courseInfo = array(), $showMessage = true)
{
    $courseInfo = empty($courseInfo) ? api_get_course_info() : $courseInfo;
    $course_id = $courseInfo['real_id'];

    $table_categories = Database::get_course_table(TABLE_FORUM_CATEGORY);

    // Find the max cat_order. The new forum category is added at the end => max cat_order + &
    $sql = "SELECT MAX(cat_order) as sort_max
            FROM $table_categories
            WHERE c_id = $course_id";
    $result = Database::query($sql);
    $row = Database::fetch_array($result);
    $new_max = $row['sort_max'] + 1;
    $session_id = api_get_session_id();
    $clean_cat_title = $values['forum_category_title'];
    $last_id = null;

    if (isset($values['forum_category_id'])) {
        // Storing after edition.
        $params = [
            'cat_title' => $clean_cat_title,
            'cat_comment' => isset($values['forum_category_comment']) ? $values['forum_category_comment'] : '',
        ];
        Database::update(
            $table_categories,
            $params,
            [
                'c_id = ? AND cat_id = ?' => [
                    $course_id,
                    $values['forum_category_id'],
                ],
            ]
        );

        api_item_property_update(
            $courseInfo,
            TOOL_FORUM_CATEGORY,
            $values['forum_category_id'],
            'ForumCategoryUpdated',
            api_get_user_id()
        );
        $return_message = get_lang('ForumCategoryEdited');
    } else {

        $params = [
            'c_id' => $course_id,
            'cat_title' => $clean_cat_title,
            'cat_comment' => isset($values['forum_category_comment']) ? $values['forum_category_comment'] : '',
            'cat_order' => $new_max,
            'session_id' => $session_id,
            'locked' => 0,
            'cat_id' => 0
        ];
        $last_id = Database::insert($table_categories, $params);

        if ($last_id > 0) {

            $sql = "UPDATE $table_categories SET cat_id = $last_id WHERE iid = $last_id";
            Database::query($sql);

            api_item_property_update(
                $courseInfo,
                TOOL_FORUM_CATEGORY,
                $last_id,
                'ForumCategoryAdded',
                api_get_user_id()
            );
            api_set_default_visibility(
                $last_id,
                TOOL_FORUM_CATEGORY,
                0,
                $courseInfo
            );
        }
        $return_message = get_lang('ForumCategoryAdded');
    }

    if ($showMessage) {
        Display::display_confirmation_message($return_message, 'success');
    }

    return $last_id;
}

/**
 * This function stores the forum in the database. The new forum is added to the end.
 *
 * @param array $values
 * @param array $courseInfo
 * @param bool  $returnId
 * @return string language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_forum($values, $courseInfo = array(), $returnId = false)
{
    $now = api_get_utc_datetime();
    $courseInfo = empty($courseInfo) ? api_get_course_info() : $courseInfo;
    $course_id = $courseInfo['real_id'];
    $session_id = api_get_session_id();

    if (isset($values['group_id']) && !empty($values['group_id'])) {
        $group_id = $values['group_id'];
    } else {
        $group_id = api_get_group_id();
    }

    $table_forums = Database::get_course_table(TABLE_FORUM);

    // Find the max forum_order for the given category. The new forum is added at the end => max cat_order + &
    if (is_null($values['forum_category'])) {
        $new_max = null;
    } else {
        $sql = "SELECT MAX(forum_order) as sort_max
                FROM ".$table_forums."
                WHERE
                    c_id = $course_id AND
                    forum_category='".Database::escape_string($values['forum_category'])."'";
        $result = Database::query($sql);
        $row = Database::fetch_array($result);
        $new_max = $row['sort_max'] + 1;
    }

    // Forum images
    $image_moved = false;
    if (!empty($_FILES['picture']['name'])) {
        $upload_ok = process_uploaded_file($_FILES['picture']);
        $has_attachment = true;
    } else {
        $image_moved = true;
    }

    // Remove existing picture if it was requested.
    if (!empty($_POST['remove_picture'])) {
        delete_forum_image($values['forum_id']);
    }

    $new_file_name = '';
    if (isset($upload_ok)) {
        if ($has_attachment) {
            $course_dir = $courseInfo['path'].'/upload/forum/images';
            $sys_course_path = api_get_path(SYS_COURSE_PATH);
            $updir = $sys_course_path.$course_dir;
            // Try to add an extension to the file if it hasn't one.
            $new_file_name = add_ext_on_mime(
                Database::escape_string($_FILES['picture']['name']),
                $_FILES['picture']['type']
            );
            if (!filter_extension($new_file_name)) {
                //Display :: display_error_message(get_lang('UplUnableToSaveFileFilteredExtension'));
                $image_moved = false;
            } else {
                $file_extension = explode('.', $_FILES['picture']['name']);
                $file_extension = strtolower($file_extension[sizeof($file_extension) - 1]);
                $new_file_name = uniqid('').'.'.$file_extension;
                $new_path = $updir.'/'.$new_file_name;
                $result = @move_uploaded_file($_FILES['picture']['tmp_name'], $new_path);
                // Storing the attachments if any
                if ($result) {
                    $image_moved = true;
                }
            }
        }
    }

    if (isset($values['forum_id'])) {
        $sql_image = isset($sql_image) ? $sql_image : '';
        $new_file_name = isset($new_file_name) ? $new_file_name : '';
        if ($image_moved) {
            if (empty($_FILES['picture']['name'])) {
                $sql_image = "";
            } else {
                $sql_image = $new_file_name;
                delete_forum_image($values['forum_id']);
            }
        }
        // Storing after edition.
        $params = [
            'forum_title'=> $values['forum_title'],
            'forum_image'=> $sql_image,
            'forum_comment'=> isset($values['forum_comment']) ? $values['forum_comment'] : null,
            'forum_category'=> isset($values['forum_category']) ? $values['forum_category'] : null,
            'allow_anonymous'=> isset($values['allow_anonymous_group']['allow_anonymous']) ? $values['allow_anonymous_group']['allow_anonymous'] : null,
            'allow_edit'=> isset($values['students_can_edit_group']['students_can_edit']) ? $values['students_can_edit_group']['students_can_edit'] : null,
            'approval_direct_post'=> isset($values['approval_direct_group']['approval_direct']) ? $values['approval_direct_group']['approval_direct'] : null,
            'allow_attachments'=> isset($values['allow_attachments_group']['allow_attachments']) ? $values['allow_attachments_group']['allow_attachments'] : null,
            'allow_new_threads'=> isset($values['allow_new_threads_group']['allow_new_threads']) ? $values['allow_new_threads_group']['allow_new_threads'] : null,
            'default_view'=> isset($values['default_view_type_group']['default_view_type']) ? $values['default_view_type_group']['default_view_type'] : null,
            'forum_of_group'=> isset($values['group_forum']) ? $values['group_forum'] : null,
            'forum_group_public_private'=> isset($values['public_private_group_forum_group']['public_private_group_forum']) ? $values['public_private_group_forum_group']['public_private_group_forum'] : null,
            'moderated'=> $values['moderated']['moderated'],
            'start_time' => !empty($values['start_time']) ? api_get_utc_datetime($values['start_time']) : null,
            'end_time' => !empty($values['end_time']) ? api_get_utc_datetime($values['end_time']) : null,
            'forum_order'=> isset($new_max) ? $new_max : null,
            'session_id'=> $session_id,
            'lp_id' => isset($values['lp_id']) ? intval($values['lp_id']) : 0
        ];

        Database::update(
            $table_forums,
            $params,
            ['c_id = ? AND forum_id = ?' => [$course_id, $values['forum_id']]]
        );

        api_item_property_update(
            $courseInfo,
            TOOL_FORUM,
            Database::escape_string($values['forum_id']),
            'ForumUpdated',
            api_get_user_id(),
            $group_id
        );

        $return_message = get_lang('ForumEdited');
    } else {
        if ($image_moved) {
            $new_file_name = isset($new_file_name) ? $new_file_name : '';
        }

        $params = [
            'c_id' => $course_id,
            'forum_title'=> $values['forum_title'],
            'forum_image'=> $new_file_name,
            'forum_comment'=> isset($values['forum_comment']) ? $values['forum_comment'] : null,
            'forum_category'=> isset($values['forum_category']) ? $values['forum_category'] : null,
            'allow_anonymous'=> isset($values['allow_anonymous_group']['allow_anonymous']) ? $values['allow_anonymous_group']['allow_anonymous'] : null,
            'allow_edit'=> isset($values['students_can_edit_group']['students_can_edit']) ? $values['students_can_edit_group']['students_can_edit'] : null,
            'approval_direct_post'=> isset($values['approval_direct_group']['approval_direct']) ? $values['approval_direct_group']['approval_direct'] : null,
            'allow_attachments'=> isset($values['allow_attachments_group']['allow_attachments']) ? $values['allow_attachments_group']['allow_attachments'] : null,
            'allow_new_threads'=> isset($values['allow_new_threads_group']['allow_new_threads']) ? $values['allow_new_threads_group']['allow_new_threads'] : null,
            'default_view'=> isset($values['default_view_type_group']['default_view_type']) ? $values['default_view_type_group']['default_view_type'] : null,
            'forum_of_group'=> isset($values['group_forum']) ? $values['group_forum'] : null,
            'forum_group_public_private'=> isset($values['public_private_group_forum_group']['public_private_group_forum']) ? $values['public_private_group_forum_group']['public_private_group_forum'] : null,
            'moderated'=> (int) $values['moderated'],
            'start_time' => !empty($values['start_time']) ? api_get_utc_datetime($values['start_time']) : null,
            'end_time' => !empty($values['end_time']) ? api_get_utc_datetime($values['end_time']) : null,
            'forum_order'=> isset($new_max) ? $new_max : null,
            'session_id'=> $session_id,
            'lp_id' => isset($values['lp_id']) ? intval($values['lp_id']) : 0,
            'locked' => 0,
            'forum_id' => 0
        ];

        $last_id = Database::insert($table_forums, $params);
        if ($last_id > 0) {

            $sql = "UPDATE $table_forums SET forum_id = iid WHERE iid = $last_id";
            Database::query($sql);

            api_item_property_update(
                $courseInfo,
                TOOL_FORUM,
                $last_id,
                'ForumAdded',
                api_get_user_id(),
                $group_id
            );

            api_set_default_visibility(
                $last_id,
                TOOL_FORUM,
                $group_id,
                $courseInfo
            );
        }
        $return_message = get_lang('ForumAdded');
        if ($returnId) {

            return $last_id;
        }
    }

    return $return_message;
}

/**
 * This function deletes a forum or a forum category
 * This function currently does not delete the forums inside the category,
 * nor the threads and replies inside these forums.
 * For the moment this is the easiest method and it has the advantage that it
 * allows to recover fora that were acidently deleted
 * when the forum category got deleted.
 *
 * @param $content = what we are deleting (a forum or a forum category)
 * @param $id The id of the forum category that has to be deleted.
 *
 * @todo write the code for the cascading deletion of the forums inside a
 * forum category and also the threads and replies inside these forums
 * @todo config setting for recovery or not
 * (see also the documents tool: real delete or not).
 * @return string
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function deleteForumCategoryThread($content, $id)
{
    $_course = api_get_course_info();
    $table_forums = Database::get_course_table(TABLE_FORUM);
    $table_forums_post = Database::get_course_table(TABLE_FORUM_POST);
    $table_forum_thread = Database::get_course_table(TABLE_FORUM_THREAD);
    $course_id = api_get_course_int_id();
    $groupId = api_get_group_id();
    $userId = api_get_user_id();
    $id = intval($id);

    // Delete all attachment file about this tread id.
    $sql = "SELECT post_id FROM $table_forums_post
            WHERE c_id = $course_id AND thread_id = '".$id."' ";
    $res = Database::query($sql);
    while ($poster_id = Database::fetch_row($res)) {
        delete_attachment($poster_id[0]);
    }

    $tool_constant = null;
    $return_message = '';

    if ($content == 'forumcategory') {
        $tool_constant = TOOL_FORUM_CATEGORY;
        $return_message = get_lang('ForumCategoryDeleted');

        if (!empty($forum_list)) {
            $sql = "SELECT forum_id FROM ".$table_forums."
                    WHERE c_id = $course_id AND forum_category='".$id."'";
            $result = Database::query($sql);
            $row = Database::fetch_array($result);
            foreach ($row as $arr_forum) {
                $forum_id = $arr_forum['forum_id'];
                api_item_property_update(
                    $_course,
                    'forum',
                    $forum_id,
                    'delete',
                    api_get_user_id()
                );
            }
        }
    }

    if ($content == 'forum') {
        $tool_constant = TOOL_FORUM;
        $return_message = get_lang('ForumDeleted');

        if (!empty($number_threads)) {
            $sql = "SELECT thread_id FROM".$table_forum_thread."
                    WHERE c_id = $course_id AND forum_id='".$id."'";
            $result = Database::query($sql);
            $row = Database::fetch_array($result);
            foreach ($row as $arr_forum) {
                $forum_id = $arr_forum['thread_id'];
                api_item_property_update($_course, 'forum_thread', $forum_id, 'delete', api_get_user_id());
            }
        }
    }

    if ($content == 'thread') {
        $tool_constant = TOOL_FORUM_THREAD;
        $return_message = get_lang('ThreadDeleted');
    }

    api_item_property_update(
        $_course,
        $tool_constant,
        $id,
        'delete',
        $userId,
        $groupId
    );

    // Check if this returns a true and if so => return $return_message, if not => return false;
    return $return_message;
}

/**
 * This function deletes a forum post. This separate function is needed because forum posts do not appear in the item_property table (yet)
 * and because deleting a post also has consequence on the posts that have this post as parent_id (they are also deleted).
 * an alternative would be to store the posts also in item_property and mark this post as deleted (visibility = 2).
 * We also have to decrease the number of replies in the thread table
 *
 * @param $post_id the id of the post that will be deleted
 * @todo write recursive function that deletes all the posts that have this message as parent
 * @return string language variable
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @author Hubert Borderiou Function cleanead and fixed
 * @version february 2006
 */
function delete_post($post_id)
{
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $post_id = intval($post_id);
    $course_id = api_get_course_int_id();
    $em = Database::getManager();

    $post = $em
        ->getRepository('ChamiloCourseBundle:CForumPost')
        ->findOneBy(['cId' => $course_id, 'postId' => $post_id]);

    if ($post) {
        $em
            ->createQuery('
                UPDATE ChamiloCourseBundle:CForumPost p
                SET p.postParentId = :parent_of_deleted_post
                WHERE
                    p.cId = :course AND
                    p.postParentId = :post AND
                    p.threadId = :thread_of_deleted_post AND
                    p.forumId = :forum_of_deleted_post
            ')
            ->execute([
                'parent_of_deleted_post' => $post->getPostParentId(),
                'course' => $course_id,
                'post' => $post->getPostId(),
                'thread_of_deleted_post' => $post->getThreadId(),
                'forum_of_deleted_post' => $post->getForumId()
            ]);

        $em->remove($post);
        $em->flush();

        // Delete attachment file about this post id.
        delete_attachment($post_id);
    }

    $last_post_of_thread = check_if_last_post_of_thread($_GET['thread']);

    if (is_array($last_post_of_thread)) {
        // Decreasing the number of replies for this thread and also changing the last post information.
        $sql = "UPDATE $table_threads
                SET
                    thread_replies=thread_replies-1,
                    thread_last_post = ".intval($last_post_of_thread['post_id']).",
                    thread_date='".Database::escape_string($last_post_of_thread['post_date'])."'
                WHERE c_id = $course_id AND thread_id = ".intval($_GET['thread']);
        Database::query($sql);

        return 'PostDeleted';
    }
    if (!$last_post_of_thread) {
        // We deleted the very single post of the thread so we need to delete the entry in the thread table also.
        $sql = "DELETE FROM $table_threads
                WHERE c_id = $course_id AND thread_id = ".intval($_GET['thread']);
        Database::query($sql);

        return 'PostDeletedSpecial';
    }
}

/**
 * This function gets the all information of the last (=most recent) post of the thread
 * This can be done by sorting the posts that have the field thread_id=$thread_id and sort them by post_date
 *
 * @param $thread_id the id of the thread we want to know the last post of.
 * @return an array or bool if there is a last post found, false if there is
 * no post entry linked to that thread => thread will be deleted
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function check_if_last_post_of_thread($thread_id)
{
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $course_id = api_get_course_int_id();
    $sql = "SELECT * FROM $table_posts
            WHERE c_id = $course_id AND thread_id = ".intval($thread_id)."
            ORDER BY post_date DESC";
    $result = Database::query($sql);
    if (Database::num_rows($result) > 0) {
        $row = Database::fetch_array($result);

        return $row;
    } else {
        return false;
    }
}

/**
 * @param $content what is it that we want to make (in)visible: forum category, forum, thread, post
 * @param $id the id of the content we want to make invisible
 * @param $current_visibility_status what is the current status of the visibility (0 = invisible, 1 = visible)
 * @param array $additional_url_parameters
 *
 * @return string HTML
 */
function return_visible_invisible_icon($content, $id, $current_visibility_status, $additional_url_parameters = '')
{
    $html = '';
    $id = Security::remove_XSS($id);
    if ($current_visibility_status == '1') {
        $html .= '<a href="' . api_get_self() . '?' . api_get_cidreq() . '&';
        if (is_array($additional_url_parameters)) {
            foreach ($additional_url_parameters as $key => $value) {
                $html .= $key . '=' . $value . '&';
            }
        }
        $html.='action=invisible&content='.$content.'&id='.$id.'">'.
            Display::return_icon('visible.png', get_lang('MakeInvisible'), array(), ICON_SIZE_SMALL).'</a>';
    }
    if ($current_visibility_status == '0') {
        $html .= '<a href="' . api_get_self() . '?' . api_get_cidreq() . '&';
        if (is_array($additional_url_parameters)) {
            foreach ($additional_url_parameters as $key => $value) {
                $html .= $key . '=' . $value . '&';
            }
        }
        $html .= 'action=visible&content=' . $content . '&id=' . $id . '">' .
            Display::return_icon('invisible.png', get_lang('MakeVisible'), array(), ICON_SIZE_SMALL) . '</a>';
    }
    return $html;
}

/**
 * @param $content
 * @param $id
 * @param $current_lock_status
 * @param string $additional_url_parameters
 * @return string
 */
function return_lock_unlock_icon($content, $id, $current_lock_status, $additional_url_parameters = '')
{
    $html = '';
    $id = intval($id);
    //check if the forum is blocked due
    if ($content == 'thread') {
        if (api_resource_is_locked_by_gradebook($id, LINK_FORUM_THREAD)) {
            $html .= Display::return_icon('lock_na.png', get_lang('ResourceLockedByGradebook'), array(), ICON_SIZE_SMALL);

            return $html;
        }
    }
    if ($current_lock_status == '1') {
        $html .= '<a href="'.api_get_self().'?'.api_get_cidreq().'&';
        if (is_array($additional_url_parameters)) {
            foreach ($additional_url_parameters as $key => $value) {
                $html .= $key . '=' . $value . '&';
            }
        }
        $html.= 'action=unlock&content='.$content.'&id='.$id.'">'.
            Display::return_icon('lock.png', get_lang('Unlock'), array(), ICON_SIZE_SMALL).'</a>';
    }
    if ($current_lock_status == '0') {
        $html .= '<a href="'.api_get_self().'?'.api_get_cidreq().'&';
        if (is_array($additional_url_parameters)) {
            foreach ($additional_url_parameters as $key => $value) {
                $html .= $key . '=' . $value . '&';
            }
        }
        $html .= 'action=lock&content=' . $content . '&id=' . $id . '">' .
            Display::return_icon('unlock.png', get_lang('Lock'), array(), ICON_SIZE_SMALL) . '</a>';
    }
    return $html;
}

/**
 * This function takes care of the display of the up and down icon
 *
 * @param $content what is it that we want to make (in)visible: forum category, forum, thread, post
 * @param $id is the id of the item we want to display the icons for
 * @param $list is an array of all the items. All items in this list should have
 * an up and down icon except for the first (no up icon) and the last (no down icon)
 *          The key of this $list array is the id of the item.
 *
 * @return string HTML
 **/
function return_up_down_icon($content, $id, $list)
{
    $id = strval(intval($id));
    $total_items = count($list);
    $position = 0;
    $internal_counter = 0;
    $forumCategory = isset($_GET['forumcategory']) ? Security::remove_XSS($_GET['forumcategory']) : null;

    if (is_array($list)) {
        foreach ($list as $key => $listitem) {
            $internal_counter++;
            if ($id == $key) {
                $position = $internal_counter;
            }
        }
    }

    if ($position > 1) {
        $return_value = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=move&direction=up&content='.$content.'&forumcategory='.$forumCategory.'&id='.$id.'" title="'.get_lang('MoveUp').'">'.
            Display::return_icon('up.png', get_lang('MoveUp'), array(), ICON_SIZE_SMALL).'</a>';
    } else {
        $return_value = Display::return_icon('up_na.png', '-', array(), ICON_SIZE_SMALL);
    }

    if ($position < $total_items) {
        $return_value .= '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=move&direction=down&content='.$content.'&forumcategory='.$forumCategory.'&id='.$id.'" title="'.get_lang('MoveDown').'" >'.
            Display::return_icon('down.png', get_lang('MoveDown'), array(), ICON_SIZE_SMALL).'</a>';
    } else {
        $return_value .= Display::return_icon('down_na.png', '-', array(), ICON_SIZE_SMALL);
    }
    return $return_value;
}

/**
 * This function changes the visibility in the database (item_property)
 *
 * @param string $content what is it that we want to make (in)visible: forum category, forum, thread, post
 * @param int $id the id of the content we want to make invisible
 * @param string $target_visibility what is the current status of the visibility (0 = invisible, 1 = visible)
 *
 * @todo change the get parameter so that it matches the tool constants.
 * @todo check if api_item_property_update returns true or false => returnmessage depends on it.
 * @todo move to itemmanager
 *
 * @return string language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function change_visibility($content, $id, $target_visibility)
{
    $_course = api_get_course_info();
    $constants = array(
        'forumcategory' => TOOL_FORUM_CATEGORY,
        'forum' => TOOL_FORUM,
        'thread' => TOOL_FORUM_THREAD,
    );
    api_item_property_update(
        $_course,
        $constants[$content],
        $id,
        $target_visibility,
        api_get_user_id()
    );

    if ($target_visibility == 'visible') {
        handle_mail_cue($content, $id);
    }
    return get_lang('VisibilityChanged');
}

/**
 * This function changes the lock status in the database
 *
 * @param string $content what is it that we want to (un)lock: forum category, forum, thread, post
 * @param int $id the id of the content we want to (un)lock
 * @param string $action do we lock (=>locked value in db = 1) or unlock (=> locked value in db = 0)
 * @return string language variable
 *
 * @todo move to item manager
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function change_lock_status($content, $id, $action)
{
    $table_categories = Database :: get_course_table(TABLE_FORUM_CATEGORY);
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);

    // Determine the relevant table.
    if ($content == 'forumcategory') {
        $table = $table_categories;
        $id_field = 'cat_id';
    } elseif ($content == 'forum') {
        $table = $table_forums;
        $id_field = 'forum_id';
    } elseif ($content == 'thread') {
        $table = $table_threads;
        $id_field = 'thread_id';
    } else {
        return get_lang('Error');
    }

    // Determine what we are doing => defines the value for the database and the return message.
    if ($action == 'lock') {
        $db_locked = 1;
        $return_message = get_lang('Locked');
    } elseif ($action == 'unlock') {
        $db_locked = 0;
        $return_message = get_lang('Unlocked');
    } else {
        return get_lang('Error');
    }

    $course_id = api_get_course_int_id();

    // Doing the change in the database
    $sql = "UPDATE $table SET locked='".Database::escape_string($db_locked)."'
            WHERE c_id = $course_id AND $id_field='".Database::escape_string($id)."'";
    if (Database::query($sql)) {
        return $return_message;
    } else {
        return get_lang('Error');
    }
}

/**
 * This function moves a forum or a forum category up or down
 *
 * @param $content what is it that we want to make (in)visible: forum category, forum, thread, post
 * @param $direction do we want to move it up or down.
 * @param $id the id of the content we want to make invisible
 * @todo consider removing the table_item_property calls here but this can
 * prevent unwanted side effects when a forum does not have an entry in
 * the item_property table but does have one in the forum table.
 * @return string language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function move_up_down($content, $direction, $id)
{
    $table_categories = Database :: get_course_table(TABLE_FORUM_CATEGORY);
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $course_id = api_get_course_int_id();
    $id = intval($id);

    // Determine which field holds the sort order.
    if ($content == 'forumcategory') {
        $table = $table_categories;
        $sort_column = 'cat_order';
        $id_column = 'cat_id';
        $sort_column = 'cat_order';
    } elseif ($content == 'forum') {
        $table = $table_forums;
        $sort_column = 'forum_order';
        $id_column = 'forum_id';
        $sort_column = 'forum_order';
        // We also need the forum_category of this forum.
        $sql = "SELECT forum_category FROM $table_forums
                WHERE c_id = $course_id AND forum_id = ".intval($id);
        $result = Database::query($sql);
        $row = Database::fetch_array($result);
        $forum_category = $row['forum_category'];
    } else {
        return get_lang('Error');
    }

    // Determine the need for sorting ascending or descending order.
    if ($direction == 'down') {
        $sort_direction = 'ASC';
    } elseif ($direction == 'up') {
        $sort_direction = 'DESC';
    } else {
        return get_lang('Error');
    }

    // The SQL statement
    if ($content == 'forumcategory') {
        $sql = "SELECT *
                FROM $table_categories forum_categories, $table_item_property item_properties
                WHERE
                    forum_categories.c_id = $course_id AND
                    item_properties.c_id = $course_id AND
                    forum_categories.cat_id=item_properties.ref AND
                    item_properties.tool='" . TOOL_FORUM_CATEGORY . "'
                ORDER BY forum_categories.cat_order $sort_direction";
    }
    if ($content == 'forum') {
        $sql = "SELECT *
            FROM $table
            WHERE
                c_id = $course_id AND
                forum_category='" . Database::escape_string($forum_category) . "'
            ORDER BY forum_order $sort_direction";
    }
    // Finding the items that need to be switched.
    $result = Database::query($sql);
    $found = false;
    while ($row = Database::fetch_array($result)) {
        //echo $row[$id_column].'-';
        if ($found) {
            $next_id = $row[$id_column];
            $next_sort = $row[$sort_column];
            $found = false;
        }
        if ($id == $row[$id_column]) {
            $this_id = $id;
            $this_sort = $row[$sort_column];
            $found = true;
        }
    }

    // Committing the switch.
    // We do an extra check if we do not have illegal values. If your remove this if statment you will
    // be able to mess with the sorting by refreshing the page over and over again.
    if ($this_sort != '' && $next_sort != '' && $next_id != '' && $this_id != '') {
        $sql = "UPDATE $table SET $sort_column='".Database::escape_string($this_sort)."'
                WHERE c_id = $course_id AND $id_column='".Database::escape_string($next_id)."'";
        Database::query($sql);

        $sql = "UPDATE $table SET $sort_column='".Database::escape_string($next_sort)."'
                WHERE c_id = $course_id AND $id_column='".Database::escape_string($this_id)."'";
        Database::query($sql);
    }

    return get_lang(ucfirst($content).'Moved');
}

/**
 * This function returns a piece of html code that make the links grey (=invisible for the student)
 *
 * @param int 0 = invisible, 1 = visible
 * @return string language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function class_visible_invisible($current_visibility_status)
{
    $current_visibility_status = intval($current_visibility_status);
    if ($current_visibility_status == 0) {
        return 'class="invisible"';
    }
}

function return_visible_invisible($current_visibility_status)
{
    $current_visibility_status = intval($current_visibility_status);
    if ($current_visibility_status == 0) {
        $status='invisible';
        return $status;
    }
}

/**
 * Retrieve all the information off the forum categories (or one specific) for the current course.
 * The categories are sorted according to their sorting order (cat_order
 *
 * @param int|string $id default ''. When an id is passed we only find the information
 * about that specific forum category. If no id is passed we get all the forum categories.
 * @param int $courseId Optional. The course ID
 * @param int $sessionId Optional. The session ID
 * @return array containing all the information about all the forum categories
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_forum_categories($id = '', $courseId = 0, $sessionId = 0)
{
    $table_categories = Database :: get_course_table(TABLE_FORUM_CATEGORY);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);

    // Condition for the session
    $session_id = $sessionId ?: api_get_session_id();
    $course_id = $courseId ?: api_get_course_int_id();

    $condition_session = api_get_session_condition($session_id, true, true, 'forum_categories.session_id');
    $condition_session .= " AND forum_categories.c_id = $course_id AND item_properties.c_id = $course_id";

    if (empty($id)) {
        $sql = "SELECT *
                FROM ".$table_item_property." item_properties, ".$table_categories." forum_categories
                WHERE
                    forum_categories.cat_id=item_properties.ref AND
                    item_properties.visibility=1 AND
                    item_properties.tool = '".TOOL_FORUM_CATEGORY."'
                    $condition_session
                ORDER BY forum_categories.cat_order ASC";
        if (api_is_allowed_to_edit()) {
            $sql = "SELECT *
                    FROM ".$table_item_property." item_properties, ".$table_categories." forum_categories
                    WHERE
                        forum_categories.cat_id=item_properties.ref AND
                        item_properties.visibility<>2 AND
                        item_properties.tool='".TOOL_FORUM_CATEGORY."'
                        $condition_session
                    ORDER BY forum_categories.cat_order ASC";
        }
    } else {
        $sql = "SELECT *
                FROM ".$table_item_property." item_properties, ".$table_categories." forum_categories
                WHERE
                    forum_categories.cat_id=item_properties.ref AND
                    item_properties.tool='".TOOL_FORUM_CATEGORY."' AND
                    forum_categories.cat_id = ".intval($id)."
                    $condition_session
                ORDER BY forum_categories.cat_order ASC";
    }
    $result = Database::query($sql);
    $forum_categories_list = array();

    while ($row = Database::fetch_assoc($result)) {
        if (empty($id)) {
            $forum_categories_list[$row['cat_id']] = $row;
        } else {
            $forum_categories_list = $row;
        }
    }

    return $forum_categories_list;
}

/**
 * This function retrieves all the fora in a given forum category
 *
 * @param int $cat_id the id of the forum category
 * @param int $courseId Optional. The course ID
 * @return array containing all the information about the forums (regardless of their category)
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_forums_in_category($cat_id, $courseId = 0)
{
    $table_forums = Database::get_course_table(TABLE_FORUM);
    $table_item_property = Database::get_course_table(TABLE_ITEM_PROPERTY);

    $forum_list = array();
    $course_id = $courseId ?: api_get_course_int_id();

    $sql = "SELECT * FROM ".$table_forums." forum , ".$table_item_property." item_properties
            WHERE
                forum.forum_category='".Database::escape_string($cat_id)."' AND
                forum.forum_id=item_properties.ref AND
                item_properties.visibility = 1 AND
                item_properties.c_id = $course_id AND
                item_properties.tool='".TOOL_FORUM."' AND
                forum.c_id = $course_id
            ORDER BY forum.forum_order ASC";
    if (api_is_allowed_to_edit()) {
        $sql = "SELECT * FROM ".$table_forums." forum , ".$table_item_property." item_properties
                WHERE
                    forum.forum_category = '".Database::escape_string($cat_id)."' AND
                    forum.forum_id = item_properties.ref AND
                    item_properties.visibility <> 2 AND
                    item_properties.tool = '".TOOL_FORUM."' AND
                    item_properties.c_id = $course_id AND
                    forum.c_id = $course_id
                ORDER BY forum_order ASC";
    }
    $result = Database::query($sql);
    while ($row = Database::fetch_array($result)) {
        $forum_list[$row['forum_id']] = $row;
    }

    return $forum_list;
}

/**
 * Retrieve all the forums (regardless of their category) or of only one.
 * The forums are sorted according to the forum_order.
 * Since it does not take the forum category into account there probably
 * will be two or more forums that have forum_order=1, ...
 * @param int $id forum id
 * @param string $course_code
 * @param bool $includeGroupsForum
 * @param int $sessionId
 * @return array an array containing all the information about the forums (regardless of their category)
 * @todo check $sql4 because this one really looks fishy.
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_forums(
    $id = '',
    $course_code = '',
    $includeGroupsForum = true,
    $sessionId = 0
) {
    $course_info = api_get_course_info($course_code);

    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);

    // Condition for the session
    $session_id = intval($sessionId) ?: api_get_session_id();

    $sessionIdLink = ($session_id === 0) ? '' : 'AND threads.session_id = item_properties.session_id';

    $condition_session = api_get_session_condition(
        $session_id,
        true,
        false,
        'item_properties.session_id'
    );
    $course_id = $course_info['real_id'];

    $forum_list = array();
    $includeGroupsForumSelect = "";
    if (!$includeGroupsForum) {
        $includeGroupsForumSelect = " AND forum_of_group = 0 ";
    }

    if ($id == '') {
        // Student
        // Select all the forum information of all forums (that are visible to students).
        $sql = "SELECT item_properties.*, forum.* FROM $table_forums forum
                INNER JOIN ".$table_item_property." item_properties
                ON (
                    forum.forum_id = item_properties.ref AND
                    forum.c_id = item_properties.c_id
                )
                WHERE
                    item_properties.visibility=1 AND
                    item_properties.tool = '".TOOL_FORUM."'
                    $condition_session AND
                    forum.c_id = $course_id AND
                    item_properties.c_id = $course_id
                    $includeGroupsForumSelect
                ORDER BY forum.forum_order ASC";

        // Select the number of threads of the forums (only the threads that are visible).
        $sql2 = "SELECT count(*) AS number_of_threads, threads.forum_id
                FROM $table_threads threads
                INNER JOIN ".$table_item_property." item_properties
                ON (
                    threads.thread_id=item_properties.ref AND
                    threads.c_id = item_properties.c_id
                    $sessionIdLink
                )
                WHERE
                    item_properties.visibility=1 AND
                    item_properties.tool='".TOOL_FORUM_THREAD."' AND
                    threads.c_id = $course_id AND
                    item_properties.c_id = $course_id
                GROUP BY threads.forum_id";


        // Course Admin
        if (api_is_allowed_to_edit()) {
            // Select all the forum information of all forums (that are not deleted).
            $sql = "SELECT item_properties.*, forum.* FROM ".$table_forums." forum
                    INNER JOIN ".$table_item_property." item_properties
                    ON (
                        forum.forum_id = item_properties.ref AND
                        forum.c_id = item_properties.c_id
                    )
                    WHERE
                        item_properties.visibility <> 2 AND
                        item_properties.tool='".TOOL_FORUM."'
                        $condition_session AND
                        forum.c_id = $course_id AND
                        item_properties.c_id = $course_id
                        $includeGroupsForumSelect
                    ORDER BY forum_order ASC";

            // Select the number of threads of the forums (only the threads that are not deleted).
            $sql2 = "SELECT count(*) AS number_of_threads, threads.forum_id
                    FROM $table_threads threads
                    INNER JOIN ".$table_item_property." item_properties
                    ON (
                        threads.thread_id=item_properties.ref AND
                        threads.c_id = item_properties.c_id
                        $sessionIdLink
                    )
                    WHERE
                        item_properties.visibility<>2 AND
                        item_properties.tool='".TOOL_FORUM_THREAD."' AND
                        threads.c_id = $course_id AND
                        item_properties.c_id = $course_id
                    GROUP BY threads.forum_id";
        }
    } else {
        // GETTING ONE SPECIFIC FORUM
        /* We could do the splitup into student and course admin also but we want
        to have as much as information about a certain forum as possible
        so we do not take too much information into account. This function
         (or this section of the function) is namely used to fill the forms
        when editing a forum (and for the moment it is the only place where
        we use this part of the function) */

        // Select all the forum information of the given forum (that is not deleted).
        $sql = "SELECT * FROM ".$table_item_property." item_properties, $table_forums forum
                WHERE
                    forum.forum_id = item_properties.ref AND
                    forum.iid = " . intval($id) . " AND
                    item_properties.visibility != 2 AND
                    item_properties.tool = '".TOOL_FORUM."' AND 
                    forum.c_id = item_properties.c_id
                ORDER BY forum_order ASC";

        // Select the number of threads of the forum.
        $sql2 = "SELECT count(*) AS number_of_threads, forum_id
                FROM $table_threads
                WHERE
                    forum_id = " . intval($id) . "
                GROUP BY forum_id";
    }

    // Handling all the forum information.

    $result = Database::query($sql);
    while ($row = Database::fetch_assoc($result)) {
        if ($id == '') {
            $forum_list[$row['forum_id']] = $row;
        } else {
            $forum_list = $row;
        }
    }

    // Handling the thread count information.
    $result2 = Database::query($sql2);
    while ($row2 = Database::fetch_array($result2)) {
        if ($id == '') {
            $forum_list[$row2['forum_id']]['number_of_threads'] = $row2['number_of_threads'];
        } else {
            $forum_list['number_of_threads'] = $row2['number_of_threads'];
        }
    }

    /* Finding the last post information
    (last_post_id, last_poster_id, last_post_date, last_poster_name, last_poster_lastname, last_poster_firstname)*/
    if ($id == '') {
        if (is_array($forum_list)) {
            foreach ($forum_list as $key => $value) {
                $last_post_info_of_forum = get_last_post_information(
                    $key,
                    api_is_allowed_to_edit(),
                    $course_id
                );
                $forum_list[$key]['last_post_id'] = $last_post_info_of_forum['last_post_id'];
                $forum_list[$key]['last_poster_id'] = $last_post_info_of_forum['last_poster_id'];
                $forum_list[$key]['last_post_date'] = $last_post_info_of_forum['last_post_date'];
                $forum_list[$key]['last_poster_name'] = $last_post_info_of_forum['last_poster_name'];
                $forum_list[$key]['last_poster_lastname'] = $last_post_info_of_forum['last_poster_lastname'];
                $forum_list[$key]['last_poster_firstname'] = $last_post_info_of_forum['last_poster_firstname'];
            }
        } else {
            $forum_list = array();
        }
    } else {
        $last_post_info_of_forum = get_last_post_information(
            $id,
            api_is_allowed_to_edit(),
            $course_id
        );
        $forum_list['last_post_id'] = $last_post_info_of_forum['last_post_id'];
        $forum_list['last_poster_id'] = $last_post_info_of_forum['last_poster_id'];
        $forum_list['last_post_date'] = $last_post_info_of_forum['last_post_date'];
        $forum_list['last_poster_name'] = $last_post_info_of_forum['last_poster_name'];
        $forum_list['last_poster_lastname'] = $last_post_info_of_forum['last_poster_lastname'];
        $forum_list['last_poster_firstname'] = $last_post_info_of_forum['last_poster_firstname'];
    }

    return $forum_list;
}

/**
 * @param int $course_id
 * @param int $thread_id
 * @param int $forum_id
 * @param bool $show_visible
 * @return array|bool
 */
function get_last_post_by_thread($course_id, $thread_id, $forum_id, $show_visible = true)
{
    if (empty($thread_id) || empty($forum_id) || empty($course_id)) {
        return false;
    }

    $thread_id = intval($thread_id);
    $forum_id = intval($forum_id);
    $course_id = intval($course_id);

    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $sql = "SELECT * FROM $table_posts
            WHERE c_id = $course_id AND thread_id = $thread_id AND forum_id = $forum_id";

    if ($show_visible == false) {
        $sql .= " AND visible = 1 ";
    }

    $sql .= " ORDER BY post_id DESC LIMIT 1";
    $result = Database::query($sql);
    if (Database::num_rows($result)) {
        return Database::fetch_array($result, 'ASSOC');
    } else {
        return false;
    }
}

/**
 * This function gets all the last post information of a certain forum
 *
 * @param int   $forum_id the id of the forum we want to know the last post information of.
 * @param bool  $show_invisibles
 * @param string course db name
 * @return array containing all the information about the last post
 * (last_post_id, last_poster_id, last_post_date, last_poster_name, last_poster_lastname, last_poster_firstname)
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_last_post_information($forum_id, $show_invisibles = false, $course_id = null)
{
    if (!isset($course_id)) {
        $course_id = api_get_course_int_id();
    } else {
        $course_id = intval($course_id);
    }

    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $table_users = Database :: get_main_table(TABLE_MAIN_USER);

    $sql = "SELECT
                post.post_id,
                post.forum_id,
                post.poster_id,
                post.poster_name,
                post.post_date,
                users.lastname,
                users.firstname,
                post.visible,
                thread_properties.visibility AS thread_visibility,
                forum_properties.visibility AS forum_visibility
            FROM
                $table_posts post,
                $table_users users,
                $table_item_property thread_properties,
                $table_item_property forum_properties
            WHERE
                post.forum_id = ".intval($forum_id)."
                AND post.poster_id=users.user_id
                AND post.thread_id=thread_properties.ref
                AND thread_properties.tool='".TOOL_FORUM_THREAD."'
                AND post.forum_id=forum_properties.ref
                AND forum_properties.tool='".TOOL_FORUM."'
                AND post.c_id = $course_id AND
                thread_properties.c_id = $course_id AND
                forum_properties.c_id = $course_id
            ORDER BY post.post_id DESC";
    $result = Database::query($sql);

    if ($show_invisibles) {
        $row = Database::fetch_array($result);
        $return_array['last_post_id'] = $row['post_id'];
        $return_array['last_poster_id'] = $row['poster_id'];
        $return_array['last_post_date'] = $row['post_date'];
        $return_array['last_poster_name'] = $row['poster_name'];
        $return_array['last_poster_lastname'] = $row['lastname'];
        $return_array['last_poster_firstname'] = $row['firstname'];

        return $return_array;
    } else {
        // We have to loop through the results to find the first one that is
        // actually visible to students (forum_category, forum, thread AND post are visible).
        while ($row = Database::fetch_array($result)) {
            if ($row['visible'] == '1' && $row['thread_visibility'] == '1' && $row['forum_visibility'] == '1') {
                $return_array['last_post_id'] = $row['post_id'];
                $return_array['last_poster_id'] = $row['poster_id'];
                $return_array['last_post_date'] = $row['post_date'];
                $return_array['last_poster_name'] = $row['poster_name'];
                $return_array['last_poster_lastname'] = $row['lastname'];
                $return_array['last_poster_firstname'] = $row['firstname'];

                return $return_array;
            }
        }
    }
}

/**
 * Retrieve all the threads of a given forum
 *
 * @param int   forum id
 * @param string course db name
 * @return an array containing all the information about the threads
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_threads($forum_id)
{
    $groupId = api_get_group_id();
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_users = Database :: get_main_table(TABLE_MAIN_USER);

    // important note:  it might seem a little bit awkward that we have 'thread.locked as locked' in the sql statement
    // because we also have thread.* in it. This is because thread has a field locked and post also has the same field
    // since we are merging these we would have the post.locked value but in fact we want the thread.locked value
    // This is why it is added to the end of the field selection
    $groupCondition = api_get_group_id() != 0 ? " AND item_properties.to_group_id = '$groupId' " : "";

    $sql = "SELECT DISTINCT
                item_properties.*,
                users.firstname,
                users.lastname,
                users.user_id,
                thread.locked as locked,
                thread.*
            FROM $table_threads thread
            INNER JOIN $table_item_property item_properties
            ON
                thread.thread_id = item_properties.ref AND
                item_properties.c_id = thread.c_id AND
                item_properties.tool = '".TABLE_FORUM_THREAD."' $groupCondition
            LEFT JOIN $table_users users
                ON thread.thread_poster_id=users.user_id
            WHERE
                item_properties.visibility='1' AND
                thread.forum_id = ".intval($forum_id)."
            ORDER BY thread.thread_sticky DESC, thread.thread_date DESC";

    if (api_is_allowed_to_edit()) {
        $sql = "SELECT DISTINCT
                    item_properties.*,
                    users.firstname,
                    users.lastname,
                    users.user_id,
                    thread.locked as locked,
                    thread.*
                FROM $table_threads thread
                INNER JOIN $table_item_property item_properties
                ON
                    thread.thread_id = item_properties.ref AND
                    item_properties.c_id = thread.c_id AND
                    item_properties.tool = '".TABLE_FORUM_THREAD."'
                    $groupCondition
                LEFT JOIN $table_users users
                    ON thread.thread_poster_id=users.user_id
                WHERE
                    item_properties.visibility<>2 AND
                    thread.forum_id = ".intval($forum_id)."
                ORDER BY thread.thread_sticky DESC, thread.thread_date DESC";
    }
    $result = Database::query($sql);
    $list = array();
    $alreadyAdded = array();
    while ($row = Database::fetch_array($result, 'ASSOC')) {
        if (in_array($row['thread_id'], $alreadyAdded)) {
            continue;
        }
        $list[] = $row;
        $alreadyAdded[] = $row['thread_id'];
    }

    return $list;
}

/**
 * Get a thread by Id and course id
 *
 * @param int $threadId the thread Id
 * @param int $cId the course id
 * @return array containing all the information about the thread
 */
function getThreadInfo($threadId, $cId)
{
    $em = Database::getManager();
    $forumThread = $em->getRepository('ChamiloCourseBundle:CForumThread')->findOneBy(['threadId' => $threadId, 'cId' => $cId]);

    $thread = [];

    if ($forumThread) {
        $thread['threadId'] = $forumThread->getThreadId();
        $thread['threadTitle'] = $forumThread->getThreadTitle();
        $thread['forumId'] = $forumThread->getForumId();
        $thread['sessionId'] = $forumThread->getSessionId();
        $thread['threadSticky'] = $forumThread->getThreadSticky();
        $thread['locked'] = $forumThread->getLocked();
        $thread['threadTitleQualify'] = $forumThread->getThreadTitleQualify();
        $thread['threadQualifyMax'] = $forumThread->getThreadQualifyMax();
        $thread['threadCloseDate'] = $forumThread->getThreadCloseDate();
        $thread['threadWeight'] = $forumThread->getThreadWeight();
        $thread['threadPeerQualify'] = $forumThread->isThreadPeerQualify();
    }

    return $thread;
}

/**
 * Retrieve all posts of a given thread
 * @param array $forumInfo
 * @param int $threadId The thread ID
 * @param string $orderDirection Optional. The direction for sort the posts
 * @param boolean $recursive Optional. If the list is recursive
 * @param int $postId Optional. The post ID for recursive list
 * @param int $depth Optional. The depth to indicate the indent
 * @todo move to a repository
 *
 * @return array containing all the information about the posts of a given thread
 */
function getPosts($forumInfo, $threadId, $orderDirection = 'ASC', $recursive = false, $postId = null, $depth = -1)
{
    $list = [];

    $em = Database::getManager();

    if (api_is_allowed_to_edit(false, true)) {
        $visibleCriteria = Criteria::expr()->neq('visible', 2);
    } else {
        $visibleCriteria = Criteria::expr()->eq('visible', 1);
    }

    $criteria = Criteria::create();
    $criteria
        ->where(Criteria::expr()->eq('threadId', $threadId))
        ->andWhere($visibleCriteria)
    ;

    $groupId = api_get_group_id();
    $filterModerated = true;

    if (empty($groupId)) {
        if (api_is_allowed_to_edit()) {
            $filterModerated = false;
        }
    } else {
        if (GroupManager::is_tutor_of_group(api_get_user_id(), $groupId)) {
            $filterModerated = false;
        }
    }

    if ($filterModerated && $forumInfo['moderated'] == 1) {
        $criteria->andWhere(Criteria::expr()->eq('status', 1));
    }

    if ($recursive) {
        $criteria->andWhere(Criteria::expr()->eq('postParentId', $postId));
    }

    $qb = $em->getRepository('ChamiloCourseBundle:CForumPost')->createQueryBuilder('p');
    $qb->select('p')
        ->addCriteria($criteria)
        ->addOrderBy('p.postId', $orderDirection);

    $posts = $qb->getQuery()->getResult();

    $depth++;
    /** @var CForumPost $post */
    foreach ($posts as $post) {
        $user = $em->find('ChamiloUserBundle:User', $post->getPosterId());

        $list[$post->getPostId()] = [
            'iid' => $post->getIid(),
            'c_id' => $post->getCId(),
            'post_id' => $post->getPostId(),
            'post_title' => $post->getPostTitle(),
            'post_text' => $post->getPostText(),
            'thread_id' => $post->getThreadId(),
            'forum_id' => $post->getForumId(),
            'poster_id' => $post->getPosterId(),
            'poster_name' => $post->getPosterName(),
            'post_date' => $post->getPostDate(),
            'post_notification' => $post->getPostNotification(),
            'post_parent_id' => $post->getPostParentId(),
            'visible' => $post->getVisible(),
            'status' => $post->getStatus(),
            'indent_cnt' => $depth,
            'user_id' => $user->getUserId(),
            'username' => $user->getUsername(),
            'username_canonical' => $user->getUsernameCanonical(),
            'lastname' => $user->getLastname(),
            'firstname' => $user->getFirstname(),
        ];

        if (!$recursive) {
            continue;
        }

        $list = array_merge(
            $list,
            getPosts(
                $forumInfo,
                $threadId,
                $orderDirection,
                $recursive,
                $post->getPostId(),
                $depth
            )
        );
    }

    return $list;
}

/**
 * This function retrieves all the information of a post
 *
 * @param int $post_id integer that indicates the forum
 *
 * @return array returns
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_post_information($post_id)
{
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $table_users = Database :: get_main_table(TABLE_MAIN_USER);
    $course_id = api_get_course_int_id();

    $sql = "SELECT posts.*, email FROM ".$table_posts." posts, ".$table_users." users
            WHERE
                c_id = $course_id AND
                posts.poster_id=users.user_id AND
                posts.post_id = ".intval($post_id);
    $result = Database::query($sql);
    $row = Database::fetch_array($result, 'ASSOC');

    return $row;
}

/**
 * This function retrieves all the information of a thread
 *
 * @param $thread_id integer that indicates the forum
 *
 * @return array returns
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_thread_information($thread_id)
{
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $thread_id = intval($thread_id);

    $sql = "SELECT * FROM " . $table_item_property . " item_properties, " . $table_threads . " threads
            WHERE
                item_properties.tool= '".TOOL_FORUM_THREAD."' AND
                item_properties.ref = threads.thread_id AND
                threads.iid   = ".$thread_id." AND
                threads.c_id = item_properties.c_id
            ";
    $result = Database::query($sql);
    $row = Database::fetch_assoc($result);

    return $row;
}

/**
 * This function retrieves forum thread users details
 * @param   int Thread ID
 * @param   string  Course DB name (optional)
 * @return  Doctrine\DBAL\Driver\Statement|null array Array of type ([user_id=>w,lastname=>x,firstname=>y,thread_id=>z],[])
 * @author Christian Fasanando <christian.fasanando@dokeos.com>,
 * @todo     this function need to be improved
 * @version octubre 2008, dokeos 1.8
 */
function get_thread_users_details($thread_id)
{
    $t_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $t_users = Database :: get_main_table(TABLE_MAIN_USER);
    $t_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
    $t_session_rel_user = Database :: get_main_table(TABLE_MAIN_SESSION_COURSE_USER);

    $course_id = api_get_course_int_id();

    $is_western_name_order = api_is_western_name_order();
    if ($is_western_name_order) {
        $orderby = 'ORDER BY user.firstname, user.lastname ';
    } else {
        $orderby = 'ORDER BY user.lastname, user.firstname';
    }

    if (api_get_session_id()) {
        $session_info = api_get_session_info(api_get_session_id());
        $user_to_avoid = "'".$session_info['id_coach']."', '".$session_info['session_admin_id']."'";
        //not showing coaches
        $sql = "SELECT DISTINCT user.id, user.lastname, user.firstname, thread_id
                  FROM $t_posts p, $t_users user, $t_session_rel_user session_rel_user_rel_course
                  WHERE p.poster_id = user.id AND
                  user.id = session_rel_user_rel_course.user_id AND
                  session_rel_user_rel_course.status<>'2' AND
                  session_rel_user_rel_course.user_id NOT IN ($user_to_avoid) AND
                  p.thread_id = ".intval($thread_id)." AND
                  session_id = ".api_get_session_id()." AND
                  p.c_id = $course_id AND
                  session_rel_user_rel_course.c_id = ".$course_id." $orderby ";
    } else {
        $sql = "SELECT DISTINCT user.id, user.lastname, user.firstname, thread_id
                  FROM $t_posts p, $t_users user, $t_course_user course_user
                  WHERE p.poster_id = user.id
                  AND user.id = course_user.user_id
                  AND course_user.relation_type<>".COURSE_RELATION_TYPE_RRHH."
                  AND p.thread_id = ".intval($thread_id)."
                  AND course_user.status NOT IN('1') AND
                  p.c_id = $course_id AND
                  course_user.c_id = ".$course_id." $orderby";
    }
    $result = Database::query($sql);

    return $result;
}

/**
 * This function retrieves forum thread users qualify
 * @param   int Thread ID
 * @param   string  Course DB name (optional)
 * @return  Doctrine\DBAL\Driver\Statement|null Array of type ([user_id=>w,lastname=>x,firstname=>y,thread_id=>z],[])
 * @author Jhon Hinojosa
 * @todo     this function need to be improved
 */
function get_thread_users_qualify($thread_id)
{
    $t_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $t_qualify = Database :: get_course_table(TABLE_FORUM_THREAD_QUALIFY);
    $t_users = Database :: get_main_table(TABLE_MAIN_USER);
    $t_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
    $t_session_rel_user = Database :: get_main_table(TABLE_MAIN_SESSION_COURSE_USER);

    $course_id = api_get_course_int_id();

    $is_western_name_order = api_is_western_name_order();
    if ($is_western_name_order) {
        $orderby = 'ORDER BY user.firstname, user.lastname ';
    } else {
        $orderby = 'ORDER BY user.lastname, user.firstname';
    }

    if (api_get_session_id()) {
        $session_info = api_get_session_info(api_get_session_id());
        $user_to_avoid = "'".$session_info['id_coach']."', '".$session_info['session_admin_id']."'";
        //not showing coaches
        $sql = "SELECT DISTINCT post.poster_id, user.lastname, user.firstname, post.thread_id,user.id,qualify.qualify
                FROM $t_posts post , $t_users user, $t_session_rel_user scu, $t_qualify qualify
                WHERE poster_id = user.id
                    AND post.poster_id = qualify.user_id
                    AND user.id = session_rel_user_rel_course.user_id
                    AND scu.status<>'2'
                    AND scu.user_id NOT IN ($user_to_avoid)
                    AND qualify.thread_id = ".intval($thread_id)."
                    AND post.thread_id = ".intval($thread_id)."
                    AND session_id = ".api_get_session_id()."
                    AND scu.c_id = ".$course_id." AND
                    qualify.c_id = $course_id AND
                    post.c_id = $course_id
                $orderby ";
    } else {
        $sql = "SELECT DISTINCT post.poster_id, user.lastname, user.firstname, post.thread_id,user.id,qualify.qualify
                FROM $t_posts post,
                     $t_qualify qualify,
                     $t_users user,
                     $t_course_user course_user
                WHERE
                     post.poster_id = user.id
                     AND post.poster_id = qualify.user_id
                     AND user.id = course_user.user_id
                     AND course_user.relation_type<>".COURSE_RELATION_TYPE_RRHH."
                     AND qualify.thread_id = ".intval($thread_id)."
                     AND post.thread_id = ".intval($thread_id)."
                     AND course_user.status not in('1')
                     AND course_user.c_id = $course_id
                     AND qualify.c_id = $course_id
                     AND post.c_id = $course_id
                 $orderby ";
    }
    $result = Database::query($sql);

    return $result;
}

/**
 * This function retrieves forum thread users not qualify
 * @param   int Thread ID
 * @param   string  Course DB name (optional)
 * @return  Doctrine\DBAL\Driver\Statement|null Array of type ([user_id=>w,lastname=>x,firstname=>y,thread_id=>z],[])
 * @author   Jhon Hinojosa<jhon.hinojosa@dokeos.com>,
 * @version oct 2008, dokeos 1.8
 */
function get_thread_users_not_qualify($thread_id)
{
    $t_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $t_qualify = Database :: get_course_table(TABLE_FORUM_THREAD_QUALIFY);
    $t_users = Database :: get_main_table(TABLE_MAIN_USER);
    $t_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
    $t_session_rel_user = Database :: get_main_table(TABLE_MAIN_SESSION_COURSE_USER);

    $is_western_name_order = api_is_western_name_order();
    if ($is_western_name_order) {
        $orderby = 'ORDER BY user.firstname, user.lastname ';
    } else {
        $orderby = 'ORDER BY user.lastname, user.firstname';
    }

    $course_id = api_get_course_int_id();

    $sql1 = "SELECT user_id FROM  $t_qualify
             WHERE c_id = $course_id AND thread_id = '".$thread_id."'";
    $result1 = Database::query($sql1);
    $cad = '';
    while ($row = Database::fetch_array($result1)) {
        $cad .= $row['user_id'].',';
    }
    if ($cad == '') {
        $cad = '0';
    } else {
        $cad = substr($cad, 0, strlen($cad) - 1);
    }

    if (api_get_session_id()) {
        $session_info = api_get_session_info(api_get_session_id());
        $user_to_avoid = "'".$session_info['id_coach']."', '".$session_info['session_admin_id']."'";
        //not showing coaches
        $sql = "SELECT DISTINCT user.id, user.lastname, user.firstname, post.thread_id
                FROM $t_posts post , $t_users user, $t_session_rel_user session_rel_user_rel_course
                WHERE poster_id = user.id
                    AND user.id NOT IN (".$cad.")
                    AND user.id = session_rel_user_rel_course.user_id
                    AND session_rel_user_rel_course.status<>'2'
                    AND session_rel_user_rel_course.user_id NOT IN ($user_to_avoid)
                    AND post.thread_id = ".intval($thread_id)."
                    AND session_id = ".api_get_session_id()."
                    AND session_rel_user_rel_course.c_id = $course_id AND post.c_id = $course_id $orderby ";
    } else {
        $sql = "SELECT DISTINCT user.id, user.lastname, user.firstname, post.thread_id
                FROM $t_posts post, $t_users user,$t_course_user course_user
                WHERE post.poster_id = user.id
                AND user.id NOT IN (".$cad.")
                AND user.id = course_user.user_id
                AND course_user.relation_type<>".COURSE_RELATION_TYPE_RRHH."
                AND post.thread_id = ".intval($thread_id)."
                AND course_user.status not in('1')
                AND course_user.c_id = $course_id AND post.c_id = $course_id  $orderby";
    }
    $result = Database::query($sql);

    return $result;
}

/**
 * This function retrieves all the information of a given forum_id
 *
 * @param $forum_id integer that indicates the forum
 * @return array returns
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 *
 * @deprecated this functionality is now moved to get_forums($forum_id)
 */
function get_forum_information($forum_id, $courseId = 0)
{
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $courseId = empty($courseId) ? api_get_course_int_id(): intval($courseId);
    $forum_id = intval($forum_id);

    $sql = "SELECT *
            FROM $table_forums forums
            INNER JOIN $table_item_property item_properties
            ON (forums.c_id = item_properties.c_id)
            WHERE
                item_properties.tool = '".TOOL_FORUM."' AND
                item_properties.ref = '".$forum_id."' AND
                forums.forum_id = '".$forum_id."' AND
                forums.c_id = ".$courseId."
            ";

    $result = Database::query($sql);
    $row = Database::fetch_array($result, 'ASSOC');
    $row['approval_direct_post'] = 0;
    // We can't anymore change this option, so it should always be activated.

    return $row;
}

/**
 * This function retrieves all the information of a given forumcategory id
 *
 * @param $cat_id integer that indicates the forum
 *
 * @return array returns if there are category or bool returns if there aren't category
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_forumcategory_information($cat_id)
{
    $table_categories = Database :: get_course_table(TABLE_FORUM_CATEGORY);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);

    $course_id = api_get_course_int_id();
    $sql = "SELECT *
            FROM ".$table_categories." forumcategories, ".$table_item_property." item_properties
            WHERE
                forumcategories.c_id = $course_id AND
                item_properties.c_id = $course_id AND
                item_properties.tool='".TOOL_FORUM_CATEGORY."' AND
                item_properties.ref='".Database::escape_string($cat_id)."' AND
                forumcategories.cat_id='".Database::escape_string($cat_id)."'";
    $result = Database::query($sql);
    $row = Database::fetch_array($result);

    return $row;
}

/**
 * This function counts the number of forums inside a given category
 *
 * @param int $cat_id the id of the forum category
 * @todo an additional parameter that takes the visibility into account. For instance $countinvisible=0 would return the number
 *      of visible forums, $countinvisible=1 would return the number of visible and invisible forums
 * @return int the number of forums inside the given category
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function count_number_of_forums_in_category($cat_id)
{
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $course_id = api_get_course_int_id();
    $sql = "SELECT count(*) AS number_of_forums
            FROM ".$table_forums."
            WHERE c_id = $course_id AND forum_category='".Database::escape_string($cat_id)."'";
    $result = Database::query($sql);
    $row = Database::fetch_array($result);

    return $row['number_of_forums'];
}

/**
 * This function update a thread
 *
 * @param array $values - The form Values
 * @return void HTML
 *
 */
function updateThread($values)
{
    $threadTable = Database :: get_course_table(TABLE_FORUM_THREAD);
    $courseId = api_get_course_int_id();

    $params = [
        'thread_title' => $values['thread_title'],
        'thread_sticky' => isset($values['thread_sticky']) ? $values['thread_sticky'] : null,
        'thread_title_qualify' => $values['calification_notebook_title'],
        'thread_qualify_max' => $values['numeric_calification'],
        'thread_weight' => $values['weight_calification'],
        'thread_peer_qualify' => $values['thread_peer_qualify'],
    ];
    $where = ['c_id = ? AND thread_id = ?' => [$courseId, $values['thread_id']]];
    Database::update($threadTable, $params, $where);

    if (api_is_course_admin() == true) {
        $option_chek = isset($values['thread_qualify_gradebook']) ? $values['thread_qualify_gradebook'] : false; // values 1 or 0
        if ($option_chek) {
            $id = $values['thread_id'];
            $titleGradebook = Security::remove_XSS(stripslashes($values['calification_notebook_title']));
            $valueCalification = isset($values['numeric_calification']) ? intval($values['numeric_calification']) : 0;
            $weightCalification = isset($values['weight_calification']) ? floatval($values['weight_calification']) : 0;
            $description = '';
            $sessionId = api_get_session_id();
            $courseId = api_get_course_id();

            $linkInfo = GradebookUtils::isResourceInCourseGradebook(
                $courseId,
                LINK_FORUM_THREAD,
                $id,
                $sessionId
            );
            $linkId = $linkInfo['id'];

            if (!$linkInfo) {
                GradebookUtils::add_resource_to_course_gradebook(
                    $values['category_id'],
                    $courseId,
                    LINK_FORUM_THREAD,
                    $id,
                    $titleGradebook,
                    $weightCalification,
                    $valueCalification,
                    $description,
                    1,
                    $sessionId
                );
            } else {
                $em = Database::getManager();
                $gradebookLink = $em->getRepository('ChamiloCoreBundle:GradebookLink')->find($linkId);
                $gradebookLink->setWeight($weightCalification);
                $em->persist($gradebookLink);
                $em->flush();
            }
        }
    }

    $message = get_lang('EditPostStored').'<br />';
    Display :: display_confirmation_message($message, false);
}

/**
 * This function stores a new thread. This is done through an entry in the forum_thread table AND
 * in the forum_post table because. The threads are also stored in the item_property table. (forum posts are not (yet))
 *
 * @param array $current_forum
 * @param array $values
 * @param array $courseInfo
 * @param bool $showMessage
 * @return int
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_thread($current_forum, $values, $courseInfo = array(), $showMessage = true)
{
    $courseInfo = empty($courseInfo) ? api_get_course_info() : $courseInfo;
    $_user = api_get_user_info();
    $course_id = $courseInfo['real_id'];
    $courseCode = $courseInfo['code'];

    $em = Database::getManager();
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);

    $gradebook = isset($_GET['gradebook']) ? Security::remove_XSS($_GET['gradebook']) : '';
    $upload_ok = 1;
    $has_attachment = false;

    if (!empty($_FILES['user_upload']['name'])) {
        $upload_ok = process_uploaded_file($_FILES['user_upload']);
        $has_attachment = true;
    }

    if (!$upload_ok) {
        if ($showMessage) {
            Display::addFlash(
                Display::return_message(get_lang('UplNoFileUploaded'), 'error', false)
            );
        }

        return null;
    }

    $post_date = new DateTime(api_get_utc_datetime(), new DateTimeZone('UTC'));

    if ($current_forum['approval_direct_post'] == '1' && !api_is_allowed_to_edit(null, true)) {
        $visible = 0; // The post has not been approved yet.
    } else {
        $visible = 1;
    }

    $clean_post_title = $values['post_title'];

    // We first store an entry in the forum_thread table because the thread_id is used in the forum_post table.

    $lastThread = new \Chamilo\CourseBundle\Entity\CForumThread();
    $lastThread
        ->setCId($course_id)
        ->setThreadTitle($clean_post_title)
        ->setForumId($values['forum_id'])
        ->setThreadPosterId($_user['user_id'])
        ->setThreadPosterName(isset($values['poster_name']) ? $values['poster_name'] : null)
        ->setThreadDate($post_date)
        ->setThreadSticky(isset($values['thread_sticky']) ? $values['thread_sticky'] : 0)
        ->setThreadTitleQualify(
            isset($values['calification_notebook_title']) ? $values['calification_notebook_title'] : null
        )
        ->setThreadQualifyMax(isset($values['numeric_calification']) ? (int) $values['numeric_calification'] : 0)
        ->setThreadWeight(isset($values['weight_calification']) ? (int) $values['weight_calification'] : 0)
        ->setThreadPeerQualify(isset($values['thread_peer_qualify']) ? (int) $values['thread_peer_qualify'] : 0)
        ->setSessionId(api_get_session_id())
        ->setLpItemId(isset($values['lp_item_id']) ? (int) $values['lp_item_id'] : 0)
        ->setThreadId(0)
        ->setLocked(0);

    $em->persist($lastThread);
    $em->flush();

    // Add option gradebook qualify.

    if (isset($values['thread_qualify_gradebook']) &&
        1 == $values['thread_qualify_gradebook']
    ) {
        // Add function gradebook.
        $resourcetype = 5;
        $resourceid = $lastThread->getIid();
        $resourcename = stripslashes($values['calification_notebook_title']);
        $maxqualify = $values['numeric_calification'];
        $weigthqualify = $values['weight_calification'];
        $resourcedescription = '';
        GradebookUtils::add_resource_to_course_gradebook(
            $values['category_id'],
            $courseCode,
            $resourcetype,
            $resourceid,
            $resourcename,
            $weigthqualify,
            $maxqualify,
            $resourcedescription,
            0,
            api_get_session_id()
        );
    }

    if ($lastThread->getIid()) {
        $lastThread->setThreadId($lastThread->getIid());

        $em->merge($lastThread);
        $em->flush();

        api_item_property_update(
            $courseInfo,
            TOOL_FORUM_THREAD,
            $lastThread->getIid(),
            'ForumThreadAdded',
            api_get_user_id(),
            api_get_group_id(),
            null,
            null,
            null,
            api_get_session_id()
        );

        // If the forum properties tell that the posts have to be approved
        // we have to put the whole thread invisible,
        // because otherwise the students will see the thread and not the post
        // in the thread.
        // We also have to change $visible because the post itself has to be
        // visible in this case (otherwise the teacher would have
        // to make the thread visible AND the post.
        // Default behaviour
        api_set_default_visibility(
            $lastThread->getIid(),
            TOOL_FORUM_THREAD,
            api_get_group_id(),
            $courseInfo
        );

        if ($visible == 0) {
            api_item_property_update(
                $courseInfo,
                TOOL_FORUM_THREAD,
                $lastThread->getIid(),
                'invisible',
                api_get_user_id(),
                api_get_group_id()

            );
            $visible = 1;
        }
    }

    // We now store the content in the table_post table.
    $lastPost = new CForumPost();
    $lastPost
        ->setCId($course_id)
        ->setPostTitle($clean_post_title)
        ->setPostText($values['post_text'])
        ->setThreadId($lastThread->getIid())
        ->setForumId($values['forum_id'])
        ->setPosterId($_user['user_id'])
        ->setPosterName(isset($values['poster_name']) ? $values['poster_name'] : null)
        ->setPostDate($post_date)
        ->setPostNotification(isset($values['post_notification']) ? (int) $values['post_notification'] : null)
        ->setPostParentId(null)
        ->setVisible($visible)
        ->setPostId(0)
        ->setStatus(CForumPost::STATUS_VALIDATED);

    if ($current_forum['moderated']) {
        $lastPost->setStatus(
            api_is_course_admin() ? CForumPost::STATUS_VALIDATED : CForumPost::STATUS_WAITING_MODERATION
        );
    }

    $em->persist($lastPost);
    $em->flush();

    $last_post_id = $lastPost->getIid();

    if ($last_post_id) {
        $lastPost->setPostId($last_post_id);
        $em->merge($lastPost);
        $em->flush();
    }

    // Update attached files
    if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {
        foreach ($_POST['file_ids'] as $key => $id) {
            editAttachedFile(
                array(
                    'comment' => $_POST['file_comments'][$key],
                    'post_id' => $last_post_id
                ),
                $id
            );
        }
    }

    // Now we have to update the thread table to fill the thread_last_post
    // field (so that we know when the thread has been updated for the last time).
    $sql = "UPDATE $table_threads
            SET thread_last_post = '".Database::escape_string($last_post_id)."'
            WHERE
                c_id = $course_id AND
                thread_id='".Database::escape_string($lastThread->getIid())."'";
    $result = Database::query($sql);
    $message = get_lang('NewThreadStored');
    // Storing the attachments if any.
    if ($has_attachment) {

        // Try to add an extension to the file if it hasn't one.
        $new_file_name = add_ext_on_mime(
            stripslashes($_FILES['user_upload']['name']),
            $_FILES['user_upload']['type']
        );

        if (!filter_extension($new_file_name)) {
            if ($showMessage) {
                Display:: display_error_message(
                    get_lang('UplUnableToSaveFileFilteredExtension')
                );
            }
        } else {
            if ($result) {
                add_forum_attachment_file(
                    isset($values['file_comment']) ? $values['file_comment'] : null,
                    $last_post_id
                );
            }
        }
    } else {
        $message .= '<br />';
    }

    if ($current_forum['approval_direct_post'] == '1' && !api_is_allowed_to_edit(null, true)) {
        $message .= get_lang('MessageHasToBeApproved').'<br />';
        $message .= get_lang('ReturnTo').' <a href="viewforum.php?'.api_get_cidreq().'&forum='.$values['forum_id'].'">'.
            get_lang('Forum').'</a><br />';
    } else {
        $message .= get_lang('ReturnTo').' <a href="viewforum.php?'.api_get_cidreq().'&forum='.$values['forum_id'].'">'.
            get_lang('Forum').'</a><br />';
        $message .= get_lang('ReturnTo').' <a href="viewthread.php?'.api_get_cidreq().'&forum='.$values['forum_id'].'&gradebook='.$gradebook.'&thread='.$lastThread->getIid().'">'.
            get_lang('Message').'</a>';
    }
    $reply_info['new_post_id'] = $last_post_id;
    $my_post_notification = isset($values['post_notification']) ? $values['post_notification'] : null;

    if ($my_post_notification == 1) {
        set_notification('thread', $lastThread->getIid(), true);
    }

    send_notification_mails($lastThread->getIid(), $reply_info);

    Session::erase('formelements');
    Session::erase('origin');
    Session::erase('breadcrumbs');
    Session::erase('addedresource');
    Session::erase('addedresourceid');

    if ($showMessage) {
        Display::addFlash(Display::return_message($message, 'success', false));
    }
    return $lastThread->getIid();
}

/**
 * This function displays the form that is used to UPDATE a Thread.
 * @param array $currentForum
 * @param array $forumSetting
 * @param array $formValues
 * @return void HMTL
 * @author José Loguercio <jose.loguercio@beeznest.com>
 * @version february 2016, chamilo 1.10.4
 */
function showUpdateThreadForm($currentForum, $forumSetting, $formValues = '')
{
    $myThread = isset($_GET['thread']) ? intval($_GET['thread']) : '';
    $myForum = isset($_GET['forum']) ? intval($_GET['forum']) : '';
    $myGradebook = isset($_GET['gradebook']) ? Security::remove_XSS($_GET['gradebook']) : '';
    $form = new FormValidator(
        'thread',
        'post',
        api_get_self() . '?' . http_build_query([
            'forum' => $myForum,
            'gradebook' => $myGradebook,
            'thread' => $myThread,
        ]) . '&' . api_get_cidreq()
    );

    $form->addElement('header', get_lang('EditThread'));
    $form->setConstants(array('forum' => '5'));
    $form->addElement('hidden', 'forum_id', $myForum);
    $form->addElement('hidden', 'thread_id', $myThread);
    $form->addElement('hidden', 'gradebook', $myGradebook);
    $form->addElement('text', 'thread_title', get_lang('Title'));
    $form->addElement('advanced_settings', 'advanced_params', get_lang('AdvancedParameters'));
    $form->addElement('html', '<div id="advanced_params_options" style="display:none">');

    if ((api_is_course_admin() || api_is_course_coach() || api_is_course_tutor()) && ($myThread)) {
        // Thread qualify
        if (Gradebook::is_active()) {
            //Loading gradebook select
            GradebookUtils::load_gradebook_select_in_tool($form);
            $form->addElement(
                'checkbox',
                'thread_qualify_gradebook',
                '',
                get_lang('QualifyThreadGradebook'),
                [
                    'id' => 'thread_qualify_gradebook'
                ]
            );
        } else {
            $form->addElement('hidden', 'thread_qualify_gradebook', false);
        }

        $form->addElement('html', '<div id="options_field" style="display:none">');
        $form->addElement('text', 'numeric_calification', get_lang('QualificationNumeric'));
        $form->applyFilter('numeric_calification', 'html_filter');
        $form->addElement('text', 'calification_notebook_title', get_lang('TitleColumnGradebook'));
        $form->applyFilter('calification_notebook_title', 'html_filter');
        $form->addElement(
            'text',
            'weight_calification',
            get_lang('QualifyWeight'),
            array('value' => '0.00', 'onfocus' => "javascript: this.select();")
        );
        $form->applyFilter('weight_calification', 'html_filter');
        $group = array();
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('Yes'), 1);
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('No'), 0);
        $form->addGroup(
            $group,
            '',
            [
                get_lang('ForumThreadPeerScoring'),
                get_lang('ForumThreadPeerScoringComment'),
            ]
        );
        $form->addElement('html', '</div>');
    }

    if ($forumSetting['allow_sticky'] && api_is_allowed_to_edit(null, true)) {
        $form->addElement('checkbox', 'thread_sticky', '', get_lang('StickyPost'));
    }

    $form->addElement('html', '</div>');

    if (!empty($formValues)) {
        $defaults['thread_qualify_gradebook'] = ($formValues['threadQualifyMax'] > 0 && empty($_POST)) ? 1 : 0 ;
        $defaults['thread_title'] = prepare4display($formValues['threadTitle']);
        $defaults['thread_sticky'] = strval(intval($formValues['threadSticky']));
        $defaults['thread_peer_qualify'] = intval($formValues['threadPeerQualify']);
        $defaults['numeric_calification'] = $formValues['threadQualifyMax'];
        $defaults['calification_notebook_title'] = $formValues['threadTitleQualify'];
        $defaults['weight_calification'] = $formValues['threadWeight'];
    } else {
        $defaults['thread_qualify_gradebook'] = 0;
        $defaults['numeric_calification'] = 0;
        $defaults['calification_notebook_title'] = '';
        $defaults['weight_calification'] = 0;
        $defaults['thread_peer_qualify'] = 0;
    }
    $form->setDefaults(isset($defaults) ? $defaults : null);

    $form->addButtonUpdate(get_lang('ModifyThread'), 'SubmitPost');

    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $values = $form->exportValues();
            if (isset($values['thread_qualify_gradebook']) &&
                $values['thread_qualify_gradebook'] == '1' &&
                empty($values['weight_calification'])
            ) {
                Display::display_error_message(
                    get_lang('YouMustAssignWeightOfQualification').'&nbsp;<a href="javascript:window.history.go(-1);">'.
                    get_lang('Back').'</a>',
                    false
                );
                return false;
            }
            Security::clear_token();
            return $values;
        }
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(array('sec_token' => $token));


        $form->display();
    }
}

/**
 * This function displays the form that is used to add a post. This can be a new thread or a reply.
 * @param array $current_forum
 * @param array $forum_setting
 * @param string $action is the parameter that determines if we are
 *  1. newthread: adding a new thread (both empty) => No I-frame
 *  2. replythread: Replying to a thread ($action = replythread) => I-frame with the complete thread (if enabled)
 *  3. replymessage: Replying to a message ($action =replymessage) => I-frame with the complete thread (if enabled) (I first thought to put and I-frame with the message only)
 *  4. quote: Quoting a message ($action= quotemessage) => I-frame with the complete thread (if enabled). The message will be in the reply. (I first thought not to put an I-frame here)
 * @return FormValidator
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function show_add_post_form($current_forum, $forum_setting, $action, $id = '', $form_values = '')
{
    $_user = api_get_user_info();
    $action = isset($action) ? Security::remove_XSS($action) : '';
    $myThread = isset($_GET['thread']) ? (int) $_GET['thread'] : '';
    $forumId = isset($_GET['forum']) ? (int) $_GET['forum'] : '';
    $my_post = isset($_GET['post']) ? (int) $_GET['post'] : '';
    $my_gradebook = isset($_GET['gradebook']) ? Security::remove_XSS($_GET['gradebook']) : '';

    $url = api_get_self() . '?' . http_build_query([
        'action' => $action,
        'forum' => $forumId,
        'gradebook' => $my_gradebook,
        'thread' => $myThread,
        'post' => $my_post
    ]) . '&' . api_get_cidreq();

    $form = new FormValidator(
        'thread',
        'post',
        $url
    );

    $form->setConstants(array('forum' => '5'));

    // Setting the form elements.
    $form->addElement('hidden', 'forum_id', $forumId);
    $form->addElement('hidden', 'thread_id', $myThread);
    $form->addElement('hidden', 'gradebook', $my_gradebook);
    $form->addElement('hidden', 'action', $action);

    // If anonymous posts are allowed we also display a form to allow the user to put his name or username in.
    if ($current_forum['allow_anonymous'] == 1 && !isset($_user['user_id'])) {
        $form->addElement('text', 'poster_name', get_lang('Name'));
        $form->applyFilter('poster_name', 'html_filter');
    }

    $form->addElement('text', 'post_title', get_lang('Title'));
    $form->addHtmlEditor(
        'post_text',
        get_lang('Text'),
        true,
        false,
        api_is_allowed_to_edit(null, true) ? array(
            'ToolbarSet' => 'Forum',
            'Width' => '100%',
            'Height' => '300',
        ) : array(
            'ToolbarSet' => 'ForumStudent',
            'Width' => '100%',
            'Height' => '300',
            'UserStatus' => 'student'
        )
    );

    $form->addRule('post_text', get_lang('ThisFieldIsRequired'), 'required');
    $iframe = null;
    $myThread = Security::remove_XSS($myThread);
    if ($forum_setting['show_thread_iframe_on_reply'] && $action != 'newthread' && !empty($myThread)) {
        $iframe = "<iframe style=\"border: 1px solid black\" src=\"iframe_thread.php?".api_get_cidreq()."&forum=".Security::remove_XSS($forumId)."&thread=".$myThread."#".Security::remove_XSS($my_post)."\" width=\"100%\"></iframe>";
    }
    if (!empty($iframe)) {
        $form->addElement('label', get_lang('Thread'), $iframe);
    }

    if ((api_is_course_admin() || api_is_course_coach() || api_is_course_tutor()) && !($myThread)) {

        $form->addElement('advanced_settings', 'advanced_params', get_lang('AdvancedParameters'));
        $form->addElement('html', '<div id="advanced_params_options" style="display:none">');

        // Thread qualify
        if (Gradebook::is_active()) {
            //Loading gradebook select
            GradebookUtils::load_gradebook_select_in_tool($form);
            $form->addElement(
                'checkbox',
                'thread_qualify_gradebook',
                '',
                get_lang('QualifyThreadGradebook'),
                'onclick="javascript:if(this.checked==true){document.getElementById(\'options_field\').style.display = \'block\';}else{document.getElementById(\'options_field\').style.display = \'none\';}"'
            );
        } else {
            $form->addElement('hidden', 'thread_qualify_gradebook', false);
        }

        $form->addElement('html', '<div id="options_field" style="display:none">');

        $form->addElement('text', 'numeric_calification', get_lang('QualificationNumeric'));
        $form->applyFilter('numeric_calification', 'html_filter');

        $form->addElement('text', 'calification_notebook_title', get_lang('TitleColumnGradebook'));
        $form->applyFilter('calification_notebook_title', 'html_filter');

        $form->addElement(
            'text',
            'weight_calification',
            get_lang('QualifyWeight'),
            array('value' => '0.00', 'onfocus' => "javascript: this.select();")
        );
        $form->applyFilter('weight_calification', 'html_filter');

        $group = array();
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('Yes'), 1);
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('No'), 0);
        $form->addGroup(
            $group,
            '',
            [
                get_lang('ForumThreadPeerScoring'),
                get_lang('ForumThreadPeerScoringComment'),
            ]
        );
        $form->addElement('html', '</div>');
        $form->addElement('html', '</div>');
    }

    if ($forum_setting['allow_sticky'] && api_is_allowed_to_edit(null, true) && $action == 'newthread') {
        $form->addElement('checkbox', 'thread_sticky', '', get_lang('StickyPost'));
    }

    if (in_array($action, ['quote', 'replymessage'])) {
        $form->addFile('user_upload[]', get_lang('Attachment'));
        $form->addButton(
            'add_attachment',
            get_lang('AddAttachment'),
            'paperclip',
            'default',
            'default',
            null,
            ['id' => 'reply-add-attachment']
        );
    } else {
        $form->addFile('user_upload', get_lang('Attachment'));
    }

    // Setting the class and text of the form title and submit button.
    if ($action == 'quote') {
        $form->addButtonCreate(get_lang('QuoteMessage'), 'SubmitPost');
    } elseif ($action == 'replythread') {
        $form->addButtonCreate(get_lang('ReplyToThread'), 'SubmitPost');
    } elseif ($action == 'replymessage') {
        $form->addButtonCreate(get_lang('ReplyToMessage'), 'SubmitPost');
    } else {
        $form->addButtonCreate(get_lang('CreateThread'), 'SubmitPost');
    }

    if (!empty($form_values)) {
        $defaults['post_title'] = prepare4display($form_values['post_title']);
        $defaults['post_text'] = prepare4display($form_values['post_text']);
        $defaults['post_notification'] = strval(intval($form_values['post_notification']));
        $defaults['thread_sticky'] = strval(intval($form_values['thread_sticky']));
        $defaults['thread_peer_qualify'] = intval($form_values['thread_peer_qualify']);
    } else {
        $defaults['thread_peer_qualify'] = 0;
    }

    // If we are quoting a message we have to retrieve the information of the post we are quoting so that
    // we can add this as default to the textarea.
    if (($action == 'quote' || $action == 'replymessage') && isset($my_post)) {
        // We also need to put the parent_id of the post in a hidden form when
        // we are quoting or replying to a message (<> reply to a thread !!!)
        $form->addHidden('post_parent_id', intval($my_post));

        // If we are replying or are quoting then we display a default title.
        $values = get_post_information($my_post);
        $posterInfo = api_get_user_info($values['poster_id']);
        $posterName = '';
        if ($posterInfo) {
            $posterName = $posterInfo['complete_name'];
        }
        $defaults['post_title'] = get_lang('ReplyShort').api_html_entity_decode($values['post_title'], ENT_QUOTES);
        // When we are quoting a message then we have to put that message into the wysiwyg editor.
        // Note: The style has to be hardcoded here because using class="quote" didn't work.
        if ($action == 'quote') {
            $defaults['post_text'] = '<div>&nbsp;</div>
                <div style="margin: 5px;">
                    <div style="font-size: 90%; font-style: italic;">'.
                        get_lang('Quoting').' '.$posterName.':</div>
                        <div style="color: #006600; font-size: 90%;  font-style: italic; background-color: #FAFAFA; border: #D1D7DC 1px solid; padding: 3px;">'.
                            prepare4display($values['post_text']).'
                        </div>
                    </div>
                <div>&nbsp;</div>
                <div>&nbsp;</div>
            ';
        }
    }

    $form->setDefaults(isset($defaults) ? $defaults : []);

    // The course admin can make a thread sticky (=appears with special icon and always on top).
    $form->addRule('post_title', get_lang('ThisFieldIsRequired'), 'required');
    if ($current_forum['allow_anonymous'] == 1 && !isset($_user['user_id'])) {
        $form->addRule('poster_name', get_lang('ThisFieldIsRequired'), 'required');
    }

    // Validation or display
    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $values = $form->exportValues();
            if (isset($values['thread_qualify_gradebook']) &&
                $values['thread_qualify_gradebook'] == '1' &&
                empty($values['weight_calification'])
            ) {
                Display::addFlash(
                    Display::return_message(
                    get_lang('YouMustAssignWeightOfQualification').'&nbsp;<a href="javascript:window.history.go(-1);">'.get_lang('Back').'</a>',
                    'error',
                    false
                    )
                );

                return false;
            }

            switch ($action) {
                case 'newthread':
                    $myThread = store_thread($current_forum, $values);
                    break;
                case 'quote':
                case 'replythread':
                case 'replymessage':
                    store_reply($current_forum, $values);

                    break;
            }

            $url = api_get_path(WEB_CODE_PATH).'forum/viewthread.php?'.api_get_cidreq().'&'.http_build_query(
                [
                    'forum' => $forumId,
                    'thread' => $myThread
                ]
            );

            Security::clear_token();
            header('Location: '.$url);
            exit;
        }
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(array('sec_token' => $token));

        // Delete from $_SESSION forum attachment from other posts
        // and keep only attachments for new post
        clearAttachedFiles(FORUM_NEW_POST);
        // Get forum attachment ajax table to add it to form
        $attachmentAjaxTable = getAttachmentsAjaxTable(0, $current_forum['forum_id']);
        $ajaxHtml = $attachmentAjaxTable;
        $form->addElement('html', $ajaxHtml);

        return $form;
    }
}

/**
 * @param array $threadInfo
 * @param integer $user_id
 * @param integer $thread_id
 * @param integer $thread_qualify
 * @param integer $qualify_time
 * @param integer $session_id
 * @return array optional
 * @author Isaac Flores <isaac.flores@dokeos.com>, U.N.A.S University
 * @version October 2008, dokeos  1.8.6
 */
function saveThreadScore(
    $threadInfo,
    $user_id,
    $thread_id,
    $thread_qualify = 0,
    $qualify_time,
    $session_id = 0
) {
    $table_threads_qualify = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY);
    $table_threads = Database::get_course_table(TABLE_FORUM_THREAD);

    $course_id = api_get_course_int_id();
    $session_id = intval($session_id);
    $currentUserId = api_get_user_id();

    if ($user_id == strval(intval($user_id)) &&
        $thread_id == strval(intval($thread_id)) &&
        $thread_qualify == strval(floatval($thread_qualify))
    ) {
        // Testing
        $sql = "SELECT thread_qualify_max FROM $table_threads
                WHERE c_id = $course_id AND thread_id=".$thread_id;
        $res_string = Database::query($sql);
        $row_string = Database::fetch_array($res_string);
        if ($thread_qualify <= $row_string[0]) {

            if ($threadInfo['thread_peer_qualify'] == 0) {
                $sql = "SELECT COUNT(*) FROM $table_threads_qualify
                        WHERE
                            c_id = $course_id AND
                            user_id = $user_id AND
                            thread_id = ".$thread_id;
            } else {
                $sql = "SELECT COUNT(*) FROM $table_threads_qualify
                        WHERE
                            c_id = $course_id AND
                            user_id = $user_id AND
                            qualify_user_id = $currentUserId AND
                            thread_id = ".$thread_id;
            }

            $result = Database::query($sql);
            $row = Database::fetch_array($result);

            if ($row[0] == 0) {
                $sql = "INSERT INTO $table_threads_qualify (c_id, user_id, thread_id,qualify,qualify_user_id,qualify_time,session_id)
                        VALUES (".$course_id.", '".$user_id."','".$thread_id."',".(float)$thread_qualify.", '".$currentUserId."','".$qualify_time."','".$session_id."')";
                Database::query($sql);

                $insertId = Database::insert_id();
                if ($insertId) {
                    $sql = "UPDATE $table_threads_qualify SET id = iid
                            WHERE iid = $insertId";
                    Database::query($sql);
                }

                return 'insert';
            } else {

                saveThreadScoreHistory(
                    '1',
                    $course_id,
                    $user_id,
                    $thread_id
                );


                // Update
                $sql = "UPDATE $table_threads_qualify
                        SET
                            qualify = '".$thread_qualify."',
                            qualify_time = '".$qualify_time."'
                        WHERE
                            c_id = $course_id AND
                            user_id=".$user_id." AND
                            thread_id=".$thread_id." AND
                            qualify_user_id = $currentUserId
                        ";
                Database::query($sql);

                return 'update';
            }

        } else {
            return null;
        }
    }
}

/**
 * This function shows qualify.
 * @param string $option contains the information of option to run
 * @param integer $user_id contains the information the current user id
 * @param integer $thread_id contains the information the current thread id
 * @return integer qualify
 * <code> $option=1 obtained the qualification of the current thread</code>
 * @author Isaac Flores <isaac.flores@dokeos.com>, U.N.A.S University
 * @version October 2008, dokeos  1.8.6
 */
function showQualify($option, $user_id, $thread_id)
{
    $table_threads_qualify = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY);
    $table_threads = Database::get_course_table(TABLE_FORUM_THREAD);

    $course_id = api_get_course_int_id();
    $user_id = intval($user_id);
    $thread_id = intval($thread_id);

    if (empty($user_id) || empty($thread_id)) {
        return false;
    }

    $sql = '';
    switch ($option) {
        case 1:
            $sql = "SELECT qualify FROM $table_threads_qualify
                    WHERE
                        c_id = $course_id AND
                        user_id=".$user_id." AND
                        thread_id=".$thread_id;
            break;
        case 2:
            $sql = "SELECT thread_qualify_max FROM $table_threads
                    WHERE c_id = $course_id AND thread_id=".$thread_id;
            break;
    }

    if (!empty($sql)) {
        $rs = Database::query($sql);
        $row = Database::fetch_array($rs);

        return $row[0];
    }

    return array();
}

/**
 * This function gets qualify historical.
 * @param integer $user_id contains the information the current user id
 * @param integer $thread_id contains the information the current thread id
 * @param boolean $opt contains the information of option to run
 * @return array
 * @author Christian Fasanando <christian.fasanando@dokeos.com>,
 * @author Isaac Flores <isaac.flores@dokeos.com>,
 * @version October 2008, dokeos  1.8.6
 */
function getThreadScoreHistory($user_id, $thread_id, $opt)
{
    $table_threads_qualify_log = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY_LOG);
    $course_id = api_get_course_int_id();

    if ($opt == 'false') {
        $sql = "SELECT * FROM ".$table_threads_qualify_log."
                WHERE
                    c_id = $course_id AND
                    thread_id='".Database::escape_string($thread_id)."' AND
                    user_id='".Database::escape_string($user_id)."'
                ORDER BY qualify_time";
    } else {
        $sql = "SELECT * FROM ".$table_threads_qualify_log."
                WHERE
                    c_id = $course_id AND
                    thread_id='".Database::escape_string($thread_id)."' AND
                    user_id='".Database::escape_string($user_id)."'
                ORDER BY qualify_time DESC";
    }
    $rs = Database::query($sql);
    $log = array();
    while ($row = Database::fetch_array($rs, 'ASSOC')) {
        $log[] = $row;
    }

    return $log;
}

/**
 * This function stores qualify historical.
 * @param boolean contains the information of option to run
 * @param string contains the information the current course id
 * @param integer contains the information the current forum id
 * @param integer contains the information the current user id
 * @param integer contains the information the current thread id
 * @param integer contains the information the current qualify
 * @param string $option
 * @param integer $course_id
 * @param integer $user_id
 * @param integer $thread_id
 * @return void
 * <code>$option=1 obtained the qualification of the current thread</code>
 * @author Isaac Flores <isaac.flores@dokeos.com>, U.N.A.S University
 * @version October 2008, dokeos  1.8.6
 */
function saveThreadScoreHistory(
    $option,
    $course_id,
    $user_id,
    $thread_id
) {
    $table_threads_qualify = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY);
    $table_threads_qualify_log = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY_LOG);

    $course_id = intval($course_id);
    $qualify_user_id = api_get_user_id();

    if ($user_id == strval(intval($user_id)) &&
        $thread_id == strval(intval($thread_id)) && $option == 1
    ) {

        // Extract information of thread_qualify.
        $sql = "SELECT qualify, qualify_time
                FROM $table_threads_qualify
                WHERE
                    c_id = $course_id AND
                    user_id = ".$user_id." AND
                    thread_id = ".$thread_id." AND
                    qualify_user_id = $qualify_user_id
                ";
        $rs = Database::query($sql);
        $row = Database::fetch_array($rs);

        // Insert thread_historical.
        $sql = "INSERT INTO $table_threads_qualify_log (c_id, user_id, thread_id, qualify, qualify_user_id,qualify_time,session_id)
                VALUES(".$course_id.", '".$user_id."','".$thread_id."',".(float) $row[0].", '".$qualify_user_id."','".$row[1]."','')";
        Database::query($sql);

        $insertId = Database::insert_id();
        if ($insertId) {
            $sql = "UPDATE $table_threads_qualify_log SET id = iid
                    WHERE iid = $insertId";
            Database::query($sql);
        }
    }
}

/**
 * This function shows current thread qualify .
 * @param integer $threadId
 * @param integer $sessionId
 * @param integer $userId
 *
 * @return array or null if is empty
 * @author Isaac Flores <isaac.flores@dokeos.com>, U.N.A.S University
 * @version December 2008, dokeos  1.8.6
 */
function current_qualify_of_thread($threadId, $sessionId, $userId)
{
    $table_threads_qualify = Database::get_course_table(TABLE_FORUM_THREAD_QUALIFY);

    $course_id = api_get_course_int_id();
    $currentUserId = api_get_user_id();
    $sessionId = intval($sessionId);
    $threadId = intval($threadId);

    $sql = "SELECT qualify FROM $table_threads_qualify
            WHERE
                c_id = $course_id AND
                thread_id = $threadId AND
                session_id = $sessionId AND
                qualify_user_id = $currentUserId AND
                user_id = $userId
            ";
    $res = Database::query($sql);
    $row = Database::fetch_array($res, 'ASSOC');

    return $row['qualify'];
}

/**
 * This function stores a reply in the forum_post table.
 * It also updates the forum_threads table (thread_replies +1 , thread_last_post, thread_date)
 * @param array $current_forum
 * @param array $values
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_reply($current_forum, $values)
{
    $_course = api_get_course_info();
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $post_date = api_get_utc_datetime();

    if ($current_forum['approval_direct_post'] == '1' &&
        !api_is_allowed_to_edit(null, true)
    ) {
        $visible = 0;
    } else {
        $visible = 1;
    }

    $upload_ok = 1;
    $return = array();

    if ($upload_ok) {
        // We first store an entry in the forum_post table.
        $new_post_id = Database::insert(
            $table_posts,
            [
                'c_id' => api_get_course_int_id(),
                'post_title' => $values['post_title'],
                'post_text' => isset($values['post_text']) ? ($values['post_text']) : null,
                'thread_id' => $values['thread_id'],
                'forum_id' => $values['forum_id'],
                'poster_id' => api_get_user_id(),
                'post_id' => 0,
                'post_date' => $post_date,
                'post_notification' => isset($values['post_notification']) ? $values['post_notification'] : null,
                'post_parent_id' => isset($values['post_parent_id']) ? $values['post_parent_id'] : null,
                'visible' => $visible,
            ]
        );

        if ($new_post_id) {
            $sql = "UPDATE $table_posts SET post_id = iid WHERE iid = $new_post_id";
            Database::query($sql);

            $values['new_post_id'] = $new_post_id;
            $message = get_lang('ReplyAdded');

            if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {
                foreach ($_POST['file_ids'] as $key => $id) {
                    editAttachedFile(
                        array(
                            'comment' => $_POST['file_comments'][$key],
                            'post_id' => $new_post_id,
                        ),
                        $id
                    );
                }
            }

            // Update the thread.
            updateThreadInfo($values['thread_id'], $new_post_id, $post_date);

            // Update the forum.
            api_item_property_update(
                $_course,
                TOOL_FORUM,
                $values['forum_id'],
                'NewMessageInForum',
                api_get_user_id()
            );

            // Insert post
            api_item_property_update(
                $_course,
                TOOL_FORUM_POST,
                $new_post_id,
                'NewPost',
                api_get_user_id()
            );

            if ($current_forum['approval_direct_post'] == '1' &&
                !api_is_allowed_to_edit(null, true)
            ) {
                $message .= '<br />'.get_lang('MessageHasToBeApproved').'<br />';
            }

            if ($current_forum['moderated'] &&
                !api_is_allowed_to_edit(null, true)
            ) {
                $message .= '<br />'.get_lang('MessageHasToBeApproved').'<br />';
            }

            // Setting the notification correctly.
            $my_post_notification = isset($values['post_notification']) ? $values['post_notification'] : null;
            if ($my_post_notification == 1) {
                set_notification('thread', $values['thread_id'], true);
            }

            send_notification_mails($values['thread_id'], $values);
            add_forum_attachment_file('', $new_post_id);
        }

        Session::erase('formelements');
        Session::erase('origin');
        Session::erase('breadcrumbs');
        Session::erase('addedresource');
        Session::erase('addedresourceid');

        Display::addFlash(Display::return_message($message, 'confirmation', false));
    } else {
        Display::addFlash(
            Display::return_message(get_lang('UplNoFileUploaded').' '.get_lang('UplSelectFileFirst'), 'error')
        );
    }

    return $return;
}

/**
 * This function displays the form that is used to edit a post. This can be a new thread or a reply.
 * @param array contains all the information about the current post
 * @param array contains all the information about the current thread
 * @param array contains all info about the current forum (to check if attachments are allowed)
 * @param array contains the default values to fill the form
 * @return void
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function show_edit_post_form(
    $forum_setting,
    $current_post,
    $current_thread,
    $current_forum,
    $form_values = '',
    $id_attach = 0
) {
    // Initialize the object.
    $form = new FormValidator(
        'edit_post',
        'post',
        api_get_self().'?'.api_get_cidreq().'&forum='.intval($_GET['forum']).'&thread='.intval($_GET['thread']).'&post='.intval($_GET['post'])
    );
    $form->addElement('header', get_lang('EditPost'));
    // Setting the form elements.
    $form->addElement('hidden', 'post_id', $current_post['post_id']);
    $form->addElement('hidden', 'thread_id', $current_thread['thread_id']);
    $form->addElement('hidden', 'id_attach', $id_attach);

    if (empty($current_post['post_parent_id'])) {
        $form->addElement('hidden', 'is_first_post_of_thread', '1');
    }

    $form->addElement('text', 'post_title', get_lang('Title'));
    $form->applyFilter('post_title', 'html_filter');
    $form->addElement(
        'html_editor',
        'post_text',
        get_lang('Text'),
        null,
        api_is_allowed_to_edit(null, true) ? array(
            'ToolbarSet' => 'Forum',
            'Width' => '100%',
            'Height' => '400',
        ) : array(
            'ToolbarSet' => 'ForumStudent',
            'Width' => '100%',
            'Height' => '400',
            'UserStatus' => 'student',
        )
    );
    $form->addRule('post_text', get_lang('ThisFieldIsRequired'), 'required');

    $form->addButtonAdvancedSettings('advanced_params');
    $form->addElement('html', '<div id="advanced_params_options" style="display:none">');

    if (!isset($_GET['edit'])) {
        if (Gradebook::is_active()) {
            $form->addElement('label', '<strong>'.get_lang('AlterQualifyThread').'</strong>');
            $form->addElement('checkbox', 'thread_qualify_gradebook', '', get_lang('QualifyThreadGradebook'), 'onclick="javascript: if(this.checked){document.getElementById(\'options_field\').style.display = \'block\';}else{document.getElementById(\'options_field\').style.display = \'none\';}"');

            $link_info = GradebookUtils::isResourceInCourseGradebook(
                api_get_course_id(),
                5,
                $_GET['thread'],
                api_get_session_id()
            );
            if (!empty($link_info)) {
                $defaults['thread_qualify_gradebook'] = true;
                $defaults['category_id'] = $link_info['category_id'];
            } else {
                $defaults['thread_qualify_gradebook'] = false;
                $defaults['category_id'] = '';
            }
        } else {
            $form->addElement('hidden', 'thread_qualify_gradebook', false);
            $defaults['thread_qualify_gradebook'] = false;
        }

        if (!empty($defaults['thread_qualify_gradebook'])) {
            $form->addElement('html', '<div id="options_field" style="display:block">');
        } else {
            $form->addElement('html', '<div id="options_field" style="display:none">');
        }

        // Loading gradebook select
        GradebookUtils::load_gradebook_select_in_tool($form);

        $form->addElement(
            'text',
            'numeric_calification',
            get_lang('QualificationNumeric'),
            array(
                'value' => $current_thread['thread_qualify_max'],
                'style' => 'width:40px',
            )
        );
        $form->applyFilter('numeric_calification', 'html_filter');

        $form->addElement(
            'text',
            'calification_notebook_title',
            get_lang('TitleColumnGradebook'),
            array('value' => $current_thread['thread_title_qualify'])
        );
        $form->applyFilter('calification_notebook_title', 'html_filter');

        $form->addElement(
            'text',
            'weight_calification',
            array(get_lang('QualifyWeight'), null, ''),
            array(
                'value' => $current_thread['thread_weight'],
                'style' => 'width:40px',
            )
        );
        $form->applyFilter('weight_calification', 'html_filter');

        $group = array();
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('Yes'), 1);
        $group[] = $form->createElement('radio', 'thread_peer_qualify', null, get_lang('No'), 0);
        $form->addGroup(
            $group,
            '',
            [
                get_lang('ForumThreadPeerScoring'),
                get_lang('ForumThreadPeerScoringComment'),
            ]
        );

        $form->addElement('html', '</div>');
    }

    if ($current_forum['moderated']) {
        $group = array();
        $group[] = $form->createElement('radio', 'status', null, get_lang('Validated'), 1);
        $group[] = $form->createElement('radio', 'status', null, get_lang('WaitingModeration'), 2);
        $group[] = $form->createElement('radio', 'status', null, get_lang('Rejected'), 3);
        $form->addGroup($group, 'status', get_lang('Status'));
    }

    $defaults['status']['status'] = isset($current_post['status']) && !empty($current_post['status']) ? $current_post['status'] : 2;

    if ($forum_setting['allow_post_notification']) {
        $form->addElement('checkbox', 'post_notification', '', get_lang('NotifyByEmail').' ('.$current_post['email'].')');
    }

    if ($forum_setting['allow_sticky'] &&
        api_is_allowed_to_edit(null, true) &&
        empty($current_post['post_parent_id'])
    ) {
        // The sticky checkbox only appears when it is the first post of a thread.
        $form->addElement('checkbox', 'thread_sticky', '', get_lang('StickyPost'));
        if ($current_thread['thread_sticky'] == 1) {
            $defaults['thread_sticky'] = true;
        }
    }

    $form->addElement('html', '</div>');

    $form->addFile('user_upload[]', get_lang('Attachment'));
    $form->addButton(
        'add_attachment',
        get_lang('AddAttachment'),
        'paperclip',
        'default',
        'default',
        null,
        ['id' => 'reply-add-attachment']
    );

    $form->addButtonUpdate(get_lang('ModifyThread'), 'SubmitPost');

    // Setting the default values for the form elements.
    $defaults['post_title'] = $current_post['post_title'];
    $defaults['post_text'] = $current_post['post_text'];

    if ($current_post['post_notification'] == 1) {
        $defaults['post_notification'] = true;
    }

    if (!empty($form_values)) {
        $defaults['post_notification'] = Security::remove_XSS($form_values['post_notification']);
        $defaults['thread_sticky'] = Security::remove_XSS($form_values['thread_sticky']);
    }

    $defaults['thread_peer_qualify'] = intval($current_thread['thread_peer_qualify']);

    $form->setDefaults($defaults);

    // The course admin can make a thread sticky (=appears with special icon and always on top).

    $form->addRule('post_title', get_lang('ThisFieldIsRequired'), 'required');

    // Validation or display
    if ($form->validate()) {
        $values = $form->exportValues();

        if (isset($values['thread_qualify_gradebook']) && $values['thread_qualify_gradebook'] == '1' &&
            empty($values['weight_calification'])
        ) {
            Display::display_error_message(get_lang('YouMustAssignWeightOfQualification').'&nbsp;<a href="javascript:window.history.go(-1);">'.get_lang('Back').'</a>', false);
            return false;
        }
        return $values;
    } else {
        // Delete from $_SESSION forum attachment from other posts
        clearAttachedFiles($current_post['post_id']);
        // Get forum attachment ajax table to add it to form
        $fileData = getAttachmentsAjaxTable($current_post['post_id'], $current_forum['forum_id']);
        $form->addElement('html', $fileData);
        $form->display();
    }
}

/**
 * This function stores the edit of a post in the forum_post table.
 *
 * @param array
 * @return void HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_edit_post($forumInfo, $values)
{
    $threadTable = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $gradebook = Security::remove_XSS($_GET['gradebook']);
    $course_id = api_get_course_int_id();

    //check if this post is the first of the thread
    // First we check if the change affects the thread and if so we commit
    // the changes (sticky and post_title=thread_title are relevant).

    $posts = getPosts($forumInfo, $values['thread_id']);
    $first_post = null;
    if (!empty($posts) && count($posts) > 0 && isset($posts[0])) {
        $first_post = $posts[0];
    }

    if (!empty($first_post) && $first_post['post_id'] == $values['post_id']) {
        $params = [
            'thread_title' => $values['post_title'],
            'thread_sticky' => isset($values['thread_sticky']) ? $values['thread_sticky'] : null,
            'thread_title_qualify' => $values['calification_notebook_title'],
            'thread_qualify_max' => $values['numeric_calification'],
            'thread_weight' => $values['weight_calification'],
            'thread_peer_qualify' => $values['thread_peer_qualify'],
        ];
        $where = ['c_id = ? AND thread_id = ?' => [$course_id, $values['thread_id']]];

        Database::update($threadTable, $params, $where);
    }

    $status = '';
    if ($forumInfo['moderated']) {
        $status = $values['status']['status'];
    }

    // Update the post_title and the post_text.
    $params = [
        'status' => $status,
        'post_title' => $values['post_title'],
        'post_text' => $values['post_text'],
        'post_notification' => isset($values['post_notification']) ? $values['post_notification'] : '',
    ];
    $where = ['c_id = ? AND post_id = ?' => [$course_id, $values['post_id']]];

    Database::update($table_posts, $params, $where);

    // Update attached files
    if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {
        foreach ($_POST['file_ids'] as $key => $id) {
            editAttachedFile(array('comment' => $_POST['file_comments'][$key], 'post_id' => $values['post_id']), $id);
        }
    }

    if (!empty($values['remove_attach'])) {
        delete_attachment($values['post_id']);
    }

    if (empty($values['id_attach'])) {
        add_forum_attachment_file(
            isset($values['file_comment']) ? $values['file_comment'] : null,
            $values['post_id']
        );
    } else {
        edit_forum_attachment_file(
            isset($values['file_comment']) ? $values['file_comment'] : null,
            $values['post_id'],
            $values['id_attach']
        );
    }

    $message = get_lang('EditPostStored').'<br />';
    $message .= get_lang('ReturnTo').' <a href="viewforum.php?'.api_get_cidreq().'&forum='.intval($_GET['forum']).'&">'.get_lang('Forum').'</a><br />';
    $message .= get_lang('ReturnTo').' <a href="viewthread.php?'.api_get_cidreq().'&forum='.intval($_GET['forum']).'&gradebook='.$gradebook.'&thread='.$values['thread_id'].'&post='.Security::remove_XSS($_GET['post']).'">'.get_lang('Message').'</a>';

    Session::erase('formelements');
    Session::erase('origin');
    Session::erase('breadcrumbs');
    Session::erase('addedresource');
    Session::erase('addedresourceid');

    Display :: display_confirmation_message($message, false);
}

/**
 * This function displays the firstname and lastname of the user as a link to the user tool.
 *
 * @param string names
 * @ in_title : title tootip
 * @return string HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function display_user_link($user_id, $name, $origin = '', $in_title = '')
{
    if ($user_id != 0) {
        $userInfo = api_get_user_info($user_id);

        return '<a href="'.$userInfo['profile_url'].'">'.Security::remove_XSS($userInfo['complete_name']).'</a>';
    } else {
        return $name.' ('.get_lang('Anonymous').')';
    }
}

/**
 * This function displays the user image from the profile, with a link to the user's details.
 * @param   int     User's database ID
 * @param   string  User's name
 * @param   string  the origin where the forum is called (example : learnpath)
 * @return  string  An HTML with the anchor and the image of the user
 * @author Julio Montoya <gugli100@gmail.com>
 */
function display_user_image($user_id, $name, $origin = '')
{
    $userInfo = api_get_user_info($user_id);

    $link = '<a href="'.(!empty($origin) ? '#' : $userInfo['profile_url']).'" '.(!empty($origin) ? 'target="_self"' : '').'>';

    if ($user_id != 0) {
        return $link.'<img src="'.$userInfo['avatar'].'"  alt="'.$name.'"  title="'.$name.'" /></a>';
    } else {
        return $link.Display::return_icon('unknown.jpg', $name).'</a>';
    }
}

/**
 * The thread view counter gets increased every time someone looks at the thread
 *
 * @param int
 * @return void
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function increase_thread_view($thread_id)
{
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $course_id = api_get_course_int_id();

    $sql = "UPDATE $table_threads SET thread_views=thread_views+1
            WHERE c_id = $course_id AND  thread_id='".Database::escape_string($thread_id)."'"; // This needs to be cleaned first.
    Database::query($sql);
}

/**
 * The relies counter gets increased every time somebody replies to the thread
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 * @param string $last_post_id
 * @param string $post_date
 */
function updateThreadInfo($thread_id, $last_post_id, $post_date)
{
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $course_id = api_get_course_int_id();
    $sql = "UPDATE $table_threads SET thread_replies=thread_replies+1,
            thread_last_post='".Database::escape_string($last_post_id)."',
            thread_date='".Database::escape_string($post_date)."'
            WHERE c_id = $course_id AND  thread_id='".Database::escape_string($thread_id)."'"; // this needs to be cleaned first
    Database::query($sql);
}

/**
 * This function is called when the user is not allowed in this forum/thread/...
 * @return bool display message of "not allowed"
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function forum_not_allowed_here()
{
    Display :: display_error_message(get_lang('NotAllowedHere'));
    Display :: display_footer();

    return false;
}

/**
 * This function is used to find all the information about what's new in the forum tool
 * @return void
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_whats_new()
{
    $_user = api_get_user_info();
    $_course = api_get_course_info();

    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $tracking_last_tool_access = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LASTACCESS);

    // Note: This has to be replaced by the tool constant later. But temporarily bb_forum is used since this is the only thing that is in the tracking currently.
    //$tool = TOOL_FORUM;
    $tool = TOOL_FORUM; //
    // to do: Remove this. For testing purposes only.
    //Session::erase('last_forum_access');
    //Session::erase('whatsnew_post_info');

    $course_id = api_get_course_int_id();
    $lastForumAccess = Session::read('last_forum_access');

    if (!$lastForumAccess) {
        $sql = "SELECT * FROM ".$tracking_last_tool_access."
                WHERE
                    access_user_id='".Database::escape_string($_user['user_id'])."' AND
                    c_id ='".$course_id."' AND
                    access_tool='".Database::escape_string($tool)."'";
        $result = Database::query($sql);
        $row = Database::fetch_array($result);
        $_SESSION['last_forum_access'] = $row['access_date'];
    }

    $whatsNew = Session::read('whatsnew_post_info');

    if (!$whatsNew) {
        if ($lastForumAccess != '') {
            $whatsnew_post_info = array();
            $sql = "SELECT * FROM $table_posts
                    WHERE
                        c_id = $course_id AND
                        post_date >'".Database::escape_string($_SESSION['last_forum_access'])."'"; // note: check the performance of this query.
            $result = Database::query($sql);
            while ($row = Database::fetch_array($result)) {
                $whatsnew_post_info[$row['forum_id']][$row['thread_id']][$row['post_id']] = $row['post_date'];
            }
            $_SESSION['whatsnew_post_info'] = $whatsnew_post_info;
        }
    }
}

/**
 * This function approves a post = change
 *
 * @param int $post_id the id of the post that will be deleted
 * @param string $action make the post visible or invisible
 * @return string language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function approve_post($post_id, $action)
{
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $course_id = api_get_course_int_id();

    if ($action == 'invisible') {
        $visibility_value = 0;
    }

    if ($action == 'visible') {
        $visibility_value = 1;
        handle_mail_cue('post', $post_id);
    }

    $sql = "UPDATE $table_posts SET
            visible='".Database::escape_string($visibility_value)."'
            WHERE c_id = $course_id AND post_id='".Database::escape_string($post_id)."'";
    $return = Database::query($sql);

    if ($return) {
        return 'PostVisibilityChanged';
    }
}

/**
 * This function retrieves all the unapproved messages for a given forum
 * This is needed to display the icon that there are unapproved messages in that thread (only the courseadmin can see this)
 *
 * @param $forum_id the forum where we want to know the unapproved messages of
 * @return array returns
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function get_unaproved_messages($forum_id)
{
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $course_id = api_get_course_int_id();

    $return_array = array();
    $sql = "SELECT DISTINCT thread_id FROM $table_posts
            WHERE c_id = $course_id AND forum_id='".Database::escape_string($forum_id)."' AND visible='0'";
    $result = Database::query($sql);
    while ($row = Database::fetch_array($result)) {
        $return_array[] = $row['thread_id'];
    }

    return $return_array;
}

/**
 * This function sends the notification mails to everybody who stated that they wanted to be informed when a new post
 * was added to a given thread.
 *
 * @param array reply information
 * @return void
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function send_notification_mails($thread_id, $reply_info)
{
    $table = Database::get_course_table(TABLE_FORUM_MAIL_QUEUE);

    // First we need to check if
    // 1. the forum category is visible
    // 2. the forum is visible
    // 3. the thread is visible
    // 4. the reply is visible (=when there is)
    $current_thread = get_thread_information($thread_id);
    $current_forum = get_forum_information($current_thread['forum_id']);

    $current_forum_category = null;
    if (isset($current_forum['forum_category'])) {
        $current_forum_category = get_forumcategory_information(
            $current_forum['forum_category']
        );
    }

    if ($current_thread['visibility'] == '1' &&
        $current_forum['visibility'] == '1' &&
        ($current_forum_category && $current_forum_category['visibility'] == '1') &&
        $current_forum['approval_direct_post'] != '1'
    ) {
        $send_mails = true;
    } else {
        $send_mails = false;
    }

    // The forum category, the forum, the thread and the reply are visible to the user
    if ($send_mails) {
        if (isset($current_thread['forum_id'])) {
            send_notifications($current_thread['forum_id'], $thread_id);
        }
    } else {
        $table_notification = Database::get_course_table(TABLE_FORUM_NOTIFICATION);
        if (isset($current_forum['forum_id'])) {
            $sql = "SELECT * FROM $table_notification
                    WHERE
                        c_id = ".api_get_course_int_id()." AND
                        (
                            forum_id = '".intval($current_forum['forum_id'])."' OR
                            thread_id = '".intval($thread_id)."'
                        ) ";

            $result = Database::query($sql);
            $user_id = api_get_user_id();
            while ($row = Database::fetch_array($result)) {
                $sql = "INSERT INTO $table (c_id, thread_id, post_id, user_id)
                        VALUES (".api_get_course_int_id().", '".intval($thread_id)."', '".intval($reply_info['new_post_id'])."', '$user_id' )";
                Database::query($sql);
            }
        }
    }
}

/**
 * This function is called whenever something is made visible because there might
 * be new posts and the user might have indicated that (s)he wanted to be
 * informed about the new posts by mail.
 *
 * @param string  Content type (post, thread, forum, forum_category)
 * @param int     Item DB ID
 * @param string $content
 * @param integer $id
 * @return string language variable
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function handle_mail_cue($content, $id)
{
    $table_mailcue = Database :: get_course_table(TABLE_FORUM_MAIL_QUEUE);
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $table_users = Database :: get_main_table(TABLE_MAIN_USER);

    $course_id = api_get_course_int_id();

    /* If the post is made visible we only have to send mails to the people
     who indicated that they wanted to be informed for that thread.*/
    if ($content == 'post') {
        // Getting the information about the post (need the thread_id).
        $post_info = get_post_information($id);
        $thread_id = intval($post_info['thread_id']);

        // Sending the mail to all the users that wanted to be informed for replies on this thread.
        $sql = "SELECT users.firstname, users.lastname, users.user_id, users.email
                FROM $table_mailcue mailcue, $table_posts posts, $table_users users
                WHERE
                    posts.c_id = $course_id AND
                    mailcue.c_id = $course_id AND
                    posts.thread_id='$thread_id'
                    AND posts.post_notification='1'
                    AND mailcue.thread_id='$thread_id'
                    AND users.user_id=posts.poster_id
                    AND users.active=1
                GROUP BY users.email";

        $result = Database::query($sql);
        while ($row = Database::fetch_array($result)) {
            send_mail($row, get_thread_information($post_info['thread_id']));
        }
    } elseif ($content == 'thread') {
        // Sending the mail to all the users that wanted to be informed for replies on this thread.
        $sql = "SELECT users.firstname, users.lastname, users.user_id, users.email
                FROM $table_mailcue mailcue, $table_posts posts, $table_users users
                WHERE
                    posts.c_id = $course_id AND
                    mailcue.c_id = $course_id AND
                    posts.thread_id = ".intval($id)."
                    AND posts.post_notification='1'
                    AND mailcue.thread_id = ".intval($id)."
                    AND users.user_id=posts.poster_id
                    AND users.active=1
                GROUP BY users.email";
        $result = Database::query($sql);
        while ($row = Database::fetch_array($result)) {
            send_mail($row, get_thread_information($id));
        }

        // Deleting the relevant entries from the mailcue.
        $sql = "DELETE FROM $table_mailcue
                WHERE c_id = $course_id AND thread_id='".Database::escape_string($id)."'";
        Database::query($sql);
    } elseif ($content == 'forum') {
        $sql = "SELECT thread_id FROM $table_threads
                WHERE c_id = $course_id AND forum_id='".Database::escape_string($id)."'";
        $result = Database::query($sql);
        while ($row = Database::fetch_array($result)) {
            handle_mail_cue('thread', $row['thread_id']);
        }
    } elseif ($content == 'forum_category') {
        $sql = "SELECT forum_id FROM $table_forums
                WHERE c_id = $course_id AND forum_category ='".Database::escape_string($id)."'";
        $result = Database::query($sql);
        while ($row = Database::fetch_array($result)) {
            handle_mail_cue('forum', $row['forum_id']);
        }
    } else {
        return get_lang('Error');
    }
}

/**
 * This function sends the mails for the mail notification
 *
 * @param array
 * @param array
 * @return void
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function send_mail($user_info = array(), $thread_information = array())
{
    $_course = api_get_course_info();
    $user_id = api_get_user_id();
    $subject = get_lang('NewForumPost').' - '.$_course['official_code'];
    if (isset($thread_information) && is_array($thread_information)) {
        $thread_link = api_get_path(WEB_CODE_PATH).'forum/viewthread.php?'.api_get_cidreq().'&forum='.$thread_information['forum_id'].'&thread='.$thread_information['thread_id'];
    }
    $email_body = get_lang('Dear').' '.api_get_person_name($user_info['firstname'], $user_info['lastname'], null, PERSON_NAME_EMAIL_ADDRESS).", <br />\n\r";
    $email_body .= get_lang('NewForumPost')."\n";
    $email_body .= get_lang('Course').': '.$_course['name'].' - ['.$_course['official_code']."] - <br />\n";
    $email_body .= get_lang('YouWantedToStayInformed')."<br />\n";
    $email_body .= get_lang('ThreadCanBeFoundHere')." : <br /><a href=\"".$thread_link."\">".$thread_link."</a>\n";

    if ($user_info['user_id'] <> $user_id) {
        MessageManager::send_message($user_info['user_id'], $subject, $email_body, [], [], null, null, null, null, $user_id);
    }
}

/**
 * This function displays the form for moving a thread to a different (already existing) forum
 * @return void HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function move_thread_form()
{
    $gradebook = Security::remove_XSS($_GET['gradebook']);
    $form = new FormValidator(
        'movepost',
        'post',
        api_get_self().'?forum='.intval($_GET['forum']).'&gradebook='.$gradebook.'&thread='.intval($_GET['thread']).'&action='.Security::remove_XSS($_GET['action']).'&'.api_get_cidreq()
    );
    // The header for the form
    $form->addElement('header', get_lang('MoveThread'));
    // Invisible form: the thread_id
    $form->addElement('hidden', 'thread_id', intval($_GET['thread']));
    // the fora
    $forum_categories = get_forum_categories();
    $forums = get_forums();

    $htmlcontent = '<div class="row">
        <div class="label">
            <span class="form_required">*</span>'.get_lang('MoveTo').'
        </div>
        <div class="formw">';
    $htmlcontent .= '<select name="forum">';
    foreach ($forum_categories as $key => $category) {
        $htmlcontent .= '<optgroup label="'.$category['cat_title'].'">';
        foreach ($forums as $key => $forum) {
            if (isset($forum['forum_category'])) {
                if ($forum['forum_category'] == $category['cat_id']) {
                    $htmlcontent .= '<option value="'.$forum['forum_id'].'">'.$forum['forum_title'].'</option>';
                }
            }
        }
        $htmlcontent .= '</optgroup>';
    }
    $htmlcontent .= "</select>";
    $htmlcontent .= '   </div>
                    </div>';

    $form->addElement('html', $htmlcontent);

    // The OK button
    $form->addButtonSave(get_lang('MoveThread'), 'SubmitForum');

    // Validation or display
    if ($form->validate()) {
        $values = $form->exportValues();
        if (isset($_POST['forum'])) {
            store_move_thread($values);
        }
    } else {
        $form->display();
    }
}

/**
 * This function displays the form for moving a post message to a different (already existing) or a new thread.
 * @return void HTML
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function move_post_form()
{
    $gradebook = Security::remove_XSS($_GET['gradebook']);
    // initiate the object
    $form = new FormValidator('movepost', 'post', api_get_self().'?'.api_get_cidreq().'&forum='.Security::remove_XSS($_GET['forum']).'&thread='.Security::remove_XSS($_GET['thread']).'&gradebook='.$gradebook.'&post='.Security::remove_XSS($_GET['post']).'&action='.Security::remove_XSS($_GET['action']).'&post='.Security::remove_XSS($_GET['post']));
    // The header for the form
    $form->addElement('header', '', get_lang('MovePost'));

    // Invisible form: the post_id
    $form->addElement('hidden', 'post_id', intval($_GET['post']));

    // Dropdown list: Threads of this forum
    $threads = get_threads($_GET['forum']);
    //my_print_r($threads);
    $threads_list[0] = get_lang('ANewThread');
    foreach ($threads as $key => $value) {
        $threads_list[$value['thread_id']] = $value['thread_title'];
    }
    $form->addElement('select', 'thread', get_lang('MoveToThread'), $threads_list);
    $form->applyFilter('thread', 'html_filter');

    // The OK button
    $form->addButtonSave(get_lang('MovePost'), 'submit');

    // Setting the rules
    $form->addRule('thread', get_lang('ThisFieldIsRequired'), 'required');

    // Validation or display
    if ($form->validate()) {
        $values = $form->exportValues();
        store_move_post($values);
    } else {
        $form->display();
    }
}

/**
 *
 * @param array
 * @return string HTML language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_move_post($values)
{
    $_course = api_get_course_info();
    $course_id = api_get_course_int_id();

    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);

    if ($values['thread'] == '0') {
        $current_post = get_post_information($values['post_id']);

        // Storing a new thread.
        $params = [
            'c_id' => $course_id,
            'thread_title' => $current_post['post_title'],
            'forum_id' => $current_post['forum_id'],
            'thread_poster_id' => $current_post['poster_id'],
            'thread_poster_name' => $current_post['poster_name'],
            'thread_last_post' => $values['post_id'],
            'thread_date' => $current_post['post_date'],
        ];

        $new_thread_id = Database::insert($table_threads, $params);

        api_item_property_update(
            $_course,
            TOOL_FORUM_THREAD,
            $new_thread_id,
            'visible',
            $current_post['poster_id']
        );

        // Moving the post to the newly created thread.
        $sql = "UPDATE $table_posts SET thread_id='".intval($new_thread_id)."', post_parent_id = NULL
                WHERE c_id = $course_id AND post_id='".intval($values['post_id'])."'";
        Database::query($sql);

        // Resetting the parent_id of the thread to 0 for all those who had this moved post as parent.
        $sql = "UPDATE $table_posts SET post_parent_id = NULL
                WHERE c_id = $course_id AND post_parent_id='".intval($values['post_id'])."'";
        Database::query($sql);

        // Updating updating the number of threads in the forum.
        $sql = "UPDATE $table_forums SET forum_threads=forum_threads+1
                WHERE c_id = $course_id AND forum_id='".intval($current_post['forum_id'])."'";
        Database::query($sql);

        // Resetting the last post of the old thread and decreasing the number of replies and the thread.
        $sql = "SELECT * FROM $table_posts
                WHERE c_id = $course_id AND thread_id='".intval($current_post['thread_id'])."'
                ORDER BY post_id DESC";
        $result = Database::query($sql);
        $row = Database::fetch_array($result);
        $sql = "UPDATE $table_threads SET
                    thread_last_post='".$row['post_id']."',
                    thread_replies=thread_replies-1
                WHERE
                    c_id = $course_id AND
                    thread_id='".intval($current_post['thread_id'])."'";
        Database::query($sql);
    } else {
        // Moving to the chosen thread.

        $sql = "SELECT thread_id FROM ".$table_posts."
                WHERE c_id = $course_id AND post_id = '".$values['post_id']."' ";
        $result = Database::query($sql);
        $row = Database::fetch_array($result);

        $original_thread_id = $row['thread_id'];

        $sql = "SELECT thread_last_post FROM ".$table_threads."
                WHERE c_id = $course_id AND thread_id = '".$original_thread_id."' ";

        $result = Database::query($sql);
        $row = Database::fetch_array($result);
        $thread_is_last_post = $row['thread_last_post'];
        // If is this thread, update the thread_last_post with the last one.

        if ($thread_is_last_post == $values['post_id']) {
            $sql = "SELECT post_id FROM ".$table_posts."
                    WHERE c_id = $course_id AND thread_id = '".$original_thread_id."' AND post_id <> '".$values['post_id']."'
                    ORDER BY post_date DESC LIMIT 1";
            $result = Database::query($sql);

            $row = Database::fetch_array($result);
            $thread_new_last_post = $row['post_id'];

            $sql = "UPDATE ".$table_threads." SET thread_last_post = '".$thread_new_last_post."'
                    WHERE c_id = $course_id AND thread_id = '".$original_thread_id."' ";
            Database::query($sql);
        }

        $sql = "UPDATE $table_threads SET thread_replies=thread_replies-1
                WHERE c_id = $course_id AND thread_id='".$original_thread_id."'";
        Database::query($sql);

        // moving to the chosen thread
        $sql = "UPDATE $table_posts SET thread_id='".intval($_POST['thread'])."', post_parent_id = NULL
                WHERE c_id = $course_id AND post_id='".intval($values['post_id'])."'";
        Database::query($sql);

        // resetting the parent_id of the thread to 0 for all those who had this moved post as parent
        $sql = "UPDATE $table_posts SET post_parent_id = NULL
                WHERE c_id = $course_id AND post_parent_id='".intval($values['post_id'])."'";
        Database::query($sql);

        $sql = "UPDATE $table_threads SET thread_replies=thread_replies+1
                WHERE c_id = $course_id AND thread_id='".intval($_POST['thread'])."'";
        Database::query($sql);
    }

    return get_lang('ThreadMoved');
}

/**
 *
 * @param array
 * @return string HTML language variable
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @version february 2006, dokeos 1.8
 */
function store_move_thread($values)
{
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);

    $courseId = api_get_course_int_id();
    $sessionId = api_get_session_id();

    $forumId = intval($_POST['forum']);
    $threadId = intval($_POST['thread_id']);
    $forumInfo = get_forums($forumId);

    // Change the thread table: Setting the forum_id to the new forum.
    $sql = "UPDATE $table_threads SET forum_id = $forumId
            WHERE c_id = $courseId AND thread_id = $threadId";
    Database::query($sql);

    // Changing all the posts of the thread: setting the forum_id to the new forum.
    $sql = "UPDATE $table_posts SET forum_id = $forumId
            WHERE c_id = $courseId AND thread_id= $threadId";
    Database::query($sql);
    // Fix group id, if forum is moved to a different group
    if (!empty($forumInfo['to_group_id'])) {
        $groupId = $forumInfo['to_group_id'];
        $item = api_get_item_property_info($courseId, TABLE_FORUM_THREAD, $threadId, $sessionId, $groupId);
        $table = Database:: get_course_table(TABLE_ITEM_PROPERTY);
        $sessionCondition = api_get_session_condition($sessionId);

        if (!empty($item)) {
            if ($item['to_group_id'] != $groupId) {
                $sql = "UPDATE $table
                    SET to_group_id = $groupId
                    WHERE
                      tool = '".TABLE_FORUM_THREAD."' AND
                      c_id = $courseId AND
                      ref = ".$item['ref']."
                      $sessionCondition
                ";
                Database::query($sql);
            }
        } else {
            $sql = "UPDATE $table
                    SET to_group_id = $groupId
                    WHERE
                      tool = '".TABLE_FORUM_THREAD."' AND
                      c_id = $courseId AND
                      ref = ".$threadId."
                      $sessionCondition
            ";
            Database::query($sql);
        }
    }

    return get_lang('ThreadMoved');
}

/**
 * Prepares a string for displaying by highlighting the search results inside, if any.
 * @param string $input    The input string.
 * @return string          The same string with highlighted hits inside.
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, February 2006 - the initial version.
 * @author Ivan Tcholakov, March 2011 - adaptation for Chamilo LMS.
 */
function prepare4display($input)
{
    static $highlightcolors = array('yellow', '#33CC33', '#3399CC', '#9999FF', '#33CC33');
    static $search;

    if (!isset($search)) {
        if (isset($_POST['search_term'])) {
            $search = $_POST['search_term']; // No html at all.
        } elseif (isset($_GET['search'])) {
            $search = $_GET['search'];
        } else {
            $search = '';
        }
    }

    if (!empty($search)) {
        if (strstr($search, '+')) {
            $search_terms = explode('+', $search);
        } else {
            $search_terms[] = trim($search);
        }
        $counter = 0;
        foreach ($search_terms as $key => $search_term) {
            $input = api_preg_replace('/'.preg_quote(trim($search_term), '/').'/i', '<span style="background-color: '.$highlightcolors[$counter].'">$0</span>', $input);
            $counter++;
        }
    }

    // TODO: Security should be implemented outside this function.
    // Change this to COURSEMANAGERLOWSECURITY or COURSEMANAGER to lower filtering and allow more styles (see comments of Security::remove_XSS() method to learn about other levels).

    return Security::remove_XSS($input, STUDENT, true);
}

/**
 * Display the search form for the forum and display the search results
 * @return void display an HTML search results
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version march 2008, dokeos 1.8.5
 */
function forum_search()
{
    // Initialize the object.
    $form = new FormValidator('forumsearch', 'post', 'forumsearch.php?'.api_get_cidreq());

    // Setting the form elements.
    $form->addElement('header', '', get_lang('ForumSearch'));
    $form->addElement('text', 'search_term', get_lang('SearchTerm'), array('autofocus'));
    $form->applyFilter('search_term', 'html_filter');
    $form->addElement('static', 'search_information', '', get_lang('ForumSearchInformation'));
    $form->addButtonSearch(get_lang('Search'));

    // Setting the rules.
    $form->addRule('search_term', get_lang('ThisFieldIsRequired'), 'required');
    $form->addRule('search_term', get_lang('TooShort'), 'minlength', 3);

    // Validation or display.
    if ($form->validate()) {
        $values = $form->exportValues();
        $form->setDefaults($values);
        $form->display();
        // Display the search results.
        display_forum_search_results(stripslashes($values['search_term']));
    } else {
        $form->display();
    }
}

/**
 * Display the search results
 * @param string
 * @param string $search_term
 * @return void display the results
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version march 2008, dokeos 1.8.5
 */
function display_forum_search_results($search_term)
{
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $session_id = api_get_session_id();
    $gradebook = Security::remove_XSS($_GET['gradebook']);
    $course_id = api_get_course_int_id();

    // Defining the search strings as an array.
    if (strstr($search_term, '+')) {
        $search_terms = explode('+', $search_term);
    } else {
        $search_terms[] = $search_term;
    }

    // Search restriction.
    foreach ($search_terms as $value) {
        $search_restriction[] = "
        (posts.post_title LIKE '%".Database::escape_string(trim($value))."%'
        OR posts.post_text LIKE '%".Database::escape_string(trim($value))."%')";
    }

    $sql = "SELECT posts.*
            FROM $table_posts posts, $table_threads threads, $table_item_property item_property
            WHERE
                posts.c_id = $course_id
                AND item_property.c_id = $course_id
                AND posts.thread_id = threads.thread_id
                AND item_property.ref = threads.thread_id
                AND item_property.visibility = 1
                AND item_property.session_id = $session_id
                AND posts.visible = 1
                AND item_property.tool = '".TOOL_FORUM_THREAD."'
                AND ".implode(' AND ', $search_restriction)."
                GROUP BY posts.post_id";

    // Getting all the information of the forum categories.
    $forum_categories_list = get_forum_categories();

    // Getting all the information of the forums.
    $forum_list = get_forums();

    $result = Database::query($sql);
    $search_results = [];
    while ($row = Database::fetch_array($result, 'ASSOC')) {
        $display_result = false;
        /*
          We only show it when
          1. forum category is visible
          2. forum is visible
          3. thread is visible (to do)
          4. post is visible
         */
        if (!api_is_allowed_to_edit(null, true)) {
            if ($forum_categories_list[$forum_list[$row['forum_id']]['forum_category']]['visibility'] == '1' AND
                $forum_list[$row['forum_id']]['visibility'] == '1' AND $row['visible'] == '1'
            ) {
                $display_result = true;
            }
        } else {
            $display_result = true;
        }

        if ($display_result) {
            $search_results_item = '<li><a href="viewforumcategory.php?'.api_get_cidreq().'&forumcategory='.$forum_list[$row['forum_id']]['forum_category'].'&search='.urlencode($search_term).'">'.
                prepare4display($forum_categories_list[$row['forum_id']['forum_category']]['cat_title']).'</a> &gt; ';
            $search_results_item .= '<a href="viewforum.php?'.api_get_cidreq().'&forum='.$row['forum_id'].'&search='.urlencode($search_term).'">'.
                prepare4display($forum_list[$row['forum_id']]['forum_title']).'</a> &gt; ';
            //$search_results_item .= '<a href="">THREAD</a> &gt; ';
            $search_results_item .= '<a href="viewthread.php?'.api_get_cidreq().'&forum='.$row['forum_id'].'&gradebook='.$gradebook.'&thread='.$row['thread_id'].'&search='.urlencode($search_term).'">'.
                prepare4display($row['post_title']).'</a>';
            $search_results_item .= '<br />';
            if (api_strlen($row['post_title']) > 200) {
                $search_results_item .= prepare4display(api_substr(strip_tags($row['post_title']), 0, 200)).'...';
            } else {
                $search_results_item .= prepare4display($row['post_title']);
            }
            $search_results_item .= '</li>';
            $search_results[] = $search_results_item;
        }
    }
    echo '<legend>'.count($search_results).' '.get_lang('ForumSearchResults').'</legend>';
    echo '<ol>';
    if ($search_results) {
        echo implode($search_results);
    }
    echo '</ol>';
}

/**
 * Return the link to the forum search page
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version April 2008, dokeos 1.8.5
 */
function search_link()
{
    $return = '';
    $origin = api_get_origin();
    if ($origin != 'learnpath') {
        $return = '<a href="forumsearch.php?'.api_get_cidreq().'&action=search"> ';
        $return .= Display::return_icon('search.png', get_lang('Search'), '', ICON_SIZE_MEDIUM).'</a>';

        if (!empty($_GET['search'])) {
            $return .= ': '.Security::remove_XSS($_GET['search']).' ';
            $url = api_get_self().'?';
            $url_parameter = array();
            foreach ($_GET as $key => $value) {
                if ($key != 'search') {
                    $url_parameter[] = Security::remove_XSS($key).'='.Security::remove_XSS($value);
                }
            }
            $url = $url.implode('&', $url_parameter);
            $return .= '<a href="'.$url.'">'.Display::return_icon('delete.gif', get_lang('RemoveSearchResults')).'</a>';
        }
    }

    return $return;
}

/**
 * This function adds an attachment file into a forum
 * @param string $file_comment  a comment about file
 * @param int $last_id from forum_post table
 * @return false|null
 */
function add_forum_attachment_file($file_comment, $last_id)
{
    $_course = api_get_course_info();
    $agenda_forum_attachment = Database::get_course_table(TABLE_FORUM_ATTACHMENT);

    if (!isset($_FILES['user_upload'])) {
        return false;
    }

    $fileCount = count($_FILES['user_upload']['name']);

    $filesData = [];

    if (!is_array($_FILES['user_upload']['name'])) {
        $filesData[] = $_FILES['user_upload'];
    } else {
        $fileKeys = array_keys($_FILES['user_upload']);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $filesData[$i][$key] = $_FILES['user_upload'][$key][$i];
            }
        }
    }

    foreach ($filesData as $attachment) {
        if (empty($attachment['name'])) {
            continue;
        }

        $upload_ok = process_uploaded_file($attachment);

        if (!$upload_ok) {
            continue;
        }

        $course_dir = $_course['path'] . '/upload/forum';
        $sys_course_path = api_get_path(SYS_COURSE_PATH);
        $updir = $sys_course_path . $course_dir;

        // Try to add an extension to the file if it hasn't one.
        $new_file_name = add_ext_on_mime(
            stripslashes($attachment['name']),
            $attachment['type']
        );
        // User's file name
        $file_name = $attachment['name'];

        if (!filter_extension($new_file_name)) {
            Display :: display_error_message(get_lang('UplUnableToSaveFileFilteredExtension'));

            return;
        }

        $new_file_name = uniqid('');
        $new_path = $updir . '/' . $new_file_name;
        $result = @move_uploaded_file($attachment['tmp_name'], $new_path);
        $safe_file_comment = Database::escape_string($file_comment);
        $safe_file_name = Database::escape_string($file_name);
        $safe_new_file_name = Database::escape_string($new_file_name);
        $last_id = intval($last_id);
        // Storing the attachments if any.
        if (!$result) {
            return;
        }

        $last_id_file = Database::insert(
            $agenda_forum_attachment,
            [
                'c_id' => api_get_course_int_id(),
                'filename' => $safe_file_name,
                'comment' => $safe_file_comment,
                'path' => $safe_new_file_name,
                'post_id' => $last_id,
                'size' => intval($attachment['size']),
            ]
        );

        api_item_property_update(
            $_course,
            TOOL_FORUM_ATTACH,
            $last_id_file,
            'ForumAttachmentAdded',
            api_get_user_id()
        );
    }
}

/**
 * This function edits an attachment file into a forum
 * @param string $file_comment  a comment about file
 * @param int $post_id
 * @param int $id_attach attachment file Id
 * @return void
 */
function edit_forum_attachment_file($file_comment, $post_id, $id_attach)
{
    $_course = api_get_course_info();
    $table_forum_attachment = Database::get_course_table(TABLE_FORUM_ATTACHMENT);
    $course_id = api_get_course_int_id();

    $fileCount = count($_FILES['user_upload']['name']);

    $filesData = [];

    if (!is_array($_FILES['user_upload']['name'])) {
        $filesData[] = $_FILES['user_upload'];
    } else {
        $fileKeys = array_keys($_FILES['user_upload']);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $filesData[$i][$key] = $_FILES['user_upload'][$key][$i];
            }
        }
    }

    foreach ($filesData as $attachment) {
        if (empty($attachment['name'])) {
            continue;
        }

        $upload_ok = process_uploaded_file($attachment);

        if (!$upload_ok) {
            continue;
        }

        $course_dir = $_course['path'].'/upload/forum';
        $sys_course_path = api_get_path(SYS_COURSE_PATH);
        $updir = $sys_course_path.$course_dir;

        // Try to add an extension to the file if it hasn't one.
        $new_file_name = add_ext_on_mime(stripslashes($attachment['name']), $attachment['type']);
        // User's file name
        $file_name = $attachment['name'];

        if (!filter_extension($new_file_name)) {
            Display :: display_error_message(get_lang('UplUnableToSaveFileFilteredExtension'));
        } else {
            $new_file_name = uniqid('');
            $new_path = $updir.'/'.$new_file_name;
            $result = @move_uploaded_file($attachment['tmp_name'], $new_path);
            $safe_file_comment = Database::escape_string($file_comment);
            $safe_file_name = Database::escape_string($file_name);
            $safe_new_file_name = Database::escape_string($new_file_name);
            $safe_post_id = (int) $post_id;
            $safe_id_attach = (int) $id_attach;
            // Storing the attachments if any.
            if ($result) {
                $sql = "UPDATE $table_forum_attachment SET filename = '$safe_file_name', comment = '$safe_file_comment', path = '$safe_new_file_name', post_id = '$safe_post_id', size ='".$attachment['size']."'
                       WHERE c_id = $course_id AND id = '$safe_id_attach'";
                Database::query($sql);
                api_item_property_update($_course, TOOL_FORUM_ATTACH, $safe_id_attach, 'ForumAttachmentUpdated', api_get_user_id());
            }
        }
    }
}

/**
 * Show a list with all the attachments according to the post's id
 * @param int $post_id
 * @return array with the post info
 * @author Julio Montoya Dokeos
 * @version avril 2008, dokeos 1.8.5
 */
function get_attachment($post_id)
{
    $forum_table_attachment = Database :: get_course_table(TABLE_FORUM_ATTACHMENT);
    $course_id = api_get_course_int_id();
    $row = array();
    $post_id = intval($post_id);
    $sql = "SELECT iid, path, filename,comment FROM $forum_table_attachment
            WHERE c_id = $course_id AND post_id = $post_id";
    $result = Database::query($sql);
    if (Database::num_rows($result) != 0) {
        $row = Database::fetch_array($result);
    }

    return $row;
}

function getAllAttachment($postId)
{
    $forumAttachmentTable = Database :: get_course_table(TABLE_FORUM_ATTACHMENT);
    $courseId = api_get_course_int_id();
    $postId = intval($postId);
    $columns = array('iid', 'path', 'filename', 'comment');
    $conditions = array(
        'where' => array(
            'c_id = ? AND post_id = ?' => array($courseId, $postId),
        ),
    );
    $array = Database::select(
        $columns,
        $forumAttachmentTable,
        $conditions,
        'all',
        'ASSOC'
    );

    return $array;
}

/**
 * Delete the all the attachments from the DB and the file according to the post's id or attach id(optional)
 * @param post id
 * @param int $id_attach
 * @param bool $display to show or not result message
 * @return integer
 * @author Julio Montoya Dokeos
 * @version october 2014, chamilo 1.9.8
 */
function delete_attachment($post_id, $id_attach = 0, $display = true)
{
    $_course = api_get_course_info();

    $forum_table_attachment = Database::get_course_table(TABLE_FORUM_ATTACHMENT);
    $course_id = api_get_course_int_id();

    $cond = (!empty($id_attach)) ? " iid = " . (int) $id_attach . "" : " post_id = " . (int) $post_id . "";
    $sql = "SELECT path FROM $forum_table_attachment WHERE c_id = $course_id AND $cond";
    $res = Database::query($sql);
    $row = Database::fetch_array($res);

    $course_dir = $_course['path'] . '/upload/forum';
    $sys_course_path = api_get_path(SYS_COURSE_PATH);
    $updir = $sys_course_path . $course_dir;
    $my_path = isset($row['path']) ? $row['path'] : null;
    $file = $updir . '/' . $my_path;
    if (Security::check_abs_path($file, $updir)) {
        @unlink($file);
    }

    // Delete from forum_attachment table.
    $sql = "DELETE FROM $forum_table_attachment WHERE c_id = $course_id AND $cond ";
    $result = Database::query($sql);
    if ($result !== false) {
        $affectedRows = Database::affected_rows($result);
    } else {
        $affectedRows = 0;
    }

    // Update item_property.
    api_item_property_update($_course, TOOL_FORUM_ATTACH, $id_attach, 'ForumAttachmentDelete', api_get_user_id());

    if (!empty($result) && !empty($id_attach) && $display) {
        $message = get_lang('AttachmentFileDeleteSuccess');
        Display::display_confirmation_message($message);
    }

    return $affectedRows;
}

/**
 * This function gets all the forum information of the all the forum of the group
 *
 * @param integer $group_id the id of the group we need the fora of (see forum.forum_of_group)
 * @return array
 *
 * @todo this is basically the same code as the get_forums function. Consider merging the two.
 */
function get_forums_of_group($group_id)
{
    $table_forums = Database :: get_course_table(TABLE_FORUM);
    $table_threads = Database :: get_course_table(TABLE_FORUM_THREAD);
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $table_item_property = Database :: get_course_table(TABLE_ITEM_PROPERTY);
    $course_id = api_get_course_int_id();

    // Student
    // Select all the forum information of all forums (that are visible to students).

    $sql = "SELECT * FROM ".$table_forums." forum , ".$table_item_property." item_properties
            WHERE
                forum.forum_of_group = '".Database::escape_string($group_id)."' AND
                forum.c_id = $course_id AND
                item_properties.c_id = $course_id AND
                forum.forum_id = item_properties.ref AND
                item_properties.visibility = 1 AND
                item_properties.tool = '".TOOL_FORUM."'
            ORDER BY forum.forum_order ASC";
    // Select the number of threads of the forums (only the threads that are visible).
    $sql2 = "SELECT count(thread_id) AS number_of_threads, threads.forum_id
            FROM $table_threads threads, ".$table_item_property." item_properties
            WHERE
                threads.thread_id = item_properties.ref AND
                threads.c_id = $course_id AND
                item_properties.c_id = $course_id AND
                item_properties.visibility = 1 AND
                item_properties.tool='".TOOL_FORUM_THREAD."'
            GROUP BY threads.forum_id";
    // Select the number of posts of the forum (post that are visible and that are in a thread that is visible).
    $sql3 = "SELECT count(post_id) AS number_of_posts, posts.forum_id
            FROM $table_posts posts, $table_threads threads, ".$table_item_property." item_properties
            WHERE posts.visible=1 AND
                posts.c_id = $course_id AND
                item_properties.c_id = $course_id AND
                threads.c_id = $course_id
                AND posts.thread_id=threads.thread_id
                AND threads.thread_id=item_properties.ref
                AND item_properties.visibility = 1
                AND item_properties.tool='".TOOL_FORUM_THREAD."'
            GROUP BY threads.forum_id";

    // Course Admin
    if (api_is_allowed_to_edit()) {
        // Select all the forum information of all forums (that are not deleted).
        $sql = "SELECT *
                FROM ".$table_forums." forum , ".$table_item_property." item_properties
                WHERE
                    forum.forum_of_group = '".Database::escape_string($group_id)."' AND
                    forum.c_id = $course_id AND
                    item_properties.c_id = $course_id AND
                    forum.forum_id = item_properties.ref AND
                    item_properties.visibility <> 2 AND
                    item_properties.tool = '".TOOL_FORUM."'
                ORDER BY forum_order ASC";

        // Select the number of threads of the forums (only the threads that are not deleted).
        $sql2 = "SELECT count(thread_id) AS number_of_threads, threads.forum_id
                 FROM $table_threads threads, ".$table_item_property." item_properties
                 WHERE
                    threads.thread_id=item_properties.ref AND
                    threads.c_id = $course_id AND
                    item_properties.c_id = $course_id AND
                    item_properties.visibility <> 2 AND
                    item_properties.tool='".TOOL_FORUM_THREAD."'
                GROUP BY threads.forum_id";
        // Select the number of posts of the forum.
        $sql3 = "SELECT count(post_id) AS number_of_posts, forum_id
                FROM $table_posts
                WHERE c_id = $course_id GROUP BY forum_id";

    }

    // Handling all the forum information.

    $result = Database::query($sql);
    $forum_list = array();
    while ($row = Database::fetch_array($result, 'ASSOC')) {
        $forum_list[$row['forum_id']] = $row;
    }

    // Handling the thread count information.
    $result2 = Database::query($sql2);
    while ($row2 = Database::fetch_array($result2, 'ASSOC')) {
        if (is_array($forum_list)) {
            if (array_key_exists($row2['forum_id'], $forum_list)) {
                $forum_list[$row2['forum_id']]['number_of_threads'] = $row2['number_of_threads'];
            }
        }
    }

    // Handling the post count information.
    $result3 = Database::query($sql3);
    while ($row3 = Database::fetch_array($result3, 'ASSOC')) {
        if (is_array($forum_list)) {
            if (array_key_exists($row3['forum_id'], $forum_list)) {
                // This is needed because sql3 takes also the deleted forums into account.
                $forum_list[$row3['forum_id']]['number_of_posts'] = $row3['number_of_posts'];
            }
        }
    }

    // Finding the last post information (last_post_id, last_poster_id, last_post_date, last_poster_name, last_poster_lastname, last_poster_firstname).
    if (!empty($forum_list)) {
        foreach ($forum_list as $key => $value) {
            $last_post_info_of_forum = get_last_post_information($key, api_is_allowed_to_edit());
            $forum_list[$key]['last_post_id'] = $last_post_info_of_forum['last_post_id'];
            $forum_list[$key]['last_poster_id'] = $last_post_info_of_forum['last_poster_id'];
            $forum_list[$key]['last_post_date'] = $last_post_info_of_forum['last_post_date'];
            $forum_list[$key]['last_poster_name'] = $last_post_info_of_forum['last_poster_name'];
            $forum_list[$key]['last_poster_lastname'] = $last_post_info_of_forum['last_poster_lastname'];
            $forum_list[$key]['last_poster_firstname'] = $last_post_info_of_forum['last_poster_firstname'];
        }
    }

    return $forum_list;
}

/**
 * This function stores which users have to be notified of which forums or threads
 *
 * @param string $content does the user want to be notified about a forum or about a thread
 * @param integer $id the id of the forum or thread
 * @return string language variable
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version May 2008, dokeos 1.8.5
 * @since May 2008, dokeos 1.8.5
 */
function set_notification($content, $id, $add_only = false)
{
    $_user = api_get_user_info();

    // Database table definition
    $table_notification = Database::get_course_table(TABLE_FORUM_NOTIFICATION);

    $course_id = api_get_course_int_id();

    // Which database field do we have to store the id in?
    if ($content == 'forum') {
        $database_field = 'forum_id';
    } else {
        $database_field = 'thread_id';
    }

    // First we check if the notification is already set for this.
    $sql = "SELECT * FROM $table_notification
            WHERE
                c_id = $course_id AND
                $database_field = '".Database::escape_string($id)."' AND
                user_id = '".intval($_user['user_id'])."'";
    $result = Database::query($sql);
    $total = Database::num_rows($result);

    // If the user did not indicate that (s)he wanted to be notified already
    // then we store the notification request (to prevent double notification requests).
    if ($total <= 0) {
        $sql = "INSERT INTO $table_notification (c_id, $database_field, user_id)
                VALUES (".$course_id.", '".Database::escape_string($id)."','".intval($_user['user_id'])."')";
        Database::query($sql);
        Session::erase('forum_notification');
        get_notifications_of_user(0, true);

        return get_lang('YouWillBeNotifiedOfNewPosts');
    } else {
        if (!$add_only) {
            $sql = "DELETE FROM $table_notification
                    WHERE
                        c_id = $course_id AND
                        $database_field = '".Database::escape_string($id)."' AND
                        user_id = '".intval($_user['user_id'])."'";
            Database::query($sql);
            Session::erase('forum_notification');
            get_notifications_of_user(0, true);

            return get_lang('YouWillNoLongerBeNotifiedOfNewPosts');
        }
    }
}

/**
 * This function retrieves all the email adresses of the users who wanted to be notified
 * about a new post in a certain forum or thread
 *
 * @param string $content does the user want to be notified about a forum or about a thread
 * @param integer $id the id of the forum or thread
 * @return array returns
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version May 2008, dokeos 1.8.5
 * @since May 2008, dokeos 1.8.5
 */
function get_notifications($content, $id)
{
    // Database table definition
    $table_users = Database::get_main_table(TABLE_MAIN_USER);
    $table_notification = Database::get_course_table(TABLE_FORUM_NOTIFICATION);

    $course_id = api_get_course_int_id();

    // Which database field contains the notification?
    if ($content == 'forum') {
        $database_field = 'forum_id';
    } else {
        $database_field = 'thread_id';
    }

    $sql = "SELECT user.user_id, user.firstname, user.lastname, user.email, user.user_id user
            FROM $table_users user, $table_notification notification
            WHERE notification.c_id = $course_id AND user.active = 1 AND
            user.user_id = notification.user_id AND
            notification.$database_field= '".Database::escape_string($id)."'";

    $result = Database::query($sql);
    $return = array();

    while ($row = Database::fetch_array($result)) {
        $return['user'.$row['user_id']] = array('email' => $row['email'], 'user_id' => $row['user_id']);
    }

    return $return;
}

/**
 * Get all the users who need to receive a notification of a new post (those subscribed to
 * the forum or the thread)
 *
 * @param integer $forum_id the id of the forum
 * @param integer $thread_id the id of the thread
 * @param integer $post_id the id of the post
 * @return false|null
 *
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version May 2008, dokeos 1.8.5
 * @since May 2008, dokeos 1.8.5
 */
function send_notifications($forum_id = 0, $thread_id = 0, $post_id = 0)
{
    $_course = api_get_course_info();

    // The content of the mail
    $thread_link = api_get_path(WEB_CODE_PATH).'forum/viewthread.php?'.api_get_cidreq().'&forum='.$forum_id.'&thread='.$thread_id;

    // Users who subscribed to the forum
    if ($forum_id != 0) {
        $users_to_be_notified_by_forum = get_notifications('forum', $forum_id);
    } else {
        return false;
    }

    $current_thread = get_thread_information($thread_id);
    $current_forum = get_forum_information($current_thread['forum_id']);
    $subject = get_lang('NewForumPost').' - '.$_course['official_code'].' - '.$current_forum['forum_title'].' - '.$current_thread['thread_title'];

    // User who subscribed to the thread
    if ($thread_id != 0) {
        $users_to_be_notified_by_thread = get_notifications('thread', $thread_id);
    }

    // Merging the two
    $users_to_be_notified = array_merge($users_to_be_notified_by_forum, $users_to_be_notified_by_thread);
    $sender_id = api_get_user_id();

    if (is_array($users_to_be_notified)) {
        foreach ($users_to_be_notified as $value) {

            $user_info = api_get_user_info($value['user_id']);
            $email_body = get_lang('Dear').' '.api_get_person_name($user_info['firstname'], $user_info['lastname'], null, PERSON_NAME_EMAIL_ADDRESS).", <br />\n\r";
            $email_body .= get_lang('NewForumPost').": ".$current_forum['forum_title'].' - '.$current_thread['thread_title']." <br />\n";
            $email_body .= get_lang('Course').': '.$_course['name'].' - ['.$_course['official_code']."]  <br />\n";
            $email_body .= get_lang('YouWantedToStayInformed')."<br />\n";
            $email_body .= get_lang('ThreadCanBeFoundHere').': <br /> <a href="'.$thread_link.'">'.$thread_link."</a>\n";

            MessageManager::send_message_simple(
                $value['user_id'], $subject, $email_body, $sender_id
            );
        }
    }
}

/**
 * Get all the notification subscriptions of the user
 * = which forums and which threads does the user wants to be informed of when a new
 * post is added to this thread
 *
 * @param integer $user_id the user_id of a user (default = 0 => the current user)
 * @param boolean $force force get the notification subscriptions (even if the information is already in the session
 * @return array returns
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @version May 2008, dokeos 1.8.5
 * @since May 2008, dokeos 1.8.5
 */
function get_notifications_of_user($user_id = 0, $force = false)
{
    // Database table definition
    $table_notification = Database::get_course_table(TABLE_FORUM_NOTIFICATION);
    $course_id = api_get_course_int_id();
    if (empty($course_id) || $course_id == -1) {
        return null;
    }
    if ($user_id == 0) {
        $user_id = api_get_user_id();
    }

    if (!isset($_SESSION['forum_notification']) ||
        $_SESSION['forum_notification']['course'] != $course_id ||
        $force = true
    ) {
        $_SESSION['forum_notification']['course'] = $course_id;

        $sql = "SELECT * FROM $table_notification
                WHERE c_id = $course_id AND user_id='".intval($user_id)."'";
        $result = Database::query($sql);
        while ($row = Database::fetch_array($result)) {
            if (!is_null($row['forum_id'])) {
                $_SESSION['forum_notification']['forum'][] = $row['forum_id'];
            }
            if (!is_null($row['thread_id'])) {
                $_SESSION['forum_notification']['thread'][] = $row['thread_id'];
            }
        }
    }
}

/**
 * This function counts the number of post inside a thread
 * @param   int $thread_id
 * @return  int the number of post inside a thread
 * @author Jhon Hinojosa <jhon.hinojosa@dokeos.com>,
 * @version octubre 2008, dokeos 1.8
 */
function count_number_of_post_in_thread($thread_id)
{
    $table_posts = Database :: get_course_table(TABLE_FORUM_POST);
    $course_id = api_get_course_int_id();
    if (empty($course_id)) {
        return 0;
    }
    $sql = "SELECT * FROM $table_posts
            WHERE c_id = $course_id AND thread_id='".intval($thread_id)."' ";
    $result = Database::query($sql);

    return count(Database::store_result($result));
}

/**
 * This function counts the number of post inside a thread user
 * @param   int $thread_id
 * @param   int $user_id
 *
 * @return  int the number of post inside a thread user
 */
function count_number_of_post_for_user_thread($thread_id, $user_id)
{
    $table_posts = Database::get_course_table(TABLE_FORUM_POST);
    $course_id = api_get_course_int_id();
    $sql = "SELECT count(*) as count FROM $table_posts
            WHERE c_id = $course_id AND
                  thread_id=".intval($thread_id)." AND
                  poster_id = ".intval($user_id)." AND visible = 1 ";
    $result = Database::query($sql);
    $count = 0;
    if (Database::num_rows($result) > 0) {
        $count = Database::fetch_array($result);
        $count = $count['count'];
    }

    return $count;
}

/**
 * This function counts the number of user register in course
 * @param   int $course_id Course ID
 *
 * @return  int the number of user register in course
 * @author Jhon Hinojosa <jhon.hinojosa@dokeos.com>,
 * @version octubre 2008, dokeos 1.8
 */
function count_number_of_user_in_course($course_id)
{
    $table = Database::get_main_table(TABLE_MAIN_COURSE_USER);

    $sql = "SELECT * FROM $table
            WHERE c_id ='".intval($course_id)."' ";
    $result = Database::query($sql);

    return count(Database::store_result($result));
}

/**
 * This function retrieves information of statistical
 * @param   int $thread_id
 * @param   int $user_id
 * @param   int $course_id
 *
 * @return  array the information of statistical
 * @author Jhon Hinojosa <jhon.hinojosa@dokeos.com>,
 * @version octubre 2008, dokeos 1.8
 */
function get_statistical_information($thread_id, $user_id, $course_id)
{
    $result = array();
    $result['user_course'] = count_number_of_user_in_course($course_id);
    $result['post'] = count_number_of_post_in_thread($thread_id);
    $result['user_post'] = count_number_of_post_for_user_thread($thread_id, $user_id);

    return $result;
}

/**
 * This function return the posts inside a thread from a given user
 * @param   string $course_code
 * @param   int $thread_id
 * @param   int $user_id
 *
 * @return  int the number of post inside a thread
 * @author Jhon Hinojosa <jhon.hinojosa@dokeos.com>,
 * @version octubre 2008, dokeos 1.8
 */
function get_thread_user_post($course_code, $thread_id, $user_id)
{
    $table_posts = Database::get_course_table(TABLE_FORUM_POST);
    $table_users = Database::get_main_table(TABLE_MAIN_USER);
    $thread_id = intval($thread_id);
    $user_id = intval($user_id);
    $course_info = api_get_user_info($course_code);
    $course_id = $course_info['real_id'];

    if (empty($course_id)) {
        $course_id = api_get_course_int_id();
    }
    $sql = "SELECT * FROM $table_posts posts
            LEFT JOIN  $table_users users
                ON posts.poster_id=users.user_id
            WHERE
                posts.c_id = $course_id AND
                posts.thread_id='$thread_id'
                AND posts.poster_id='$user_id'
            ORDER BY posts.post_id ASC";

    $result = Database::query($sql);
    $post_list = array();
    while ($row = Database::fetch_array($result)) {
        $row['status'] = '1';
        $post_list[] = $row;
        $sql = "SELECT * FROM $table_posts posts
                LEFT JOIN  $table_users users
                    ON posts.poster_id=users.user_id
                WHERE
                    posts.c_id = $course_id AND
                    posts.thread_id='$thread_id'
                    AND posts.post_parent_id='".$row['post_id']."'
                ORDER BY posts.post_id ASC";
        $result2 = Database::query($sql);
        while ($row2 = Database::fetch_array($result2)) {
            $row2['status'] = '0';
            $post_list[] = $row2;
        }
    }

    return $post_list;
}

/**
 * This function get the name of an thread by id
 * @param int thread_id
 * @return String
 * @author Christian Fasanando
 * @author Julio Montoya <gugli100@gmail.com> Adding security
 */
function get_name_thread_by_id($thread_id)
{
    $t_forum_thread = Database::get_course_table(TABLE_FORUM_THREAD);
    $course_id = api_get_course_int_id();
    $sql = "SELECT thread_title FROM ".$t_forum_thread."
            WHERE c_id = $course_id AND thread_id = '".intval($thread_id)."' ";
    $result = Database::query($sql);
    $row = Database::fetch_array($result);

    return $row[0];
}

/**
 * This function gets all the post written by an user
 * @param int $user_id
 * @param string $course_code
 *
 * @return string
 */
function get_all_post_from_user($user_id, $course_code)
{
    $j = 0;
    $forums = get_forums('', $course_code);
    krsort($forums);
    $forum_results = '';

    foreach ($forums as $forum) {
        if ($forum['visibility'] == 0) {
            continue;
        }
        if ($j <= 4) {
            $threads = get_threads($forum['forum_id']);

            if (is_array($threads)) {
                $i = 0;
                $hand_forums = '';
                $post_counter = 0;

                foreach ($threads as $thread) {
                    if ($thread['visibility'] == 0) {
                        continue;
                    }
                    if ($i <= 4) {
                        $post_list = get_thread_user_post_limit($course_code, $thread['thread_id'], $user_id, 1);
                        $post_counter = count($post_list);
                        if (is_array($post_list) && count($post_list) > 0) {
                            $hand_forums.= '<div id="social-thread">';
                            $hand_forums.= Display::return_icon('thread.png', get_lang('Thread'), '', ICON_SIZE_MEDIUM);
                            $hand_forums.= '&nbsp;'.Security::remove_XSS($thread['thread_title'], STUDENT);
                            $hand_forums.= '</div>';

                            foreach ($post_list as $posts) {
                                $hand_forums.= '<div id="social-post">';
                                $hand_forums.= '<strong>'.Security::remove_XSS($posts['post_title'], STUDENT).'</strong>';
                                $hand_forums.= '<br / >';
                                $hand_forums.= Security::remove_XSS($posts['post_text'], STUDENT);
                                $hand_forums.= '</div>';
                                $hand_forums.= '<br / >';
                            }
                        }
                    }
                    $i++;
                }
                $forum_results .='<div id="social-forum">';
                $forum_results .='<div class="clear"></div><br />';
                $forum_results .='<div id="social-forum-title">'.
                    Display::return_icon('forum.gif', get_lang('Forum')).'&nbsp;'.Security::remove_XSS($forum['forum_title'], STUDENT).
                    '<div style="float:right;margin-top:-35px">
                        <a href="../forum/viewforum.php?'.api_get_cidreq_params($course_code).'&forum='.$forum['forum_id'].' " >'.
                            get_lang('SeeForum').'    
                        </a>
                     </div></div>';
                $forum_results .='<br / >';
                if ($post_counter > 0) {
                    $forum_results .=$hand_forums;
                }
                $forum_results .='</div>';
            }$j++;
        }
    }

    return $forum_results;
}

/**
 * @param string $course_code
 * @param int $thread_id
 * @param int $user_id
 * @param int $limit
 *
 * @return array
 */
function get_thread_user_post_limit($course_code, $thread_id, $user_id, $limit = 10)
{
    $table_posts = Database::get_course_table(TABLE_FORUM_POST);
    $table_users = Database::get_main_table(TABLE_MAIN_USER);

    $course_info = api_get_course_info($course_code);
    $course_id = $course_info['real_id'];

    $sql = "SELECT * FROM $table_posts posts
            LEFT JOIN  $table_users users
                ON posts.poster_id=users.user_id
            WHERE
                posts.c_id = $course_id AND
                posts.thread_id='".Database::escape_string($thread_id)."'
                AND posts.poster_id='".Database::escape_string($user_id)."'
            ORDER BY posts.post_id DESC LIMIT $limit ";
    $result = Database::query($sql);
    $post_list = array();
    while ($row = Database::fetch_array($result)) {
        $row['status'] = '1';
        $post_list[] = $row;
    }

    return $post_list;
}

/**
 * @param string $user_id
 * @param int $courseId
 * @param int $sessionId
 *
 * @return array
 */
function getForumCreatedByUser($user_id, $courseId, $sessionId)
{
    $items = api_get_item_property_list_by_tool_by_user(
        $user_id,
        'forum',
        $courseId,
        $sessionId
    );

    $courseInfo = api_get_course_info_by_id($courseId);

    $forumList = array();
    if (!empty($items)) {
        foreach ($items as $forum) {
            $forumInfo = get_forums(
                $forum['ref'],
                $courseInfo['code'],
                true,
                $sessionId
            );

            $forumList[] = array(
                $forumInfo['forum_title'],
                api_get_local_time($forum['insert_date']),
                api_get_local_time($forum['lastedit_date']),
            );
        }
    }

    return $forumList;
}

/**
 * This function builds an array of all the posts in a given thread
 * where the key of the array is the post_id
 * It also adds an element children to the array which itself is an array
 * that contains all the id's of the first-level children
 * @return array $rows containing all the information on the posts of a thread
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 */
function calculate_children($rows)
{
    $sorted_rows = array(0 => array());
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $rows_with_children[$row['post_id']] = $row;
            $rows_with_children[$row['post_parent_id']]['children'][] = $row['post_id'];
        }

        $rows = $rows_with_children;
        forumRecursiveSort($rows, $sorted_rows);
        unset($sorted_rows[0]);
    }

    return $sorted_rows;
}

/**
 * @param $rows
 * @param $threads
 * @param int $seed
 * @param int $indent
 */
function forumRecursiveSort($rows, &$threads, $seed = 0, $indent = 0)
{
    if ($seed > 0) {
        $threads[$rows[$seed]['post_id']] = $rows[$seed];
        $threads[$rows[$seed]['post_id']]['indent_cnt'] = $indent;
        $indent++;
    }

    if (isset($rows[$seed]['children'])) {
        foreach ($rows[$seed]['children'] as $child) {
            forumRecursiveSort($rows, $threads, $child, $indent);
        }
    }
}

/**
 * Update forum attachment data, used to update comment and post ID.
 * @param $array Array (field => value) to update forum attachment row.
 * @param $id Attach ID to find row to update.
 * @param null $courseId Course ID to find row to update.
 * @return int Number of affected rows.
 */
function editAttachedFile($array, $id, $courseId = null) {
    // Init variables
    $setString = '';
    $id = intval($id);
    $courseId = intval($courseId);
    if (empty($courseId)) {
        // $courseId can be null, use api method
        $courseId= api_get_course_int_id();
    }
    /*
     * Check if Attachment ID and Course ID are greater than zero
     * and array of field values is not empty
     */
    if ($id > 0 && $courseId > 0 && !empty($array) && is_array($array)) {
        foreach($array as $key => &$item) {
            $item = Database::escape_string($item);
            $setString .= $key . ' = "' .$item . '", ';
        }
        // Delete last comma
        $setString = substr($setString, 0, strlen($setString) - 2);
        $forumAttachmentTable = Database::get_course_table(TABLE_FORUM_ATTACHMENT);
        $sql = "UPDATE $forumAttachmentTable SET $setString WHERE c_id = $courseId AND id = $id";
        $result = Database::query($sql);
        if ($result !== false) {
            $affectedRows = Database::affected_rows($result);
            if ($affectedRows > 0) {
                /*
                 * If exist in $_SESSION variable, then delete them from it
                 * because they would be deprecated
                 */
                if (!empty($_SESSION['forum']['upload_file'][$courseId][$id])) {
                    unset($_SESSION['forum']['upload_file'][$courseId][$id]);
                }
            }

            return $affectedRows;
        }
    }

    return 0;
}

/**
 * Return a table where the attachments will be set
 * @param int $postId Forum Post ID
 *
 * @return string The Forum Attachments Ajax Table
 */
function getAttachmentsAjaxTable($postId = 0)
{
    // Init variables
    $postId = intval($postId);
    $courseId = api_get_course_int_id();
    $attachIds = getAttachmentIdsByPostId($postId, $courseId);
    $fileDataContent = '';
    // Update comment to show if form did not pass validation
    if (!empty($_REQUEST['file_ids']) && is_array($_REQUEST['file_ids'])) {
        // 'file_ids is the name from forum attachment ajax form
        foreach ($_REQUEST['file_ids'] as $key => $attachId) {
            if (!empty($_SESSION['forum']['upload_file'][$courseId][$attachId]) &&
                is_array($_SESSION['forum']['upload_file'][$courseId][$attachId])
            ) {
                // If exist forum attachment then update into $_SESSION data
                $_SESSION['forum']['upload_file'][$courseId][$attachId]['comment'] = $_POST['file_comments'][$key];
            }
        }
    }

    // Get data to fill into attachment files table
    if (!empty($_SESSION['forum']['upload_file'][$courseId]) &&
        is_array($_SESSION['forum']['upload_file'][$courseId])
    ) {
        $uploadedFiles = $_SESSION['forum']['upload_file'][$courseId];
        foreach ($uploadedFiles as $k => $uploadedFile) {
            if (!empty($uploadedFile) && in_array($uploadedFile['id'], $attachIds)) {
                // Buil html table including an input with attachmentID
                $fileDataContent .= '<tr id="' . $uploadedFile['id'] . '" ><td>' . $uploadedFile['name'] . '</td><td>' . $uploadedFile['size'] . '</td><td>&nbsp;' . $uploadedFile['result'] .
                    ' </td><td> <input style="width:90%;" type="text" value="' . $uploadedFile['comment'] . '" name="file_comments[]"> </td><td>' .
                    $uploadedFile['delete'] . '</td>' .
                    '<input type="hidden" value="' . $uploadedFile['id'] .'" name="file_ids[]">' . '</tr>';
            } else {
                /*
                 * If attachment data is empty, then delete it from $_SESSION
                 * because could generate and empty row into html table
                 */
                unset($_SESSION['forum']['upload_file'][$courseId][$k]);
            }
        }
    }
    $style = empty($fileDataContent) ? 'display: none;' : '';
    // Forum attachment Ajax table
    $fileData = '
    <div class="control-group " style="'. $style . '">
        <label class="control-label">'.get_lang('AttachmentList').'</label>
        <div class="controls">
            <table id="attachmentFileList" class="files data_table span10">
                <tr>
                    <th>'.get_lang('FileName').'</th>
                    <th>'.get_lang('Size').'</th>
                    <th>'.get_lang('Status').'</th>
                    <th>'.get_lang('Comment').'</th>
                    <th>'.get_lang('Delete').'</th>
                </tr>
                '.$fileDataContent.'
            </table>
        </div>
    </div>';

    return $fileData;
}

/**
 * Return an array of prepared attachment data to build forum attachment table
 * Also, save this array into $_SESSION to do available the attachment data
 * @param int $forumId
 * @param int $threadId
 * @param int $postId
 * @param int $attachId
 * @param int $courseId
 *
 * @return array
 */
function getAttachedFiles($forumId, $threadId, $postId = 0, $attachId = 0, $courseId = 0)
{
    $forumId = intval($forumId);
    $courseId = intval($courseId);
    $attachId = intval($attachId);
    $postId = intval($postId);
    $threadId = !empty($threadId) ? intval($threadId) : isset($_REQUEST['thread']) ? intval($_REQUEST['thread']) : '';
    if (empty($courseId)) {
        // $courseId can be null, use api method
        $courseId = api_get_course_int_id();
    }
    if (empty($forumId)) {
        if (!empty($_REQUEST['forum'])) {
            $forumId = intval($_REQUEST['forum']);
        } else {
            // if forum ID is empty, cannot generate delete url

            return array();
        }
    }
    // Check if exist at least one of them to filter forum attachment select query
    if (empty($postId) && empty($attachId)) {

        return array();
    } elseif (empty($postId)) {
        $filter = "AND iid = $attachId";
    } elseif (empty($attachId)) {
        $filter = "AND post_id = $postId";
    } else {
        $filter = "AND post_id = $postId AND iid = $attachId";
    }
    $forumAttachmentTable = Database::get_course_table(TABLE_FORUM_ATTACHMENT);
    $sql = "SELECT iid, comment, filename, path, size
            FROM $forumAttachmentTable
            WHERE c_id = $courseId $filter";
    $result = Database::query($sql);
    $json = array();
    if ($result !== false && Database::num_rows($result) > 0) {
        while ($row = Database::fetch_array($result, 'ASSOC')) {
            // name contains an URL to download attachment file and its filename
            $json['name'] = Display::url(
                api_htmlentities($row['filename']),
                api_get_path(WEB_CODE_PATH) . 'forum/download.php?file='.$row['path'].'&'.api_get_cidreq(),
                array('target'=>'_blank', 'class' => 'attachFilename')
            );
            $json['id'] = $row['iid'];
            $json['comment'] = $row['comment'];
            // Format file size
            $json['size'] = format_file_size($row['size']);
            // Check if $row is consistent
            if (!empty($row) && is_array($row)) {
                // Set result as success and bring delete URL
                $json['result'] = Display::return_icon('accept.png', get_lang('Uploaded'));
                $url = api_get_path(WEB_CODE_PATH) . 'forum/viewthread.php?' . api_get_cidreq() . '&action=delete_attach&forum=' . $forumId . '&thread=' . $threadId.'&id_attach=' . $row['iid'];
                $json['delete'] = Display::url(
                    Display::return_icon('delete.png',get_lang('Delete'), array(), ICON_SIZE_SMALL),
                    $url,
                    array('class' => 'deleteLink')
                );
            } else {
                // If not, set an exclamation result
                $json['result'] = Display::return_icon('exclamation.png', get_lang('Error'));
            }
            // Store array data into $_SESSION
            $_SESSION['forum']['upload_file'][$courseId][$json['id']] = $json;
        }
    }

    return $json;
}

/**
 * Clear forum attachment data stored in $_SESSION,
 * If is not defined post, it will clear all forum attachment data from course
 * @param int $postId -1 : Clear all attachments from course stored in $_SESSION
 *                      0 : Clear attachments from course, except from temporal post "0"
 *                          but without delete them from file system and database
 *                     Other values : Clear attachments from course except specified post
 *                          and delete them from file system and database
 * @param int $courseId : Course ID, if it is null, will use api_get_course_int_id()
 *
 * @return array
 */
function clearAttachedFiles($postId = null, $courseId = null) {
    // Init variables
    $courseId = intval($courseId);
    $postId = intval($postId);
    $array = array();
    if (empty($courseId)) {
        // $courseId can be null, use api method
        $courseId = api_get_course_int_id();
    }
    if ($postId === -1) {
        // If post ID is -1 then delete course's attachment data from $_SESSION
        if (!empty($_SESSION['forum']['upload_file'][$courseId])) {
            $array = array_keys($_SESSION['forum']['upload_file'][$courseId]);
            unset($_SESSION['forum']['upload_file'][$courseId]);
        }
    } else {
        $attachIds = getAttachmentIdsByPostId($postId, $courseId);
        if (!empty($_SESSION['forum']['upload_file'][$courseId]) &&
            is_array($_SESSION['forum']['upload_file'][$courseId])) {
            foreach ($_SESSION['forum']['upload_file'][$courseId] as $attachId => $attach) {
                if (!in_array($attachId, $attachIds)) {
                    // If attach ID is not into specified post, delete attachment
                    // Save deleted attachment ID
                    $array[] = $attachId;
                    if ($postId !== 0) {
                        // Post 0 is temporal, delete them from file system and DB
                        delete_attachment(0, $attachId, false);
                    }
                    // Delete attachment data from $_SESSION
                    unset($_SESSION['forum']['upload_file'][$courseId][$attachId]);
                }
            }
        }
    }

    return $array;
}

/**
 * Returns an array of forum attachment ids into a course and forum post
 * @param int $postId
 * @param int $courseId
 *
 * @return array
 */
function getAttachmentIdsByPostId($postId, $courseId = null)
{

    $array = array();
    $courseId = intval($courseId);
    $postId = intval($postId);
    if (empty($courseId)) {
        // $courseId can be null, use api method
        $courseId = api_get_course_int_id();
    }
    if ($courseId > 0) {
        $forumAttachmentTable = Database::get_course_table(TABLE_FORUM_ATTACHMENT);
        $sql = "SELECT id FROM $forumAttachmentTable
                WHERE c_id = $courseId AND post_id = $postId";
        $result = Database::query($sql);
        if ($result !== false && Database::num_rows($result) > 0) {
            while ($row = Database::fetch_array($result,'ASSOC')) {
                $array[] = $row['id'];
            }
        }
    }
    return $array;
}

/**
 * Check if the forum category exists looking for its title
 * @param string $title The forum category title
 * @param int $courseId The course ID
 * @param int $sessionId Optional. The session ID
 * @return boolean
 */
function getForumCategoryByTitle($title, $courseId, $sessionId = 0)
{
    $sessionId = intval($sessionId);

    $forumCategoryTable = Database::get_course_table(TABLE_FORUM_CATEGORY);
    $itemProperty = Database::get_course_table(TABLE_ITEM_PROPERTY);

    $fakeFrom = "$forumCategoryTable fc
        INNER JOIN $itemProperty ip ";

    if ($sessionId === 0) {
        $fakeFrom .= "
            ON (
                fc.cat_id = ip.ref AND fc.c_id = ip.c_id AND (fc.session_id = ip.session_id OR ip.session_id IS NULL)
            )
        ";
    } else {
        $fakeFrom .= "
            ON (
                fc.cat_id = ip.ref AND fc.c_id = ip.c_id AND fc.session_id = ip.session_id
            )
        ";
    }

    $resultData = Database::select(
        'fc.*',
        $fakeFrom,
        [
            'where' => [
                'ip.visibility != ? AND ' => 2,
                'ip.tool = ? AND ' => TOOL_FORUM_CATEGORY,
                'fc.session_id = ? AND ' => $sessionId,
                'fc.cat_title = ? AND ' => $title,
                'fc.c_id = ?' => intval($courseId)
            ]
        ],
        'first'
    );

    if (empty($resultData)) {
        return false;
    }

    return $resultData;
}

function getPostStatus($current_forum, $row)
{
    $statusIcon = '';
    if ($current_forum['moderated']) {
        $statusIcon = '<br /><br />';
        $row['status'] = empty($row['status']) ? 2 : $row['status'];
        switch ($row['status']) {
            case CForumPost::STATUS_VALIDATED:
                $statusIcon .= Display::label(get_lang('Validated'), 'success');
                break;
            case CForumPost::STATUS_WAITING_MODERATION:
                $statusIcon .= Display::label(get_lang('WaitingModeration'), 'warning');
                break;
            case CForumPost::STATUS_REJECTED:
                $statusIcon .= Display::label(get_lang('Rejected'), 'danger');
                break;
        }
    }

    return $statusIcon;
}
