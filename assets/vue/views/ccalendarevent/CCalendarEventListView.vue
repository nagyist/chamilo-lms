<template>
  <div class="flex flex-col gap-4">
    <CalendarSectionHeader
      active-view="list"
      @addClick="goToAddEvent"
      @agendaListClick="goToCalendar"
      @sessionPlanningClick="goToSessionsPlan"
      @myStudentsScheduleClick="goToMyStudentsSchedule"
    />

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="px-2 py-1 rounded border bg-white text-sm text-gray-90">
          {{ t("Events list") }}
        </span>
        <span class="px-2 py-1 rounded border bg-white text-sm text-gray-90">
          {{ rangeLabel }}
        </span>
        <span class="px-2 py-1 rounded border bg-white text-sm text-gray-50">
          {{ t("Events") }}: {{ filteredEvents.length }}
        </span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <BaseButton
          :label="t('Previous')"
          icon="chevron-left"
          type="black"
          @click="shiftRange(-1)"
        />
        <BaseButton
          :label="t('Today')"
          icon="calendar"
          type="black"
          @click="goToday"
        />
        <BaseButton
          :label="t('Next')"
          icon="chevron-right"
          type="black"
          @click="shiftRange(1)"
        />
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="f in filters"
        :key="f.key"
        class="px-3 py-1 rounded border text-sm"
        :class="activeFilter === f.key ? 'bg-black text-white border-black' : 'bg-white text-gray-90'"
        @click="activeFilter = f.key"
      >
        {{ f.label }}
      </button>
    </div>

    <!-- Results -->
    <div class="border rounded overflow-hidden bg-white relative">
      <!-- Local overlay loading (not full screen) -->
      <div
        v-if="isLoading"
        class="absolute inset-0 z-10 bg-white/70 flex items-center justify-center"
      >
        <div class="flex items-center gap-3 text-gray-90">
          <i class="pi pi-spin pi-spinner text-2xl" />
          <span class="text-sm">{{ t("Loading") }}</span>
        </div>
      </div>

      <!-- Inline skeleton loading inside results (more friendly) -->
      <div
        v-if="showSkeleton"
        class="p-4"
      >
        <!-- Skeleton placeholders while loading results -->
        <div
          v-for="d in skeletonDaysCount"
          :key="`sk-day-${d}`"
          class="border-b last:border-b-0"
        >
          <div class="px-4 py-3 bg-gray-20 font-semibold flex items-center gap-3">
            <div class="h-4 w-40 bg-gray-30 rounded animate-pulse" />
          </div>

          <div class="p-4 flex flex-col gap-3">
            <div
              v-for="i in skeletonItemsPerDay"
              :key="`sk-item-${d}-${i}`"
              class="rounded border overflow-hidden"
            >
              <div class="flex">
                <div class="w-2 bg-gray-30 animate-pulse" />
                <div class="flex-1 p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="h-4 w-64 bg-gray-30 rounded animate-pulse" />
                    <div class="h-3 w-24 bg-gray-30 rounded animate-pulse" />
                  </div>

                  <div class="mt-3 h-3 w-5/6 bg-gray-30 rounded animate-pulse" />
                  <div class="mt-2 h-3 w-2/3 bg-gray-30 rounded animate-pulse" />

                  <div class="mt-3 flex flex-wrap items-center gap-2">
                    <div class="h-6 w-20 bg-gray-30 rounded animate-pulse" />
                    <div class="h-6 w-28 bg-gray-30 rounded animate-pulse" />
                    <div class="h-6 w-24 bg-gray-30 rounded animate-pulse" />
                    <div class="h-6 w-16 bg-gray-30 rounded animate-pulse" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="groupedDays.length === 0 && !isLoading && !showSkeleton"
        class="p-4 text-sm text-gray-50"
      >
        {{ t("No events found") }}
      </div>

      <div
        v-for="day in groupedDays"
        :key="day.key"
        class="border-b last:border-b-0"
      >
        <div class="px-4 py-3 bg-gray-20 font-semibold">
          {{ day.label }}
        </div>

        <div class="p-4 flex flex-col gap-3">
          <div
            v-for="ev in day.items"
            :key="ev.key"
            class="rounded border overflow-hidden"
          >
            <div class="flex">
              <div
                class="w-2"
                :style="{ background: ev.color }"
              />
              <div class="flex-1 p-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="font-semibold">
                    <a
                      v-if="ev.url"
                      :href="ev.url"
                      class="hover:underline"
                    >
                      {{ ev.title }}
                    </a>
                    <span v-else>{{ ev.title }}</span>
                  </div>
                  <div class="text-sm text-gray-50 whitespace-nowrap">
                    {{ ev.range }}
                  </div>
                </div>

                <div
                  v-if="ev.content"
                  class="mt-2 text-sm text-gray-90"
                >
                  {{ ev.content }}
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-50">
                  <span
                    v-if="ev.scope"
                    class="px-2 py-1 border rounded bg-white"
                  >
                    {{ ev.scope }}
                  </span>

                  <span
                    v-if="ev.course"
                    class="px-2 py-1 border rounded bg-white"
                  >
                    {{ t("Course") }}: {{ ev.course }}
                  </span>

                  <span
                    v-if="ev.session"
                    class="px-2 py-1 border rounded bg-white"
                  >
                    {{ t("Session") }}: {{ ev.session }}
                  </span>

                  <span
                    v-if="ev.type"
                    class="px-2 py-1 border rounded bg-white"
                  >
                    {{ t("Type") }}: {{ ev.type }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /Results -->
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue"
import { useI18n } from "vue-i18n"
import { useRoute, useRouter } from "vue-router"
import { DateTime } from "luxon"
import { storeToRefs } from "pinia"

import CalendarSectionHeader from "../../components/ccalendarevent/CalendarSectionHeader.vue"
import BaseButton from "../../components/basecomponents/BaseButton.vue"

import { useCidReqStore } from "../../store/cidReq"
import { useFormatDate } from "../../composables/formatDate"
import { useLocale } from "../../composables/locale"
import { useCalendarEvent } from "../../composables/calendar/calendarEvent"

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const cidReqStore = useCidReqStore()
const { course, session, group } = storeToRefs(cidReqStore)

const { getCurrentTimezone } = useFormatDate()
const { appLocaleTag } = useLocale()

const { getCalendarEvents } = useCalendarEvent()

const timezone = getCurrentTimezone()

// Prevent locale-related errors from breaking rendering.
function safeToLocaleString(dt, fmt) {
  if (!dt) return ""
  try {
    return dt.setLocale(appLocaleTag.value).toLocaleString(fmt)
  } catch (e) {
    // Fallback: never break rendering
    return dt.setLocale("en").toLocaleString(fmt)
  }
}

function dtWithLocale(dt) {
  // Keep this helper for places where a DateTime is needed with locale applied.
  if (!dt) return dt
  try {
    return dt.setLocale(appLocaleTag.value)
  } catch (e) {
    return dt.setLocale("en")
  }
}

function parseQueryDate(value) {
  if (!value) return DateTime.now().setZone(timezone).startOf("day")
  const dt = DateTime.fromISO(String(value), { zone: timezone })
  return dt.isValid ? dt.startOf("day") : DateTime.now().setZone(timezone).startOf("day")
}

function syncDateToQuery(dt) {
  const dateStr = dt.toISODate()
  if (!dateStr) return
  if (route.query.date === dateStr) return

  const nextQuery = { ...route.query, date: dateStr }
  router
    .replace({ name: route.name ?? "CCalendarEventListView", params: route.params, query: nextQuery })
    .catch(() => {})
}

/**
 * We keep "view" in query to make the list range consistent with FullCalendar.
 * Expected values: dayGridMonth, timeGridWeek, timeGridDay (or similar).
 */
const viewType = computed(() => String(route.query.view || "timeGridWeek"))
const viewMode = computed(() => {
  const v = viewType.value
  if (v.includes("Month")) return "month"
  if (v.includes("Day")) return "day"
  return "week"
})

const anchor = ref(parseQueryDate(route.query.date))
watch(
  () => route.query.date,
  (d) => {
    anchor.value = parseQueryDate(d)
  },
)

const activeFilter = ref("all")

const filters = computed(() => [
  { key: "all", label: t("All") },
  { key: "personal", label: t("Personal") },
  { key: "course", label: t("Course") },
  { key: "session", label: t("Session") },
  { key: "assignment", label: t("Assignments") },
])

function goToday() {
  // Keep current view mode but jump to today.
  anchor.value = DateTime.now().setZone(timezone).startOf("day")
  syncDateToQuery(anchor.value)
}

function shiftRange(direction) {
  // direction is -1 or +1
  // Month view: move 1 month, Week view: move 1 week, Day view: move 1 day
  if (viewMode.value === "month") {
    anchor.value = anchor.value.plus({ months: direction }).startOf("day")
  } else if (viewMode.value === "day") {
    anchor.value = anchor.value.plus({ days: direction }).startOf("day")
  } else {
    anchor.value = anchor.value.plus({ days: 7 * direction }).startOf("day")
  }

  syncDateToQuery(anchor.value)
}

const rangeStart = computed(() => {
  const dt = anchor.value.setZone(timezone)

  if (viewMode.value === "month") {
    // Align with calendar-like range (full weeks for the month)
    return dt.startOf("month").startOf("week")
  }

  if (viewMode.value === "day") {
    return dt.startOf("day")
  }

  return dt.startOf("week")
})

const rangeEnd = computed(() => {
  const dt = anchor.value.setZone(timezone)

  if (viewMode.value === "month") {
    return dt.endOf("month").endOf("week")
  }

  if (viewMode.value === "day") {
    return dt.endOf("day")
  }

  return dt.endOf("week")
})

// Same as FullCalendar fetch semantics (end exclusive)
const apiRangeStart = computed(() => rangeStart.value.startOf("day"))
const apiRangeEnd = computed(() => rangeEnd.value.plus({ days: 1 }).startOf("day"))

const rangeLabel = computed(() => {
  const start = rangeStart.value
  const end = rangeEnd.value

  // Month view: show month + year based on anchor (not rangeStart which can be previous month week)
  if (viewMode.value === "month") {
    // Comment: Luxon supports Intl options object.
    return safeToLocaleString(anchor.value, { month: "long", year: "numeric" })
  }

  // Day view: single day label
  if (viewMode.value === "day") {
    return safeToLocaleString(start, DateTime.DATE_FULL)
  }

  // Week view: start — end
  const a = safeToLocaleString(start, DateTime.DATE_MED)
  const b = safeToLocaleString(end, DateTime.DATE_MED)
  return `${a} — ${b}`
})

function goToSessionsPlan() {
  router.push({ name: "CalendarSessionsPlan", query: { ...route.query } }).catch(() => {})
}

function goToMyStudentsSchedule() {
  router.push({ name: "CalendarMyStudentsSchedule", query: { ...route.query } }).catch(() => {})
}

function goToCalendar() {
  // Keep the same date and view when switching back to calendar.
  const nextQuery = { ...route.query, date: anchor.value.toISODate(), view: viewType.value }
  router.push({ name: "CCalendarEventList", query: nextQuery }).catch(() => {})
}

function goToAddEvent() {
  router
    .push({
      name: "CCalendarEventList",
      query: { ...route.query, date: anchor.value.toISODate(), view: viewType.value, openAdd: "1" },
    })
    .catch(() => {})
}

const isLoading = ref(false)
const rawEvents = ref([])

// Skeleton settings for a friendly inline loading state.
const skeletonDaysCount = 3
const skeletonItemsPerDay = 2
const showSkeleton = computed(() => isLoading.value && groupedDays.value.length === 0)

function computeCommonParams() {
  const commonParams = {}

  if (course.value) {
    commonParams.cid = course.value.id
  }

  if (session.value) {
    commonParams.sid = session.value.id
  }

  if (route.query?.type === "global") {
    commonParams.type = "global"
  }

  const gidFromRoute = Number(route.query.gid ?? 0)
  const gidFromStore = Number(group.value?.id ?? 0)
  const effectiveGid = gidFromStore > 0 ? gidFromStore : gidFromRoute

  if (effectiveGid > 0) {
    commonParams.gid = effectiveGid
  }

  return commonParams
}

async function loadEvents() {
  try {
    isLoading.value = true
    const params = computeCommonParams()
    const events = await getCalendarEvents(apiRangeStart.value.toJSDate(), apiRangeEnd.value.toJSDate(), params)
    rawEvents.value = Array.isArray(events) ? events : []
  } catch (e) {
    console.error("[CalendarList] Failed to load events", e)
    rawEvents.value = []
  } finally {
    isLoading.value = false
  }
}

const HEX6 = /^#([0-9a-f]{6})$/i
const HEX3 = /^#([0-9a-f]{3})$/i

function normalizeHex(c) {
  if (!c) return null
  const s = String(c).trim()
  if (HEX6.test(s)) return s.toUpperCase()
  const m3 = s.match(HEX3)
  if (m3) {
    const [r, g, b] = m3[1].toUpperCase().split("")
    return `#${r}${r}${g}${g}${b}${b}`
  }
  const mRgb = s.match(/rgba?\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i)
  if (mRgb) {
    const r = Math.min(255, +mRgb[1])
    const g = Math.min(255, +mRgb[2])
    const b = Math.min(255, +mRgb[3])
    return `#${r.toString(16).padStart(2, "0")}${g.toString(16).padStart(2, "0")}${b.toString(16).padStart(2, "0")}`.toUpperCase()
  }
  return null
}

function defaultColorByContext(ctx) {
  return ctx === "global" ? "#FF0000" : ctx === "course" ? "#458B00" : ctx === "session" ? "#00496D" : "#4682B4"
}

function toLuxon(value) {
  if (!value) return null

  // Date instance
  if (value instanceof Date) {
    return DateTime.fromJSDate(value, { zone: timezone })
  }

  // Numeric timestamp (seconds or milliseconds)
  if (typeof value === "number" && Number.isFinite(value)) {
    const isMillis = value > 1e12
    return isMillis ? DateTime.fromMillis(value, { zone: timezone }) : DateTime.fromSeconds(value, { zone: timezone })
  }

  // String: ISO date, ISO datetime, or numeric timestamp string
  if (typeof value === "string") {
    const s = value.trim()

    if (/^\d{10,13}$/.test(s)) {
      const n = Number(s)
      const isMillis = n > 1e12
      return isMillis ? DateTime.fromMillis(n, { zone: timezone }) : DateTime.fromSeconds(n, { zone: timezone })
    }

    const iso = s.length === 10 ? `${s}T00:00:00` : s
    const dt = DateTime.fromISO(iso, { zone: timezone })
    return dt.isValid ? dt : null
  }

  // Object with toISOString()
  if (typeof value === "object" && typeof value.toISOString === "function") {
    const dt = DateTime.fromISO(value.toISOString(), { zone: timezone })
    return dt.isValid ? dt : null
  }

  return null
}

function overlapsRange(start, end) {
  if (!start) return false
  const e = end || start
  return start < apiRangeEnd.value && e > apiRangeStart.value
}

function scopeLabel(scope) {
  if (!scope) return null
  const s = String(scope).toLowerCase()
  if (s === "personal") return t("Personal")
  if (s === "course") return t("Course")
  if (s === "session") return t("Session")
  if (s === "global") return t("Global")
  return scope
}

function extractCourse(ep) {
  return ep?.course?.code || ep?.course?.title || ep?.courseCode || ep?.courseTitle || (ep?.cid ? String(ep.cid) : null)
}

function extractSession(ep) {
  return (
    ep?.session?.name || ep?.session?.title || ep?.sessionName || ep?.sessionTitle || (ep?.sid ? String(ep.sid) : null)
  )
}

function extractType(ep) {
  return (
    ep?.resourceNode?.resourceType?.name || ep?.resourceNode?.resourceType || ep?.objectType || ep?.eventType || null
  )
}

function mapToBaseItem(e) {
  const ep = e?.extendedProps ?? {}

  const scopeRaw = ep?.type ?? ep?.scope ?? null
  const typeValue = extractType(ep)

  const objectTypeRaw = ep?.objectType ?? ep?.["objectType"] ?? null
  const objectType = String(objectTypeRaw || "").toLowerCase()
  const typeLower = String(typeValue || "").toLowerCase()
  const scopeLower = String(scopeRaw || "").toLowerCase()
  const title = e?.title || ep?.title || ""

  // Prefer the most "FullCalendar-like" keys first
  let start =
    toLuxon(e?.start) ||
    toLuxon(e?.startStr) ||
    toLuxon(e?.startDate) ||
    toLuxon(ep?.startDate) ||
    toLuxon(ep?.start) ||
    null

  let end =
    toLuxon(e?.end) || toLuxon(e?.endStr) || toLuxon(e?.endDate) || toLuxon(ep?.endDate) || toLuxon(ep?.end) || null

  // fix inverted ranges caused by parsing/timezone issues
  if (start && end && end < start) {
    end = start.plus({ days: 1 })
  }

  // Robust session detection
  const looksLikeSession =
    objectType === "session" ||
    typeLower === "session" ||
    scopeLower === "session" ||
    String(title).toLowerCase().startsWith("session")

  // If duration is >= 1 day, treat as all-day
  const durationDays = start && end ? end.diff(start, "days").days : 0
  const looksMultiDay = Number.isFinite(durationDays) && durationDays >= 1

  // Force all-day for sessions and multi-day events
  const allDay =
    Boolean(e?.allDay === true) ||
    Boolean(ep?.allDay === true) ||
    looksLikeSession ||
    looksMultiDay ||
    (typeof e?.start === "string" && String(e.start).length === 10)

  // Normalize all-day dates to midnight to avoid confusing time ranges
  if (allDay && start) {
    start = start.startOf("day")
    if (end) end = end.startOf("day")
  }

  // If an all-day event has no (valid) end, give it a minimal exclusive end
  if (allDay && start) {
    if (!end || end <= start) {
      end = start.plus({ days: 1 })
    }
  }

  const rawColor = ep?.color ?? e?.backgroundColor ?? e?.borderColor ?? e?.color ?? null
  const color = normalizeHex(rawColor) || defaultColorByContext(scopeRaw || "personal")

  const content = ep?.content || ep?.description || ""

  return {
    keyBase: e?.id || ep?.["@id"] || `${title}-${start?.toISO() || ""}`,
    title,
    content,
    color,
    url: e?.url || ep?.url || null,
    scope: scopeLabel(scopeRaw),
    course: extractCourse(ep),
    session: extractSession(ep),
    type: typeValue,

    _start: start,
    _end: end,
    _allDay: allDay,
    _scopeRaw: scopeRaw ? String(scopeRaw).toLowerCase() : null,
    _isAssignment: String(typeValue || "")
      .toLowerCase()
      .includes("assign"),
  }
}

/**
 * Expand multi-day all-day events into one row per day within the current range.
 * This keeps list output consistent with FullCalendar bars spanning multiple days.
 */
function expandToOccurrences(base) {
  if (!base._start) return []
  const end = base._end || base._start

  if (!overlapsRange(base._start, end)) return []

  // Non all-day: show once at start day
  if (!base._allDay) {
    const range =
      base._start && end
        ? `${base._start.toFormat("HH:mm")} - ${end.toFormat("HH:mm")}`
        : base._start
          ? base._start.toFormat("HH:mm")
          : ""

    return [
      {
        ...base,
        key: base.keyBase,
        _groupDay: base._start.startOf("day"),
        range,
      },
    ]
  }

  // All-day: end is usually exclusive (FullCalendar)
  const startDay = base._start.startOf("day")
  let endDay = (end || base._start).startOf("day")

  if (end && end > base._start) {
    endDay = end.minus({ days: 1 }).startOf("day")
  } else {
    endDay = startDay
  }

  const rangeFrom = apiRangeStart.value.startOf("day")
  const rangeTo = apiRangeEnd.value.minus({ days: 1 }).startOf("day")

  const from = startDay < rangeFrom ? rangeFrom : startDay
  const to = endDay > rangeTo ? rangeTo : endDay

  if (from > to) return []

  const out = []
  let cursor = from

  while (cursor <= to) {
    out.push({
      ...base,
      key: `${base.keyBase}-${cursor.toISODate()}`,
      _groupDay: cursor,
      range: t("All day"),
    })

    cursor = cursor.plus({ days: 1 })
  }

  return out
}

const listItems = computed(() => {
  const base = (rawEvents.value || []).map(mapToBaseItem)
  const out = []
  for (const b of base) {
    out.push(...expandToOccurrences(b))
  }
  return out
})

const filteredEvents = computed(() => {
  const items = listItems.value
  if (activeFilter.value === "all") return items
  if (activeFilter.value === "assignment") return items.filter((e) => e._isAssignment)
  return items.filter((e) => e._scopeRaw === activeFilter.value)
})

const groupedDays = computed(() => {
  const groups = new Map()
  const sorted = [...filteredEvents.value].sort((a, b) => a._groupDay.toMillis() - b._groupDay.toMillis())

  for (const ev of sorted) {
    const key = ev._groupDay.toISODate()
    const label = safeToLocaleString(ev._groupDay, DateTime.DATE_FULL)

    if (!groups.has(key)) {
      groups.set(key, { key, label, items: [] })
    }

    groups.get(key).items.push(ev)
  }

  return Array.from(groups.values())
})

const fetchKey = computed(() => {
  const params = computeCommonParams()
  return [
    apiRangeStart.value.toISO(),
    apiRangeEnd.value.toISO(),
    viewType.value,
    params.cid ?? "",
    params.sid ?? "",
    params.gid ?? "",
    params.type ?? "",
  ].join("|")
})

watch(
  fetchKey,
  () => {
    loadEvents()
  },
  { immediate: true },
)
</script>
