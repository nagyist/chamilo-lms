<template>
  <div class="flex flex-col gap-4">
    <CalendarSectionHeader
      @addClick="goToAddEvent"
      @agendaListClick="goToAgendaList"
      @sessionPlanningClick="goToSessionsPlan"
      @myStudentsScheduleClick="goToMyStudentsSchedule"
    />

    <!-- Distinct header to avoid confusion with global agenda -->
    <div class="rounded border bg-gray-10 p-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <div class="font-semibold text-gray-90">
            {{ t("My students schedule") }}
          </div>
          <div class="text-sm text-gray-90">
            {{ t("Tutor view (session-wide scope)") }}
          </div>
          <div class="text-xs text-gray-90 mt-1">
            {{ t("Exception: as a coach in a session, you can see all session events, not only your courses.") }}
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-1 rounded border bg-white text-xs text-gray-90">
            {{ t("Read only") }}
          </span>
          <span class="px-2 py-1 rounded border bg-white text-xs text-gray-90">
            {{ t("Includes assignments deadlines") }}
          </span>
          <span class="px-2 py-1 rounded border bg-white text-xs text-gray-90">
            {{ t("Session scope") }}
          </span>
        </div>
      </div>
    </div>

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
      <div class="w-full md:w-[420px]">
        <BaseSelect
          id="my-students-session"
          v-model="selectedSessionId"
          :label="t('Session')"
          :options="sessionOptions"
          option-label="name"
          option-value="id"
          :allow-clear="true"
        />
      </div>

      <!-- Mode switch to look different from global agenda -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="px-3 py-2 rounded border text-sm"
          :class="viewMode === 'timeline' ? 'bg-black text-white border-black' : 'bg-white text-gray-90'"
          @click="setViewMode('timeline')"
        >
          {{ t("Timeline") }}
        </button>
        <button
          type="button"
          class="px-3 py-2 rounded border text-sm"
          :class="viewMode === 'calendar' ? 'bg-black text-white border-black' : 'bg-white text-gray-90'"
          @click="setViewMode('calendar')"
        >
          {{ t("Calendar") }}
        </button>

        <div class="flex items-center gap-3 ml-0 md:ml-3 text-sm text-gray-90">
          <label class="inline-flex items-center gap-2">
            <input
              v-model="filterAssignments"
              type="checkbox"
              class="accent-black"
            />
            <span>{{ t("Assignments") }}</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input
              v-model="filterEvents"
              type="checkbox"
              class="accent-black"
            />
            <span>{{ t("Events") }}</span>
          </label>
        </div>
      </div>
    </div>

    <div
      v-if="errorMessage"
      class="p-3 border rounded bg-white text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <!-- TIMELINE MODE (default): clearly different from the global agenda list -->
    <div
      v-if="viewMode === 'timeline'"
      class="border rounded bg-white relative overflow-hidden"
    >
      <div
        v-if="isLoading"
        class="absolute inset-0 z-10 bg-white/70 flex items-center justify-center"
      >
        <div class="flex items-center gap-3 text-gray-90">
          <i class="pi pi-spin pi-spinner text-2xl" />
          <span class="text-sm">{{ t("Loading") }}</span>
        </div>
      </div>

      <div class="p-4 border-b bg-white">
        <div class="text-sm text-gray-90 font-semibold">
          {{ t("Schedule timeline") }}
        </div>
        <div class="text-xs text-gray-90 mt-1">
          {{ t("Grouped by day for the selected session.") }}
        </div>
      </div>

      <div
        v-if="!isLoading && timelineGroups.length === 0"
        class="p-4 text-sm text-gray-90"
      >
        {{ t("No events found") }}
      </div>

      <div
        v-else
        class="divide-y"
      >
        <div
          v-for="g in timelineGroups"
          :key="g.date"
          class="p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="font-semibold text-gray-90">
              {{ g.label }}
            </div>
            <div class="text-xs text-gray-25">{{ g.items.length }} {{ t("items") }}</div>
          </div>

          <div class="mt-3 flex flex-col gap-2">
            <button
              v-for="ev in g.items"
              :key="ev.id"
              type="button"
              class="text-left rounded border px-3 py-2 hover:bg-gray-50"
              @click="openEvent(ev)"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span
                      class="inline-block w-2.5 h-2.5 rounded-full mt-0.5"
                      :style="{ background: ev.color || '#4682B4' }"
                    />
                    <div class="font-semibold text-gray-90 truncate">
                      {{ ev.title }}
                    </div>
                  </div>

                  <div class="text-xs text-gray-90 mt-1">
                    <span class="font-mono">{{ ev.whenLabel }}</span>
                    <span v-if="ev.courseTitle"> • {{ ev.courseTitle }}</span>
                  </div>
                </div>

                <div class="flex flex-col items-end gap-1">
                  <span
                    class="px-2 py-0.5 rounded text-xs border"
                    :class="
                      ev.objectType === 'assignment'
                        ? 'bg-orange-50 border-orange-200 text-orange-800'
                        : 'bg-blue-50 border-blue-200 text-blue-800'
                    "
                  >
                    {{ ev.objectType === "assignment" ? t("Assignment") : t("Event") }}
                  </span>

                  <span
                    v-if="ev.allDay"
                    class="px-2 py-0.5 rounded text-[11px] border bg-white text-gray-90"
                  >
                    {{ t("All day") }}
                  </span>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CALENDAR MODE (optional) -->
    <div
      v-else
      class="border rounded bg-white relative overflow-hidden"
    >
      <div
        v-if="isLoading"
        class="absolute inset-0 z-10 bg-white/70 flex items-center justify-center"
      >
        <div class="flex items-center gap-3 text-gray-90">
          <i class="pi pi-spin pi-spinner text-2xl" />
          <span class="text-sm">{{ t("Loading") }}</span>
        </div>
      </div>

      <FullCalendar
        ref="cal"
        :options="calendarOptions"
      />
    </div>

    <Dialog
      v-model:visible="dialogShow"
      :header="t('Event')"
      :modal="true"
      :style="{ width: '35rem' }"
    >
      <div
        v-if="clickedEvent"
        class="flex flex-col gap-2"
      >
        <h5 class="font-semibold">
          {{ clickedEvent.title }}
        </h5>

        <p v-if="clickedEvent.start">{{ t("Date") }}: {{ clickedEvent.start }}</p>

        <p v-if="clickedEvent.extendedProps?.courseTitle">
          {{ t("Course") }}: {{ clickedEvent.extendedProps.courseTitle }}
        </p>

        <p v-if="clickedEvent.extendedProps?.objectType">
          {{ t("Type") }}: {{ clickedEvent.extendedProps.objectType }}
        </p>
      </div>

      <template #footer>
        <BaseButton
          :label="t('Cancel')"
          icon="close"
          type="black"
          @click="dialogShow = false"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, ref, computed, watch } from "vue"
