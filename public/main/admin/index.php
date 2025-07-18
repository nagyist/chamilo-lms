<?php

/* For licensing terms, see /license.txt */

/**
 * Index page of the admin tools.
 */

use Chamilo\CoreBundle\Enums\ActionIcon;
use Chamilo\CoreBundle\Enums\ObjectIcon;
use Chamilo\CoreBundle\Enums\StateIcon;
use Chamilo\CoreBundle\Enums\ToolIcon;
use Chamilo\CoreBundle\Framework\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;

// Resetting the course id.
$cidReset = true;

// Including some necessary chamilo files.
require_once __DIR__.'/../inc/global.inc.php';

$response = new RedirectResponse(
    Container::getRouter()->generate('admin')
);
$response->send();

exit;

// Setting the section (for the tabs).
$this_section = SECTION_PLATFORM_ADMIN;

// Access restrictions.
api_protect_admin_script(true);

$nameTools = get_lang('Administration');
$accessUrlId = 0;

// Displaying the header
if (api_is_platform_admin()) {
    if (is_dir(api_get_path(SYS_ARCHIVE_PATH)) &&
        !is_writable(api_get_path(SYS_ARCHIVE_PATH))
    ) {
        Display::addFlash(
            Display::return_message(
                get_lang(
                    'The app/cache/ directory, used by this tool, is not writeable. Please contact your platform administrator.'
                ),
                'warning'
            )
        );
    }

    /* ACTION HANDLING */
    if (!empty($_POST['Register'])) {
        api_register_campus(!$_POST['donotlistcampus']);
        $message = Display:: return_message(get_lang('Version check enabled'), 'confirmation');
        Display::addFlash($message);
    }
    $keyword_url = Security::remove_XSS((empty($_GET['keyword']) ? '' : $_GET['keyword']));
}
$blocks = [];

/* Users */
$blocks['users']['mdi_icon'] = 'account';
$blocks['users']['icon'] = Display::getMdiIcon(
    ObjectIcon::USER,
    'ch-tool-icon',
    null,
    ICON_SIZE_MEDIUM,
    get_lang('User management')
);
$blocks['users']['description'] = get_lang('Here you can manage registered users within your platform');
$blocks['users']['label'] = get_lang('User management');
$blocks['users']['class'] = 'block-admin-users';

$searchForm = new FormValidator(
    'search_user',
    'GET',
    api_get_path(WEB_CODE_PATH).'admin/user_list.php',
    null,
    [],
    FormValidator::LAYOUT_BOX_SEARCH
);
$searchForm->addText('keyword', get_lang('Keyword'));
$searchForm->addButtonSearch(get_lang('Search'));
$blocks['users']['search_form'] = $searchForm->returnForm();

if (api_is_platform_admin()) {
    $blocks['users']['editable'] = true;
    $items = [
        ['url' => 'user_list.php', 'label' => get_lang('User list')],
        ['url' => 'user_add.php', 'label' => get_lang('Add a user')],
        ['url' => 'user_export.php', 'label' => get_lang('Export users list')],
        ['url' => 'user_import.php', 'label' => get_lang('Import users list')],
        ['url' => 'user_update_import.php', 'label' => get_lang('Edit users list')],
    ];

    if (isset($extAuthSource) && isset($extAuthSource['extldap']) && count($extAuthSource['extldap']) > 0) {
        $items[] = ['url' => 'ldap_users_list.php', 'label' => get_lang('Import LDAP users into the platform')];
    }
    $items[] = ['url' => 'extra_fields.php?type=user', 'label' => get_lang('Profiling')];
    $items[] = ['url' => 'usergroups.php', 'label' => get_lang('Classes')];
    if ('true' !== api_get_setting('profile.disable_gdpr')) {
        $items[] = ['url' => 'user_list_consent.php', 'label' => get_lang('Users in consents list')];
    }
    if ('true' === api_get_setting('admin.show_link_request_hrm_user')) {
        $items[] = ['url' => 'user_linking_requests.php', 'label' => get_lang('Student linking requests')];
    }
} else {
    $items = [
        ['url' => 'user_list.php', 'label' => get_lang('User list')],
        ['url' => 'user_add.php', 'label' => get_lang('Add a user')],
        ['url' => 'user_import.php', 'label' => get_lang('Import users list')],
        ['url' => 'usergroups.php', 'label' => get_lang('Classes')],
    ];

    if (api_is_session_admin()) {
        if ('true' === api_get_setting('limit_session_admin_role')) {
            $items = array_filter($items, function (array $item) {
                $urls = ['user_list.php', 'user_add.php'];

                return in_array($item['url'], $urls);
            });
        }

        if ('true' === api_get_setting('session.limit_session_admin_list_users')) {
            $items = array_filter($items, function (array $item) {
                $urls = ['user_list.php'];

                return !in_array($item['url'], $urls);
            });
        }
    }
}

