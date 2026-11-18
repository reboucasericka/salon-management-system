<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
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
  createSupplier,
  deleteSupplier,
  fetchSuppliers,
  updateSupplier,
} from '@/services/salonApi'
import type { Supplier } from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const suppliers = ref<Supplier[]>([])
const loading = ref(true)
const saving = ref(false)
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const modalOpen = ref(false)
const editing = ref<Supplier | null>(null)

const form = reactive({
  name: '',
  phone: '',
  email: '',
  tax_id: '',
  address: '',
  delivery_time_days: '',
  notes: '',
  is_active: true,
})

const statusOptions = [
  { value: '1', label: 'Ativos' },
  { value: '0', label: 'Inativos' },
]

const pageTitle = computed(() => (editing.value ? 'Editar fornecedor' : 'Novo fornecedor'))

function resetForm() {
  form.name = ''
  form.phone = ''
  form.email = ''
  form.tax_id = ''
  form.address = ''
  form.delivery_time_days = ''
  form.notes = ''
  form.is_active = true
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

function openEdit(row: Supplier) {
  editing.value = row
  form.name = row.name
  form.phone = row.phone
  form.email = row.email ?? ''
  form.tax_id = row.tax_id ?? ''
  form.address = row.address ?? ''
  form.delivery_time_days =
    row.delivery_time_days === null || row.delivery_time_days === undefined
      ? ''
      : String(row.delivery_time_days)
  form.notes = row.notes ?? ''
  form.is_active = row.is_active
  modalOpen.value = true
}

async function load() {
  loading.value = true
  try {
    const response = await fetchSuppliers({
      page: page.value,
      per_page: 15,
      search: search.value || undefined,
      is_active: statusFilter.value === '' ? undefined : statusFilter.value === '1',
    })
    suppliers.value = response.data ?? []
    lastPage.value = response.meta?.last_page ?? 1
    total.value = response.meta?.total ?? suppliers.value.length
  } catch (err) {
    suppliers.value = []
    ui.error(getErrorMessage(err))
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  try {
    const payload = {
      name: form.name,
      phone: form.phone,
      email: form.email || null,
      tax_id: form.tax_id || null,
      address: form.address || null,
      delivery_time_days:
        form.delivery_time_days === '' ? null : Number(form.delivery_time_days),
      notes: form.notes || null,
      is_active: Boolean(form.is_active),
    }

    if (editing.value) {
      await updateSupplier(editing.value.id, payload)
      ui.success('Fornecedor atualizado.')
    } else {
      await createSupplier(payload)
      ui.success('Fornecedor criado.')
    }

    closeModal()
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function toggleActive(row: Supplier) {
  const next = !row.is_active
  try {
    await updateSupplier(row.id, { is_active: next })
    ui.success(next ? 'Fornecedor ativado.' : 'Fornecedor desativado.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

async function remove(row: Supplier) {
  const count = row.products_count ?? 0
  if (count > 0) {
    ui.error(
      'Este fornecedor possui produtos associados e não pode ser eliminado. Reatribua os produtos ou desative o fornecedor.',
    )
    return
  }

  const ok = await ui.confirm({
    title: 'Eliminar fornecedor',
    message: `Eliminar permanentemente "${row.name}"?`,
    confirmLabel: 'Eliminar',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deleteSupplier(row.id)
    ui.success('Fornecedor eliminado.')
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

onMounted(load)
</script>

<template>
  <div class="w-full space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-zinc-900">Fornecedores</h1>
      <p class="mt-1 text-sm text-zinc-600">
        Gerencie os fornecedores associados aos produtos e ao abastecimento do salão.
      </p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="onSearch">
        <AppInput
          v-model="search"
          label="Pesquisar"
          placeholder="Nome, email, NIF ou telefone"
          class="min-w-[200px] flex-1"
        />
        <AppSelect
          v-model="statusFilter"
          label="Estado"
          empty-label="Todos os estados"
          :options="statusOptions"
          class="min-w-[160px]"
        />
        <AppButton type="submit" variant="secondary" class="self-end">Filtrar</AppButton>
      </form>
      <AppButton @click="openCreate">Novo fornecedor</AppButton>
    </div>

    <LoadingState v-if="loading" />
    <EmptyState
      v-else-if="!suppliers.length"
      title="Nenhum fornecedor encontrado"
      description="Adicione o primeiro fornecedor para associar aos produtos."
    />

    <template v-else>
      <div class="hidden md:block">
        <AppTable>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Contacto</th>
              <th>Email</th>
              <th>NIF</th>
              <th>Produtos</th>
              <th>Estado</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in suppliers" :key="row.id">
              <td>
                <div class="font-medium text-zinc-900">{{ row.name }}</div>
                <div v-if="row.delivery_time_days != null" class="mt-0.5 text-xs text-zinc-500">
                  Prazo {{ row.delivery_time_days }} dia(s)
                </div>
              </td>
              <td>{{ row.phone }}</td>
              <td>{{ row.email || '—' }}</td>
              <td>{{ row.tax_id || '—' }}</td>
              <td>{{ row.products_count ?? 0 }}</td>
              <td>
                <AppBadge :tone="row.is_active ? 'success' : 'default'">
                  {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </AppBadge>
              </td>
              <td>
                <div class="flex flex-wrap gap-2">
                  <AppButton variant="secondary" @click="openEdit(row)">Editar</AppButton>
                  <AppButton variant="secondary" @click="toggleActive(row)">
                    {{ row.is_active ? 'Desativar' : 'Ativar' }}
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
          v-for="row in suppliers"
          :key="row.id"
          class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-semibold text-zinc-900">{{ row.name }}</h3>
              <p class="mt-1 text-sm text-zinc-600">{{ row.phone }}</p>
            </div>
            <AppBadge :tone="row.is_active ? 'success' : 'default'">
              {{ row.is_active ? 'Ativo' : 'Inativo' }}
            </AppBadge>
          </div>
          <p class="mt-3 text-sm text-zinc-600">
            {{ row.email || 'Sem email' }} · NIF {{ row.tax_id || '—' }} ·
            {{ row.products_count ?? 0 }} produto(s)
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton variant="secondary" class="flex-1" @click="openEdit(row)">Editar</AppButton>
            <AppButton variant="secondary" @click="toggleActive(row)">
              {{ row.is_active ? 'Desativar' : 'Ativar' }}
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
          <AppInput v-model="form.name" label="Nome da empresa" required class="sm:col-span-2" />
          <AppInput v-model="form.tax_id" label="NIF" maxlength="20" />
          <AppInput
            v-model="form.delivery_time_days"
            label="Prazo de entrega (dias)"
            type="number"
            min="0"
          />
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
          <h2 class="text-sm font-semibold text-zinc-800 sm:col-span-2">Contacto</h2>
          <AppInput v-model="form.phone" label="Telefone" required />
          <AppInput v-model="form.email" label="Email" type="email" />
        </section>

        <section class="grid gap-4">
          <h2 class="text-sm font-semibold text-zinc-800">Morada</h2>
          <AppTextarea v-model="form.address" label="Morada" :rows="2" />
          <AppTextarea v-model="form.notes" label="Observações" :rows="3" />
        </section>

        <section>
          <h2 class="mb-3 text-sm font-semibold text-zinc-800">Estado</h2>
          <label class="flex items-center gap-2 text-sm text-zinc-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
            Fornecedor ativo
          </label>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <AppButton variant="secondary" type="button" @click="closeModal">Cancelar</AppButton>
          <AppButton type="submit" :loading="saving">Guardar</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
