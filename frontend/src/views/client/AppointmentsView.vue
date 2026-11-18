<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage } from '@/lib/api'
import { cancelAppointment, fetchAppointments } from '@/services/salonApi'
import type { Appointment } from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const appointments = ref<Appointment[]>([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

const now = () => Date.now()

const upcoming = computed(() =>
  appointments.value.filter(
    (item) =>
      new Date(item.start_time).getTime() >= now() &&
      item.status !== 'canceled' &&
      item.status !== 'completed',
  ),
)

const history = computed(() =>
  appointments.value.filter(
    (item) =>
      new Date(item.start_time).getTime() < now() ||
      item.status === 'canceled' ||
      item.status === 'completed',
  ),
)

function statusLabel(status: string) {
  const map: Record<string, string> = {
    pending: 'Pendente',
    confirmed: 'Confirmado',
    completed: 'Concluído',
    canceled: 'Cancelado',
  }
  return map[status] ?? status
}

function statusTone(status: string) {
  if (status === 'confirmed') return 'success'
  if (status === 'pending') return 'warning'
  if (status === 'canceled') return 'danger'
  return 'default'
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('pt-PT', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value))
}

function formatTime(value: string) {
  return new Intl.DateTimeFormat('pt-PT', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

async function load() {
  loading.value = true
  try {
    const response = await fetchAppointments({ page: page.value, per_page: 12 })
    appointments.value = response.data ?? []
    lastPage.value = response.meta?.last_page ?? 1
    total.value = response.meta?.total ?? appointments.value.length
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    loading.value = false
  }
}

async function cancel(item: Appointment) {
  const ok = await ui.confirm({
    title: 'Cancelar agendamento',
    message: 'Tem a certeza que deseja cancelar este agendamento?',
    confirmLabel: 'Sim, cancelar',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await cancelAppointment(item.id)
    ui.success('Agendamento cancelado.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

function onPageChange(newPage: number) {
  page.value = newPage
  load()
}

function canCancel(item: Appointment) {
  return item.status !== 'canceled' && item.status !== 'completed'
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-zinc-600">
        Consulte próximos horários e o histórico das suas visitas.
      </p>
      <RouterLink to="/booking" class="shrink-0">
        <AppButton class="w-full sm:w-auto">Marcar agendamento</AppButton>
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />

    <template v-else-if="!appointments.length">
      <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-12 shadow-sm">
        <EmptyState title="Sem agendamentos" description="Ainda não tem marcações." />
        <div class="mt-4 flex justify-center">
          <RouterLink to="/booking">
            <AppButton>Marcar agendamento</AppButton>
          </RouterLink>
        </div>
      </div>
    </template>

    <template v-else>
      <section v-if="upcoming.length" class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Próximos</h2>
        <div class="grid gap-3 md:grid-cols-2">
          <article
            v-for="item in upcoming"
            :key="item.id"
            class="flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-4 shadow-sm"
          >
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-zinc-900">{{ item.service?.name ?? 'Serviço' }}</h3>
                <AppBadge :tone="statusTone(item.status)">{{ statusLabel(item.status) }}</AppBadge>
              </div>
              <p class="mt-2 text-sm text-zinc-600">
                {{ item.professional?.name ?? 'Profissional a definir' }}
              </p>
              <p class="mt-1 text-sm text-zinc-500">
                {{ formatDate(item.start_time) }} · {{ formatTime(item.start_time) }}
              </p>
              <p v-if="item.notes" class="mt-2 line-clamp-2 text-sm text-zinc-400">{{ item.notes }}</p>
            </div>
            <div v-if="canCancel(item)" class="mt-4">
              <AppButton variant="danger" @click="cancel(item)">Cancelar</AppButton>
            </div>
          </article>
        </div>
      </section>

      <section v-if="history.length" class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Histórico</h2>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
          <ul class="divide-y divide-zinc-100">
            <li
              v-for="item in history"
              :key="item.id"
              class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
            >
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-zinc-900">{{ item.service?.name ?? 'Serviço' }}</p>
                  <AppBadge :tone="statusTone(item.status)">{{ statusLabel(item.status) }}</AppBadge>
                </div>
                <p class="mt-0.5 text-sm text-zinc-500">
                  {{ item.professional?.name ?? '—' }} · {{ formatDate(item.start_time) }} ·
                  {{ formatTime(item.start_time) }}
                </p>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <AppPagination :current-page="page" :last-page="lastPage" :total="total" @change="onPageChange" />
    </template>
  </div>
</template>
