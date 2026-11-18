<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppTable from '@/components/ui/AppTable.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { fetchDashboard, fetchNotifications, markNotificationRead } from '@/services/salonApi'
import type { Appointment, DashboardData, Notification } from '@/types/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const auth = useAuthStore()
const ui = useUiStore()
const dashboard = ref<DashboardData | null>(null)
const notifications = ref<Notification[]>([])
const loading = ref(true)

const secondaryMetrics = computed(() => {
  const t = dashboard.value?.totals
  if (!t) return []
  return [
    { label: 'Profissionais', value: t.professionals, to: '/panel/admin/professionals' },
    { label: 'Serviços', value: t.services, to: '/panel/admin/services' },
    { label: 'Produtos', value: t.products ?? 0, to: '/panel/admin/products' },
    {
      label: 'Stock baixo',
      value: t.products_low_stock ?? 0,
      to: '/panel/admin/stock',
      alert: (t.products_low_stock ?? 0) > 0,
    },
    { label: 'Vendas hoje', value: t.sales_today ?? 0, to: '/panel/admin/sales' },
    { label: 'Notificações', value: dashboard.value?.unread_notifications ?? 0 },
  ]
})

async function load() {
  loading.value = true
  try {
    const [dash, notifs] = await Promise.all([fetchDashboard(), fetchNotifications(true)])
    dashboard.value = dash
    notifications.value = notifs.data ?? []
  } catch {
    ui.error('Não foi possível carregar o dashboard.')
  } finally {
    loading.value = false
  }
}

async function readNotification(id: number) {
  await markNotificationRead(id)
  await load()
}

function formatMoney(value: string | number) {
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(value))
}

function formatDateTime(iso: string) {
  return new Intl.DateTimeFormat('pt-PT', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso))
}

function appointmentRow(appt: Appointment) {
  return {
    client: appt.client?.name ?? '—',
    service: appt.service?.name ?? '—',
    professional: appt.professional?.name ?? '—',
    when: formatDateTime(appt.start_time),
    status: appt.status,
  }
}

onMounted(load)
</script>

