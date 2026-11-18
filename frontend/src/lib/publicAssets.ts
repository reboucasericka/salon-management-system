/** Assets CSS/JS do site público (legado + Bootstrap). Nunca carregar em Admin nem Client. */

const STYLESHEETS = [
  {
    id: 'public-bootstrap-css',
    href: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  },
  {
    id: 'public-font-awesome',
    href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  },
  {
    id: 'public-legacy-css',
    href: '/css/style.css',
  },
] as const

const SCRIPTS = [
  {
    id: 'public-bootstrap-js',
    src: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  },
] as const

/**
 * Estado desejado da rota actual — NÃO é ref-count.
 * Incrementar por navegação deixava Bootstrap/style.css no primeiro paint do Admin
 * (Public → Auth → Admin: acquire, acquire, release-1 → assets ficavam no <head>).
 */
let active = false
let loadGeneration = 0
let loadPromise: Promise<void> | null = null

function ensureLink(id: string, href: string): Promise<void> {
  const existing = document.getElementById(id) as HTMLLinkElement | null
  if (existing) return Promise.resolve()

  return new Promise((resolve) => {
    const link = document.createElement('link')
    link.id = id
    link.rel = 'stylesheet'
    link.href = href
    link.onload = () => resolve()
    link.onerror = () => resolve()
    document.head.appendChild(link)
  })
}

function ensureScript(id: string, src: string) {
  if (document.getElementById(id)) return
  const script = document.createElement('script')
  script.id = id
  script.src = src
  document.body.appendChild(script)
}

function removeById(id: string) {
  document.getElementById(id)?.remove()
}

function removePublicAssetNodes() {
  for (const sheet of STYLESHEETS) {
    removeById(sheet.id)
  }
  for (const script of SCRIPTS) {
    removeById(script.id)
  }
}

/** Carrega folhas/scripts públicos. Idempotente: a rota precisa ou não precisa. */
export async function acquirePublicAssets(): Promise<void> {
  if (active && loadPromise) {
    document.body.classList.add('legacy-site')
    return loadPromise
  }

  const generation = ++loadGeneration
  active = true
  document.body.classList.add('legacy-site')

  loadPromise = Promise.all(STYLESHEETS.map((sheet) => ensureLink(sheet.id, sheet.href))).then(
    () => {
      if (generation !== loadGeneration || !active) return
      for (const script of SCRIPTS) {
        ensureScript(script.id, script.src)
      }
    },
  )

  await loadPromise

  if (generation !== loadGeneration || !active) {
    document.body.classList.remove('legacy-site')
    removePublicAssetNodes()
  }
}

/**
 * Remove TODOS os assets públicos de imediato (síncrono).
 * Tem de correr no beforeEach da rota Admin/Client, antes do mount — não no afterEach.
 */
export function releasePublicAssets(): void {
  loadGeneration += 1
  active = false
  loadPromise = null
  document.body.classList.remove('legacy-site')
  removePublicAssetNodes()
}

/**
 * Shells que precisam do CSS/JS legado + Bootstrap.
 * Admin e Client NÃO entram aqui.
 */
export function routeNeedsPublicAssets(
  matched: { meta: Record<string, unknown> }[],
): boolean {
  return matched.some((record) => {
    const shell = record.meta.shell
    if (shell === 'public' || shell === 'auth') return true
    // Compatibilidade: rotas antigas só com meta.public
    return record.meta.public === true
  })
}

export function routeNeedsAdminStyles(
  matched: { meta: Record<string, unknown> }[],
): boolean {
  return matched.some((record) => record.meta.shell === 'admin')
}

/** Garante admin.css no documento antes do primeiro paint do shell Admin. */
export function acquireAdminStyles(): Promise<void> {
  return import('@/styles/admin.css').then(() => undefined)
}
