<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AccountMenu from '@/components/layout/AccountMenu.vue'
import { IMAGES } from '@/lib/images'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const menuOpen = ref(false)

const links = [
  { to: '/panel/client/dashboard', name: 'client-dashboard', label: 'Início' },
  { to: '/panel/client/appointments', name: 'client-appointments', label: 'Meus agendamentos' },
]

const firstName = computed(() => auth.user?.first_name?.trim() || auth.fullName.split(' ')[0] || 'Cliente')

async function handleLogout() {
  await auth.logout()
  ui.info('Sessão terminada.')
  window.location.href = '/login'
}

function goEditProfile() {
  router.push({ name: 'client-account', query: { action: 'edit-profile' } })
}

function goChangePassword() {
  router.push({ name: 'client-account', query: { action: 'change-password' } })
}

function isActive(path: string) {
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <!-- div[role=banner]: evita seletor global `header` do CSS legado público -->
  <div role="banner" class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur">
    <div class="client-page-container flex h-14 items-center justify-between gap-3 sm:h-16">
      <div class="flex min-w-0 items-center gap-3">
        <RouterLink to="/panel/client/dashboard" class="flex min-w-0 items-center gap-2.5">
          <img :src="IMAGES.logo" alt="Salon System" class="h-8 w-auto sm:h-9" />
          <span class="hidden min-w-0 sm:block">
            <span class="block truncate text-sm font-bold text-brand-700">Salon System</span>
            <span class="block truncate text-[11px] text-zinc-500">Olá, {{ firstName }}</span>
          </span>
        </RouterLink>
      </div>

      <nav class="hidden items-center gap-1 md:flex" aria-label="Área do cliente">
        <RouterLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="
            isActive(link.to)
              ? 'bg-brand-100 text-brand-800'
              : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
          "
        >
          {{ link.label }}
        </RouterLink>
      </nav>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 md:hidden"
          :aria-expanded="menuOpen"
          aria-label="Abrir menu"
          @click="menuOpen = !menuOpen"
        >
          Menu
        </button>

        <AccountMenu
          align="right"
          account-route-name="client-account"
          :full-name="auth.fullName"
          role-label="Cliente"
          @logout="handleLogout"
          @edit-profile="goEditProfile"
          @change-password="goChangePassword"
        />
      </div>
    </div>

    <nav
      v-if="menuOpen"
      class="border-t border-zinc-100 px-4 py-2 md:hidden"
      aria-label="Menu mobile"
    >
      <RouterLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="block rounded-lg px-3 py-2.5 text-sm font-medium"
        :class="isActive(link.to) ? 'bg-brand-50 text-brand-700' : 'text-zinc-600'"
        @click="menuOpen = false"
      >
        {{ link.label }}
      </RouterLink>
      <RouterLink
        :to="{ name: 'client-account' }"
        class="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600"
        @click="menuOpen = false"
      >
        Minha conta
      </RouterLink>
    </nav>
  </div>
</template>
