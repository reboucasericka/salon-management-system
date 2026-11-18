<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage } from '@/lib/api'
import {
  createPayable,
  deletePayable,
  fetchPayables,
  fetchProfessionals,
  fetchSuppliers,
  markPayablePaid,
  updatePayable,
} from '@/services/salonApi'
import type { Payable, PayableType, Professional, Supplier } from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const payables = ref<Payable[]>([])
const suppliers = ref<Supplier[]>([])
const professionals = ref<Professional[]>([])
const loading = ref(true)
const saving = ref(false)
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const supplierFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const modalOpen = ref(false)
const editing = ref<Payable | null>(null)

const form = reactive({
  description: '',
  amount: '',
  due_date: '',
  type: 'expense' as PayableType,
  notes: '',
  supplier_id: '',
  professional_id: '',
})

const statusOptions = [
  { value: 'pending', label: 'Pendente' },
  { value: 'paid', label: 'Pago' },
  { value: 'overdue', label: 'Vencido' },
]

const typeOptions = [
  { value: 'expense', label: 'Despesa' },
  { value: 'commission', label: 'Comissão' },
  { value: 'supplier', label: 'Fornecedor' },
  { value: 'other', label: 'Outro' },
]

const supplierOptions = computed(() =>
  suppliers.value.map((s) => ({ value: String(s.id), label: s.name })),
)

const professionalOptions = computed(() =>
  professionals.value.map((p) => ({ value: String(p.id), label: p.name })),
)

const pageTitle = computed(() => (editing.value ? 'Editar conta a pagar' : 'Nova conta a pagar'))

const showSupplierField = computed(
  () => form.type === 'supplier' || form.type === 'expense' || form.type === 'other',
)

const showProfessionalField = computed(
  () => form.type === 'commission' || form.type === 'expense' || form.type === 'other',
)

function formatMoney(value: string | number) {
  const num = typeof value === 'string' ? Number(value) : value
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(num || 0)
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  const [y, m, d] = value.split('-')
  if (!y || !m || !d) return value
  return `${d}/${m}/${y}`
}

function partyLabel(row: Payable) {
  if (row.supplier?.name) return row.supplier.name
  if (row.professional?.name) return row.professional.name
  return '—'
}

function statusTone(row: Payable): 'default' | 'success' | 'warning' | 'danger' {
  if (row.display_status === 'paid') return 'success'
  if (row.display_status === 'overdue') return 'danger'
  return 'warning'
}

