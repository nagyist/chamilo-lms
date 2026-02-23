<template>
  <div class="flex flex-col gap-4">
    <CalendarSectionHeader
      @addClick="goToAddEvent"
      @agendaListClick="goToAgendaList"
      @sessionPlanningClick="goToSessionsPlan"
      @myStudentsScheduleClick="goToMyStudentsSchedule"
    />

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <BaseButton
          :label="t('Previous')"
          icon="chevron-left"
          type="black"
          @click="setYear(year - 1)"
        />
        <div class="text-lg font-semibold">
          {{ year }}
        </div>
        <BaseButton
          :label="t('Next')"
          icon="chevron-right"
          type="black"
          @click="setYear(year + 1)"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
        <span class="px-2 py-1 rounded border bg-white">
          {{ t("Sessions plan calendar") }}
        </span>
        <span class="px-2 py-1 rounded border bg-white">
          {{ t("Weeks are displayed from 1 to 52") }}
        </span>
        <span class="px-2 py-1 rounded border bg-white">
          {{ t("Sessions") }}: {{ sessions.length }}
        </span>
      </div>
    </div>

    <div
      v-if="errorMessage"
      class="p-3 border rounded bg-white text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <div class="border rounded overflow-hidden bg-white relative">
      <div
        v-if="isLoading"
        class="absolute inset-0 z-10 bg-white/70 flex items-center justify-center"
      >
        <div class="flex items-center gap-3 text-gray-700">
          <i class="pi pi-spin pi-spinner text-2xl" />
          <span class="text-sm">{{ t("Loading") }}</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <div class="min-w-[1400px]">
          <!-- Header -->
          <div class="plan-grid plan-header border-b bg-white sticky top-0 z-10">
            <div class="px-3 py-2 font-semibold">
              {{ t("Session") }}
            </div>
            <div
              v-for="w in 52"
              :key="`w-${w}`"
              class="text-xs text-center px-1 py-2 border-l"
            >
              {{ w }}
            </div>
          </div>

          <div
            v-if="!isLoading && sessions.length === 0 && !errorMessage"
            class="p-4 text-sm text-gray-600"
          >
            {{ t("No sessions found") }}
          </div>

          <!-- Rows -->
          <div
            v-for="s in sessions"
            :key="`s-${s.id}`"
            class="plan-grid plan-row border-b"
          >
            <div class="px-3 py-2">
              <div
                class="font-semibold truncate"
                :title="s.title"
              >
                {{ s.title }}
              </div>

              <div class="text-xs text-gray-600">
                {{ t("From") }} {{ s.startDate || "—" }} • {{ t("Until") }} {{ s.endDate || "—" }}
              </div>

              <div
                v-if="s.humanDate"
                class="text-xs text-gray-500 mt-0.5"
              >
                {{ s.humanDate }}
              </div>
            </div>

            <div class="relative col-span-52 border-l">
              <div
                v-if="showBar(s)"
                class="absolute top-1/2 -translate-y-1/2 h-5 rounded"
                :style="barStyle(s)"
                :title="barTooltip(s)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue"
import { useI18n } from "vue-i18n"
import { DateTime } from "luxon"
import CalendarSectionHeader from "../../components/ccalendarevent/CalendarSectionHeader.vue"
import BaseButton from "../../components/basecomponents/BaseButton.vue"
import { useRoute, useRouter } from "vue-router"

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

function parseQueryYear(value) {
  const y = Number(value)
  const current = DateTime.now().year
  if (!Number.isFinite(y) || y < 1970 || y > 2100) return current
  return y
}

const year = ref(parseQueryYear(route.query.year))

watch(
  () => route.query.year,
  (y) => {
    year.value = parseQueryYear(y)
  },
)

function syncYearToQuery(nextYear) {
  if (String(route.query.year || "") === String(nextYear)) return
  const nextQuery = { ...route.query, year: String(nextYear) }
  router.replace({ name: route.name ?? "CalendarSessionsPlan", params: route.params, query: nextQuery }).catch(() => {})
}

function setYear(nextYear) {
  year.value = nextYear
  syncYearToQuery(nextYear)
}

function goToSessionsPlan() {
  router.push({ name: "CalendarSessionsPlan", query: { ...route.query, year: String(year.value) } }).catch(() => {})
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

const isLoading = ref(false)
const errorMessage = ref("")
const sessions = ref([])

async function fetchPlan() {
  try {
    isLoading.value = true
    errorMessage.value = ""

    const url = `/api/calendar/sessions-plan?year=${encodeURIComponent(String(year.value))}`
    const resp = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/ld+json, application/json" },
    })

    if (!resp.ok) {
      const text = await resp.text().catch(() => "")
      // eslint-disable-next-line no-console
      console.error("[SessionsPlan] Request failed", resp.status, text)

      if (resp.status === 403) {
        errorMessage.value = t("TooMuchSessionsInPlanification")
      } else {
        errorMessage.value = t("Failed to load sessions plan")
      }

      sessions.value = []
      return
    }

    const data = await resp.json()
    const items = Array.isArray(data) ? data : Array.isArray(data?.["hydra:member"]) ? data["hydra:member"] : []

    sessions.value = items.map((x) => ({
      id: x.id,
      title: x.title,
      startDate: x.startDate ?? null,
      endDate: x.endDate ?? null,
      humanDate: x.humanDate ?? null,
      start: Number.isFinite(Number(x.start)) ? Number(x.start) : 0,
      duration: Number.isFinite(Number(x.duration)) ? Math.max(1, Number(x.duration)) : 1,
      color: x.color || "rgba(70,130,180,0.9)",
      noStart: Boolean(x.noStart),
      noEnd: Boolean(x.noEnd),
      startInLastYear: Boolean(x.startInLastYear),
      endInNextYear: Boolean(x.endInNextYear),
    }))
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error("[SessionsPlan] Unexpected error", e)
    errorMessage.value = t("Failed to load sessions plan")
    sessions.value = []
  } finally {
    isLoading.value = false
  }
}

watch(
  () => year.value,
  () => {
    fetchPlan()
  },
  { immediate: true },
)

function showBar(s) {
  return Number.isFinite(s.start) && Number.isFinite(s.duration) && s.duration > 0
}

function barTooltip(s) {
  const from = s.startDate || "—"
  const until = s.endDate || "—"
  return `${s.title}\n${t("From")} ${from}\n${t("Until")} ${until}`
}

function barStyle(s) {
  const start = Math.min(51, Math.max(0, s.start))
  const duration = Math.min(52, Math.max(1, s.duration))

  const leftPercent = (start / 52) * 100
  const widthPercent = (duration / 52) * 100

  return {
    left: `${leftPercent}%`,
    width: `${widthPercent}%`,
    background: s.color,
    opacity: "0.9",
  }
}
</script>

<style scoped>
.plan-grid {
  display: grid;
  grid-template-columns: 320px repeat(52, minmax(10px, 1fr));
}
.plan-header > div,
.plan-row > div {
  min-height: 44px;
}
.plan-row:hover {
  background: rgba(0, 0, 0, 0.02);
}
</style>
