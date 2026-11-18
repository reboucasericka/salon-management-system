<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppImageUpload from '@/components/ui/AppImageUpload.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage } from '@/lib/api'
import { needsMultipart, toFormData } from '@/lib/formData'
import { IMAGES, mediaUrl } from '@/lib/images'
import {
  createProduct,
  deleteProduct,
  fetchProductCategories,
  fetchProducts,
  fetchSuppliers,
  updateProduct,
} from '@/services/salonApi'
import type { Product, ProductCategory, Supplier } from '@/types/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const auth = useAuthStore()
const ui = useUiStore()
const isAdmin = computed(() => auth.role === 'admin')

const products = ref<Product[]>([])
const categories = ref<ProductCategory[]>([])
const suppliers = ref<Supplier[]>([])
const loading = ref(true)
const saving = ref(false)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const categoryFilter = ref('')
const supplierFilter = ref('')
const modalOpen = ref(false)
const editing = ref<Product | null>(null)
const imageFile = ref<File | null>(null)
const removeImage = ref(false)
const currentImageUrl = ref<string | null>(null)

const form = reactive({
  name: '',
  description: '',
  sku: '',
  barcode: '',
  product_category_id: '',
  supplier_id: '',
  price: '',
  cost_price: '',
  stock_quantity: '0',
  min_stock: '0',
})

const categoryOptions = computed(() => [
  { value: '', label: 'Todas as categorias' },
  { value: '__none__', label: 'Sem categoria' },
  ...categories.value.map((c) => ({ value: String(c.id), label: c.name })),
])

const supplierFilterOptions = computed(() => [
  { value: '', label: 'Todos os fornecedores' },
  { value: '__none__', label: 'Sem fornecedor' },
  ...suppliers.value.map((s) => ({ value: String(s.id), label: s.name })),
])

const formCategoryOptions = computed(() =>
  categories.value
    .filter((c) => c.is_active || c.id === editing.value?.product_category_id)
    .map((c) => ({ value: String(c.id), label: c.name })),
)

const formSupplierOptions = computed(() =>
  suppliers.value
    .filter((s) => s.is_active || s.id === editing.value?.supplier_id)
    .map((s) => ({ value: String(s.id), label: s.name })),
)

function categoryName(product: Product) {
  return product.product_category?.name?.trim() || product.category?.trim() || 'Sem categoria'
}

function supplierName(product: Product) {
  return product.supplier?.name?.trim() || 'Sem fornecedor'
}

function productImage(product: Product) {
  return mediaUrl(product.image_url || product.image) || IMAGES.placeholder
}

function onProductImageError(event: Event) {
  const img = event.target as HTMLImageElement
  if (img.dataset.fallback === '1') return
  img.dataset.fallback = '1'
  img.src = IMAGES.placeholder
}

function resetForm() {
  form.name = ''
  form.description = ''
  form.sku = ''
  form.barcode = ''
  form.product_category_id = ''
  form.supplier_id = ''
  form.price = ''
  form.cost_price = ''
  form.stock_quantity = '0'
  form.min_stock = '0'
  imageFile.value = null
  removeImage.value = false
  currentImageUrl.value = null
  editing.value = null
}

function openCreate() {
  resetForm()
  if (categoryFilter.value && categoryFilter.value !== '__none__') {
    form.product_category_id = categoryFilter.value
  }
  if (supplierFilter.value && supplierFilter.value !== '__none__') {
    form.supplier_id = supplierFilter.value
  }
  modalOpen.value = true
}

function openEdit(product: Product) {
  editing.value = product
  form.name = product.name
  form.description = product.description ?? ''
  form.sku = product.sku ?? ''
  form.barcode = product.barcode ?? ''
  form.product_category_id = product.product_category_id ? String(product.product_category_id) : ''
  form.supplier_id = product.supplier_id ? String(product.supplier_id) : ''
  form.price = String(product.price)
  form.cost_price = product.cost_price ? String(product.cost_price) : ''
  form.stock_quantity = String(product.stock_quantity)
  form.min_stock = String(product.min_stock)
  imageFile.value = null
  removeImage.value = false
  currentImageUrl.value = mediaUrl(product.image_url || product.image)
  modalOpen.value = true
}

async function loadCategories() {
  if (!isAdmin.value) return
  try {
    const response = await fetchProductCategories({ per_page: 200 })
    categories.value = response.data ?? []
  } catch (err) {
    categories.value = []
    ui.error(getErrorMessage(err, 'Não foi possível carregar as categorias de produto.'))
  }
}