import { useI18n } from "vue-i18n"
import { DateTime } from "luxon"
import { useRoute, useRouter } from "vue-router"

import FullCalendar from "@fullcalendar/vue3"
import dayGridPlugin from "@fullcalendar/daygrid"
import interactionPlugin from "@fullcalendar/interaction"
import timeGridPlugin from "@fullcalendar/timegrid"
import allLocales from "@fullcalendar/core/locales-all"

import Dialog from "primevue/dialog"
import BaseButton from "../../components/basecomponents/BaseButton.vue"
import BaseSelect from "../../components/basecomponents/BaseSelect.vue"
import CalendarSectionHeader from "../../components/ccalendarevent/CalendarSectionHeader.vue"
import { useLocale, useParentLocale } from "../../composables/locale"
import { useFormatDate } from "../../composables/formatDate"

const { t } = useI18n()
const { appLocale } = useLocale()
const { getCurrentTimezone } = useFormatDate()

const route = useRoute()
const router = useRouter()

const selectedSessionId = ref(route.query.sid ? Number(route.query.sid) : null)
const sessionOptions = ref([])

const viewMode = ref("timeline") // default: clearly different from global agenda
const filterAssignments = ref(true)
const filterEvents = ref(true)

const isLoading = ref(false)
const errorMessage = ref("")

const dialogShow = ref(false)
const clickedEvent = ref(null)
const cal = ref(null)

const timelineRawEvents = ref([])

const calendarLocale = computed(() => {
  return allLocales.find(
    (calLocale) =>
      calLocale.code === appLocale.value.replace("_", "-") || calLocale.code === useParentLocale(appLocale.value),
  )
})

function parseInitialView(value) {
  const allowed = new Set(["dayGridMonth", "timeGridWeek", "timeGridDay"])
  const v = String(value || "")
  return allowed.has(v) ? v : "dayGridMonth"
}

function parseInitialDate(value) {
  const v = String(value || "")
  const dt = DateTime.fromISO(v)
  return dt.isValid ? dt.toISODate() : DateTime.now().toISODate()
}

