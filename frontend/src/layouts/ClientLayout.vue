<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import ClientShell from '@/components/layout/ClientShell.vue'

const route = useRoute()

/** Dashboard usa intro própria; restantes páginas mostram título no shell. */
const title = computed(() => {
  const map: Record<string, string | undefined> = {
    'client-dashboard': undefined,
    'client-appointments': 'Meus agendamentos',
    'client-account': 'Minha conta',
  }
  return map[route.name as string] ?? 'Área do cliente'
})
</script>

<template>
  <ClientShell :title="title">
    <RouterView />
  </ClientShell>
</template>