$blocks['users']['items'] = $items;
$blocks['users']['extra'] = null;

if (api_is_platform_admin()) {
    /* Courses */
    $blocks['courses']['mdi_icon'] = 'book-open-page-variant';
    $blocks['courses']['icon'] = Display::getMdiIcon(
        ObjectIcon::COURSE,
        'ch-tool-icon',
        null,
        ICON_SIZE_MEDIUM,
        get_lang('Course management')
    );
    $blocks['courses']['label'] = get_lang('Course management');
    $blocks['courses']['description'] = get_lang('Create and manage your courses in a simple way');
    $blocks['courses']['class'] = 'block-admin-courses';
    $blocks['courses']['editable'] = true;

    $searchForm = new FormValidator(
        'search_course',
        'GET',
        api_get_path(WEB_CODE_PATH).'admin/course_list.php',
        null,
        null,
        FormValidator::LAYOUT_BOX_SEARCH
    );
    $searchForm->addText('keyword', get_lang('Keyword'));
    $searchForm->addButtonSearch(get_lang('Search'));

    $blocks['courses']['search_form'] = $searchForm->returnForm();

    $items = [];
    $items[] = ['url' => 'course_list.php', 'label' => get_lang('Course list')];
    $items[] = ['url' => 'course_add.php', 'label' => get_lang('Add course')];

    if ('true' === api_get_setting('course_validation')) {
        $items[] = ['url' => 'course_request_review.php', 'label' => get_lang('Review incoming course requests')];
        $items[] = ['url' => 'course_request_accepted.php', 'label' => get_lang('Accepted course requests')];
        $items[] = ['url' => 'course_request_rejected.php', 'label' => get_lang('Rejected course requests')];
    }

    $items[] = ['url' => 'course_export.php', 'label' => get_lang('Export courses')];
    $items[] = ['url' => 'course_import.php', 'label' => get_lang('Import courses list')];
    $items[] = ['url' => 'course_category.php', 'label' => get_lang('Courses categories')];
    $items[] = ['url' => 'subscribe_user2course.php', 'label' => get_lang('Add a user to a course')];
    $items[] = ['url' => 'course_user_import.php', 'label' => get_lang('Import users list')];

    if ('true' === api_get_setting('gradebook_enable_grade_model')) {
        $items[] = ['url' => 'grade_models.php', 'label' => get_lang('Grading model')];
    }

    if (isset($extAuthSource) && isset($extAuthSource['ldap']) && count($extAuthSource['ldap']) > 0) {
        $items[] = ['url' => 'ldap_import_students.php', 'label' => get_lang('Import LDAP users into a course')];
    }

    $items[] = ['url' => 'extra_fields.php?type=course', 'label' => get_lang('Manage extra fields for courses')];
    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'admin/teacher_time_report.php',
        'label' => get_lang('Teachers time report'),
    ];

    $blocks['courses']['items'] = $items;
    $blocks['courses']['extra'] = null;

    /* Sessions */
    $blocks['sessions']['mdi_icon'] = 'google-classroom';
    $blocks['sessions']['icon'] = Display::getMdiIcon(
        ObjectIcon::SESSION,
        'ch-tool-icon',
        null,
        ICON_SIZE_MEDIUM,
        get_lang('Sessions management')
    );
    $blocks['sessions']['label'] = get_lang('Sessions management');
    $blocks['sessions']['description'] = get_lang('Create course packages for a certain time with training sessions.');
    $blocks['sessions']['class'] = 'block-admin-sessions';

    if (api_is_platform_admin()) {
        $blocks['sessions']['editable'] = true;
    }
    $sessionPath = api_get_path(WEB_CODE_PATH).'session/';

    $searchForm = new FormValidator(
        'search_session',
        'GET',
        $sessionPath.'session_list.php',
        null,
        null,
        FormValidator::LAYOUT_BOX_SEARCH
    );
    $searchForm->addText('keyword', get_lang('Keyword'));
    $searchForm->addButtonSearch(get_lang('Search'));
    $blocks['sessions']['search_form'] = $searchForm->returnForm();
    $items = [];
    $items[] = ['url' => $sessionPath.'session_list.php', 'label' => get_lang('Training sessions list')];
    $items[] = ['url' => $sessionPath.'session_add.php', 'label' => get_lang('Add a training session')];
    $items[] = [
        'url' => $sessionPath.'session_category_list.php',
        'label' => get_lang('Sessions categories list'),
    ];
    $items[] = ['url' => $sessionPath.'session_import.php', 'label' => get_lang('Import sessions list')];
    $items[] = [
        'url' => $sessionPath.'session_import_drh.php',
        'label' => get_lang('Import list of HR directors into sessions'),
    ];
    if (isset($extAuthSource) && isset($extAuthSource['ldap']) && count($extAuthSource['ldap']) > 0) {
        $items[] = [
            'url' => 'ldap_import_students_to_session.php',
            'label' => get_lang('Import LDAP users into a session'),
        ];
    }
    $items[] = [
        'url' => $sessionPath.'session_export.php',
        'label' => get_lang('Export sessions list'),
    ];

    if (api_is_global_platform_admin()) {
        $items[] = [
            'url' => '../course_copy/copy_course_session.php',
            'label' => get_lang('Copy from course in session to another session'),
        ];
    }

    $allowCareer = ('true' === api_get_setting('session.allow_session_admin_read_careers'));

    if (api_is_platform_admin() || ($allowCareer && api_is_session_admin())) {
        // option only visible in development mode. Enable through code if required
        if (is_dir(api_get_path(SYS_TEST_PATH).'datafiller/')) {
            $items[] = ['url' => 'user_move_stats.php', 'label' => get_lang('Move users results from/to a session')];
        }
        $items[] = ['url' => 'career_dashboard.php', 'label' => get_lang('Careers and promotions')];
        $items[] = ['url' => 'extra_fields.php?type=session', 'label' => get_lang('Manage session fields')];
    }

    $blocks['sessions']['items'] = $items;
    $blocks['sessions']['extra'] = null;

    // Skills
    if (SkillModel::isToolAvailable()) {
        $blocks['skills']['mdi_icon'] = 'certificate';
        $blocks['skills']['icon'] = Display::getMdiIcon(
            ObjectIcon::BADGE,
            'ch-tool-icon',
            null,
            ICON_SIZE_MEDIUM,
            get_lang('Skills')
        );
        $blocks['skills']['label'] = get_lang('Skills and gradebook');
        $blocks['skills']['description'] = get_lang('Manage the skills of your users, through courses and badges');
        $blocks['skills']['class'] = 'block-admin-skills';

        $items = [];
        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'skills/skills_wheel.php',
            'label' => get_lang('Skills wheel'),
        ];
        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'skills/skills_import.php',
            'label' => get_lang('Skills import'),
        ];
        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'skills/skill_list.php',
            'label' => get_lang('Manage skills'),
        ];
        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'skills/skill.php',
            'label' => get_lang('Manage skills levels'),
        ];

        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'social/skills_ranking.php',
            'label' => get_lang('Skills ranking'),
        ];
        $items[] = [
            'url' => api_get_path(WEB_CODE_PATH).'skills/skills_gradebook.php',
            'label' => get_lang('Skills and assessments'),
        ];
        /*$items[] = array(
            'url' => api_get_path(WEB_CODE_PATH).'skills/skill_badge.php',
            'label' => get_lang('Badges')
        );*/
        $allow = ('true' === api_get_setting('gradebook.gradebook_dependency'));
        if (!$allow) {
            $items[] = [
                'url' => 'gradebook_list.php',
                'label' => get_lang('List of qualifications'),
            ];
        }
        $blocks['skills']['items'] = $items;
        $blocks['skills']['extra'] = null;
        $blocks['skills']['search_form'] = null;
    }

    /* Platform */
    $blocks['platform']['mdi_icon'] = 'cogs';
    $blocks['platform']['icon'] = Display::return_icon(
        'platform.png',
        get_lang('Platform management'),
        [],
        ICON_SIZE_MEDIUM,
        false
    );
    $blocks['platform']['label'] = get_lang('Platform management');
    $blocks['platform']['description'] = get_lang('Configure your platform, view reports, publish and send announcements globally');
    $blocks['platform']['class'] = 'block-admin-platform';
    $blocks['platform']['editable'] = true;

    $searchForm = new FormValidator(
        'search_setting',
        'GET',
        api_get_path(WEB_PATH).'admin/settings/search_settings/',
        null,
        null,
        FormValidator::LAYOUT_BOX_SEARCH
    );
    $searchForm->addText('keyword', get_lang('Keyword'));
    $searchForm->addButtonSearch(get_lang('Search'));
    $blocks['platform']['search_form'] = $searchForm->returnForm();

    $url = api_get_path(WEB_PUBLIC_PATH).'admin/settings/platform';
    $items = [];
    $items[] = ['url' => $url, 'label' => get_lang('Configuration settings')];
    $items[] = ['url' => 'languages.php', 'label' => get_lang('Languages')];
    $items[] = ['url' => 'settings.php?category=Plugins', 'label' => get_lang('Plugins')];
    $items[] = ['url' => 'settings.php?category=Regions', 'label' => get_lang('Regions')];
    $items[] = ['url' => 'system_announcements.php', 'label' => get_lang('Portal news')];
    $items[] = ['url' => '/resources/pages', 'label' => get_lang('Pages')];
    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'calendar/agenda_js.php?type=admin',
        'label' => get_lang('Global agenda'),
    ];
    // Replaced by page blocks
    //$items[] = ['url' => 'configure_homepage.php', 'label' => get_lang('Edit portal homepage')];
    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'auth/inscription.php?create_intro_page=1',
        'label' => get_lang('Setting the registration page')
    ];
    $items[] = ['url' => 'statistics/index.php', 'label' => get_lang('Statistics')];
    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'my_space/company_reports.php',
        'label' => get_lang('Reports'),
    ];
    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'admin/teacher_time_report.php',
        'label' => get_lang('Teachers time report'),
    ];

    if (api_get_configuration_value('chamilo_cms')) {
        $items[] = [
            'url' => api_get_path(WEB_PATH).'web/app_dev.php/administration/dashboard',
            'label' => get_lang('CMS'),
        ];
    }

    $items[] = ['url' => 'extra_field_list.php', 'label' => get_lang('Extra fields')];

    if (api_is_global_platform_admin()) {
        $items[] = ['url' => 'access_urls.php', 'label' => get_lang('Configure multiple access URL')];
    }

    if ('true' == api_get_plugin_setting('dictionary', 'enable_plugin_dictionary')) {
        $items[] = [
            'url' => api_get_path(WEB_PLUGIN_PATH).'Dictionary/terms.php',
            'label' => get_lang('Dictionary'),
        ];
    }

    if ('true' == api_get_setting('allow_terms_conditions')) {
        $items[] = ['url' => 'legal_add.php', 'label' => get_lang('Terms and Conditions')];
    }

    $items[] = ['url' => api_get_path(WEB_PUBLIC_PATH).'admin/lti/', 'label' => get_lang('External tools')];

    $blocks['platform']['items'] = $items;
    $blocks['platform']['extra'] = null;
}

