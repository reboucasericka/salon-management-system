<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppTable from '@/components/ui/AppTable.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage } from '@/lib/api'
import {
  createReceivable,
  deleteReceivable,
  fetchClients,
  fetchProfessionals,
  fetchReceivables,
  fetchServices,
  markReceivableReceived,
  updateReceivable,
} from '@/services/salonApi'
import type {
  Client,
  PaymentMethod,
  Professional,
  Receivable,
  Service,
} from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const receivables = ref<Receivable[]>([])
const clients = ref<Client[]>([])
const professionals = ref<Professional[]>([])
const services = ref<Service[]>([])
const loading = ref(true)
const saving = ref(false)
const search = ref('')
const statusFilter = ref('')
const referenceFilter = ref('')
const clientFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const modalOpen = ref(false)
const editing = ref<Receivable | null>(null)

const form = reactive({
  description: '',
  amount: '',
  payment_method: 'cash' as PaymentMethod,
  client_id: '',
  professional_id: '',
  service_id: '',
})

const statusOptions = [
  { value: 'pending', label: 'Pendente' },
  { value: 'received', label: 'Recebido' },
  { value: 'overdue', label: 'Vencido' },
]

const referenceOptions = [
  { value: 'service', label: 'Serviço' },
  { value: 'sale', label: 'Venda' },
  { value: 'none', label: 'Sem referência' },
]

const paymentMethodOptions = [
  { value: 'cash', label: 'Dinheiro' },
  { value: 'card', label: 'Cartão' },
  { value: 'transfer', label: 'Transferência' },
  { value: 'other', label: 'Outro' },
]

const clientOptions = computed(() =>
  clients.value.map((c) => ({ value: String(c.id), label: c.name })),
)

const professionalOptions = computed(() =>
  professionals.value.map((p) => ({ value: String(p.id), label: p.name })),
)

const serviceOptions = computed(() =>
  services.value.map((s) => ({ value: String(s.id), label: s.name })),
)

const pageTitle = computed(() =>
  editing.value ? 'Editar conta a receber' : 'Nova conta a receber',
)

function formatMoney(value: string | number) {
  const num = typeof value === 'string' ? Number(value) : value
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(num || 0)
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  if (value.includes('T')) {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return value
    return d.toLocaleDateString('pt-PT')
  }
  const [y, m, d] = value.split('-')
  if (!y || !m || !d) return value
  return `${d}/${m}/${y}`
}

function statusTone(row: Receivable): 'default' | 'success' | 'warning' | 'danger' {
  if (row.display_status === 'received') return 'success'
  if (row.display_status === 'overdue') return 'danger'
  return 'warning'
}

function resetForm() {
  form.description = ''
  form.amount = ''
  form.payment_method = 'cash'
  form.client_id = ''
  form.professional_id = ''
  form.service_id = ''
  editing.value = null
}

function closeModal() {
  modalOpen.value = false
  resetForm()
}

function openCreate() {
  resetForm()
  modalOpen.value = true
}

function openEdit(row: Receivable) {
  editing.value = row
  form.description = row.description
  form.amount = row.amount
  form.payment_method = row.payment_method
  form.client_id = String(row.client_id)
  form.professional_id = String(row.professional_id)
  form.service_id = row.service_id ? String(row.service_id) : ''
  modalOpen.value = true
}

