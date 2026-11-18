<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppTable from '@/components/ui/AppTable.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage } from '@/lib/api'
import {
  fetchCommissionsPreview,
  fetchProfessionals,
  generateCommissions,
} from '@/services/salonApi'
import type { CommissionPreviewRow, Professional } from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const rows = ref<CommissionPreviewRow[]>([])
const professionals = ref<Professional[]>([])
const loading = ref(true)
const generating = ref(false)
const from = ref('')
const to = ref('')
const professionalFilter = ref('')
const statusFilter = ref('')
const totalCommission = ref('0.00')
const note = ref('')
const count = ref(0)
const selected = ref<number[]>([])

const professionalOptions = computed(() =>
  professionals.value.map((p) => ({ value: String(p.id), label: p.name })),
)

const statusOptions = [
  { value: 'calculated', label: 'Calculada (sem Payable)' },
  { value: 'payable_pending', label: 'Payable pendente' },
  { value: 'paid', label: 'Paga' },
]

const selectableIds = computed(() =>
  rows.value
    .filter((row) => row.financial_status === 'calculated' && Number(row.commission_amount) > 0)
    .map((row) => row.origin_id),
)

const allSelectableChecked = computed(
  () => selectableIds.value.length > 0 && selectableIds.value.every((id) => selected.value.includes(id)),
)

function formatMoney(value: string | number) {
  const num = typeof value === 'string' ? Number(value) : value
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(num || 0)
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('pt-PT')
}

function sourceLabel(source: string) {
  if (source === 'professional_service') return 'Especifica do serviço'
  if (source === 'professional') return 'Padrão do profissional'
  return source
}

function statusLabel(status: string | null | undefined) {
  if (status === 'paid') return 'Paga'
  if (status === 'payable_pending') return 'Payable pendente'
  return 'Calculada'
}

function statusTone(status: string | null | undefined) {
  if (status === 'paid') return 'success'
  if (status === 'payable_pending') return 'warning'
  return 'default'
}

function toggleSelect(id: number) {
  if (selected.value.includes(id)) {
    selected.value = selected.value.filter((x) => x !== id)
  } else {
    selected.value = [...selected.value, id]
  }
}

function toggleSelectAll() {
  if (allSelectableChecked.value) {
    selected.value = []
  } else {
    selected.value = [...selectableIds.value]
  }
}

async function loadLookups() {
  try {
    const response = await fetchProfessionals({ per_page: 100 })
    professionals.value = response.data ?? []
  } catch {
    professionals.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const response = await fetchCommissionsPreview({
      from: from.value || undefined,
      to: to.value || undefined,
      professional_id: professionalFilter.value
        ? Number(professionalFilter.value)
        : undefined,
      financial_status: statusFilter.value || undefined,
    })
    rows.value = response.data ?? []
    totalCommission.value = response.meta?.total_commission ?? '0.00'
    note.value = response.meta?.note ?? ''
    count.value = response.meta?.count ?? rows.value.length
    selected.value = selected.value.filter((id) =>
      rows.value.some((row) => row.origin_id === id && row.financial_status === 'calculated'),
    )
  } catch (err) {
    rows.value = []
    ui.error(getErrorMessage(err))
  } finally {
    loading.value = false
  }
}

async function onGenerate() {
  if (!selected.value.length) {
    ui.error('Selecione pelo menos um agendamento calculado.')
    return
  }

  const ok = await ui.confirm({
    title: 'Gerar contas a pagar',
    message: `Criar ${selected.value.length} conta(s) a pagar do tipo comissão? O valor fica fixo (snapshot).`,
    confirmLabel: 'Gerar',
  })
  if (!ok) return

  generating.value = true
  try {
    const result = await generateCommissions({ appointment_ids: selected.value })
    ui.success(
      `${result.meta.created_count} criada(s), ${result.meta.skipped_count} ignorada(s).`,
    )
    selected.value = []
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    generating.value = false
  }
}

function onSearch() {
  load()
}

onMounted(async () => {
  await loadLookups()
  await load()
})
</script>