async function loadSuppliers() {
  if (!isAdmin.value) return
  try {
    const response = await fetchSuppliers({ per_page: 200 })
    suppliers.value = response.data ?? []
  } catch {
    suppliers.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number | boolean | undefined> = {
      page: page.value,
      per_page: 9,
      search: search.value || undefined,
    }
    if (categoryFilter.value === '__none__') {
      params.product_category_id = 'null'
    } else if (categoryFilter.value) {
      params.product_category_id = Number(categoryFilter.value)
    }
    if (supplierFilter.value === '__none__') {
      params.supplier_id = 'null'
    } else if (supplierFilter.value) {
      params.supplier_id = Number(supplierFilter.value)
    }
    const response = await fetchProducts(params)
    products.value = response.data ?? []
    lastPage.value = response.meta?.last_page ?? 1
    total.value = response.meta?.total ?? products.value.length
  } catch (err) {
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
      description: form.description || null,
      sku: form.sku || null,
      barcode: form.barcode || null,
      product_category_id: form.product_category_id ? Number(form.product_category_id) : null,
      supplier_id: form.supplier_id ? Number(form.supplier_id) : null,
      price: form.price,
      cost_price: form.cost_price || null,
      stock_quantity: Number(form.stock_quantity),
      min_stock: Number(form.min_stock),
      is_active: true,
    }
    const useMultipart = needsMultipart(
      { image: imageFile.value },
      { remove_image: removeImage.value },
    )

    if (editing.value) {
      if (useMultipart) {
        await updateProduct(
          editing.value.id,
          toFormData(
            { ...payload, remove_image: removeImage.value },
            { method: 'PUT', files: { image: imageFile.value } },
          ),
        )
      } else {
        await updateProduct(editing.value.id, payload)
      }
      ui.success('Produto atualizado.')
    } else if (useMultipart) {
      await createProduct(toFormData({ ...payload }, { files: { image: imageFile.value } }))
      ui.success('Produto criado.')
    } else {
      await createProduct(payload)
      ui.success('Produto criado.')
    }
    modalOpen.value = false
    resetForm()
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function remove(product: Product) {
  const ok = await ui.confirm({
    title: 'Desativar produto',
    message: `Desativar "${product.name}"? O produto deixa de aparecer nas vendas.`,
    confirmLabel: 'Desativar',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteProduct(product.id)
    ui.success('Produto desativado.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

function formatPrice(value: string) {
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(value))
}

function onPageChange(newPage: number) {
  page.value = newPage
  load()
}

function onSearch() {
  page.value = 1
  load()
}

onMounted(async () => {
  await Promise.all([loadCategories(), loadSuppliers()])
  await load()
})
</script>

<template>
  <div class="w-full space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="onSearch">
        <AppInput v-model="search" label="Pesquisar" placeholder="Nome, SKU ou categoria" class="min-w-[200px] flex-1" />
        <AppSelect
          v-if="isAdmin && categories.length"
          v-model="categoryFilter"
          label="Categoria"
          :options="categoryOptions"
          class="min-w-[200px]"
        />
        <AppSelect
          v-if="isAdmin && suppliers.length"
          v-model="supplierFilter"
          label="Fornecedor"
          :options="supplierFilterOptions"
          class="min-w-[200px]"
        />
        <AppButton type="submit" variant="secondary" class="self-end">Filtrar</AppButton>
      </form>
      <AppButton v-if="isAdmin" @click="openCreate">Novo produto</AppButton>
    </div>

    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!products.length" title="Nenhum produto encontrado" />

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <AppCard v-for="product in products" :key="product.id">
        <div class="mb-4 flex items-start gap-3">
          <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-zinc-100">
            <img
              :src="productImage(product)"
              alt=""
              class="h-full w-full object-cover"
              @error="onProductImageError"
            />
            <span
              v-if="product.is_low_stock"
              class="absolute -right-1 -top-1 rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white"
            >
              Stock baixo
            </span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="font-semibold text-zinc-900">{{ product.name }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ categoryName(product) }}</p>
                <p class="mt-0.5 text-xs text-zinc-400">{{ supplierName(product) }}</p>
              </div>
              <span
                class="shrink-0 rounded-full px-2 py-1 text-xs font-medium"
                :class="product.is_low_stock ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
              >
                {{ product.stock_quantity }} / min {{ product.min_stock }}
              </span>
            </div>
            <p class="mt-3 text-lg font-bold">{{ formatPrice(product.price) }}</p>
          </div>
        </div>
        <div v-if="isAdmin" class="mt-4 flex gap-2">
          <AppButton variant="secondary" class="flex-1" @click="openEdit(product)">Editar</AppButton>
          <AppButton variant="danger" @click="remove(product)">Desativar</AppButton>
        </div>
      </AppCard>
    </div>

    <AppPagination
      v-if="!loading && products.length"
      :current-page="page"
      :last-page="lastPage"
      :total="total"
      @change="onPageChange"
    />

    <AppModal :open="modalOpen" :title="editing ? 'Editar produto' : 'Novo produto'" @close="modalOpen = false">
      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <AppInput v-model="form.name" label="Nome" required class="sm:col-span-2" />
        <AppInput v-model="form.sku" label="SKU" />
        <AppInput v-model="form.barcode" label="Código de barras" />
        <AppSelect
          v-model="form.product_category_id"
          label="Categoria"
          :options="formCategoryOptions"
          empty-label="Sem categoria"
          class="sm:col-span-2"
        />
        <p
          v-if="isAdmin && !categories.length"
          class="sm:col-span-2 -mt-2 text-xs text-amber-700"
        >
          Ainda não há categorias de produto. Em Categorias, abre o separador
          <strong>Produtos</strong> (não Serviços) e cria as categorias aí.
        </p>
        <AppSelect
          v-model="form.supplier_id"
          label="Fornecedor"
          :options="formSupplierOptions"
          empty-label="Sem fornecedor"
          class="sm:col-span-2"
        />
        <AppInput v-model="form.price" label="Preço (€)" type="number" step="0.01" required />
        <AppInput v-model="form.cost_price" label="Preço de custo (€)" type="number" step="0.01" />
        <AppInput v-model="form.stock_quantity" label="Stock atual" type="number" min="0" />
        <AppInput v-model="form.min_stock" label="Stock mínimo" type="number" min="0" />
        <AppImageUpload
          v-model="imageFile"
          v-model:remove="removeImage"
          label="Imagem"
          :current-url="currentImageUrl"
          class="sm:col-span-2"
        />
        <AppInput v-model="form.description" label="Descrição" class="sm:col-span-2" />
        <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
          <AppButton variant="secondary" type="button" @click="modalOpen = false">Cancelar</AppButton>
          <AppButton type="submit" :loading="saving">Guardar</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
