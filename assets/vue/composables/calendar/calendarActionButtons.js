import { ref, watchEffect } from "vue"
import { storeToRefs } from "pinia"
import { useCidReqStore } from "../../store/cidReq"
import { usePlatformConfig } from "../../store/platformConfig"
import { useSecurityStore } from "../../store/securityStore"
import { checkIsAllowedToEdit } from "../userPermissions"

export function useCalendarActionButtons() {
  const cidReqStore = useCidReqStore()
  const platformConfigStore = usePlatformConfig()
  const securityStore = useSecurityStore()

  const { course } = storeToRefs(cidReqStore)

  const isAllowedToEdit = ref(false)
  checkIsAllowedToEdit(false, true).then((response) => (isAllowedToEdit.value = response))

  // TODO: Replace with real permissions/settings when available.
  const isAllowedToSessionEdit = false
  const courseAllowUserEditAgenda = "0"

  const showAddButton = ref(false)
  const showImportICalButton = ref(false)
  const showImportCourseEventsButton = ref(false)
  const showSessionPlanningButton = ref(false)
  const showMyStudentsScheduleButton = ref(false)
  const showAgendaListButton = ref(false)

  watchEffect(() => {
    // Reset flags on each reactive run to avoid stale UI state.
    showAddButton.value = false
    showImportICalButton.value = false
    showImportCourseEventsButton.value = false
    showSessionPlanningButton.value = false
    showMyStudentsScheduleButton.value = false
    showAgendaListButton.value = false

    const isPersonal = !course.value

    if (
      isAllowedToEdit.value ||
      (isPersonal &&
        securityStore.isAuthenticated &&
        "true" === platformConfigStore.getSetting("agenda.allow_personal_agenda")) ||
      ("1" === courseAllowUserEditAgenda && securityStore.isAuthenticated && isAllowedToSessionEdit)
    ) {
      showAddButton.value = true
      showImportICalButton.value = true

      if (course.value && isAllowedToEdit.value) {
        showImportCourseEventsButton.value = true
      }
    }

    if (securityStore.isAuthenticated) {
      showAgendaListButton.value = true
    }

    // Buttons that only make sense in personal agenda (outside courses)
    if (!course.value && securityStore.isAuthenticated) {
      showSessionPlanningButton.value = true

      if (securityStore.isStudentBoss || securityStore.isAdmin) {
        showMyStudentsScheduleButton.value = true
      }
    }
  })

  return {
    showAddButton,
    showImportICalButton,
    showImportCourseEventsButton,
    showSessionPlanningButton,
    showMyStudentsScheduleButton,
    showAgendaListButton,
  }
}
