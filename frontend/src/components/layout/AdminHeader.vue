<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AccountMenu from '@/components/layout/AccountMenu.vue'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  mobileOpen?: boolean
}>()

const emit = defineEmits<{
  'toggle-mobile': []
  logout: []
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const pageTitle = computed(() => {
  const titled = [...route.matched].reverse().find((record) => record.meta?.title)
  return titled?.meta?.title ?? 'Painel'
})

function goEditProfile() {
  router.push({ name: 'panel-account', query: { action: 'edit-profile' } })
}

function goChangePassword() {
  router.push({ name: 'panel-account', query: { action: 'change-password' } })
}
</script>

<template>
  <!-- div[role=banner]: evita seletor global `header` do CSS legado público -->
  <div
    role="banner"
    class="admin-header sticky top-0 z-30 h-14 shrink-0 border-b border-[#E5E7EB] bg-white sm:h-16"
  >
    <div class="admin-page-container flex h-full items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <button
          type="button"
          class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-[#E5E7EB] bg-white text-[#4B5563] hover:bg-[#F9FAFB] lg:hidden"
          :aria-expanded="mobileOpen"
          aria-label="Abrir menu"
          @click="emit('toggle-mobile')"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
              fill-rule="evenodd"
              d="M3 5h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
        <h1 class="truncate text-[1.5rem] font-semibold leading-tight text-[#1F2937] sm:text-[1.75rem]">
          {{ pageTitle }}
        </h1>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <a
          href="/booking"
          target="_blank"
          rel="noopener noreferrer"
          class="hidden h-9 items-center gap-1.5 rounded-md border border-[#E5E7EB] bg-white px-3 text-sm font-medium text-[#4B5563] transition hover:border-[#E6C6B6] hover:bg-[#FAF7F5] hover:text-[#C97D5D] md:inline-flex"
        >
          Ver site público
          <svg class="h-3.5 w-3.5 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
              d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"
            />
            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
          </svg>
        </a>

        <AccountMenu
          align="right"
          :full-name="auth.fullName"
          :role-label="auth.user?.role_label"
          @logout="emit('logout')"
          @edit-profile="goEditProfile"
          @change-password="goChangePassword"
        />
      </div>
    </div>
  </div>
</template>