function resetForm() {
  form.description = ''
  form.amount = ''
  form.due_date = ''
  form.type = 'expense'
  form.notes = ''
  form.supplier_id = ''
  form.professional_id = ''
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

function openEdit(row: Payable) {
  editing.value = row
  form.description = row.description
  form.amount = row.amount
  form.due_date = row.due_date
  form.type = row.type
  form.notes = row.notes ?? ''
  form.supplier_id = row.supplier_id ? String(row.supplier_id) : ''
  form.professional_id = row.professional_id ? String(row.professional_id) : ''
  modalOpen.value = true
}

watch(
  () => form.type,
  (type) => {
    if (type === 'commission') form.supplier_id = ''
    if (type === 'supplier') form.professional_id = ''
  },
)

async function loadLookups() {
  try {
    const [suppliersRes, professionalsRes] = await Promise.all([
      fetchSuppliers({ per_page: 100, is_active: true }),
      fetchProfessionals({ per_page: 100 }),
    ])
    suppliers.value = suppliersRes.data ?? []
    professionals.value = professionalsRes.data ?? []
  } catch {
    suppliers.value = []
    professionals.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const response = await fetchPayables({
      page: page.value,
      per_page: 15,
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      type: typeFilter.value || undefined,
      supplier_id: supplierFilter.value ? Number(supplierFilter.value) : undefined,
    })
    payables.value = response.data ?? []
    lastPage.value = response.meta?.last_page ?? 1
    total.value = response.meta?.total ?? payables.value.length
  } catch (err) {
    payables.value = []
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
      due_date: form.due_date,
      type: form.type,
      notes: form.notes || null,
      supplier_id: form.supplier_id ? Number(form.supplier_id) : null,
      professional_id: form.professional_id ? Number(form.professional_id) : null,
    }

    if (editing.value) {
      await updatePayable(editing.value.id, payload)
      ui.success('Conta a pagar atualizada.')
    } else {
      await createPayable(payload)
      ui.success('Conta a pagar criada.')
    }

    closeModal()
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function markPaid(row: Payable) {
  if (row.is_paid) return

  const ok = await ui.confirm({
    title: 'Marcar como pago',
    message: `Confirmar pagamento de "${row.description}" (${formatMoney(row.amount)})?`,
    confirmLabel: 'Marcar como pago',
  })
  if (!ok) return

  try {
    await markPayablePaid(row.id)
    ui.success('Conta marcada como paga.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

async function remove(row: Payable) {
  const ok = await ui.confirm({
    title: 'Eliminar conta a pagar',
    message: `Eliminar permanentemente "${row.description}"?`,
    confirmLabel: 'Eliminar',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deletePayable(row.id)
    ui.success('Conta a pagar eliminada.')
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
      <h1 class="text-2xl font-semibold text-zinc-900">Contas a pagar</h1>
      <p class="mt-1 text-sm text-zinc-600">
        Gerencie despesas, comissões e pagamentos a fornecedores.
      </p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="onSearch">
        <AppInput
          v-model="search"
          label="Pesquisar"
          placeholder="Descrição ou notas"
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
          v-model="typeFilter"
          label="Tipo"
          empty-label="Todos os tipos"
          :options="typeOptions"
          class="min-w-[150px]"
        />
        <AppSelect
          v-model="supplierFilter"
          label="Fornecedor"
          empty-label="Todos"
          :options="supplierOptions"
          class="min-w-[160px]"
        />
        <AppButton type="submit" variant="secondary" class="self-end">Filtrar</AppButton>
      </form>
      <AppButton @click="openCreate">Nova conta</AppButton>
    </div>

    <LoadingState v-if="loading" />
    <EmptyState
      v-else-if="!payables.length"
      title="Nenhuma conta a pagar"
      description="Crie a primeira conta a pagar para começar o controlo financeiro."
    />

    <template v-else>
      <div class="hidden md:block">
        <AppTable>
          <thead>
            <tr>
              <th>Descrição</th>
              <th>Fornecedor/Profissional</th>
              <th>Tipo</th>
              <th>Vencimento</th>
              <th>Valor</th>
              <th>Estado</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in payables" :key="row.id">
              <td>
                <div class="font-medium text-zinc-900">{{ row.description }}</div>
                <div v-if="row.paid_at" class="mt-0.5 text-xs text-zinc-500">
                  Pago em {{ formatDate(row.paid_at) }}
                </div>
              </td>
              <td>{{ partyLabel(row) }}</td>
              <td>{{ row.type_label }}</td>
              <td>{{ formatDate(row.due_date) }}</td>
              <td class="font-medium">{{ formatMoney(row.amount) }}</td>
              <td>
                <AppBadge :tone="statusTone(row)">{{ row.display_status_label }}</AppBadge>
              </td>
              <td>
                <div class="flex flex-wrap gap-2">
                  <AppButton variant="secondary" @click="openEdit(row)">Editar</AppButton>
                  <AppButton
                    v-if="!row.is_paid"
                    variant="secondary"
                    @click="markPaid(row)"
                  >
                    Pagar
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
          v-for="row in payables"
          :key="row.id"
          class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-semibold text-zinc-900">{{ row.description }}</h3>
              <p class="mt-1 text-sm text-zinc-600">{{ partyLabel(row) }}</p>
            </div>
            <AppBadge :tone="statusTone(row)">{{ row.display_status_label }}</AppBadge>
          </div>
          <p class="mt-3 text-sm text-zinc-600">
            {{ row.type_label }} · Vence {{ formatDate(row.due_date) }} ·
            <span class="font-medium text-zinc-900">{{ formatMoney(row.amount) }}</span>
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton variant="secondary" class="flex-1" @click="openEdit(row)">Editar</AppButton>
            <AppButton v-if="!row.is_paid" variant="secondary" @click="markPaid(row)">
              Pagar
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
          <AppInput v-model="form.due_date" label="Data de vencimento" type="date" required />
          <AppSelect
            v-model="form.type"
            label="Tipo"
            :options="typeOptions"
            :empty-label="undefined"
            class="sm:col-span-2"
          />
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
          <h2 class="text-sm font-semibold text-zinc-800 sm:col-span-2">Associações</h2>
          <AppSelect
            v-if="showSupplierField"
            v-model="form.supplier_id"
            label="Fornecedor"
            empty-label="Sem fornecedor"
            :options="supplierOptions"
          />
          <AppSelect
            v-if="showProfessionalField"
            v-model="form.professional_id"
            label="Profissional"
            empty-label="Sem profissional"
            :options="professionalOptions"
          />
          <AppTextarea v-model="form.notes" label="Observações" :rows="3" class="sm:col-span-2" />
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <AppButton variant="secondary" type="button" @click="closeModal">Cancelar</AppButton>
          <AppButton type="submit" :loading="saving">Guardar</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
