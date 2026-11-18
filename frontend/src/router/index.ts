import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  acquireAdminStyles,
  acquirePublicAssets,
  releasePublicAssets,
  routeNeedsAdminStyles,
  routeNeedsPublicAssets,
} from '@/lib/publicAssets'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: () => import('@/layouts/PublicLayout.vue'),
      meta: { public: true, shell: 'public' },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/views/public/HomeView.vue'),
        },
        {
          path: 'services',
          name: 'public-services',
          component: () => import('@/views/public/PublicServicesView.vue'),
        },
        {
          path: 'products',
          name: 'public-products',
          component: () => import('@/views/public/PublicProductsView.vue'),
        },
        {
          path: 'booking',
          name: 'public-booking',
          component: () => import('@/views/public/PublicBookingView.vue'),
        },
        {
          path: 'booking/flow',
          name: 'booking-flow',
          component: () => import('@/views/public/BookingFlowView.vue'),
        },
        {
          path: 'professionals',
          name: 'public-professionals',
          component: () => import('@/views/public/PublicProfessionalsView.vue'),
        },
        {
          path: 'professionals/:id',
          name: 'public-professional-detail',
          component: () => import('@/views/public/PublicProfessionalDetailView.vue'),
        },
        {
          path: 'contact',
          name: 'public-contact',
          component: () => import('@/views/public/PublicContactView.vue'),
        },
        {
          path: 'prices',
          name: 'public-prices',
          component: () => import('@/views/public/PublicPricesView.vue'),
        },
        {
          path: 'about',
          name: 'public-about',
          component: () => import('@/views/public/PublicAboutView.vue'),
        },
        {
          path: 'academy',
          name: 'public-academy',
          component: () => import('@/views/public/PublicAcademyView.vue'),
        },
        {
          path: 'recruitment',
          name: 'public-recruitment',
          component: () => import('@/views/public/PublicRecruitmentView.vue'),
        },
        {
          path: 'services/:id',
          name: 'public-service-detail',
          component: () => import('@/views/public/ServiceDetailView.vue'),
        },
      ],
    },
    {
      path: '/login',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guest: true, public: true, shell: 'auth' },
      children: [
        {
          path: '',
          name: 'login',
          component: () => import('@/views/LoginView.vue'),
        },
      ],
    },
    {
      path: '/forgot-password',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guest: true, public: true, shell: 'auth' },
      children: [
        {
          path: '',
          name: 'forgot-password',
          component: () => import('@/views/ForgotPasswordView.vue'),
        },
      ],
    },
    {
      path: '/reset-password',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guest: true, public: true, shell: 'auth' },
      children: [
        {
          path: '',
          name: 'reset-password',
          component: () => import('@/views/ResetPasswordView.vue'),
        },
      ],
    },
    {
      path: '/register',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guest: true, public: true, shell: 'auth' },
      children: [
        {
          path: '',
          name: 'register',
          component: () => import('@/views/RegisterView.vue'),
        },
      ],
    },
    {
      path: '/panel',
      component: () => import('@/layouts/PanelLayout.vue'),
      meta: { requiresAuth: true, shell: 'panel' },
      children: [
        {
          path: '',
          name: 'panel-home',
          component: () => import('@/views/PanelView.vue'),
          meta: { roles: ['admin', 'professional'], shell: 'admin' },
        },
        {
          path: 'account',
          component: () => import('@/layouts/AdminLayout.vue'),
          meta: { roles: ['admin', 'professional'], title: 'Minha conta', shell: 'admin' },
          children: [
            {
              path: '',
              name: 'panel-account',
              component: () => import('@/views/account/AccountView.vue'),
              meta: { title: 'Minha conta', shell: 'admin' },
            },
          ],
        },
        {
          path: 'admin',
          component: () => import('@/layouts/AdminLayout.vue'),
          meta: { roles: ['admin', 'professional'], shell: 'admin' },
          children: [
            {
              path: '',
              redirect: { name: 'admin-dashboard' },
            },
            {
              path: 'dashboard',
              name: 'admin-dashboard',
              component: () => import('@/views/admin/DashboardView.vue'),
              meta: { title: 'Dashboard' },
            },
            {
              path: 'professionals',
              name: 'admin-professionals',
              component: () => import('@/views/admin/ProfessionalsView.vue'),
              meta: { roles: ['admin'], title: 'Profissionais' },
            },
            {
              path: 'clients',
              name: 'admin-clients',
              component: () => import('@/views/admin/ClientsView.vue'),
              meta: { roles: ['admin', 'professional'], title: 'Clientes' },
            },
            {
              path: 'categories',
              name: 'admin-categories',
              component: () => import('@/views/admin/CategoriesView.vue'),
              meta: { requiresAuth: true, roles: ['admin'], title: 'Categorias' },
            },
            {
              path: 'services',
              name: 'admin-services',
              component: () => import('@/views/admin/ServicesView.vue'),
              meta: { roles: ['admin'], title: 'Serviços' },
            },
            {
              path: 'appointments',
              name: 'admin-appointments',
              component: () => import('@/views/admin/AppointmentsView.vue'),
              meta: { title: 'Agendamentos' },
            },
            {
              path: 'schedules',
              name: 'admin-professional-schedules',
              component: () => import('@/views/admin/ProfessionalSchedulesView.vue'),
              meta: { roles: ['admin'], title: 'Horários' },
            },
            {
              path: 'products',
              name: 'admin-products',
              component: () => import('@/views/admin/ProductsView.vue'),
              meta: { roles: ['admin', 'professional'], title: 'Produtos' },
            },
            {
              path: 'suppliers',
              name: 'admin-suppliers',
              component: () => import('@/views/admin/SuppliersView.vue'),
              meta: { roles: ['admin'], title: 'Fornecedores' },
            },
            {
              path: 'stock',
              name: 'admin-stock',
              component: () => import('@/views/admin/StockView.vue'),
              meta: { roles: ['admin', 'professional'], title: 'Stock' },
            },
            {
              path: 'sales',
              name: 'admin-sales',
              component: () => import('@/views/admin/SalesView.vue'),
              meta: { roles: ['admin', 'professional'], title: 'Vendas' },
            },
            {
              path: 'cash',
              name: 'admin-cash',
              component: () => import('@/views/admin/CashView.vue'),
              meta: { roles: ['admin', 'professional'], title: 'Caixa' },
            },
            {
              path: 'payables',
              name: 'admin-payables',
              component: () => import('@/views/admin/PayablesView.vue'),
              meta: { roles: ['admin'], title: 'Contas a pagar' },
            },
            {
              path: 'receivables',
              name: 'admin-receivables',
              component: () => import('@/views/admin/ReceivablesView.vue'),
              meta: { roles: ['admin'], title: 'Contas a receber' },
            },
            {
              path: 'commissions',
              name: 'admin-commissions',
              component: () => import('@/views/admin/CommissionsView.vue'),
              meta: { roles: ['admin'], title: 'Comissões' },
            },
            {
              path: 'reports',
              name: 'admin-reports',
              component: () => import('@/views/admin/ReportsView.vue'),
              meta: { roles: ['admin'], title: 'Relatórios' },
            },
            {
              path: 'messages',
              name: 'admin-messages',
              component: () => import('@/views/admin/ContactMessagesView.vue'),
              meta: { roles: ['admin'], title: 'Mensagens' },
            },
            {
              path: 'settings',
              name: 'admin-settings',
              component: () => import('@/views/admin/SettingsView.vue'),
              meta: { roles: ['admin'], title: 'Definições' },
            },
            {
              path: 'profile',
              redirect: { name: 'panel-account' },
            },
          ],
        },
        {
          path: 'client',
          component: () => import('@/layouts/ClientLayout.vue'),
          meta: { roles: ['client'], shell: 'client' },
          children: [
            {
              path: '',
              redirect: { name: 'client-dashboard' },
            },
            {
              path: 'dashboard',
              name: 'client-dashboard',
              component: () => import('@/views/client/DashboardView.vue'),
              meta: { title: 'Início', shell: 'client' },
            },
            {
              path: 'appointments',
              name: 'client-appointments',
              component: () => import('@/views/client/AppointmentsView.vue'),
              meta: { title: 'Meus agendamentos', shell: 'client' },
            },
            {
              path: 'account',
              name: 'client-account',
              component: () => import('@/views/account/AccountView.vue'),
              meta: { title: 'Minha conta', shell: 'client' },
            },
          ],
        },
      ],
    },
    {
      path: '/admin',
      redirect: '/panel/admin/dashboard',
    },
    {
      path: '/admin/:pathMatch(.*)*',
      redirect: (to) => `/panel/admin/${to.params.pathMatch}`,
    },
    {
      path: '/client',
      redirect: '/panel/client/dashboard',
    },
    {
      path: '/client/:pathMatch(.*)*',
      redirect: (to) => `/panel/client/${to.params.pathMatch}`,
    },
    {
      path: '/:pathMatch(.*)*',
      component: () => import('@/layouts/PublicLayout.vue'),
      meta: { public: true, shell: 'public' },
      children: [
        {
          path: '',
          name: 'not-found',
          component: () => import('@/views/public/NotFoundView.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Redirects primeiro: não adquirir Bootstrap numa rota que vamos abandonar
  // (ex.: /login autenticado → Admin).
  if (to.meta.guest && auth.isAuthenticated) {
    return auth.homeRouteForRole(auth.role)
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  const allowedRoles =
    [...to.matched]
      .reverse()
      .map((record) => record.meta.roles as string[] | undefined)
      .find((roles) => Array.isArray(roles) && roles.length > 0) ?? []

  if (allowedRoles.length && auth.role && !allowedRoles.includes(auth.role)) {
    return auth.homeRouteForRole(auth.role)
  }

  // Estado desejado da rota final — release é síncrono e corre ANTES do mount.
  if (routeNeedsPublicAssets(to.matched)) {
    await acquirePublicAssets()
  } else {
    releasePublicAssets()
  }

  if (routeNeedsAdminStyles(to.matched)) {
    await acquireAdminStyles()
  }

  return true
})

export default router
