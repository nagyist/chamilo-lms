<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Enums\ActionIcon;
use ChamiloSession as Session;

/**
 * This script is the Tickets plugin main entry point.
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

api_protect_admin_script(true);

$toolName = get_lang('Status');
$webLibPath = api_get_path(WEB_LIBRARY_PATH);

$this_section = 'tickets';
Session::erase('this_section');

$table = new SortableTable(
    'TicketProject',
    ['TicketManager', 'getStatusCount'],
    ['TicketManager', 'getStatusAdminList'],
    1
);

if (0 == $table->per_page) {
    $table->per_page = 20;
}

$formToString = '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

$interbreadcrumb[] = [
    'url' => api_get_path(WEB_CODE_PATH).'ticket/tickets.php',
    'name' => get_lang('My tickets'),
];
$interbreadcrumb[] = [
    'url' => api_get_path(WEB_CODE_PATH).'ticket/settings.php',
    'name' => get_lang('Settings'),
];

switch ($action) {
    case 'delete':
        $tickets = TicketManager::getTicketsFromCriteria(['status' => $id]);
        if (empty($tickets)) {
            TicketManager::deleteStatus($id);
            Display::addFlash(Display::return_message(get_lang('Deleted')));
        } else {
            Display::addFlash(Display::return_message(get_lang('This item is related to other tickets.'), 'warning'));
        }

        header('Location: '.api_get_self());
        exit;

        break;
    case 'add':
        $toolName = get_lang('Add');
        $interbreadcrumb[] = [
            'url' => api_get_path(WEB_CODE_PATH).'ticket/status.php',
            'name' => get_lang('Status'),
        ];
        $url = api_get_self().'?action=add';
        $form = TicketManager::getStatusForm($url);
        $formToString = $form->returnForm();
        if ($form->validate()) {
            $values = $form->getSubmitValues();

            $params = [
                'title' => $values['name'],
                'description' => $values['description'],
            ];
            TicketManager::addStatus($params);
            Display::addFlash(Display::return_message(get_lang('Added')));

            header('Location: '.api_get_self());
            exit;
        }

        break;
    case 'edit':
        $toolName = get_lang('Edit');
        $interbreadcrumb[] = [
            'url' => api_get_path(WEB_CODE_PATH).'ticket/status.php',
            'name' => get_lang('Status'),
        ];
        $url = api_get_self().'?action=edit&id='.$id;
        $form = TicketManager::getStatusForm($url);

        $item = TicketManager::getStatus($id);
        $form->setDefaults([
            'name' => $item->getTitle(),
            'description' => $item->getDescription(),
        ]);
        $formToString = $form->returnForm();
        if ($form->validate()) {
            $values = $form->getSubmitValues();

            $params = [
                'title' => $values['name'],
                'description' => $values['description'],
            ];
            TicketManager::updateStatus($id, $params);
            Display::addFlash(Display::return_message(get_lang('Update successful')));
            header('Location: '.api_get_self());
            exit;
        }

        break;
    default:
        break;
}

$user_id = api_get_user_id();
$isAdmin = api_is_platform_admin();

/**
 * Build the modify-column of the table.
 *
 * @param int    $id     The user id
 * @param string $params URL params to add to table links
 * @param array  $row    Row of elements to alter
 *
 * @return string Some HTML-code with modify-buttons
 */
function modify_filter($id, $params, $row)
{
    $id = $row['id'];
    $code = $row['code'];

    $result = Display::url(
        Display::getMdiIcon(ActionIcon::EDIT, 'ch-tool-icon', null, ICON_SIZE_SMALL, get_lang('Edit')),
        api_get_self()."?action=edit&id={$id}"
    );

    if (!in_array($code, TicketManager::getDefaultStatusList())) {
        $result .= Display::url(
            Display::getMdiIcon(ActionIcon::DELETE, 'ch-tool-icon', null, ICON_SIZE_SMALL, get_lang('Delete')),
            api_get_self()."?action=delete&id={$id}"
        );
    }

    return $result;
}

$table->set_header(0, '', false);
$table->set_header(1, get_lang('Title'), false);
$table->set_header(2, get_lang('Description'), true, ['style' => 'width:200px']);
$table->set_header(3, get_lang('Detail'), true);
$table->set_column_filter('3', 'modify_filter');

Display::display_header($toolName);

$items = [
    'icon' => 'new_folder.png',
    'url' => 'status.php?action=add',
    'content' => get_lang('Add status'),
];

$actions = Display::url(
    Display::getMdiIcon(ActionIcon::BACK, 'ch-tool-icon', null, ICON_SIZE_MEDIUM, get_lang('Tickets')),
    api_get_path(WEB_CODE_PATH).'ticket/tickets.php'
);
$sections = TicketManager::getSettingsMenuItems('status');
array_unshift($sections, $items);
foreach ($sections as $item) {
    $actions .= Display::url(
        Display::getMdiIcon($item['icon'], 'ch-tool-icon', null, ICON_SIZE_MEDIUM, $item['content']),
        $item['url']
    );
}
echo Display::toolbarAction('ticket', [$actions]);
echo $formToString;
echo $table->return_table();

Display::display_footer();