/* Settings */
if (api_is_platform_admin()) {
    $blocks['settings']['mdi_icon'] = 'tools';
    $blocks['settings']['icon'] = Display::getMdiIcon(
        ToolIcon::SETTINGS,
        'ch-tool-icon',
        null,
        ICON_SIZE_MEDIUM,
        get_lang('System')
    );
    $blocks['settings']['label'] = get_lang('System');
    $blocks['settings']['description'] = get_lang('View the status of your server, perform performance tests');
    $blocks['settings']['class'] = 'block-admin-settings';

    $items = [];
    $items[] = [
        'url' => 'archive_cleanup.php',
        'label' => get_lang('Cleanup of cache and temporary files'),
    ];

    $items[] = [
        'url' => 'special_exports.php',
        'label' => get_lang('Special exports'),
    ];
    /*$items[] = [
        'url' => 'periodic_export.php',
        'label' => get_lang('PeriodicExport'),
    ];*/
    $items[] = [
        'url' => 'system_status.php',
        'label' => get_lang('System status'),
    ];
    if (is_dir(api_get_path(SYS_TEST_PATH).'datafiller/')) {
        $items[] = [
            'url' => 'filler.php',
            'label' => get_lang('Data filler'),
        ];
    }

    $items[] = [
        'url' => 'resource_sequence.php',
        'label' => get_lang('Resources sequencing'),
    ];
    if (is_dir(api_get_path(SYS_TEST_PATH))) {
        $items[] = [
            'url' => 'email_tester.php',
            'label' => get_lang('E-mail tester'),
        ];
    }

    $items[] = [
        'url' => api_get_path(WEB_CODE_PATH).'ticket/tickets.php',
        'label' => get_lang('Tickets'),
    ];

    /*if (true == api_get_configuration_value('db_manager_enabled') &&
        api_is_global_platform_admin()
    ) {
        $host = $_configuration['db_host'];
        $username = $_configuration['db_user'];
        $databaseName = $_configuration['main_database'];

        $items[] = [
            'url' => "db.php?username=$username&db=$databaseName&server=$host",
            'label' => get_lang('Database manager'),
        ];
    }*/

    $blocks['settings']['items'] = $items;
    $blocks['settings']['extra'] = null;
    $blocks['settings']['search_form'] = null;
}