function goToSessionsPlan() {
  router.push({ name: "CalendarSessionsPlan", query: { ...route.query } }).catch(() => {})
}
function goToMyStudentsSchedule() {
  router.push({ name: "CalendarMyStudentsSchedule", query: { ...route.query } }).catch(() => {})
}
function goToAgendaList() {
  router.push({ name: "CCalendarEventListView", query: { ...route.query } }).catch(() => {})
}
function goToAddEvent() {
  router.push({ name: "CCalendarEventList", query: { ...route.query, openAdd: "1" } }).catch(() => {})
}

function syncSidToQuery(nextSid) {
  const nextQuery = { ...route.query }
  if (!nextSid) {
    delete nextQuery.sid
  } else {
    nextQuery.sid = String(nextSid)
  }
  router
    .replace({ name: route.name ?? "CalendarMyStudentsSchedule", params: route.params, query: nextQuery })
    .catch(() => {})
}

function setViewMode(mode) {
  viewMode.value = mode
  if (mode === "timeline") {
    refreshTimelineEvents().catch(() => {})
  } else if (mode === "calendar" && cal.value?.getApi) {
    cal.value.getApi().refetchEvents()
  }
}

function getRangeForTimeline() {
  const baseDate = DateTime.fromISO(parseInitialDate(route.query.date))
  const view = parseInitialView(route.query.view)

  if (view === "timeGridDay") {
    const start = baseDate.startOf("day")
    const end = baseDate.endOf("day").plus({ seconds: 1 })
    return { start: start.toISO(), end: end.toISO() }
  }

  if (view === "timeGridWeek") {
    const start = baseDate.startOf("week")
    const end = baseDate.endOf("week").plus({ seconds: 1 })
    return { start: start.toISO(), end: end.toISO() }
  }

  // dayGridMonth
  const start = baseDate.startOf("month")
  const end = baseDate.endOf("month").plus({ seconds: 1 })
  return { start: start.toISO(), end: end.toISO() }
}

async function fetchCoachSessions() {
  try {
    isLoading.value = true
    errorMessage.value = ""

    const resp = await fetch("/api/calendar/my-students-schedule", {
      method: "GET",
      headers: { Accept: "application/json" },
    })

    if (!resp.ok) {
      const text = await resp.text().catch(() => "")

      console.error("[MyStudentsSchedule] Sessions request failed", resp.status, text)
      errorMessage.value = t("Failed to load sessions")
      sessionOptions.value = []
      return
    }

    const items = await resp.json()
    sessionOptions.value = Array.isArray(items) ? items : []

    if (!selectedSessionId.value && sessionOptions.value.length > 0) {
      selectedSessionId.value = sessionOptions.value[0].id
    }
  } catch (e) {
    console.error("[MyStudentsSchedule] Sessions unexpected error", e)
    errorMessage.value = t("Failed to load sessions")
    sessionOptions.value = []
  } finally {
    isLoading.value = false
  }
}

function normalizeObjectType(item) {
  const type = item?.extendedProps?.objectType || item?.objectType || ""
  return String(type)
}

function passesFilters(item) {
  const type = normalizeObjectType(item)
  if (type === "assignment") return filterAssignments.value
  return filterEvents.value
}

async function refreshTimelineEvents() {
  if (!selectedSessionId.value) {
    timelineRawEvents.value = []
    return
  }

  try {
    isLoading.value = true
    errorMessage.value = ""

    const { start, end } = getRangeForTimeline()

    const url =
      `/api/calendar/my-students-schedule` +
      `?sid=${encodeURIComponent(String(selectedSessionId.value))}` +
      `&start=${encodeURIComponent(start)}` +
      `&end=${encodeURIComponent(end)}`

    const resp = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
    })

    if (!resp.ok) {
      const text = await resp.text().catch(() => "")

      console.error("[MyStudentsSchedule] Timeline request failed", resp.status, text)
      errorMessage.value = resp.status === 403 ? t("Not allowed") : t("Failed to load events")
      timelineRawEvents.value = []
      return
    }

    const items = await resp.json()
    timelineRawEvents.value = Array.isArray(items) ? items : []
  } catch (e) {
    console.error("[MyStudentsSchedule] Timeline unexpected error", e)
    errorMessage.value = t("Failed to load events")
    timelineRawEvents.value = []
  } finally {
    isLoading.value = false
  }
}

