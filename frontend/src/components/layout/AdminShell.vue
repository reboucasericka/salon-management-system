<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import AdminHeader from '@/components/layout/AdminHeader.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { IMAGES } from '@/lib/images'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

type NavItem = {
  to: RouteLocationRaw
  label: string
  roles: string[]
  exact?: boolean
}

type NavGroup = {
  label: string
  items: NavItem[]
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const mobileOpen = ref(false)

const dashboardLink: NavItem = {
  to: { name: 'admin-dashboard' },
  label: 'Dashboard',
  roles: ['admin', 'professional'],
  exact: true,
}

const navGroups: NavGroup[] = [
  {
    label: 'Administração',
    items: [
      { to: { name: 'admin-professionals' }, label: 'Profissionais', roles: ['admin'] },
      { to: { name: 'admin-settings' }, label: 'Definições', roles: ['admin'] },
    ],
  },
  {
    label: 'Operacional',
    items: [
      { to: { name: 'admin-appointments' }, label: 'Agendamentos', roles: ['admin', 'professional'] },
      { to: { name: 'admin-professional-schedules' }, label: 'Horários', roles: ['admin'] },
      { to: { name: 'admin-clients' }, label: 'Clientes', roles: ['admin', 'professional'] },
    ],
  },
  {
    label: 'Catálogo',
    items: [
      { to: { name: 'admin-categories' }, label: 'Categorias', roles: ['admin'] },
      { to: { name: 'admin-services' }, label: 'Serviços', roles: ['admin'] },
      { to: { name: 'admin-products' }, label: 'Produtos', roles: ['admin', 'professional'] },
      { to: { name: 'admin-suppliers' }, label: 'Fornecedores', roles: ['admin'] },
      { to: { name: 'admin-stock' }, label: 'Stock', roles: ['admin', 'professional'] },
    ],
  },
  {
    label: 'Comercial',
    items: [
      { to: { name: 'admin-sales' }, label: 'Vendas', roles: ['admin', 'professional'] },
      { to: { name: 'admin-cash' }, label: 'Caixa', roles: ['admin', 'professional'] },
    ],
  },
  {
    label: 'Financeiro',
    items: [
      { to: { name: 'admin-payables' }, label: 'Contas a pagar', roles: ['admin'] },
      { to: { name: 'admin-receivables' }, label: 'Contas a receber', roles: ['admin'] },
      { to: { name: 'admin-commissions' }, label: 'Comissões', roles: ['admin'] },
      { to: { name: 'admin-reports' }, label: 'Relatórios', roles: ['admin'] },
    ],
  },
  {
    label: 'Comunicação',
    items: [{ to: { name: 'admin-messages' }, label: 'Mensagens', roles: ['admin'] }],
  },
]

const showDashboard = computed(() => auth.role && dashboardLink.roles.includes(auth.role))
const showAccount = computed(() => auth.role === 'admin' || auth.role === 'professional')

const visibleGroups = computed(() =>
  navGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => auth.role && item.roles.includes(auth.role)),
    }))
    .filter((group) => group.items.length > 0),
)

function linkKey(link: NavItem) {
  if (typeof link.to === 'string') return link.to
  if (typeof link.to === 'object' && link.to && 'name' in link.to && link.to.name != null) {
    return String(link.to.name)
  }
  return JSON.stringify(link.to)
}

function isActive(link: NavItem) {
  const path = router.resolve(link.to).path
  if (link.exact) return route.path === path
  return route.path === path || route.path.startsWith(`${path}/`)
}

const accountActive = computed(() => route.name === 'panel-account')

watch(
  () => route.fullPath,
  () => {
    mobileOpen.value = false
  },
)

async function handleLogout() {
  await auth.logout()
  ui.info('Sessão terminada.')
  window.location.href = '/login'
}
</script>

