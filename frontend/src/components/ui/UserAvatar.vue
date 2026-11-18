<script setup lang="ts">
import { computed } from 'vue'
import { userInitials } from '@/lib/userInitials'

const props = withDefaults(
  defineProps<{
    name?: string | null
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { size: 'md' },
)

const initials = computed(() => userInitials(props.name))

const sizeClass = computed(() => {
  if (props.size === 'sm') return 'h-8 w-8 text-[11px]'
  if (props.size === 'lg') return 'h-14 w-14 text-lg'
  return 'h-9 w-9 text-xs'
})
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center rounded-full bg-[#F7EDE8] font-semibold text-[#C97D5D] ring-1 ring-[#E6C6B6]/50"
    :class="sizeClass"
    aria-hidden="true"
  >
    {{ initials }}
  </span>
</template>
