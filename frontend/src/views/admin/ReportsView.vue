<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { api, getErrorMessage } from '@/lib/api'
import { fetchProfessionals } from '@/services/salonApi'
import type { Professional } from '@/types/api'
import { useUiStore } from '@/stores/ui'

type Tab = 'overview' | 'appointments' | 'sales' | 'commissions' | 'financial' | 'cash'

const ui = useUiStore()
const tab = ref<Tab>('overview')
const loading = ref(false)
const professionals = ref<Professional[]>([])
const report = ref<Record<string, unknown> | null>(null)

const filters = reactive({
  from: '',
  to: '',
  professional_id: '',
  status: '',
  payment_method: '',
  year: String(new Date().getFullYear()),
  month: String(new Date().getMonth() + 1),
})

const tabs: { id: Tab; label: string }[] = [
  { id: 'overview', label: 'Visão geral' },
  { id: 'appointments', label: 'Agendamentos' },
  { id: 'sales', label: 'Vendas' },
  { id: 'commissions', label: 'Comissões' },
  { id: 'financial', label: 'Financeiro' },
  { id: 'cash', label: 'Caixa mensal' },
]

const professionalOptions = computed(() => [
  ...professionals.value.map((p) => ({ value: String(p.id), label: p.name })),
])

const statusOptions = [
  { value: 'pending', label: 'Pendente' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'completed', label: 'Concluído' },
  { value: 'canceled', label: 'Cancelado' },
]

const paymentOptions = [
  { value: 'cash', label: 'Numerário' },
  { value: 'card', label: 'Cartão' },
  { value: 'transfer', label: 'Transferência' },
  { value: 'other', label: 'Outro' },
]

const monthOptions = Array.from({ length: 12 }, (_, i) => ({
  value: String(i + 1),
  label: String(i + 1).padStart(2, '0'),
}))

const summary = computed(() => (report.value?.summary as Record<string, unknown>) ?? {})
const meta = computed(() => (report.value?.meta as Record<string, unknown>) ?? {})
const rows = computed(() => (Array.isArray(report.value?.rows) ? report.value!.rows : []) as Record<string, unknown>[])
const days = computed(() => (Array.isArray(report.value?.days) ? report.value!.days : []) as Record<string, unknown>[])

function params() {
  const p: Record<string, string | number> = {}
  if (filters.from) p.from = filters.from
  if (filters.to) p.to = filters.to
  if (filters.professional_id) p.professional_id = Number(filters.professional_id)
  if (filters.status && tab.value === 'appointments') p.status = filters.status
  if (filters.payment_method && tab.value === 'sales') p.payment_method = filters.payment_method
  if (tab.value === 'cash') {
    p.year = Number(filters.year)
    p.month = Number(filters.month)
  }
  return p
}

async function load() {
  loading.value = true
  report.value = null
  try {
    const { data } = await api.get<{ data: Record<string, unknown> }>(`/api/v1/reports/${tab.value}`, {
      params: params(),
    })
    report.value = data.data
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível carregar o relatório.'))
  } finally {
    loading.value = false
  }
}

async function download(format: 'csv' | 'pdf') {
  try {
    const { data, headers } = await api.get(`/api/v1/reports/${tab.value}`, {
      params: { ...params(), format },
      responseType: 'blob',
    })
    const disposition = headers['content-disposition'] as string | undefined
    const match = disposition?.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] ?? `relatorio.${format}`
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    ui.error(getErrorMessage(err, 'Falha no export.'))
  }
}