async function loadLookups() {
  try {
    const [clientsRes, professionalsRes, servicesRes] = await Promise.all([
      fetchClients({ per_page: 100 }),
      fetchProfessionals({ per_page: 100 }),
      fetchServices({ per_page: 100 }),
    ])
    clients.value = clientsRes.data ?? []
    professionals.value = professionalsRes.data ?? []
    services.value = servicesRes.data ?? []
  } catch {
    clients.value = []
    professionals.value = []
    services.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const response = await fetchReceivables({
      page: page.value,
      per_page: 15,
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      reference: referenceFilter.value || undefined,
      client_id: clientFilter.value ? Number(clientFilter.value) : undefined,
    })
    receivables.value = response.data ?? []
    lastPage.value = response.meta?.last_page ?? 1
    total.value = response.meta?.total ?? receivables.value.length
  } catch (err) {
    receivables.value = []
    ui.error(getErrorMessage(err))
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  try {
    const payload = {
      description: form.description,
      amount: Number(form.amount),
      payment_method: form.payment_method,
      client_id: Number(form.client_id),
      professional_id: Number(form.professional_id),
      service_id: form.service_id ? Number(form.service_id) : null,
    }

    if (editing.value) {
      await updateReceivable(editing.value.id, payload)
      ui.success('Conta a receber atualizada.')
    } else {
      await createReceivable(payload)
      ui.success('Conta a receber criada.')
    }

    closeModal()
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function markReceived(row: Receivable) {
  if (row.is_received) return

  const ok = await ui.confirm({
    title: 'Marcar como recebido',
    message: `Confirmar recebimento de "${row.description}" (${formatMoney(row.amount)})?`,
    confirmLabel: 'Marcar como recebido',
  })
  if (!ok) return

  try {
    await markReceivableReceived(row.id)
    ui.success('Conta marcada como recebida.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

async function remove(row: Receivable) {
  const ok = await ui.confirm({
    title: 'Eliminar conta a receber',
    message: `Eliminar permanentemente "${row.description}"?`,
    confirmLabel: 'Eliminar',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deleteReceivable(row.id)
    ui.success('Conta a receber eliminada.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

function onSearch() {
  page.value = 1
  load()
}

function onPageChange(newPage: number) {
  page.value = newPage
  load()
}

onMounted(async () => {
  await loadLookups()
  await load()
})
</script>

<template>
  <div class="w-full space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-zinc-900">Contas a receber</h1>
      <p class="mt-1 text-sm text-zinc-600">
        Gerencie recebimentos de serviços e valores associados a clientes.
      </p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="onSearch">
        <AppInput
          v-model="search"
          label="Pesquisar"
          placeholder="Descrição"
          class="min-w-[180px] flex-1"
        />
        <AppSelect
          v-model="statusFilter"
          label="Estado"
          empty-label="Todos os estados"
          :options="statusOptions"
          class="min-w-[150px]"
        />
        <AppSelect
          v-model="referenceFilter"
          label="Referência"
          empty-label="Todas"
          :options="referenceOptions"
          class="min-w-[150px]"
        />
        <AppSelect
          v-model="clientFilter"
          label="Cliente"
          empty-label="Todos"
          :options="clientOptions"
          class="min-w-[160px]"
        />
        <AppButton type="submit" variant="secondary" class="self-end">Filtrar</AppButton>
      </form>
      <AppButton @click="openCreate">Nova conta</AppButton>
    </div>

    <LoadingState v-if="loading" />
    <EmptyState
      v-else-if="!receivables.length"
      title="Nenhuma conta a receber"
      description="Crie a primeira conta a receber para acompanhar valores pendentes."
    />

    <template v-else>
      <div class="hidden md:block">
        <AppTable>
          <thead>
            <tr>
              <th>Descrição</th>
              <th>Cliente</th>
              <th>Referência</th>
              <th>Criado</th>
              <th>Valor</th>
              <th>Estado</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in receivables" :key="row.id">
              <td>
                <div class="font-medium text-zinc-900">{{ row.description }}</div>
                <div class="mt-0.5 text-xs text-zinc-500">
                  {{ row.payment_method_label }}
                  <span v-if="row.received_at"> · Recebido em {{ formatDate(row.received_at) }}</span>
                </div>
              </td>
              <td>{{ row.client?.name || '—' }}</td>
              <td>{{ row.reference_label || '—' }}</td>
              <td>{{ formatDate(row.created_at) }}</td>
              <td class="font-medium">{{ formatMoney(row.amount) }}</td>
              <td>
                <AppBadge :tone="statusTone(row)">{{ row.display_status_label }}</AppBadge>
              </td>
              <td>
                <div class="flex flex-wrap gap-2">
                  <AppButton variant="secondary" @click="openEdit(row)">Editar</AppButton>
                  <AppButton
                    v-if="!row.is_received"
                    variant="secondary"
                    @click="markReceived(row)"
                  >
                    Receber
                  </AppButton>
                  <AppButton variant="danger" @click="remove(row)">Eliminar</AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </AppTable>
      </div>

      <div class="grid gap-3 md:hidden">
        <article
          v-for="row in receivables"
          :key="row.id"
          class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-semibold text-zinc-900">{{ row.description }}</h3>
              <p class="mt-1 text-sm text-zinc-600">{{ row.client?.name || '—' }}</p>
            </div>
            <AppBadge :tone="statusTone(row)">{{ row.display_status_label }}</AppBadge>
          </div>
          <p class="mt-3 text-sm text-zinc-600">
            {{ row.reference_label || 'Sem referência' }} · Criado {{ formatDate(row.created_at) }} ·
            <span class="font-medium text-zinc-900">{{ formatMoney(row.amount) }}</span>
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton variant="secondary" class="flex-1" @click="openEdit(row)">Editar</AppButton>
            <AppButton v-if="!row.is_received" variant="secondary" @click="markReceived(row)">
              Receber
            </AppButton>
            <AppButton variant="danger" @click="remove(row)">Eliminar</AppButton>
          </div>
        </article>
      </div>

      <AppPagination
        :current-page="page"
        :last-page="lastPage"
        :total="total"
        @change="onPageChange"
      />
    </template>

    <AppModal :open="modalOpen" :title="pageTitle" @close="closeModal">
      <form class="grid gap-5" @submit.prevent="submit">
        <section class="grid gap-4 sm:grid-cols-2">
          <h2 class="text-sm font-semibold text-zinc-800 sm:col-span-2">Dados</h2>
          <AppInput
            v-model="form.description"
            label="Descrição"
            required
            class="sm:col-span-2"
          />
          <AppInput
            v-model="form.amount"
            label="Valor"
            type="number"
            min="0.01"
            step="0.01"
            required
          />
          <AppSelect
            v-model="form.payment_method"
            label="Método de pagamento"
            :options="paymentMethodOptions"
          />
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
          <h2 class="text-sm font-semibold text-zinc-800 sm:col-span-2">Associações</h2>
          <AppSelect
            v-model="form.client_id"
            label="Cliente"
            empty-label="Selecione um cliente"
            :options="clientOptions"
            required
          />
          <AppSelect
            v-model="form.professional_id"
            label="Profissional"
            empty-label="Selecione um profissional"
            :options="professionalOptions"
            required
          />
          <AppSelect
            v-model="form.service_id"
            label="Serviço"
            empty-label="Sem serviço"
            :options="serviceOptions"
            class="sm:col-span-2"
          />
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <AppButton variant="secondary" type="button" @click="closeModal">Cancelar</AppButton>
          <AppButton type="submit" :loading="saving">Guardar</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
