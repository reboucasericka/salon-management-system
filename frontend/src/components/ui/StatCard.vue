<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  label: string
  value: string | number
  hint?: string
  /** Mantido por compatibilidade; já não aplica cor decorativa. */
  variant?: 'default' | 'info' | 'success' | 'warning' | 'danger' | 'brand'
  to?: string
  compact?: boolean
}>()
</script>

<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to"
    class="group flex h-full min-w-0 flex-col rounded-xl border border-[#E5E7EB] bg-white shadow-sm transition"
    :class="[
      to ? 'cursor-pointer hover:border-[#E6C6B6] hover:bg-[#FAFAFA]' : '',
      compact ? 'min-h-[112px] p-5' : 'min-h-[120px] p-5',
    ]"
  >
    <p class="break-words text-xs font-medium leading-snug text-[#6B7280]">
      {{ label }}
    </p>

    <p
      class="mt-2 font-semibold tabular-nums leading-none text-[#1F2937]"
      :class="compact ? 'text-2xl' : 'text-2xl'"
    >
      {{ value }}
    </p>

    <p v-if="hint" class="mt-1.5 text-xs leading-snug text-[#9CA3AF]">{{ hint }}</p>

    <span
      v-if="to && !compact"
      class="mt-auto pt-2 text-xs font-medium text-[#9CA3AF] transition group-hover:text-brand-600"
    >
      Ver detalhes →
    </span>
  </component>
</template>