watch(tab, load)
onMounted(async () => {
  try {
    professionals.value = (await fetchProfessionals({ per_page: 100 })).data
  } catch {
    professionals.value = []
  }
  await load()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Relatórios</h1>
        <p class="mt-1 text-sm text-zinc-500">
          Operacionais · timezone {{ meta.timezone || '—' }} · {{ meta.currency || 'EUR' }}
        </p>
      </div>
      <div class="flex gap-2">
        <AppButton variant="secondary" @click="download('csv')">CSV</AppButton>
        <AppButton variant="secondary" @click="download('pdf')">PDF</AppButton>
        <AppButton @click="load">Atualizar</AppButton>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm"
        :class="tab === item.id ? 'bg-brand-600 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
        @click="tab = item.id"
      >
        {{ item.label }}
      </button>
    </div>

    <AppCard>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <template v-if="tab !== 'cash'">
          <AppInput v-model="filters.from" label="De" type="date" />
          <AppInput v-model="filters.to" label="Até" type="date" />
        </template>
        <template v-else>
          <AppInput v-model="filters.year" label="Ano" type="number" />
          <AppSelect v-model="filters.month" label="Mês" :options="monthOptions" />
        </template>
        <AppSelect
          v-if="tab === 'appointments' || tab === 'commissions'"
          v-model="filters.professional_id"
          label="Profissional"
          empty-label="Todos"
          :options="professionalOptions"
        />
        <AppSelect
          v-if="tab === 'appointments'"
          v-model="filters.status"
          label="Estado"
          empty-label="Todos"
          :options="statusOptions"
        />
        <AppSelect
          v-if="tab === 'sales'"
          v-model="filters.payment_method"
          label="Pagamento"
          empty-label="Todos"
          :options="paymentOptions"
        />
      </div>
      <div class="mt-3">
        <AppButton size="sm" @click="load">Aplicar filtros</AppButton>
      </div>
    </AppCard>

    <LoadingState v-if="loading" />

    <template v-else-if="report">
      <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div
          v-for="(value, key) in summary"
          :key="String(key)"
          class="rounded-xl border border-zinc-200 bg-white p-3"
        >
          <p class="text-xs uppercase tracking-wide text-zinc-500">{{ key }}</p>
          <p class="mt-1 text-lg font-semibold text-zinc-900">{{ value }}</p>
        </div>
      </div>

      <AppCard v-if="tab === 'appointments' || tab === 'sales' || tab === 'commissions'" title="Detalhe">
        <EmptyState v-if="!rows.length" title="Sem dados" description="Não há linhas para o filtro." />
        <div v-else class="overflow-x-auto">
          <table class="admin-table w-full text-sm">
            <thead>
              <tr>
                <th
                  v-for="col in Object.keys(rows[0] || {})"
                  :key="col"
                  class="px-3 py-2 text-left text-xs font-medium text-zinc-500"
                >
                  {{ col }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in rows.slice(0, 100)" :key="idx" class="border-t border-zinc-100">
                <td v-for="(val, col) in row" :key="String(col)" class="px-3 py-2 text-zinc-700">
                  <template v-if="val && typeof val === 'object'">{{ JSON.stringify(val) }}</template>
                  <template v-else>{{ val }}</template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="rows.length > 100" class="mt-2 text-xs text-zinc-500">
          A mostrar 100 de {{ rows.length }} linhas. Use CSV/PDF para export completo.
        </p>
      </AppCard>

      <AppCard v-else-if="tab === 'cash'" title="Dias do mês">
        <EmptyState v-if="!days.length" title="Sem dados" description="Sem caixas neste mês." />
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-zinc-500">
                <th class="px-2 py-1">Data</th>
                <th class="px-2 py-1">Caixas</th>
                <th class="px-2 py-1">Abertura</th>
                <th class="px-2 py-1">Entradas</th>
                <th class="px-2 py-1">Saídas</th>
                <th class="px-2 py-1">Esperado</th>
                <th class="px-2 py-1">Fecho</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="day in days" :key="String(day.date)" class="border-t border-zinc-100">
                <td class="px-2 py-1">{{ day.date }}</td>
                <td class="px-2 py-1">{{ day.registers_count }}</td>
                <td class="px-2 py-1">{{ day.opening_amount }}</td>
                <td class="px-2 py-1">{{ day.income }}</td>
                <td class="px-2 py-1">{{ day.expense }}</td>
                <td class="px-2 py-1">{{ day.expected_balance }}</td>
                <td class="px-2 py-1">{{ day.closing_amount }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>
  </div>
</template>
