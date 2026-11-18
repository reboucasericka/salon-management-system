<script setup lang="ts">
import ClientHeader from '@/components/layout/ClientHeader.vue'

defineProps<{
  title?: string
}>()
</script>

<template>
  <div class="client-shell min-h-screen bg-gradient-to-br from-brand-50 via-white to-zinc-50">
    <ClientHeader />

    <main class="client-main pb-10 pt-6 sm:pt-8">
      <div class="client-page-container">
        <h1
          v-if="title"
          class="mb-5 text-xl font-bold tracking-tight text-zinc-900 sm:mb-6 sm:text-2xl"
        >
          {{ title }}
        </h1>
        <slot />
      </div>
    </main>
  </div>
</template>

<style scoped>
/*
  Largura do conteúdo: ~1320px.
  Causa do layout estreito anterior: max-w-5xl (1024px) no header e no main.
*/
.client-page-container {
  width: 100%;
  max-width: 82.5rem; /* 1320px */
  margin-inline: auto;
  padding-inline: 1rem; /* 16px mobile */
}

@media (min-width: 640px) {
  .client-page-container {
    padding-inline: 1.5rem; /* 24px tablet */
  }
}

@media (min-width: 1024px) {
  .client-page-container {
    padding-inline: 2rem; /* 32px desktop */
  }
}
</style>
