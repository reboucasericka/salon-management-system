<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { fetchPublicProductCategories, fetchPublicProducts } from '@/services/publicApi'
import type { Product, ProductCategory } from '@/types/api'
import { IMAGES, mediaUrl } from '@/lib/images'

const products = ref<Product[]>([])
const categories = ref<ProductCategory[]>([])
const loading = ref(true)
const categoryFilter = ref('')

function formatPrice(value: string) {
  return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(value))
}

function categoryName(product: Product) {
  return product.product_category?.name?.trim() || product.category?.trim() || 'Sem categoria'
}

function productImage(product: Product) {
  return mediaUrl(product.image_url || product.image) || IMAGES.placeholder
}

function onImageError(event: Event) {
  const img = event.target as HTMLImageElement
  if (img.dataset.fallback === '1') return
  img.dataset.fallback = '1'
  img.src = IMAGES.placeholder
}

const filteredProducts = computed(() => {
  if (!categoryFilter.value) return products.value
  if (categoryFilter.value === '__none__') {
    return products.value.filter((p) => !p.product_category_id)
  }
  const id = Number(categoryFilter.value)
  return products.value.filter((p) => p.product_category_id === id)
})

const hasUncategorized = computed(() => products.value.some((p) => !p.product_category_id))

onMounted(async () => {
  try {
    const [productsResponse, categoriesResponse] = await Promise.all([
      fetchPublicProducts({ per_page: 100 }),
      fetchPublicProductCategories(),
    ])
    products.value = productsResponse.data ?? []
    categories.value = categoriesResponse ?? []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="products-page">
    <section class="content">
      <div class="wrapper">
        <div class="intro">
          <h1 class="title">Produtos</h1>
          <p class="subtitle">Conheça os produtos disponíveis no nosso espaço</p>
        </div>

        <div
          v-if="categories.length || hasUncategorized"
          class="category-filters"
        >
          <button
            type="button"
            class="filter-chip"
            :class="{ active: !categoryFilter }"
            @click="categoryFilter = ''"
          >
            Todas
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            class="filter-chip"
            :class="{ active: categoryFilter === String(cat.id) }"
            @click="categoryFilter = String(cat.id)"
          >
            {{ cat.name }}
          </button>
          <button
            v-if="hasUncategorized"
            type="button"
            class="filter-chip"
            :class="{ active: categoryFilter === '__none__' }"
            @click="categoryFilter = '__none__'"
          >
            Sem categoria
          </button>
        </div>

        <p v-if="loading" class="state">A carregar...</p>
        <p v-else-if="!filteredProducts.length" class="state">
          Nenhum produto disponível no momento.
        </p>

        <div v-else class="grid">
          <article v-for="product in filteredProducts" :key="product.id" class="card">
            <div class="thumb">
              <img
                :src="productImage(product)"
                alt=""
                @error="onImageError"
              />
            </div>
            <div class="body">
              <h2 class="name">{{ product.name }}</h2>
              <p class="category">{{ categoryName(product) }}</p>
              <p class="price">{{ formatPrice(product.price) }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.products-page {
  margin-top: 80px;
}

.content {
  padding: 4rem 0 5rem;
  background: #f8f9fa;
}

.wrapper {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 1.25rem;
}

.intro {
  text-align: center;
  margin-bottom: 2rem;
}

.title {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: clamp(2rem, 4vw, 2.5rem);
  color: #2c2c2c;
  margin: 0 0 0.5rem;
}

.subtitle {
  color: #888;
  font-size: 1rem;
  margin: 0;
}

.category-filters {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 2rem;
}

.filter-chip {
  border: 1px solid #d4d4d8;
  background: #fff;
  border-radius: 999px;
  padding: 0.4rem 0.9rem;
  font-size: 0.85rem;
  color: #52525b;
  cursor: pointer;
}

.filter-chip.active {
  background: #8b6b4a;
  border-color: #8b6b4a;
  color: #fff;
}

.state {
  text-align: center;
  color: #888;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.25rem;
}

.card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.thumb {
  width: 5rem;
  height: 5rem;
  margin: 0 auto 1rem;
  overflow: hidden;
  border-radius: 0.75rem;
  background: #f4f4f5;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.body {
  text-align: center;
}

.name {
  font-size: 1.05rem;
  font-weight: 600;
  color: #1f1f1f;
  margin: 0 0 0.35rem;
  line-height: 1.3;
}

.category {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  color: #888;
}

.price {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #333;
}
</style>