<template>
  <div class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Comissões</h1>
        <p class="mt-1 text-sm text-zinc-600">
          Pré-visualização a partir de agendamentos concluídos. A geração de contas a pagar é
          manual (como no legado).
        </p>
      </div>
      <RouterLink
        to="/panel/admin/payables"
        class="text-sm font-medium text-brand-700 hover:underline"
      >
        Ir para Contas a pagar
      </RouterLink>
    </div>

    <div
      v-if="note"
      class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
      {{ note }}
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="onSearch">
        <AppInput v-model="from" label="De" type="date" class="min-w-[150px]" />
        <AppInput v-model="to" label="Até" type="date" class="min-w-[150px]" />
        <AppSelect
          v-model="professionalFilter"
          label="Profissional"
          empty-label="Todos"
          :options="professionalOptions"
          class="min-w-[180px]"
        />
        <AppSelect
          v-model="statusFilter"
          label="Estado"
          empty-label="Todos"
          :options="statusOptions"
          class="min-w-[180px]"
        />
        <AppButton type="submit" variant="secondary" class="self-end">Filtrar</AppButton>
      </form>
      <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
        <p class="text-sm text-zinc-600">
          Total:
          <span class="font-semibold text-zinc-900">{{ formatMoney(totalCommission) }}</span>
          · {{ count }}
        </p>
        <AppButton
          :disabled="!selected.length"
          :loading="generating"
          @click="onGenerate"
        >
          Gerar Payables ({{ selected.length }})
        </AppButton>
      </div>
    </div>

    <LoadingState v-if="loading" />
    <EmptyState
      v-else-if="!rows.length"
      title="Sem comissões calculáveis"
      description="Conclua agendamentos com profissionais que tenham serviços associados."
    />

    <template v-else>
      <div class="hidden md:block">
        <AppTable>
          <thead>
            <tr>
              <th class="w-10">
                <input
                  type="checkbox"
                  :checked="allSelectableChecked"
                  :disabled="!selectableIds.length"
                  aria-label="Selecionar todos"
                  @change="toggleSelectAll"
                />
              </th>
              <th>Profissional</th>
              <th>Serviço</th>
              <th>Base</th>
              <th>%</th>
              <th>Comissão</th>
              <th>Origem %</th>
              <th>Data</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.origin}-${row.origin_id}`">
              <td>
                <input
                  v-if="row.financial_status === 'calculated' && Number(row.commission_amount) > 0"
                  type="checkbox"
                  :checked="selected.includes(row.origin_id)"
                  :aria-label="`Selecionar agendamento ${row.origin_id}`"
                  @change="toggleSelect(row.origin_id)"
                />
              </td>
              <td>{{ row.professional?.name || '—' }}</td>
              <td>{{ row.service?.name || '—' }}</td>
              <td>{{ formatMoney(row.base_amount) }}</td>
              <td>{{ row.percentage }}%</td>
              <td class="font-medium">{{ formatMoney(row.commission_amount) }}</td>
              <td>
                <AppBadge tone="brand">{{ sourceLabel(row.source) }}</AppBadge>
              </td>
              <td>{{ formatDate(row.date) }}</td>
              <td>
                <AppBadge :tone="statusTone(row.financial_status)">
                  {{ statusLabel(row.financial_status) }}
                </AppBadge>
                <RouterLink
                  v-if="row.payable_id"
                  :to="{ name: 'admin-payables', query: { highlight: String(row.payable_id) } }"
                  class="ml-2 text-xs text-brand-700 hover:underline"
                >
                  #{{ row.payable_id }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </AppTable>
      </div>

      <div class="grid gap-3 md:hidden">
        <article
          v-for="row in rows"
          :key="`${row.origin}-${row.origin_id}`"
          class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-2">
              <input
                v-if="row.financial_status === 'calculated' && Number(row.commission_amount) > 0"
                type="checkbox"
                class="mt-1"
                :checked="selected.includes(row.origin_id)"
                @change="toggleSelect(row.origin_id)"
              />
              <div>
                <h3 class="font-semibold text-zinc-900">{{ row.professional?.name }}</h3>
                <p class="mt-1 text-sm text-zinc-600">{{ row.service?.name }}</p>
              </div>
            </div>
            <AppBadge :tone="statusTone(row.financial_status)">
              {{ statusLabel(row.financial_status) }}
            </AppBadge>
          </div>
          <p class="mt-3 text-sm text-zinc-600">
            Base {{ formatMoney(row.base_amount) }} · {{ row.percentage }}% ·
            <span class="font-medium text-zinc-900">{{ formatMoney(row.commission_amount) }}</span>
          </p>
          <p class="mt-1 text-xs text-zinc-500">
            {{ sourceLabel(row.source) }} · {{ formatDate(row.date) }}
          </p>
        </article>
      </div>
    </template>
  </div>
</template>