if (api_is_platform_admin()) {
    /* Plugins */
    global $_plugins;
    if (isset($_plugins['menu_administrator']) &&
        count($_plugins['menu_administrator']) > 0
    ) {
        $menuAdministratorItems = $_plugins['menu_administrator'];

        if ($menuAdministratorItems) {
            $blocks['plugins']['mdi_icon'] = 'puzzle';
            $blocks['plugins']['icon'] = Display::getMdiIcon(
                ToolIcon::PLUGIN,
                'ch-tool-icon',
                null,
                ICON_SIZE_MEDIUM,
                get_lang('Plugins')
            );
            $blocks['plugins']['label'] = get_lang('Plugins');
            $blocks['plugins']['class'] = 'block-admin-platform';
            $blocks['plugins']['editable'] = true;

            $plugin_obj = new AppPlugin();
            $items = [];

            foreach ($menuAdministratorItems as $pluginName) {
                $pluginInfo = $plugin_obj->getPluginInfo($pluginName, true);
                /** @var \Plugin $plugin */
                $plugin = $pluginInfo['obj'];
                $pluginUrl = $plugin->getAdminUrl();

                if (empty($pluginUrl)) {
                    continue;
                }

                $items[] = [
                    'url' => $pluginUrl,
                    'label' => $pluginInfo['title'],
                ];
            }

            $blocks['plugins']['items'] = $items;
            $blocks['plugins']['extra'] = '';
        }
    }

    /* Chamilo.org */
    $blocks['chamilo']['mdi_icon'] = 'cogs';
    $blocks['chamilo']['icon'] = Display::getMdiIcon(
        ActionIcon::INFORMATION,
        'ch-tool-icon',
        null,
        ICON_SIZE_MEDIUM,
        'Chamilo.org'
    );
    $blocks['chamilo']['label'] = 'Chamilo.org';
    $blocks['chamilo']['description'] = get_lang('Learn more about Chamilo and its use, official references links');
    $blocks['chamilo']['class'] = 'block-admin-chamilo';

    $items = [];
    $items[] = ['url' => 'http://www.chamilo.org/', 'label' => get_lang('Chamilo homepage')];
    $items[] = ['url' => 'http://www.chamilo.org/forum', 'label' => get_lang('Chamilo forum')];

    $items[] = ['url' => '../../documentation/installation_guide.html', 'label' => get_lang('Installation guide')];
    $items[] = ['url' => '../../documentation/changelog.html', 'label' => get_lang('Changes in last version')];
    $items[] = ['url' => '../../documentation/credits.html', 'label' => get_lang('Contributors list')];
    $items[] = ['url' => '../../documentation/security.html', 'label' => get_lang('Security guide')];
    $items[] = ['url' => '../../documentation/optimization.html', 'label' => get_lang('Optimization guide')];
    $items[] = ['url' => 'http://www.chamilo.org/extensions', 'label' => get_lang('Chamilo extensions')];
    $items[] = [
        'url' => 'http://www.chamilo.org/en/providers',
        'label' => get_lang('Chamilo official services providers'),
    ];

    $blocks['chamilo']['items'] = $items;
    $blocks['chamilo']['extra'] = null;
    $blocks['chamilo']['search_form'] = null;

    // Version check
    $blocks['version_check']['mdi_icon'] = '';
    $blocks['version_check']['icon'] = Display::getMdiIcon(
        StateIcon::COMPLETE,
        'ch-tool-icon',
        null,
        ICON_SIZE_MEDIUM,
        'Chamilo.org'
    );
    $blocks['version_check']['label'] = get_lang('Version Check');
    $blocks['version_check']['extra'] = '<div class="admin-block-version"></div>';
    $blocks['version_check']['search_form'] = null;
    $blocks['version_check']['items'] = '<div class="block-admin-version_check"></div>';
    $blocks['version_check']['class'] = '';
}
$admin_ajax_url = api_get_path(WEB_AJAX_PATH).'admin.ajax.php';

