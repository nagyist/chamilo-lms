<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Entity\CatalogueSessionRelAccessUrlRelUsergroup;
use Chamilo\CoreBundle\Entity\Session;
use Chamilo\CoreBundle\Entity\User;
use Chamilo\CoreBundle\Entity\Usergroup;
use Chamilo\CoreBundle\Framework\Container;

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

// setting the section (for the tabs)
$this_section = SECTION_PLATFORM_ADMIN;

$id = (int) $_GET['id'];
$currentView = $_GET['view'] ?? 'general';

$session = api_get_session_entity($id);
SessionManager::protectSession($session);

$tool_name = get_lang('Edit this session');

$interbreadcrumb[] = ['url' => 'session_list.php', 'name' => get_lang('Session list')];
$interbreadcrumb[] = ['url' => 'resume_session.php?id_session='.$id, 'name' => get_lang('Session overview')];

$categoriesList = SessionManager::get_all_session_category();

$categoriesOption = [
    '0' => get_lang('none'),
];

if (false != $categoriesList) {
    foreach ($categoriesList as $categoryItem) {
        $categoriesOption[$categoryItem['id']] = $categoryItem['title'];
    }
}

$tabs = [
    'general' => [
        'url' => 'session_edit.php?id='.$id,
        'content' => get_lang('Edit this session'),
    ],
    'catalogue_access' => [
        'url' => 'session_edit.php?id='.$id.'&view=catalogue_access',
        'content' => get_lang('Catalogue access'),
    ],
];

Display::display_header($tool_name);
echo Display::toolbarAction('toolbarCourseEdit', [Display::tabsOnlyLink($tabs, $currentView, 'session-edit-tabs')]);

