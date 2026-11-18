<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

export type HomeHeroSlide = {
  image: string
  alt: string
  titleOne: string
  titleTwo: string
  titleThree: string
}

const props = withDefaults(
  defineProps<{
    slides: HomeHeroSlide[]
    /** Intervalo de autoplay em ms (igual ao Slick: 4000). */
    autoplaySpeed?: number
    showDots?: boolean
    showArrows?: boolean
  }>(),
  {
    autoplaySpeed: 4000,
    showDots: true,
    /** Slick usava arrows: false — setas opcionais para a11y, ocultas visualmente por defeito. */
    showArrows: false,
  },
)

const current = ref(0)
const paused = ref(false)
const reducedMotion = ref(false)
let timer: ReturnType<typeof setInterval> | null = null
let motionQuery: MediaQueryList | null = null

const total = computed(() => props.slides.length)

function onMotionPreferenceChange(event: MediaQueryListEvent) {
  reducedMotion.value = event.matches
  startTimer()
}

function goTo(index: number) {
  if (total.value === 0) return
  current.value = ((index % total.value) + total.value) % total.value
}

function next() {
  goTo(current.value + 1)
}

function prev() {
  goTo(current.value - 1)
}

function clearTimer() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

function startTimer() {
  clearTimer()
  if (reducedMotion.value || paused.value || total.value < 2) return
  timer = setInterval(() => {
    next()
  }, props.autoplaySpeed)
}

function onMouseEnter() {
  paused.value = true
}

function onMouseLeave() {
  paused.value = false
}

function onFocusIn() {
  paused.value = true
}

function onFocusOut(event: FocusEvent) {
  const root = event.currentTarget as HTMLElement
  const nextTarget = event.relatedTarget as Node | null
  if (nextTarget && root.contains(nextTarget)) return
  paused.value = false
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    next()
    startTimer()
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault()
    prev()
    startTimer()
  }
}

watch(paused, () => {
  if (paused.value) clearTimer()
  else startTimer()
})

onMounted(() => {
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  reducedMotion.value = motionQuery.matches
  motionQuery.addEventListener('change', onMotionPreferenceChange)
  startTimer()
})

onBeforeUnmount(() => {
  clearTimer()
  motionQuery?.removeEventListener('change', onMotionPreferenceChange)
  motionQuery = null
})
</script>

<template>
  <section
    class="slider_home home-hero-slider"
    role="region"
    aria-roledescription="carousel"
    aria-label="Destaques da página inicial"
    tabindex="0"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
    @focusin="onFocusIn"
    @focusout="onFocusOut"
    @keydown="onKeydown"
  >
    <div
      v-for="(slide, index) in slides"
      :key="`${slide.image}-${index}`"
      class="item"
      :class="{ 'is-active': index === current }"
      role="group"
      aria-roledescription="slide"
      :aria-label="`${index + 1} de ${total}`"
      :aria-hidden="index !== current"
    >
      <img :src="slide.image" :alt="slide.alt" />
      <div class="title_one animated fadeInDown">{{ slide.titleOne }}</div>
      <div class="title_two animated fadeInRight">{{ slide.titleTwo }}</div>
      <div class="title_three animated fadeInUp">{{ slide.titleThree }}</div>
    </div>

    <button
      v-if="showArrows && total > 1"
      type="button"
      class="home-hero-slider__arrow home-hero-slider__arrow--prev"
      aria-label="Slide anterior"
      @click="prev(); startTimer()"
    >
      ‹
    </button>
    <button
      v-if="showArrows && total > 1"
      type="button"
      class="home-hero-slider__arrow home-hero-slider__arrow--next"
      aria-label="Próximo slide"
      @click="next(); startTimer()"
    >
      ›
    </button>

    <div v-if="showDots && total > 1" class="home-hero-slider__dots" role="tablist" aria-label="Slides">
      <button
        v-for="(_, index) in slides"
        :key="index"
        type="button"
        class="home-hero-slider__dot"
        :class="{ 'is-active': index === current }"
        role="tab"
        :aria-selected="index === current"
        :aria-label="`Ir para slide ${index + 1}`"
        @click="goTo(index); startTimer()"
      />
    </div>
  </section>
</template>

<style scoped>
.home-hero-slider {
  outline: none;
  /* Preenche o viewport sob o header fixo (~80px), como o utilizador vê no ecrã */
  height: calc(100vh - 80px);
}

.home-hero-slider .item {
  position: absolute;
  inset: 0;
  height: 100%;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.6s linear, visibility 0.6s linear;
  pointer-events: none;
  z-index: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.home-hero-slider .item.is-active {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
  z-index: 1;
}

.home-hero-slider .item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
  z-index: 1;
}

.home-hero-slider :is(.title_one, .title_two, .title_three) {
  left: 0;
  right: 0;
  width: 100%;
  margin: 0;
  text-align: center;
  text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.45);
}

@media (prefers-reduced-motion: reduce) {
  .home-hero-slider .item {
    transition: none;
  }
}

/* Indicadores: estilo próximo ao slick-theme, sobre a imagem (visível no 1.º viewport) */
.home-hero-slider__dots {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 1.25rem;
  z-index: 3;
  display: flex;
  justify-content: center;
  gap: 0;
  list-style: none;
  margin: 0;
  padding: 0;
  pointer-events: auto;
}

.home-hero-slider__dot {
  width: 20px;
  height: 20px;
  margin: 0 5px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  cursor: pointer;
  position: relative;
}

.home-hero-slider__dot::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  transform: translate(-50%, -50%);
  background: rgba(255, 255, 255, 0.5);
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
  transition: background 0.2s ease, transform 0.2s ease;
}

.home-hero-slider__dot.is-active::before {
  background: #fff;
  transform: translate(-50%, -50%) scale(1.15);
}

.home-hero-slider__dot:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

.home-hero-slider__arrow {
  position: absolute;
  top: 50%;
  z-index: 3;
  transform: translateY(-50%);
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.3);
  color: #fff;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
}

.home-hero-slider__arrow--prev {
  left: 1rem;
}

.home-hero-slider__arrow--next {
  right: 1rem;
}

.home-hero-slider__arrow:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .home-hero-slider {
    height: calc(100vh - 70px);
  }
}
</style>
