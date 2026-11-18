/// <reference types="vite/client" />

import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    public?: boolean
    guest?: boolean
    requiresAuth?: boolean
    roles?: string[]
    /** Isolamento visual: public/auth carregam legado; admin/client não. */
    shell?: 'public' | 'auth' | 'admin' | 'client' | 'panel'
  }
}