if ('catalogue_access' === $currentView) {
    echo Display::div(
        get_lang('Select classes for which this session will be visible for subscription in the catalogue. Subscription rules still apply apart from it being visible in the catalogue.'),
        ['class' => 'alert alert-info']
    );

    $em = Database::getManager();
    $accessUrl = Container::getAccessUrlUtil()->getCurrent();
    $accessUrlId = $accessUrl->getId();
    $sessionEntity = $em->getRepository(Session::class)->find($id);

    if (!$accessUrl || !$sessionEntity) {
        echo Display::return_message(get_lang('Invalid access URL or session'), 'error');
        Display::display_footer();
        return;
    }

    $formCatalogue = new FormValidator(
        'form_catalogue_access',
        'post',
        api_get_self().'?id='.$id.'&view=catalogue_access'
    );

    $formCatalogue->addElement('header', $session->getTitle());

    $groupEntities = $em->createQueryBuilder()
        ->select('ug')
        ->from(\Chamilo\CoreBundle\Entity\Usergroup::class, 'ug')
        ->innerJoin('ug.urls', 'urlRel')
        ->where('urlRel.url = :accessUrl')
        ->setParameter('accessUrl', $accessUrl)
        ->orderBy('ug.title', 'ASC')
        ->getQuery()
        ->getResult();

    $groups = [];
    foreach ($groupEntities as $group) {
        $groups[$group->getId()] = $group->getTitle();
    }

    $existing = $em->getRepository(CatalogueSessionRelAccessUrlRelUsergroup::class)->findBy([
        'session' => $sessionEntity,
        'accessUrl' => $accessUrl,
    ]);

    $selected = [];
    foreach ($existing as $record) {
        if ($record->getUsergroup()) {
            $selected[] = $record->getUsergroup()->getId();
        }
    }

    $formCatalogue->addMultiSelect(
        'selected_usergroups',
        get_lang('User groups'),
        $groups,
        ['style' => 'width:100%;height:300px;']
    );

    $formCatalogue->setDefaults([
        'selected_usergroups' => $selected,
    ]);

    $formCatalogue->addButtonSave(get_lang('Save'));

    if ($formCatalogue->validate()) {
        $data = $formCatalogue->getSubmitValues();
        $newGroups = $data['selected_usergroups'] ?? [];

        foreach ($existing as $old) {
            $em->remove($old);
        }
        $em->flush();

        foreach ($newGroups as $groupId) {
            $group = $em->getRepository(Usergroup::class)->find((int) $groupId);
            if ($group) {
                $rel = new CatalogueSessionRelAccessUrlRelUsergroup();
                $rel->setSession($sessionEntity);
                $rel->setAccessUrl($accessUrl);
                $rel->setUsergroup($group);
                $em->persist($rel);
            }
        }

        $em->flush();

        Display::addFlash(Display::return_message(get_lang('Changes saved successfully'), 'confirmation'));
        header('Location: '.api_get_self().'?id='.$id.'&view=catalogue_access');
        exit();
    }
    $formCatalogue->display();
} else {
    $formAction = api_get_self().'?';
    $formAction .= http_build_query([
        'page' => Security::remove_XSS($_GET['page'] ?? ''),
        'id' => $id,
    ]);

    $form = new FormValidator('edit_session', 'post', $formAction);
    $form->addElement('header', $tool_name);
    $result = SessionManager::setForm($form, $session);
    $htmlHeadXtra[] = '
<script>
$(function() {
    '.$result['js'].'
});
</script>';

    $form->addButtonUpdate(get_lang('Edit this session'));
    $showValidityField = 'true' === api_get_setting('session.enable_auto_reinscription') || 'true' === api_get_setting('session.enable_session_replication');

    $formDefaults = [
        'id' => $session->getId(),
        'session_category' => $session->getCategory()?->getId(),
        'title' => $session->getTitle(),
        'description' => $session->getDescription(),
        'show_description' => $session->getShowDescription(),
        'duration' => $session->getDuration(),
        'session_visibility' => $session->getVisibility(),
        'display_start_date' => $session->getDisplayStartDate() ? api_get_local_time($session->getDisplayStartDate()) : null,
        'display_end_date' => $session->getDisplayEndDate() ? api_get_local_time($session->getDisplayEndDate()) : null,
        'access_start_date' => $session->getAccessStartDate() ? api_get_local_time($session->getAccessStartDate()) : null,
        'access_end_date' => $session->getAccessEndDate() ? api_get_local_time($session->getAccessEndDate()) : null,
        'coach_access_start_date' => $session->getCoachAccessStartDate() ? api_get_local_time($session->getCoachAccessStartDate()) : null,
        'coach_access_end_date' => $session->getCoachAccessEndDate() ? api_get_local_time($session->getCoachAccessEndDate()) : null,
        'send_subscription_notification' => $session->getSendSubscriptionNotification(),
        'notify_boss' => $session->getNotifyBoss(),
        'coach_username' => array_map(
            function (User $user) {
                return $user->getId();
            },
            $session->getGeneralCoaches()->getValues()
        ),
        'days_before_finishing_for_reinscription' => $session->getDaysToReinscription() ?? '',
        'days_before_finishing_to_create_new_repetition' => $session->getDaysToNewRepetition() ?? '',
        'last_repetition' => $session->getLastRepetition(),
        'parent_id' => $session->getParentId() ?? 0,
    ];

    if ($showValidityField) {
        $formDefaults['validity_in_days'] = $session->getValidityInDays();
    }

    $form->setDefaults($formDefaults);
    if ($currentView && $form->validate()) {
        $params = $form->getSubmitValues();

        $name = $params['title'];
        $startDate = $params['access_start_date'];
        $endDate = $params['access_end_date'];
        $displayStartDate = $params['display_start_date'];
        $displayEndDate = $params['display_end_date'];
        $coachStartDate = $params['coach_access_start_date'];
        $coachEndDate = $params['coach_access_end_date'];
        $coachUsername = $params['coach_username'];
        $id_session_category = $params['session_category'];
        $id_visibility = $params['session_visibility'];
        $duration = isset($params['duration']) ? $params['duration'] : null;
        if (1 == $params['access']) {
            $duration = null;
        }

        $description = $params['description'];
        $showDescription = isset($params['show_description']) ? 1 : 0;
        $sendSubscriptionNotification = isset($params['send_subscription_notification']);
        $isThisImageCropped = isset($params['picture_crop_result']);

        $extraFields = [];
        foreach ($params as $key => $value) {
            if (0 === strpos($key, 'extra_')) {
                $extraFields[$key] = $value;
            }
        }

        if (isset($extraFields['extra_image']) && $isThisImageCropped) {
            $extraFields['extra_image']['crop_parameters'] = $params['picture_crop_result'];
        }

        $status = $params['status'] ?? 0;
        $notifyBoss = isset($params['notify_boss']) ? 1 : 0;

        $parentId = $params['parent_id'] ?? 0;
        $daysBeforeFinishingForReinscription = $params['days_before_finishing_for_reinscription'] ?? null;
        $daysBeforeFinishingToCreateNewRepetition = $params['days_before_finishing_to_create_new_repetition'] ?? null;
        $lastRepetition = isset($params['last_repetition']);
        $validityInDays = $params['validity_in_days'] ?? null;

        $return = SessionManager::edit_session(
            $id,
            $name,
            $startDate,
            $endDate,
            $displayStartDate,
            $displayEndDate,
            $coachStartDate,
            $coachEndDate,
            $coachUsername,
            $id_session_category,
            $id_visibility,
            $description,
            $showDescription,
            $duration,
            $extraFields,
            null,
            $sendSubscriptionNotification,
            $status,
            $notifyBoss,
            $parentId,
            $daysBeforeFinishingForReinscription,
            $daysBeforeFinishingToCreateNewRepetition,
            $lastRepetition,
            $validityInDays
        );

        if ($return) {
            // Delete picture of session
            $deletePicture = $_POST['delete_picture'] ?? '';
            if ($deletePicture && $return) {
                SessionManager::deleteAsset($return);
            }

            // Add image
            $picture = $_FILES['picture'];
            if (!empty($picture['name'])) {
                SessionManager::updateSessionPicture(
                    $return,
                    $picture,
                    $params['picture_crop_result']
                );
            }

            Display::addFlash(Display::return_message(get_lang('Update successful')));
            header('Location: resume_session.php?id_session='.$return);
            exit();
        }
    }
    $form->display();
?>

<script>
$(function() {
<?php
echo $session->getDuration() > 0 ? 'accessSwitcher(0);' : 'accessSwitcher(1);';
?>
});

function accessSwitcher(accessFromReady) {
    var access = $('#access option:selected').val();

    if (accessFromReady >= 0) {
        access = accessFromReady;
        $('[name=access]').val(access);
    }
    if (access == 1) {
        $('#duration_div').hide();
        $('#date_fields').show();
        emptyDuration();
    } else {
        $('#duration_div').show();
        $('#date_fields').hide();
    }
}

function emptyDuration() {
    if ($('#duration').val()) {
        $('#duration').val('');
    }
}

$(function() {
    $('#show-options').on('click', function (e) {
        e.preventDefault();
        var display = $('#options').css('display');
        display === 'block' ? $('#options').slideUp() : $('#options').slideDown() ;
    });
});

</script>
<?php
}
Display::display_footer();
