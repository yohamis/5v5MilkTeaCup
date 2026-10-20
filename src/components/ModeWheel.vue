<script setup>
import { onUnmounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'
import { createModeSpin, TEAM_MODES } from '../lib/modeWheel.js'

const rotation = ref(0)
const spinning = ref(false)
const result = ref(null)
const duration = ref(4200)
let pendingIndex = null
let finishTimer

function finishSpin() {
  if (!spinning.value) return
  clearTimeout(finishTimer)
  result.value = TEAM_MODES[pendingIndex]
  spinning.value = false
}

function spin() {
  if (spinning.value) return
  const plan = createModeSpin(rotation.value)
  duration.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 150 : 4200
  result.value = null
  pendingIndex = plan.index
  spinning.value = true
  rotation.value = plan.rotation
  finishTimer = setTimeout(finishSpin, duration.value + 150)
}

function handleTransition(event) {
  if (event.target === event.currentTarget && event.propertyName === 'transform') finishSpin()
}

onUnmounted(() => clearTimeout(finishTimer))
</script>

<template>
  <section class="mode-wheel-card" aria-labelledby="mode-wheel-title">
    <div class="mode-wheel-heading">
      <div><p class="eyebrow">奶茶杯 · 组队签盘</p><h2 id="mode-wheel-title">今天怎么组？转一下。</h2></div>
      <span class="wheel-local-note">随手一转，只为好玩</span>
    </div>
    <div class="mode-wheel-layout">
      <div class="wheel-stage">
        <div class="wheel-orbit" aria-hidden="true"></div>
        <span class="wheel-side-note" aria-hidden="true">十人好友局 / 三种新展开</span>
        <div class="wheel-machine">
          <svg class="wheel-rim" viewBox="0 0 400 400" aria-hidden="true"><circle cx="200" cy="200" r="192" fill="#293759" /><circle v-for="n in 36" :key="n" :cx="200 + 186 * Math.sin(n * Math.PI / 18)" :cy="200 - 186 * Math.cos(n * Math.PI / 18)" r="2" :fill="n % 3 === 0 ? '#f5cc9b' : '#7682a2'" /></svg>
          <div class="wheel-face" :style="{ transform: `rotate(${rotation}deg)`, transitionDuration: `${duration}ms` }" @transitionend="handleTransition" aria-hidden="true">
            <div v-for="(mode, index) in TEAM_MODES" :key="mode.id" class="wheel-label" :style="{ left: `${50 + 29 * Math.sin(index * Math.PI * 2 / 3)}%`, top: `${50 - 29 * Math.cos(index * Math.PI * 2 / 3)}%`, transform: `translate(-50%, -50%) rotate(${index * 120}deg)` }">
              <AppIcon :name="mode.icon" /><b>{{ mode.name }}</b><small>{{ mode.tag }}</small>
            </div>
          </div>
          <div class="wheel-pointer" aria-hidden="true"><span></span></div>
          <button class="wheel-hub" type="button" :disabled="spinning" @click="spin" :aria-label="spinning ? '正在抽取组队模式' : '转动转盘，抽取组队模式'"><AppIcon :name="spinning ? 'shuffle' : 'cup'" /><b>{{ spinning ? '好运转动中' : '转一下' }}</b><small>MILK TEA CUP</small></button>
        </div>
        <div class="wheel-base" aria-hidden="true"><span></span></div>
        <p class="wheel-probability">三种模式 · 等概率抽取 · 各 1/3</p>
      </div>
      <div class="wheel-information">
        <div class="wheel-mode-list" aria-label="可抽取的组队模式">
          <article v-for="mode in TEAM_MODES" :key="mode.id" :class="{ 'wheel-mode-selected': result?.id === mode.id }" :style="{ '--mode-color': mode.color }">
            <span class="wheel-mode-icon"><AppIcon :name="mode.icon" /></span>
            <div><h3>{{ mode.name }}<span>{{ mode.tag }}</span></h3><p>{{ mode.summary }}</p></div>
            <span class="wheel-mode-chance">1/3</span>
          </article>
        </div>
        <div class="wheel-result" role="status" aria-live="polite" aria-atomic="true">
          <template v-if="result"><small>本次抽中</small><h3 :style="{ color: result.color }">{{ result.name }}</h3><p>{{ result.rule }}</p></template>
          <template v-else><small>{{ spinning ? '签盘转动中' : '开局前的小仪式' }}</small><h3>{{ spinning ? '下一场，会是哪种展开？' : '把组队方式交给手气' }}</h3><p>{{ spinning ? '等指针停下，揭晓这一场的组队方式。' : '点击转盘或下方按钮，抽一个今天的玩法。无需登录，也不需要先凑齐报名人数。' }}</p></template>
        </div>
        <button class="primary-button wheel-spin-button" type="button" :disabled="spinning" @click="spin"><AppIcon name="shuffle" />{{ spinning ? '正在抽取…' : result ? '再转一次' : '转出今天的组队模式' }}<AppIcon name="arrow" /></button>
        <p class="wheel-footnote">仅抽取模式，不自动更改队长或分队，也不会发布到群里。</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.mode-wheel-card { --wheel-navy: #293759; --wheel-purple: #626ee7; --wheel-peach: #f3bc94; --wheel-mint: #9bd7c8; padding: 28px 30px; margin: 0 0 36px; background: #fff; border: 1px solid #e9edf5; border-radius: 24px; overflow: hidden; }
.mode-wheel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.mode-wheel-heading .eyebrow { margin-bottom: 8px; letter-spacing: .08em; }
.mode-wheel-heading h2 { margin: 0; font-size: 25px; letter-spacing: -.04em; }
.wheel-local-note { color: var(--muted); font-size: 11px; }
.mode-wheel-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 58px; align-items: center; margin-top: 24px; }
.wheel-stage { position: relative; display: grid; justify-items: center; min-width: 0; padding: 20px 20px 4px; isolation: isolate; }
.wheel-orbit { position: absolute; width: 96%; aspect-ratio: 1; max-width: 430px; top: 0; background: radial-gradient(circle, #f0f2ff 0 58%, transparent 59%); border: 1px dashed #e3e7f5; border-radius: 50%; z-index: -1; }
.wheel-side-note { position: absolute; left: -3px; top: 35%; writing-mode: vertical-rl; letter-spacing: .17em; font-size: 9px; color: #9ba4ba; }
.wheel-machine { width: min(100%, 362px); aspect-ratio: 1; position: relative; border-radius: 50%; filter: drop-shadow(0 14px 15px #29375919); }
.wheel-rim { position: absolute; inset: 0; width: 100%; height: 100%; }
.wheel-face { position: absolute; inset: 6%; border: 5px solid white; border-radius: 50%; background: conic-gradient(from -60deg, var(--wheel-purple) 0deg 119deg, #fff 119deg 120deg, var(--wheel-peach) 120deg 239deg, #fff 239deg 240deg, var(--wheel-mint) 240deg 359deg, #fff 359deg 360deg); transition-property: transform; transition-timing-function: cubic-bezier(.14,.68,.12,1); will-change: transform; }
.wheel-face::after { content: ''; position: absolute; inset: 0; border-radius: inherit; background: radial-gradient(circle at center, #ffffff00 30%, #ffffff12 78%, #ffffff2b); pointer-events: none; }
.wheel-label { position: absolute; display: grid; justify-items: center; width: 95px; color: var(--wheel-navy); gap: 4px; }
.wheel-label:first-child { color: white; }
.wheel-label .ui-icon { width: 27px; height: 27px; margin-bottom: 3px; }
.wheel-label b { font-size: 16px; letter-spacing: .04em; }
.wheel-label small { font-size: 9px; opacity: .76; letter-spacing: .12em; }
.wheel-pointer { position: absolute; left: 50%; top: -4px; transform: translateX(-50%); width: 26px; height: 44px; filter: drop-shadow(0 3px 2px #29375930); }
.wheel-pointer span { display: block; width: 100%; height: 100%; background: #f5cd9d; clip-path: polygon(0 0, 100% 0, 100% 40%, 50% 100%, 0 40%); border-radius: 7px 7px 0 0; }
.wheel-hub { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); display: grid; align-content: center; justify-items: center; width: 29%; aspect-ratio: 1; padding: 0; background: #fff; border: 6px solid #f3f5fc; border-radius: 50%; box-shadow: 0 4px 12px #29375925; color: var(--wheel-navy); }
.wheel-hub:disabled { opacity: 1; }
.wheel-hub .ui-icon { width: 26px; height: 26px; margin-bottom: 5px; color: var(--tea); }
.wheel-hub b { font-size: 13px; }
.wheel-hub small { font: 600 6px/1.5 Arial, sans-serif; letter-spacing: .1em; color: #919bb3; margin-top: 4px; }
.wheel-base { height: 22px; width: 124px; background: var(--wheel-navy); border-radius: 50% 50% 8px 8px; margin-top: -8px; position: relative; z-index: -1; }
.wheel-base span { position: absolute; bottom: 0; left: -15px; right: -15px; height: 6px; background: #dce1f2; border-radius: 10px; }
.wheel-probability { margin: 16px 0 0; color: var(--muted); font-size: 10px; letter-spacing: .03em; }
.wheel-information { min-width: 0; }
.wheel-mode-list { display: grid; gap: 8px; }
.wheel-mode-list article { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #edf0f7; border-radius: 14px; transition: background .2s, border-color .2s; }
.wheel-mode-list .wheel-mode-selected { background: #f5f7fc; border-color: var(--mode-color); }
.wheel-mode-icon { display: grid; place-items: center; width: 40px; height: 40px; flex: 0 0 auto; background: #f4f6fb; color: var(--mode-color); border-radius: 12px; }
.wheel-mode-icon .ui-icon { width: 21px; height: 21px; }
.wheel-mode-list h3 { display: flex; align-items: center; gap: 9px; margin: 0; font-size: 13px; }
.wheel-mode-list h3 span { font-size: 9px; color: var(--mode-color); font-weight: 500; }
.wheel-mode-list p { margin: 5px 0 0; font-size: 11px; color: var(--muted); line-height: 1.6; }
.wheel-mode-chance { margin-left: auto; font: 600 11px Arial, sans-serif; color: #a0a9bd; }
.wheel-result { min-height: 137px; padding: 19px 0 15px; }
.wheel-result > small { color: var(--muted); font-size: 10px; }
.wheel-result h3 { margin: 7px 0; font-size: 21px; letter-spacing: -.035em; }
.wheel-result p { margin: 0; font-size: 12px; line-height: 1.85; color: var(--muted); }
.wheel-spin-button { width: 100%; min-height: 49px; border-radius: 13px; font-size: 13px; }
.wheel-spin-button .ui-icon { width: 18px; height: 18px; }
.wheel-spin-button .ui-icon:last-child { margin-left: auto; }
.wheel-footnote { margin: 12px 0 0; color: #9ba4b8; font-size: 10px; line-height: 1.7; text-align: center; }
@media (max-width: 900px) { .mode-wheel-layout { gap: 22px; } .wheel-stage { padding: 15px 10px 4px; } .wheel-label b { font-size: 14px; } .wheel-label .ui-icon { width: 23px; height: 23px; } }
@media (max-width: 640px) { .mode-wheel-card { padding: 23px 18px; border-radius: 21px; margin-bottom: 24px; } .mode-wheel-heading { align-items: flex-start; } .mode-wheel-heading h2 { font-size: 23px; } .wheel-local-note { max-width: 62px; text-align: right; font-size: 10px; line-height: 1.7; } .mode-wheel-layout { grid-template-columns: 1fr; gap: 24px; margin-top: 22px; } .wheel-stage { width: min(100%, 350px); justify-self: center; padding: 12px 10px 0; } .wheel-label b { font-size: 15px; } .wheel-mode-list article { padding: 10px; } .wheel-result { min-height: 142px; } }
@media (max-width: 360px) { .mode-wheel-card { padding: 21px 13px; } .wheel-label b { font-size: 13px; } .wheel-label .ui-icon { width: 20px; height: 20px; margin-bottom: 0; } .wheel-label { gap: 2px; } .wheel-hub b { font-size: 11px; } .wheel-hub .ui-icon { width: 20px; height: 20px; } .wheel-mode-list h3 { gap: 6px; } .wheel-mode-list p { font-size: 10px; } }
</style>
