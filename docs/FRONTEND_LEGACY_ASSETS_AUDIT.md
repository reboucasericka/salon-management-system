# Auditoria global de frontends e remoção de legado visual

**Data:** 2026-08-11  
**Âmbito:** `frontend/` (sistema Vue novo)  
**Fora de âmbito:** `legacy-csharp/` (não apagar), backend de negócio, Commissions, app mobile profissional  

**Método:** inspeção de `router/index.ts`, layouts, `publicAssets.ts`, `public/css/style.css`, dependências npm, greps em `src/`, comparação com `legacy-csharp/wwwroot`.

---

## Sumário executivo

| Shell | Isolamento CSS legado | Estado |
|-------|------------------------|--------|
| **Public** (`meta.shell=public`) | Carrega Bootstrap + FA + `/css/style.css` via `publicAssets` | Necessário (temporário); Slick/jQuery **removidos** |
| **Auth** (`meta.shell=auth`) | Mesmos assets públicos (login/register usam `form-control`/`btn`/FA) | Aceitável enquanto Auth partilha look do site |
| **Client** (`meta.shell=client`) | **Não** carrega publicAssets | Isolado (Tailwind + ClientShell) |
| **Admin** (`meta.shell=admin`) | **Não** carrega publicAssets | Isolado (Tailwind + `admin.css`) |

**Bug histórico (corrigido):** Bootstrap + `/css/style.css` estavam em `index.html` e contaminavam Admin. Hoje só entram com `routeNeedsPublicAssets()`.

**Limpeza segura nesta execução:**
1. Removido **Bootstrap Icons** do loader (`publicAssets.ts`) — zero classes `bi-*` em `src/`.
2. Removido `frontend/public/fonts/` (FA/Glyphicons locais) — zero referências a `/fonts/` no frontend; FA vem do CDN.
3. Introduzido `meta.shell` (`public` \| `auth` \| `admin` \| `client` \| `panel`) para não depender só de `meta.public`.
4. **Home:** jQuery + Slick substituídos por `HomeHeroSlider.vue` (ver `docs/HOME_CAROUSEL_VUE_MIGRATION.md`).

---

## 1. Layouts atuais

| Ficheiro | Papel | CSS próprio |
|----------|--------|-------------|
| `layouts/PublicLayout.vue` | Site público (header/footer legado) | via publicAssets |
| `layouts/AuthLayout.vue` | Login/Register (reusa PublicHeader/Footer) | via publicAssets + secção AUTH em `style.css` |
| `layouts/PanelLayout.vue` | Gate autenticado `/panel` | nenhum |
| `layouts/AdminLayout.vue` | Admin + conta | importa `@/styles/admin.css` |
| `layouts/ClientLayout.vue` | Área cliente | Tailwind via ClientShell |
| `components/layout/AdminShell.vue` | Shell visual admin | `admin.css` |
| `components/layout/ClientShell.vue` | Shell visual cliente | Tailwind only |
| `components/auth/AuthPageShell.vue` | Cartão login/registo | classes Bootstrap + FA |
| `views/PanelView.vue` | Hub `/panel` (admin/professional) | Tailwind + classes `admin-panel` |

**Objetivo futuro (já próximo):** `PublicLayout` / `ClientLayout` / `AdminLayout` isolados. Auth pode migrar para shell próprio ou Tailwind sem Bootstrap.

---

## 2. Rotas por layout / shell