<template>
  <LoadingState v-if="loading" />

  <div v-else class="grid grid-cols-12 gap-6">
    <!-- Intro + ações locais -->
    <div
      class="col-span-12 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <p class="text-base font-medium leading-snug text-[#1F2937] sm:text-lg">
          Olá, {{ auth.fullName }}
        </p>
        <p class="mt-0.5 text-sm text-[#6B7280]">Aqui está o resumo do negócio.</p>
      </div>
      <div class="flex shrink-0 flex-wrap gap-2">
        <RouterLink v-if="auth.isAdmin" to="/panel/admin/professionals">
          <AppButton size="sm" variant="secondary">+ Profissional</AppButton>
        </RouterLink>
        <RouterLink to="/panel/admin/appointments">
          <AppButton size="sm">Novo agendamento</AppButton>
        </RouterLink>
      </div>
    </div>

    <!-- Linha 1 — KPIs principais (3+3+3+3) -->
    <template v-if="dashboard?.totals">
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <StatCard
          label="Clientes"
          :value="dashboard.totals.clients"
          to="/panel/admin/clients"
          compact
        />
      </div>
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <StatCard
          label="Agendamentos hoje"
          :value="dashboard.totals.appointments_today"
          to="/panel/admin/appointments"
          compact
        />
      </div>
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <StatCard
          label="Receita hoje"
          :value="formatMoney(dashboard.totals.revenue_today ?? 0)"
          to="/panel/admin/sales"
          compact
        />
      </div>
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <StatCard
          label="Pendentes"
          :value="dashboard.totals.appointments_pending"
          hint="Por confirmar"
          to="/panel/admin/appointments"
          compact
        />
      </div>

      <!-- Linha 2 — KPIs secundários (2×6) -->
      <component
        :is="metric.to ? RouterLink : 'div'"
        v-for="metric in secondaryMetrics"
        :key="metric.label"
        :to="metric.to"
        class="admin-card col-span-6 flex min-h-[88px] min-w-0 flex-col justify-center px-4 py-3.5 transition md:col-span-4 xl:col-span-2"
        :class="metric.to ? 'hover:border-[#E6C6B6] hover:bg-[#FAFAFA]' : ''"
      >
        <span class="text-xs font-medium leading-snug text-[#6B7280]">{{ metric.label }}</span>
        <span
          class="mt-1.5 text-lg font-semibold leading-none tabular-nums"
          :class="metric.alert ? 'text-red-600' : 'text-[#1F2937]'"
        >
          {{ metric.value }}
        </span>
      </component>
    </template>

    <template v-else-if="dashboard?.professional">
      <div class="col-span-12 sm:col-span-6">
        <StatCard
          label="Agendamentos hoje"
          :value="dashboard.professional.appointments_today"
          compact
        />
      </div>
      <div class="col-span-12 sm:col-span-6">
        <StatCard
          label="Próximos"
          :value="dashboard.professional.upcoming_appointments"
          compact
        />
      </div>
    </template>

    <!-- Linha 3 — 8 + 4 -->
    <section class="admin-card col-span-12 min-w-0 overflow-hidden xl:col-span-8">
      <div class="flex items-center justify-between border-b border-[#F3F4F6] px-4 py-3 sm:px-5">
        <h2 class="text-sm font-semibold text-[#1F2937]">Próximos agendamentos</h2>
        <RouterLink to="/panel/admin/appointments">
          <AppButton variant="ghost" size="sm">Ver todos</AppButton>
        </RouterLink>
      </div>

      <div v-if="!dashboard?.recent_appointments?.length" class="px-4 py-5 sm:px-5">
        <EmptyState
          title="Sem agendamentos próximos"
          description="Novos agendamentos aparecem aqui."
        />
      </div>

      <AppTable v-else>
        <thead>
          <tr>
            <th>Cliente</th>
            <th class="hidden md:table-cell">Serviço</th>
            <th class="hidden lg:table-cell">Profissional</th>
            <th>Horário</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="appt in dashboard.recent_appointments" :key="appt.id">
            <td>{{ appointmentRow(appt).client }}</td>
            <td class="hidden md:table-cell">{{ appointmentRow(appt).service }}</td>
            <td class="hidden lg:table-cell">{{ appointmentRow(appt).professional }}</td>
            <td class="whitespace-nowrap">{{ appointmentRow(appt).when }}</td>
            <td><StatusBadge :status="appt.status" /></td>
          </tr>
        </tbody>
      </AppTable>
    </section>

    <aside class="col-span-12 flex min-w-0 flex-col gap-6 xl:col-span-4">
      <section class="admin-card p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-[#1F2937]">Caixa</h2>
        <template v-if="dashboard?.cash_register">
          <p class="mt-2 text-xs text-[#6B7280]">{{ dashboard.cash_register.status_label }}</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums text-[#1F2937]">
            {{ formatMoney(dashboard.cash_register.expected_balance) }}
          </p>
          <RouterLink to="/panel/admin/cash" class="mt-4 block">
            <AppButton variant="secondary" size="sm" class="w-full">Gerir caixa</AppButton>
          </RouterLink>
        </template>
        <template v-else>
          <p class="mt-2 text-sm text-[#6B7280]">Nenhuma caixa aberta.</p>
          <RouterLink to="/panel/admin/cash" class="mt-4 block">
            <AppButton size="sm" class="w-full">Abrir caixa</AppButton>
          </RouterLink>
        </template>
      </section>

      <section class="admin-card p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-[#1F2937]">Atalhos</h2>
        <nav class="mt-2 grid gap-0.5">
          <RouterLink
            v-if="auth.isAdmin"
            to="/panel/admin/professionals"
            class="admin-action-link"
          >
            + Profissional
          </RouterLink>
          <RouterLink to="/panel/admin/clients" class="admin-action-link">+ Cliente</RouterLink>
          <RouterLink v-if="auth.isAdmin" to="/panel/admin/services" class="admin-action-link">
            + Serviço
          </RouterLink>
          <RouterLink to="/panel/admin/sales" class="admin-action-link">+ Venda</RouterLink>
          <RouterLink to="/panel/admin/stock" class="admin-action-link">Movimentar stock</RouterLink>
        </nav>
      </section>

      <section
        v-if="dashboard?.low_stock_products?.length"
        class="admin-card border-amber-200/80 bg-amber-50/50 p-4 sm:p-5"
      >
        <h2 class="text-sm font-semibold text-amber-900">Stock baixo</h2>
        <ul class="mt-3 space-y-2">
          <li
            v-for="product in dashboard.low_stock_products"
            :key="product.id"
            class="flex justify-between text-sm"
          >
            <span class="truncate text-amber-950">{{ product.name }}</span>
            <span class="ml-2 shrink-0 font-semibold text-amber-800">
              {{ product.stock_quantity }} un.
            </span>
          </li>
        </ul>
      </section>
    </aside>

    <!-- Linha 4 — Notificações full width -->
    <section class="admin-card col-span-12 overflow-hidden">
      <div class="border-b border-[#F3F4F6] px-4 py-3 sm:px-5">
        <h2 class="text-sm font-semibold text-[#1F2937]">Notificações</h2>
      </div>
      <div v-if="!notifications.length" class="px-4 py-5 sm:px-5">
        <EmptyState title="Tudo em dia" description="Não há notificações por ler." />
      </div>
      <ul v-else class="divide-y divide-[#F3F4F6]">
        <li
          v-for="item in notifications"
          :key="item.id"
          class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
          <div>
            <p class="text-sm font-medium text-[#1F2937]">{{ item.title }}</p>
            <p class="text-sm text-[#6B7280]">{{ item.body }}</p>
          </div>
          <AppButton variant="ghost" size="sm" @click="readNotification(item.id)">
            Marcar como lida
          </AppButton>
        </li>
      </ul>
    </section>
  </div>
</template>