$tpl = new Template();
$tpl->assign('web_admin_ajax_url', $admin_ajax_url);
$tpl->assign('blocks_admin', $blocks);

if (api_is_platform_admin()) {
    $extraContentForm = new FormValidator(
        'block_extra_data',
        'post',
        '#',
        null,
        [
            'id' => 'block-extra-data',
            'class' => '',
        ],
        FormValidator::LAYOUT_BOX_NO_LABEL
    );
    $extraContentFormRenderer = $extraContentForm->getDefaultRenderer();

    if ($extraContentForm->validate()) {
        $extraData = $extraContentForm->getSubmitValues();
        $extraData = array_map(['Security', 'remove_XSS'], $extraData);

        if (!empty($extraData['block'])) {
            //$fileSystem->put('admin/'.$extraData['block'].'_extra.html', $extraData['extra_content']);

            header('Location: '.api_get_self());
            exit;
        }
    }

    $extraContentForm->addTextarea(
        'extra_content',
        null,
        ['id' => 'extra_content']
    );
    $extraContentFormRenderer->setElementTemplate(
        '<div class="form-group">{element}</div>',
        'extra_content'
    );
    $extraContentForm->addElement(
        'hidden',
        'block',
        null,
        [
            'id' => 'extra-block',
        ]
    );
    $extraContentForm->addButtonExport(
        get_lang('Save'),
        'submit_extra_content'
    );

    $tpl->assign('extraDataForm', $extraContentForm->returnForm());
}

// The template contains the call to the AJAX version checker
$template = $tpl->get_template('admin/index.html.twig');
$content = $tpl->fetch($template);
$tpl->assign('content', $content);
$tpl->display_one_col_template();