async function loadEvents(info, successCallback) {
  if (!selectedSessionId.value) {
    successCallback([])
    return
  }

  try {
    errorMessage.value = ""

    const url =
      `/api/calendar/my-students-schedule` +
      `?sid=${encodeURIComponent(String(selectedSessionId.value))}` +
      `&start=${encodeURIComponent(info.startStr)}` +
      `&end=${encodeURIComponent(info.endStr)}`

    const resp = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
    })

    if (!resp.ok) {
      const text = await resp.text().catch(() => "")

      console.error("[MyStudentsSchedule] Calendar request failed", resp.status, text)

      errorMessage.value = resp.status === 403 ? t("Not allowed") : t("Failed to load events")
      successCallback([])
      return
    }

    const items = await resp.json()
    const arr = Array.isArray(items) ? items : []
    successCallback(arr.filter(passesFilters))
  } catch (e) {
    console.error("[MyStudentsSchedule] Calendar unexpected error", e)
    errorMessage.value = t("Failed to load events")
    successCallback([])
  }
}

function openEvent(ev) {
  clickedEvent.value = {
    id: ev.id,
    title: ev.title,
    start: ev.start,
    extendedProps: ev.extendedProps || {},
  }
  dialogShow.value = true
}

const timezone = getCurrentTimezone()

const timelineGroups = computed(() => {
  const items = (timelineRawEvents.value || []).filter(passesFilters)

  const map = new Map()

  for (const it of items) {
    const start = it.allDay ? String(it.start || "") : String(it.start || "")
    const dt = DateTime.fromISO(start, { zone: timezone })
    const dayKey = dt.isValid ? dt.toISODate() : String(it.start || "unknown")

    if (!map.has(dayKey)) map.set(dayKey, [])
    map.get(dayKey).push(it)
  }

  const sortedKeys = Array.from(map.keys()).sort()

  return sortedKeys.map((key) => {
    const dayDt = DateTime.fromISO(key, { zone: timezone })
    const label = dayDt.isValid ? dayDt.toLocaleString(DateTime.DATE_FULL) : key

    const normalized = map.get(key).map((it) => {
      const type = normalizeObjectType(it)
      const startDt = DateTime.fromISO(String(it.start || ""), { zone: timezone })
      const endDt = DateTime.fromISO(String(it.end || ""), { zone: timezone })

      let whenLabel = ""
      if (it.allDay) {
        whenLabel = t("All day")
      } else if (startDt.isValid && endDt.isValid) {
        whenLabel = `${startDt.toFormat("HH:mm")} - ${endDt.toFormat("HH:mm")}`
      } else if (startDt.isValid) {
        whenLabel = startDt.toFormat("HH:mm")
      } else {
        whenLabel = "—"
      }

      return {
        id: it.id,
        title: it.title,
        start: it.start,
        end: it.end,
        allDay: Boolean(it.allDay),
        color: it.color,
        objectType: type,
        courseTitle: it?.extendedProps?.courseTitle || null,
        whenLabel,
        extendedProps: it.extendedProps || {},
      }
    })

    normalized.sort((a, b) => {
      if (a.allDay && !b.allDay) return -1
      if (!a.allDay && b.allDay) return 1
      return String(a.start || "").localeCompare(String(b.start || ""))
    })

    return { date: key, label, items: normalized }
  })
})

watch(
  () => selectedSessionId.value,
  (sid) => {
    syncSidToQuery(sid)
    if (viewMode.value === "timeline") {
      refreshTimelineEvents().catch(() => {})
    } else if (cal.value?.getApi) {
      cal.value.getApi().refetchEvents()
    }
  },
)

watch([filterAssignments, filterEvents], () => {
  if (viewMode.value === "timeline") {
    // computed will re-render; no network needed
    return
  }
  if (cal.value?.getApi) {
    cal.value.getApi().refetchEvents()
  }
})

watch(
  () => calendarLocale.value?.code,
  (code) => {
    calendarOptions.value.locale = code ?? "en-GB"
  },
)

watch(
  () => [route.query.date, route.query.view],
  () => {
    if (viewMode.value === "timeline") {
      refreshTimelineEvents().catch(() => {})
    }
  },
)

const calendarOptions = ref({
  timeZone: timezone,
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locales: allLocales,
  locale: calendarLocale.value?.code ?? "en-GB",
  headerToolbar: {
    left: "today prev,next",
    center: "title",
    right: "dayGridMonth,timeGridWeek,timeGridDay",
  },
  nowIndicator: true,
  initialView: parseInitialView(route.query.view),
  initialDate: parseInitialDate(route.query.date),
  selectable: false,
  editable: false,
  eventClick(info) {
    info.jsEvent.preventDefault()
    const ev = info.event
    clickedEvent.value = {
      id: ev.id,
      title: ev.title,
      start: ev.startStr,
      extendedProps: ev.extendedProps,
    }
    dialogShow.value = true
  },
  events: loadEvents,
})

onMounted(async () => {
  await fetchCoachSessions()
  await refreshTimelineEvents()
})
</script>