| ROTA | COMPONENTE | LAYOUT | META | CSS CARREGADO | JS EXTERNO | ESTADO |
|------|------------|--------|------|---------------|------------|--------|
| `/` | HomeView | PublicLayout | `public`, `shell=public` | Tailwind + Bootstrap + FA + style.css | Bootstrap bundle | PUBLIC |
| `/services` | PublicServicesView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/services/:id` | ServiceDetailView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/products` | PublicProductsView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/booking` | PublicBookingView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/booking/flow` | BookingFlowView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/professionals` | PublicProfessionalsView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/professionals/:id` | PublicProfessionalDetailView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/contact` | PublicContactView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/prices` | PublicPricesView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/about` | PublicAboutView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/academy` | PublicAcademyView | PublicLayout | idem | idem | Bootstrap (carousel) | PUBLIC |
| `/recruitment` | PublicRecruitmentView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/*` (404) | NotFoundView | PublicLayout | idem | idem | Bootstrap | PUBLIC |
| `/login` | LoginView | AuthLayout | `guest`, `public`, `shell=auth` | idem public | Bootstrap | SHARED/AUTH |
| `/register` | RegisterView | AuthLayout | idem | idem | Bootstrap | SHARED/AUTH |
| `/panel` | PanelView | PanelLayout | `requiresAuth`, `shell=admin` | Tailwind + admin.css (via classes) | nenhum legado | ADMIN |
| `/panel/account` | AccountView | AdminLayout | `shell=admin` | Tailwind + admin.css | nenhum | ADMIN |
| `/panel/admin/*` | views/admin/* | AdminLayout | `shell=admin` | Tailwind + admin.css | nenhum | ADMIN |
| `/panel/client/dashboard` | client/DashboardView | ClientLayout | `shell=client` | Tailwind only | nenhum | CLIENT |
| `/panel/client/appointments` | client/AppointmentsView | ClientLayout | `shell=client` | Tailwind only | nenhum | CLIENT |
| `/admin` → redirect | — | — | — | — | — | ADMIN |
| `/client` → redirect | — | — | — | — | — | CLIENT |

**Rotas admin internas:** dashboard, professionals, clients, categories, services, appointments, schedules, products, suppliers, stock, sales, cash, payables, receivables, commissions (UI stub), messages, profile→account.

**Client existente hoje:** apenas dashboard + appointments (sem perfil/password dedicados na área cliente — conta admin está em `/panel/account`).

---

## 3. CSS global novo (`src/style.css`)

| Regra | Classificação |
|-------|----------------|
| `@import 'tailwindcss'` | GLOBAL REAL |
| `@theme` tokens brand | GLOBAL REAL (tokens partilhados) |
| `body { antialiased }` | GLOBAL REAL |
| `body:not(.legacy-site)` background | GLOBAL REAL (Admin/Client); PublicHeader adiciona `legacy-site` |

**Não contém** layout específico de Admin/Public/Client para além do token de fundo.

---

## 4. CSS público (`frontend/public/css/style.css`, ~2800 linhas)

| Secção (comentário) | Classificação | Notas |
|---------------------|---------------|-------|
| Variáveis / reset | PUBLIC | Usado |
| HEADER & NAVIGATION | PUBLIC | PublicHeader |
| HOME PAGE SECTIONS | PUBLIC | HomeView + `HomeHeroSlider` Vue |
| SERVICES | PUBLIC | |
| Academy / Recruitment / Prices | PUBLIC | |
| PUBLIC BOOKING | PUBLIC | |
| AUTH (login image, forms, auth-container) | AUTH (carregado com public) | Login/Register |
| ADMIN AREA (`.admin-link`) | UNUSED no Vue | Zero matches `admin-link` em `src/` |
| FOOTER | PUBLIC | PublicFooter |
| Responsive (home/footer/auth/booking) | PUBLIC/AUTH | |
| Responsive “Admin” | ADMIN — obsoleto | CSS do painel C# |
| Utilitários flex/spacing com `!important` | PUBLIC (perigoso se vazar) | Já isolado do Admin |
| **PAINEL ADMINISTRATIVO (Bootstrap 5)** | ADMIN — obsoleto + AUTH misturado | `.small-box`, navbar admin, tabelas; **também** estilos de auth/login no mesmo bloco |
| Cards/btn unificados | PUBLIC + AUTH (parcial) | `.btn`/`.card` ainda usados no site e login |

**Não apagar o ficheiro.** Remoção cirúrgica futura: bloco “PAINEL ADMINISTRATIVO” / `.small-box` / responsive admin **após** extrair estilos AUTH que estão misturados (~linhas 2266–2479).

---

## 5. CSS cliente

| Item | Resultado |
|------|-----------|
| `clients-panel.css` no frontend ativo | **Não existe** |
| Em `legacy-csharp/wwwroot/css/clients-panel.css` | Referência histórica apenas |
| Views Vue client | Tailwind puro (`ClientShell`, Dashboard, Appointments) |
| Carregado? | **Não** |
| Necessário? | **Não** no sistema novo |

---

## 6. CSS admin

| Item | Local | Estado |
|------|-------|--------|
| `src/styles/admin.css` | Importado por AdminLayout | **ATIVO** — fonte de verdade |
| Secção ADMIN em `style.css` legado | publicAssets (só public/auth) | **Obsoleto** para Admin Vue; morto no runtime admin |
| Contaminação Admin | Corrigida (loader + release) | OK |

---

## 7. Bootstrap — onde ainda é necessário

| Experiência | Necessário? | Evidência |
|-------------|-------------|-----------|
| Public | **SIM** | `container`/`row`/`col-*`/`btn`/`form-control`/`carousel` em views públicas; Academy usa `data-bs-ride` |
| Auth | **SIM** | Login/Register: `form-control`, `btn btn-primary` |
| Client | **NÃO** | Só Tailwind (`col-span-*` = Tailwind grid) |
| Admin | **NÃO** | `col-span-*` Tailwind; UI kit próprio |

CDN: CSS + JS bundle via `publicAssets.ts` (não em `index.html`).

---

## 8. jQuery

| Local | Uso |
|-------|-----|
| Frontend ativo | **Removido** (2026-08-11) — ver `docs/HOME_CAROUSEL_VUE_MIGRATION.md` |
| Admin / Client / Public | **Zero** |

**Decisão:** REMOVIDO do sistema novo.

---

## 9. Slick

| Aspeto | Detalhe |
|--------|---------|
| Estado | **Removido** do loader, npm e Home |
| Substituto | `components/public/HomeHeroSlider.vue` |

**Decisão:** REMOVIDO do sistema novo.

---

## 10. Font Awesome

| Aspeto | Detalhe |
|--------|---------|
| CDN | `cdnjs` all.min.css via publicAssets |
| Local `public/fonts/` | **Removido** nesta auditoria (não referenciado) |
| Uso | PublicHeader/Footer, Auth, ServiceDetail, ProfessionalDetail |
| Admin/Client | Sem `fas`/`fab` |
| Decisão | MANTER no public/auth; migrar para SVG depois |

---

## 11. JS legado (C# wwwroot vs frontend ativo)

| Ficheiro (legado) | No frontend ativo? | Carregado? | Usado por | Equivalente Vue | Decisão |
|-------------------|--------------------|------------|-----------|-----------------|---------|
| `painel.js` | Não | Não | NENHUM (novo) | AdminShell / PanelView | REVISÃO só em legacy-csharp |
| `pdv.js` / sales | Não | Não | NENHUM | SalesView / CashView | idem |
| `perfil.js` | Não | Não | NENHUM | AccountView | idem |
| `site.js` / `scripts.js` | Não | Não | NENHUM | PublicHeader Vue | idem |
| `wink-*.js` | Não | Não | NENHUM | — | MANTER em legacy |
| `login.js` | Não | Não | NENHUM | LoginView | idem |
| Chart/amcharts/flot | Não | Não | NENHUM | Dashboard Vue (sem charts JS legado) | idem |
| `menu-hamburguer.js` | Não | Não | NENHUM | PublicHeader | idem |

**Conclusão:** nenhum JS legado C# é carregado pelo SPA. Não remover de `legacy-csharp/`.

---

## 12. Imagens / assets

| Local | Classificação |
|-------|----------------|
| `frontend/public/images/**` | Conteúdo real + placeholders; usado por views públicas e `lib/images.ts` |
| `frontend/public/images/uploads/**` | Uploads runtime / demo |
| `frontend/src/assets/*` | Vite defaults (`hero.png`, `vue.svg`) — pouco uso no produto |
| `legacy-csharp/wwwroot/images` | Duplicata histórica — **não apagar** |

Não se fez purge de imagens por nome; só fontes locais órfãs foram removidas.

---

## 13. Conflitos encontrados

1. ~~CSS legado global no Admin~~ → **resolvido** com `publicAssets` + guard.
2. `meta.public` em Auth fazia Auth partilhar o mesmo bundle do site — **intencional por agora**; `meta.shell=auth` documenta a intenção.
3. Client **não** tinha `meta.public` → **não** era bug; isolamento já correto.
4. `style.css` mistura AUTH + ADMIN no bloco “PAINEL ADMINISTRATIVO” → risco se alguém “limpar” o bloco inteiro.
5. Utilitários `.mb-* !important` no legado colidem com Tailwind **se** o CSS vazar (mitigado pelo unload).

---

## 14. Public Assets Loader

**Ficheiro:** `src/lib/publicAssets.ts`  
**Guard:** `router.beforeEach` → `acquirePublicAssets()` se `routeNeedsPublicAssets(to.matched)`, senão `releasePublicAssets()`.

**Assets carregados (após limpeza):**

| Tipo | ID | Origem |
|------|-----|--------|
| CSS | Bootstrap 5.3.3 | jsDelivr |
| CSS | Font Awesome 6.4.0 | cdnjs |
| CSS | — | Slick removido |
| CSS | `/css/style.css` | local |
| JS | Bootstrap bundle | jsDelivr |

**Removido do loader:** Bootstrap Icons (não usado).

**Critério de carga:** `meta.shell === 'public' | 'auth'` **ou** `meta.public === true` (compat).

---

## 15. Isolamento Public / Client / Admin

```
PublicLayout  → Tailwind global + publicAssets (temporário)
AuthLayout    → igual ao Public (header/footer + Bootstrap forms)
ClientLayout  → Tailwind global + ClientShell (sem legado)
AdminLayout   → Tailwind global + admin.css (sem legado)
```

**Não ocorre:** Public CSS → Client (guard liberta assets).  
**Não ocorre:** Public/Client CSS → Admin.

---

## 16. Candidatos à remoção

| ITEM | LOCAL | CARREGADO? | USADO? | SUBSTITUÍDO? | SEGURO REMOVER? |
|------|-------|------------|--------|--------------|-----------------|
| Bootstrap Icons CDN | publicAssets (removido) | Não | Zero | N/A | **Removido** |
| `public/fonts/` | frontend/public | Não | Zero | FA CDN | **Removido** |
| Bootstrap CSS/JS | publicAssets | Sim (public/auth) | Sim | Não | Não |
| Slick | — | Não | Zero | HomeHeroSlider | **Removido** |
| jQuery | — | Não | Zero | HomeHeroSlider | **Removido** |
| Font Awesome CDN | publicAssets | Sim | Public/Auth | Não | Não |
| `/css/style.css` | public | Sim | Public/Auth | Parcial Tailwind | Não (ficheiro) |
| Bloco ADMIN em style.css | style.css | Sim se public | Zero no Vue admin | admin.css | **Cirúrgico futuro** (cuidado com AUTH) |
| `clients-panel.css` | só legacy-csharp | Não | Não no Vue | Tailwind client | Não apagar legacy |
| JS painel/pdv/etc. | só legacy-csharp | Não | Não | Vue admin | Não apagar legacy |
| Glyphicons (estavam em fonts/) | removidos com fonts | Não | Zero | — | **Removido** |

---

## 17. Itens mantidos / a migrar

**Mantidos (ativos):**
- Bootstrap + style.css + FA no public/auth
- `admin.css` no Admin
- Tailwind global

**A migrar (próximos passos, sem forçar agora):**
1. ~~Home: carousel Vue → dropar jQuery/Slick.~~ **Feito**
2. Public pages: Bootstrap grid → Tailwind; dropar Bootstrap.
3. Auth: formulários Tailwind (como Admin) → `shell=auth` sem publicAssets.
4. Extrair AUTH CSS do bloco ADMIN em `style.css`; apagar só regras admin mortas.
5. Substituir FA por ícones SVG no public.

---

## 18. Estrutura recomendada de estilos (já parcialmente alinhada)

```
frontend/
├── src/
│   ├── style.css              # global: Tailwind + tokens
│   ├── styles/
│   │   └── admin.css          # AdminLayout only
│   │   └── client.css         # (opcional; hoje desnecessário)
│   ├── lib/publicAssets.ts    # temporário public/auth
│   ├── layouts/
│   │   ├── PublicLayout.vue
│   │   ├── ClientLayout.vue
│   │   ├── AdminLayout.vue
│   │   ├── AuthLayout.vue
│   │   └── PanelLayout.vue
│   └── views/{public,client,admin,account}/
└── public/
    ├── css/style.css          # legado público (encolher com o tempo)
    └── images/...
```

---

## 19. Build

`npm run build` no `frontend/` — **OK** (vue-tsc + vite, 2026-08-11, exit 0).

---

## 20. Arquitetura futura — app mobile dos profissionais

O app profissional **deve**:
- ser cliente separado da API Laravel (React Native / Flutter / etc.);
- autenticar via Sanctum/token (`POST /api/v1/auth/login`, `GET /auth/me`);
- consumir endpoints de profissional (agendamentos, horários, clientes, stock/sales conforme role);
- ter UI própria;
- **não** importar CSS do frontend web, Bootstrap, `publicAssets.ts`, AdminLayout nem ClientLayout.

### API como centro (já permite multi-cliente)

| Cliente | Consome |
|---------|---------|
| Vue Public | `/api/v1/public/*` |
| Vue Client | Sanctum + appointments (role client) |
| Vue Admin | Sanctum + recursos admin/professional |
| Futuro Mobile Professional | Mesma API; gaps a avaliar depois |

**Gaps possíveis para o app (não implementar agora):**
- endpoints “meu dia” / check-in específicos mobile;
- push notifications;
- scopes/tokens mobile dedicados;
- upload de fotos de serviço offline-first.

**Já existente e reutilizável:** `appointments`, `professional-schedules`, `clients`, `products`/`stock`, `sales`, `cash`, `auth/me|profile|password`, `notifications`.

---

## 21. Referências ao legado no frontend ativo

Pesquisado: `legacy`, `wwwroot`, `.cshtml`, `Areas/`, `bootstrap`, `jquery`, `slick`, `wink`, `admin-panel.css`, `clients-panel.css`, `/css/style.css`.

Hits relevantes **ativos:**
- `publicAssets.ts` → Bootstrap, FA, `/css/style.css`
- `PublicHeader` → classe `legacy-site` no `body`
- `admin.css` / AdminShell → namespace `.admin-panel` (CSS **novo**, não o ficheiro legado)

Sem jQuery/Slick no frontend ativo. Sem referências a `.cshtml`, `wwwroot`, `wink`, `clients-panel.css` no código Vue.

---

## 22. Validação manual recomendada

**PUBLIC:** Home, Services, Products, Professionals, Booking  
**CLIENT:** `/panel/client/dashboard`, `/panel/client/appointments`  
**ADMIN:** Dashboard, Professionals, Appointments, Schedules, Clients, Categories, Services, Products, Suppliers, Stock, Sales, Cash, Payables, Receivables, Messages  

Confirmar no DevTools Network: em rotas admin/client **não** devem aparecer `bootstrap*.css`, `style.css`, `all.min.css` (FA).

---

## 23. Itens removidos nesta execução

| Item | Motivo |
|------|--------|
| Bootstrap Icons no `publicAssets.ts` | Zero uso `bi-*` |
| `frontend/public/fonts/` | Zero refs; FA via CDN |

**Não removido:** `legacy-csharp/**`, blocos CSS ADMIN dentro de `style.css` (mistura com AUTH), Bootstrap/FA ainda necessários no público.

---

*Fim do relatório.*