<template>
  <div class="admin-panel overflow-x-hidden">
    <!-- Desktop sidebar: fixed width column, sticky full height -->
    <aside
      class="admin-sidebar hidden h-screen flex-col overflow-y-auto border-r border-[#E6C6B6]/35 lg:sticky lg:top-0 lg:flex"
    >
      <div class="shrink-0 border-b border-[#E6C6B6]/30 px-4 py-3.5">
        <RouterLink :to="{ name: 'admin-dashboard' }" class="admin-nav-link block">
          <img :src="IMAGES.logo" alt="Salon" class="h-8 w-auto" />
        </RouterLink>
      </div>

      <nav class="flex min-h-0 flex-1 flex-col px-2.5 py-3 text-left">
        <ul v-if="showDashboard" class="mb-3 space-y-0.5">
          <li>
            <RouterLink
              :to="dashboardLink.to"
              class="admin-nav-link block rounded-md px-2.5 py-2 text-sm font-medium"
              :class="isActive(dashboardLink) ? 'is-active' : ''"
            >
              {{ dashboardLink.label }}
            </RouterLink>
          </li>
        </ul>

        <div v-for="group in visibleGroups" :key="group.label" class="mb-3">
          <p class="admin-nav-group mb-1 px-2.5">{{ group.label }}</p>
          <ul class="space-y-0.5">
            <li v-for="item in group.items" :key="linkKey(item)">
              <RouterLink
                :to="item.to"
                class="admin-nav-link block rounded-md px-2.5 py-2 text-sm font-medium"
                :class="isActive(item) ? 'is-active' : ''"
              >
                {{ item.label }}
              </RouterLink>
            </li>
          </ul>
        </div>

        <div v-if="showAccount" class="mt-auto border-t border-[#E6C6B6]/30 pt-2">
          <RouterLink
            :to="{ name: 'panel-account' }"
            class="admin-nav-link flex items-center gap-2.5 rounded-md px-2.5 py-2.5"
            :class="accountActive ? 'is-active' : ''"
          >
            <UserAvatar :name="auth.fullName" size="sm" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium leading-tight">{{ auth.fullName }}</span>
              <span class="block truncate text-[11px] font-normal leading-tight text-[#6B7280]">
                {{ auth.user?.role_label }}
              </span>
            </span>
          </RouterLink>
        </div>
      </nav>
    </aside>

    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-40 bg-black/35 lg:hidden"
      @click="mobileOpen = false"
    />

    <!-- Mobile drawer only -->
    <aside
      class="admin-sidebar fixed inset-y-0 left-0 z-50 flex flex-col overflow-y-auto border-r border-[#E6C6B6]/35 transition-transform duration-200 lg:hidden"
      :style="{ width: 'min(var(--admin-sidebar-width), 86vw)' }"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex shrink-0 items-center justify-between border-b border-[#E6C6B6]/30 px-3 py-3">
        <RouterLink
          :to="{ name: 'admin-dashboard' }"
          class="admin-nav-link block"
          @click="mobileOpen = false"
        >
          <img :src="IMAGES.logo" alt="Salon" class="h-7 w-auto" />
        </RouterLink>
        <button
          type="button"
          class="rounded-md px-2 py-1 text-xs text-[#6B7280] hover:bg-white"
          @click="mobileOpen = false"
        >
          Fechar
        </button>
      </div>

      <nav class="flex min-h-0 flex-1 flex-col px-2.5 py-3 text-left">
        <RouterLink
          v-if="showDashboard"
          :to="dashboardLink.to"
          class="admin-nav-link mb-3 block rounded-md px-2.5 py-2 text-sm font-medium"
          :class="isActive(dashboardLink) ? 'is-active' : ''"
          @click="mobileOpen = false"
        >
          {{ dashboardLink.label }}
        </RouterLink>

        <div v-for="group in visibleGroups" :key="`m-${group.label}`" class="mb-3">
          <p class="admin-nav-group mb-1 px-2.5">{{ group.label }}</p>
          <RouterLink
            v-for="item in group.items"
            :key="`m-${linkKey(item)}`"
            :to="item.to"
            class="admin-nav-link block rounded-md px-2.5 py-2 text-sm font-medium"
            :class="isActive(item) ? 'is-active' : ''"
            @click="mobileOpen = false"
          >
            {{ item.label }}
          </RouterLink>
        </div>

        <div v-if="showAccount" class="mt-auto border-t border-[#E6C6B6]/30 pt-2">
          <RouterLink
            :to="{ name: 'panel-account' }"
            class="admin-nav-link flex items-center gap-2.5 rounded-md px-2.5 py-2.5"
            :class="accountActive ? 'is-active' : ''"
            @click="mobileOpen = false"
          >
            <UserAvatar :name="auth.fullName" size="sm" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium leading-tight">{{ auth.fullName }}</span>
              <span class="block truncate text-[11px] font-normal leading-tight text-[#6B7280]">
                {{ auth.user?.role_label }}
              </span>
            </span>
          </RouterLink>
        </div>
      </nav>
    </aside>

    <!-- MainArea: remaining width after sidebar -->
    <div class="admin-main-area">
      <AdminHeader
        :mobile-open="mobileOpen"
        @toggle-mobile="mobileOpen = !mobileOpen"
        @logout="handleLogout"
      />

      <main class="admin-main-content">
        <div class="admin-page-container">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
