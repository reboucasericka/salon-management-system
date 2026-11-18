# Migração Home: Slick/jQuery → Vue 3 nativo

**Data:** 2026-08-11  
**Âmbito:** carousel da Home pública  
**Fora de âmbito:** Bootstrap, Font Awesome, `style.css`, login/register, backend, `legacy-csharp/`

---

## 1. Carousel antigo encontrado

| Item | Detalhe |
|------|---------|
| View | `frontend/src/views/public/HomeView.vue` |
| Seletor | `.slider_home` |
| Slides | 2× `.item` (textos + imagens) |
| Outros carousels na Home | **Nenhum** (showcase/best_sellers/how_to_use são grelhas estáticas) |

**Config Slick anterior:**

```js
{
  dots: true,
  arrows: false,
  infinite: true,
  autoplay: true,
  autoplaySpeed: 4000,
  fade: true,
  cssEase: 'linear',
}
```

---

## 2. Ficheiros JS/CSS relacionados

| Ficheiro | Papel | Ação |
|----------|--------|------|
| `HomeView.vue` | `import $ from 'jquery'` + `slick-carousel` + init/destroy | Reescrito |
| `publicAssets.ts` | CDN `slick.css` + `slick-theme.css` | Removido do loader |
| `vite-env.d.ts` | tipos `jquery` / `slick` | Removidos |
| `package.json` | deps `jquery`, `slick-carousel`, `@types/jquery` | Desinstalados |
| `public/css/style.css` | `.slider_home`, títulos, altura 100vh | **Mantido** (layout visual) |
| CDN Slick | só via publicAssets | Deixa de carregar |
| JS órfãos em `frontend/public/js/` | Não existiam | N/A |

**Verificação global pós-migração:** zero matches a `jquery`, `jQuery`, `slick`, `slick-carousel`, `$()` em `frontend/` (exceto node_modules/dist históricos se existirem).

---

## 3. Componente Vue criado

`frontend/src/components/public/HomeHeroSlider.vue`

- Composition API (`script setup`)
- Props: `slides`, `autoplaySpeed` (4000), `showDots` (true), `showArrows` (false — igual ao Slick)
- Fade via opacity/visibility
- Mantém classes legado `.slider_home` / `.item` / `.title_*` para `style.css`
- Integrado em `HomeView.vue` via `<HomeHeroSlider :slides="heroSlides" />`

---

## 4. Comportamento preservado / melhorado

| Comportamento | Estado |
|---------------|--------|
| Autoplay 4s | Preservado |
| Fade | Preservado |
| Dots | Preservados (estilo próprio, visual próximo ao Slick) |
| Setas UI | Continuam desligadas (`showArrows: false`) |
| Infinite loop | Preservado |
| Imagens `slider_home_3` / `slider_home_2` | Mantidas |
| Textos dos slides | Idênticos |
| Responsividade (CSS legado) | Preservada |
| Navegação teclado ←/→ | Adicionada |
| Pausa em hover/focus | Adicionada |
| `prefers-reduced-motion` | Autoplay e transição desativados |
| Cleanup ao sair da rota | Timer + listeners removidos |

---

## 5. jQuery removido

**SIM** — removido do código, do `package.json` e dos types.

---

## 6. Slick removido

**SIM** — removido do código, CDN no loader, deps npm e types.

---

## 7. Ficheiros removidos / alterados

**Removidos (deps npm):**
- `jquery`
- `slick-carousel`
- `@types/jquery`

**Alterados:**
- `src/views/public/HomeView.vue`
- `src/lib/publicAssets.ts`
- `src/vite-env.d.ts`
- `package.json` / `package-lock.json`

**Criados:**
- `src/components/public/HomeHeroSlider.vue`
- este relatório

Nenhum ficheiro JS órfão de carousel em `frontend/public/` para apagar.

---

## 8. Assets mantidos

- `/images/home/slider_home_3.png`
- `/images/home/slider_home_2.png`
- Restantes imagens da Home (about, products, vídeos, etc.)
- `public/css/style.css` (incluindo regras `.slider_home`)

---

## 9. Dependências ainda existentes no público

Via `publicAssets.ts` (public/auth):

- Bootstrap 5.3.3 (CSS + JS)
- Font Awesome 6.4.0 (CDN)
- `/css/style.css` legado

**Não** nesta execução: remoção de Bootstrap / FA / `style.css`.

---

## 10. Resultado do build

```
npm run build
→ vue-tsc -b && vite build
→ ✓ built (exit 0)
```

Indicador: chunk `HomeView` passou de ~127 kB (com jQuery+Slick) para ~7.4 kB.

---

## Validação adicional (proteções)

### Busca global (código ativo)

Âmbito: `frontend/src`, `package.json`, `index.html`, `frontend/public`.

| Padrão | Resultado em código ativo |
|--------|---------------------------|
| `jquery` / `jQuery` | **0** (só comentários de paridade em `HomeHeroSlider.vue`) |
| `slick` / `slick-carousel` / `.slick-` | **0** uso runtime; só comentários |
| `$()` / `window.$` / `window.jQuery` | **0** |
| `package.json` deps | **0** |

Referências restantes **apenas documentação** (`docs/*`, `frontend/README.md` se desatualizado) ou `legacy-csharp/` (não tocado).

### Comparação visual (browser)

| Critério | Resultado |
|----------|-----------|
| Altura | `calc(100vh - 80px)` sob header fixo (corrige crop abaixo do fold do `100vh` + `margin-top: 80px`) |
| `object-fit: cover` | Confirmado |
| Textos centrados | Confirmado (~centro do viewport) |
| Setas | Continuam ocultas (`arrows: false` no Slick Vue anterior e em `_Layout.cshtml`) |
| Indicadores | 2 dots; agora **visíveis** no 1.º viewport |
| Autoplay 4s | Confirmado (slide 0 → 1) |
| Clique nos dots | Confirmado |
| `/` → `/services` → `/` | Carousel remonta limpo |
| Runtime | `window.jQuery` / `$` undefined; sem CSS Slick no DOM |

### Correção visual aplicada nesta validação

Antes: dots ficavam abaixo do fold porque `.slider_home` tinha `height: 100vh` com `.home { margin-top: 80px }`.  
Depois: altura `calc(100vh - 80px)` + dots estilo slick-theme sobre a imagem.

---

## Validação manual recomendada

- [x] Home desktop — fade + dots + autoplay
- [x] Indicadores visíveis no 1.º viewport
- [x] `object-fit: cover` + títulos centrados
- [x] Teclado / dots
- [x] Refresh / retorno à Home
- [ ] Tablet físico (opcional)
- [ ] Network: sem `slick*.css` / sem jquery

---

*Fim do relatório.*
