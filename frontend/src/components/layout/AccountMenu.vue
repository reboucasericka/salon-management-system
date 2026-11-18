<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import UserAvatar from '@/components/ui/UserAvatar.vue'

const props = withDefaults(
  defineProps<{
    fullName: string
    roleLabel?: string | null
    open?: boolean
    align?: 'left' | 'right'
    /** Rota da página de conta (admin: panel-account; client: client-account). */
    accountRouteName?: string
  }>(),
  {
    open: undefined,
    align: 'right',
    accountRouteName: 'panel-account',
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  logout: []
  'edit-profile': []
  'change-password': []
}>()

const route = useRoute()
const rootEl = ref<HTMLElement | null>(null)
const internalOpen = ref(false)

const isControlled = computed(() => props.open !== undefined)
const isOpen = computed(() => (isControlled.value ? Boolean(props.open) : internalOpen.value))

function setOpen(value: boolean) {
  if (isControlled.value) emit('update:open', value)
  else internalOpen.value = value
}

function toggle() {
  setOpen(!isOpen.value)
}

function close() {
  setOpen(false)
}

function onEditProfile() {
  close()
  emit('edit-profile')
}

function onChangePassword() {
  close()
  emit('change-password')
}

function onLogout() {
  close()
  emit('logout')
}

function onDocumentClick(event: MouseEvent) {
  if (!isOpen.value) return
  const target = event.target as Node | null
  if (rootEl.value && target && !rootEl.value.contains(target)) close()
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) close()
}

watch(
  () => route.fullPath,
  () => close(),
)

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="rootEl" class="relative shrink-0">
    <button
      type="button"
      class="flex max-w-[220px] cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-left transition hover:bg-[#F9FAFB] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C97D5D]/25"
      :aria-expanded="isOpen"
      aria-haspopup="menu"
      @click.stop="toggle"
    >
      <UserAvatar :name="fullName" size="sm" />
      <span class="hidden min-w-0 sm:block">
        <span class="block truncate text-sm font-medium leading-tight text-[#1F2937]">
          {{ fullName }}
        </span>
        <span v-if="roleLabel" class="block truncate text-[11px] leading-tight text-[#6B7280]">
          {{ roleLabel }}
        </span>
      </span>
      <svg
        class="hidden h-3.5 w-3.5 shrink-0 text-[#9CA3AF] sm:block"
        viewBox="0 0 20 20"
        fill="currentColor"
        aria-hidden="true"
      >
        <path
          fill-rule="evenodd"
          d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
          clip-rule="evenodd"
        />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="absolute top-full z-[60] mt-1.5 w-[240px] overflow-hidden rounded-lg border border-[#E5E7EB] bg-white py-1 shadow-md shadow-black/5"
      :class="align === 'left' ? 'left-0' : 'right-0'"
      role="menu"
    >
      <RouterLink
        :to="{ name: props.accountRouteName }"
        class="flex h-9 items-center px-3 text-sm text-[#374151] transition hover:bg-[#F7EDE8] hover:text-[#C97D5D]"
        role="menuitem"
        @click="close"
      >
        Minha conta
      </RouterLink>
      <button
        type="button"
        class="flex h-9 w-full items-center px-3 text-left text-sm text-[#374151] transition hover:bg-[#F7EDE8] hover:text-[#C97D5D]"
        role="menuitem"
        @click="onEditProfile"
      >
        Editar perfil
      </button>
      <button
        type="button"
        class="flex h-9 w-full items-center px-3 text-left text-sm text-[#374151] transition hover:bg-[#F7EDE8] hover:text-[#C97D5D]"
        role="menuitem"
        @click="onChangePassword"
      >
        Alterar palavra-passe
      </button>
      <div class="my-1 border-t border-[#E5E7EB]" />
      <button
        type="button"
        class="flex h-9 w-full items-center px-3 text-left text-sm text-red-600 transition hover:bg-red-50"
        role="menuitem"
        @click="onLogout"
      >
        Sair
      </button>
    </div>
  </div>
</template>
