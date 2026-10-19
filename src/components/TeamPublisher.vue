<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import { orderedTeams, randomTeams, registeredPlayers, swapTeamSlot, validateTeamPlan } from '../lib/teams'
import { createTeamPoster } from '../lib/teamPoster'

const props = defineProps({ event: { type: Object, required: true }, captainHint: { type: String, default: '' } })
const players = computed(() => registeredPlayers(props.event))
const selectedIds = ref([])
const slots = ref([])
const captains = ref({ blue: '', red: '' })
const title = ref('奶茶杯 · 今日对阵')
const note = ref('')
const mode = ref('手动组队')
const message = ref('')
const busy = ref(false)
const posterUrl = ref('')
const posterFile = ref(null)
const posterOpen = ref(false)
const revision = ref(0)
const sides = [{ key: 'blue', label: '蓝方', offset: 0 }, { key: 'red', label: '红方', offset: 5 }]
const selectedPlayers = computed(() => players.value.filter((player) => selectedIds.value.includes(player.id)))
const planError = computed(() => validateTeamPlan(selectedPlayers.value, slots.value, captains.value))
const ready = computed(() => selectedPlayers.value.length === 10)
const storageKey = computed(() => `milkTeaTeamDraft:${props.event.id}:${props.event.event_date}`)
let previousOverflow = ''
let previousFocus = null

function clearPoster() {
  if (posterUrl.value) URL.revokeObjectURL(posterUrl.value)
  posterUrl.value = ''
  posterFile.value = null
  posterOpen.value = false
}

watch(() => `${storageKey.value}|${players.value.map((player) => `${player.id}:${player.name}`).join('|')}`, () => {
  const allowed = new Set(players.value.map((player) => player.id))
  let draft = null
  try { draft = JSON.parse(localStorage.getItem(storageKey.value) || 'null') } catch { /* 无效草稿从当前名单重新开始 */ }
  selectedIds.value = players.value.length <= 10 ? [...allowed] : []
  slots.value = []
  captains.value = { blue: '', red: '' }
  title.value = '奶茶杯 · 今日对阵'
  note.value = ''
  mode.value = '手动组队'
  if (draft && Array.isArray(draft.selectedIds)) {
    selectedIds.value = players.value.length <= 10 ? [...allowed] : [...new Set(draft.selectedIds.filter((id) => allowed.has(id)))].slice(0, 10)
    if (typeof draft.title === 'string') title.value = draft.title.slice(0, 60)
    if (typeof draft.note === 'string') note.value = draft.note.slice(0, 120)
    if (Array.isArray(draft.slots) && draft.captains && !validateTeamPlan(selectedPlayers.value, draft.slots, draft.captains)) {
      slots.value = draft.slots
      captains.value = { blue: draft.captains.blue, red: draft.captains.red }
      mode.value = draft.mode === '随机组队' ? '随机组队' : '手动组队'
      message.value = '已恢复这场赛事的本机组队草稿。'
    } else message.value = '报名名单已变化，请重新确认本次 10 人阵容。'
  }
  clearPoster()
}, { immediate: true })

watch([selectedIds, slots, captains, title, note, mode], () => {
  revision.value++
  clearPoster()
  try { localStorage.setItem(storageKey.value, JSON.stringify({ selectedIds: selectedIds.value, slots: slots.value, captains: captains.value, title: title.value, note: note.value, mode: mode.value })) } catch { /* 不支持本机存储时仍可临时组队 */ }
}, { deep: true, flush: 'sync' })

watch(posterOpen, async (open) => {
  if (open) {
    previousOverflow = document.body.style.overflow
    previousFocus = document.activeElement
    document.body.style.overflow = 'hidden'
    await nextTick()
    document.querySelector('.team-poster-dialog button')?.focus({ preventScroll: true })
  } else {
    document.body.style.overflow = previousOverflow
    previousFocus?.focus({ preventScroll: true })
  }
})
onUnmounted(() => {
  if (posterUrl.value) URL.revokeObjectURL(posterUrl.value)
  if (posterOpen.value) document.body.style.overflow = previousOverflow
})

function choosePlayer(id, checked) {
  selectedIds.value = checked ? [...selectedIds.value, id] : selectedIds.value.filter((item) => item !== id)
  slots.value = []
  for (const side of sides) if (!selectedIds.value.includes(captains.value[side.key])) captains.value[side.key] = ''
  message.value = '参赛名单已调整，请重新组队。'
}

function arrange(random) {
  if (!ready.value) return
  try {
    const result = random ? randomTeams(selectedPlayers.value, captains.value) : orderedTeams(selectedPlayers.value, captains.value)
    slots.value = result.slots
    captains.value = result.captains
    mode.value = random ? '随机组队' : '手动组队'
    message.value = random ? '已打乱其余队员，指定队长保持不变。可继续手动互换席位。' : '已按名单顺序排入两队，可用选手下拉框互换席位。'
  } catch (error) { message.value = error.message }
}

function syncCaptains() {
  if (slots.value.length !== 10) return
  for (const side of sides) {
    if (!slots.value.slice(side.offset, side.offset + 5).includes(captains.value[side.key])) captains.value[side.key] = slots.value[side.offset]
  }
}

function moveSlot(index, id) {
  slots.value = swapTeamSlot(slots.value, index, id)
  syncCaptains()
  mode.value = '手动组队'
  message.value = '已互换两名选手的席位，请核对队长与阵容。'
}

function chooseCaptain(side, id) {
  const other = side === 'blue' ? 'red' : 'blue'
  if (id && id === captains.value[other]) return
  if (id && slots.value.length === 10) {
    const offset = side === 'blue' ? 0 : 5
    if (!slots.value.slice(offset, offset + 5).includes(id)) {
      const previous = slots.value.indexOf(captains.value[side])
      slots.value = swapTeamSlot(slots.value, previous < 0 ? offset : previous, id)
    }
  }
  captains.value[side] = id
  if (id) syncCaptains()
}

function playerName(id) { return players.value.find((player) => player.id === id)?.name || '未选择' }

async function exportPoster() {
  message.value = ''
  if (planError.value) { message.value = planError.value; return }
  busy.value = true
  const currentRevision = revision.value
  const team = (side) => slots.value.slice(side.offset, side.offset + 5).map((id) => ({ name: playerName(id), captain: captains.value[side.key] === id }))
  const payload = { title: title.value, date: props.event.event_date, note: note.value, mode: mode.value, blue: team(sides[0]), red: team(sides[1]) }
  try {
    const blob = await createTeamPoster(payload)
    if (currentRevision !== revision.value) { message.value = '配置刚刚发生变化，请重新生成图片。'; return }
    clearPoster()
    posterUrl.value = URL.createObjectURL(blob)
    posterFile.value = new File([blob], `奶茶杯-${props.event.event_date}-组队.png`, { type: 'image/png' })
    posterOpen.value = true
  } catch (error) { message.value = error.message || '生成失败，请重试。' } finally { busy.value = false }
}

function downloadPoster() {
  if (!posterUrl.value) return
  const link = document.createElement('a')
  link.href = posterUrl.value
  link.download = posterFile.value.name
  document.body.appendChild(link)
  link.click()
  link.remove()
}

const nativeShareAvailable = computed(() => !!posterFile.value && !!navigator.canShare?.({ files: [posterFile.value] }))
async function sharePoster() {
  try { await navigator.share({ files: [posterFile.value], title: title.value }) } catch (error) {
    if (error.name !== 'AbortError') message.value = '此环境暂不支持直接分享，请保存图片后发送到群里。'
  }
}

function posterKeydown(event) {
  if (event.key === 'Escape') posterOpen.value = false
  if (event.key !== 'Tab') return
  const buttons = [...event.currentTarget.querySelectorAll('button:not(:disabled)')]
  if (event.shiftKey && document.activeElement === buttons[0]) { event.preventDefault(); buttons.at(-1)?.focus() }
  else if (!event.shiftKey && document.activeElement === buttons.at(-1)) { event.preventDefault(); buttons[0]?.focus() }
}
</script>

<template>
  <section class="team-publisher" :aria-labelledby="`team-publisher-${event.id}`">
    <header class="team-publisher-heading"><div><p class="eyebrow">TEAM STUDIO · 组队发布</p><h3 :id="`team-publisher-${event.id}`">十人到齐，排一场好局。</h3></div><AppIcon name="shuffle" /></header>
    <p class="team-publisher-intro">只使用本场正式报名名单；队伍配置保存在本机，生成图片后即可发到群里。</p>
    <p v-if="captainHint" class="team-captain-hint">{{ captainHint }}。报名昵称可能不同，请在下方手动确认对应队长。</p>
    <details class="team-roster-picker" :open="players.length !== 10"><summary>本次参赛名单 <b>{{ selectedIds.length }} / 10</b><AppIcon name="chevron-down" /></summary><div><label v-for="player in players" :key="player.id"><input type="checkbox" :checked="selectedIds.includes(player.id)" :disabled="!selectedIds.includes(player.id) && selectedIds.length >= 10" @change="choosePlayer(player.id, $event.target.checked)" /><span>{{ player.name }}</span></label></div><p v-if="players.length < 10">目前只有 {{ players.length }} 位正式报名选手，还需 {{ 10 - players.length }} 人；候补席不自动计入。</p><p v-else-if="players.length > 10">正式报名超过 10 人，请手动选定本次出战的 10 位选手。</p></details>
    <div class="team-publish-fields"><label>海报标题<input v-model="title" maxlength="60" placeholder="奶茶杯 · 今日对阵" /></label><label>集合提醒<input v-model="note" maxlength="120" placeholder="例如：今晚 21:00 集合，提前上线" /></label></div>
    <div class="team-captain-fields"><label v-for="side in sides" :key="side.key">{{ side.label }}队长<select :value="captains[side.key]" :aria-label="`${side.label}队长`" @change="chooseCaptain(side.key, $event.target.value)"><option value="">随机产生队长</option><option v-for="player in selectedPlayers" :key="player.id" :value="player.id" :disabled="captains[side.key === 'blue' ? 'red' : 'blue'] === player.id">{{ player.name }}</option></select></label></div>
    <div class="team-arrange-actions"><button type="button" :disabled="!ready" @click="arrange(true)"><AppIcon name="shuffle" />{{ slots.length ? '重新打乱队员' : '随机分成两队' }}</button><button class="secondary" type="button" :disabled="!ready" @click="arrange(false)">按名单手动排队</button><button class="secondary" type="button" @click="captains = { blue: '', red: '' }; slots = []; message = '已清除队长，下次随机组队将同时抽取两位队长。'">清除队长重抽</button></div>
    <p class="team-local-note">已指定的队长会保留；更换队员时会自动互换席位，避免重复。重新随机不会自动发布。</p>
    <div v-if="slots.length === 10" class="team-lineup-grid">
      <article v-for="side in sides" :key="side.key" :class="['team-lineup', side.key]"><header><b>{{ side.label }}战队</b><span>5 / 5</span></header><div v-for="(id, index) in slots.slice(side.offset, side.offset + 5)" :key="index" class="team-member-row"><span class="team-seat-number">{{ index + 1 }}</span><select :value="id" :aria-label="`${side.label}第${index + 1}位选手`" @change="moveSlot(side.offset + index, $event.target.value)"><option v-for="player in selectedPlayers" :key="player.id" :value="player.id">{{ player.name }}</option></select><span v-if="captains[side.key] === id" class="team-captain-badge"><AppIcon name="crown" />队长</span><button v-else type="button" :aria-label="`设${playerName(id)}为${side.label}队长`" @click="chooseCaptain(side.key, id)">设队长</button></div></article>
    </div>
    <p v-if="message" class="team-publish-message" role="status">{{ message }}</p>
    <div class="team-export-actions"><span>核对阵容后，生成可分享的 PNG 图片</span><button type="button" :disabled="!!planError || busy" @click="exportPoster"><AppIcon name="image" />{{ busy ? '正在生成…' : '发布组队 · 生成图片' }}</button></div>
    <Teleport to="body"><div v-if="posterOpen && posterUrl" class="team-poster-backdrop" @click.self="posterOpen = false" @keydown="posterKeydown"><section class="team-poster-dialog" role="dialog" aria-modal="true" aria-label="组队图片预览"><header><div><h3>组队图片已生成</h3><p>长按图片保存，或下载后发到微信群。</p></div><button type="button" aria-label="关闭组队图片预览" @click="posterOpen = false"><AppIcon name="close" /></button></header><div class="team-poster-image"><img :src="posterUrl" alt="奶茶杯本场组队名单，含蓝红双方各五名选手及队长" /></div><footer><button type="button" @click="downloadPoster"><AppIcon name="download" />下载图片</button><button v-if="nativeShareAvailable" type="button" class="secondary" @click="sharePoster"><AppIcon name="share" />分享图片</button></footer></section></div></Teleport>
  </section>
</template>

<style scoped>
.team-publisher { padding: 28px; border-top: 1px solid #e9edf5; background: #fdfdff; }
.team-publisher-heading { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
.team-publisher-heading .eyebrow { font-size: 9px; margin-bottom: 7px; }
.team-publisher-heading h3 { font-size: 23px; margin: 0; letter-spacing: -.04em; }
.team-publisher-heading > svg { width: 42px; height: 42px; color: #7888d5; padding: 9px; border-radius: 14px; background: #eef1ff; }
.team-publisher-intro, .team-local-note { color: #8b97af; line-height: 1.8; font-size: 11px; }
.team-captain-hint { color: #7e8ab0; background: #f1f3ff; padding: 13px 15px; font-size: 11px; line-height: 1.8; border-radius: 12px; }
.team-roster-picker { border: 1px solid #e9edf5; border-radius: 14px; padding: 0 16px; margin: 20px 0; background: #fff; }
.team-roster-picker summary { display: flex; gap: 9px; align-items: center; min-height: 50px; cursor: pointer; list-style: none; font-size: 12px; }
.team-roster-picker summary::-webkit-details-marker { display: none; }
.team-roster-picker summary b { color: #5263e8; margin-left: auto; font: 600 13px Arial, sans-serif; }
.team-roster-picker summary svg { width: 16px; height: 16px; color: #8d9ab7; }
.team-roster-picker[open] summary svg { transform: rotate(180deg); }
.team-roster-picker > div { display: flex; flex-wrap: wrap; gap: 9px; padding: 5px 0 17px; }
.team-roster-picker label { display: inline-flex; align-items: center; gap: 7px; background: #f5f7fc; padding: 10px 12px; border-radius: 9px; font-size: 12px; cursor: pointer; }
.team-roster-picker input { min-height: 0; width: 15px; height: 15px; margin: 0; accent-color: #5263e8; }
.team-roster-picker p { font-size: 11px; line-height: 1.8; color: #a28e70; margin-top: 0; }
.team-publish-fields, .team-captain-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; }
.team-publish-fields label, .team-captain-fields label { display: grid; gap: 8px; min-width: 0; color: #8390ac; font-size: 11px; }
.team-publish-fields input, .team-captain-fields select { width: 100%; background-color: #fff; font-size: 12px; min-height: 44px; }
.team-arrange-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin-top: 21px; }
.team-publisher button, .team-poster-dialog button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 0; padding: 12px 16px; border-radius: 11px; background: #5263e8; color: #fff; font-size: 12px; font-weight: 600; }
.team-publisher button .ui-icon, .team-poster-dialog button .ui-icon { width: 17px; height: 17px; }
.team-publisher button.secondary, .team-poster-dialog button.secondary { background: #eef1ff; color: #7c8bc2; }
.team-lineup-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 23px 0; }
.team-lineup { padding: 17px; border-radius: 16px; background: #f0f3ff; min-width: 0; }
.team-lineup.red { background: #fff3eb; }
.team-lineup > header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; color: #6577c6; }
.team-lineup.red > header { color: #b98b6e; }
.team-lineup header b { font-size: 15px; }
.team-lineup header span { font: 600 11px Arial, sans-serif; }
.team-member-row { display: grid; grid-template-columns: 18px minmax(0, 1fr) 62px; align-items: center; gap: 8px; padding: 8px 0; }
.team-seat-number { color: #a1aece; font: 600 11px Arial, sans-serif; }
.team-member-row select { width: 100%; font-size: 12px; background-color: #ffffffc9; border-color: transparent; }
.team-member-row > button { padding: 6px; background: #ffffff94; color: #8e9ac0; font-size: 10px; font-weight: 400; min-height: 38px; }
.team-captain-badge { display: inline-flex; align-items: center; justify-content: center; gap: 3px; font-size: 10px; color: #6a7bd1; }
.team-captain-badge svg { width: 14px; height: 14px; }
.red .team-captain-badge { color: #bd8d67; }
.team-publish-message { padding: 12px 15px; border-radius: 10px; color: #7e8bac; background: #f2f5fc; font-size: 11px; line-height: 1.8; }
.team-export-actions { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-top: 18px; margin-top: 20px; border-top: 1px solid #e9edf5; }
.team-export-actions > span { font-size: 11px; color: #93a0b8; }
.team-poster-backdrop { position: fixed; z-index: 1100; inset: 0; display: grid; place-items: center; padding: 24px; background: #20294380; backdrop-filter: blur(6px); }
.team-poster-dialog { display: flex; flex-direction: column; max-height: calc(100dvh - 48px); width: min(600px, 100%); background: white; border-radius: 23px; overflow: hidden; box-shadow: 0 20px 80px #1b2b5540; }
.team-poster-dialog > header { display: flex; justify-content: space-between; align-items: center; gap: 18px; padding: 20px 24px; flex: 0 0 auto; border-bottom: 1px solid #e9edf5; }
.team-poster-dialog h3 { margin: 0; font-size: 21px; }
.team-poster-dialog header p { margin: 7px 0 0; color: #8b97b0; font-size: 11px; }
.team-poster-dialog header button { background: #f2f4fa; color: #8d9ab7; padding: 9px; border-radius: 50%; }
.team-poster-image { overflow-y: auto; padding: 18px; background: #f1f4fa; overscroll-behavior: contain; }
.team-poster-image img { display: block; width: 100%; height: auto; border-radius: 10px; user-select: auto; -webkit-touch-callout: default; }
.team-poster-dialog > footer { display: flex; justify-content: center; gap: 12px; padding: 16px 22px; flex: 0 0 auto; border-top: 1px solid #e9edf5; }
@media (max-width: 700px) {
  .team-publisher { padding: 25px 18px; }
  .team-publisher-heading { align-items: start; }
  .team-publisher-heading h3 { font-size: 20px; line-height: 1.5; }
  .team-publisher-heading .eyebrow { font-size: 8px; }
  .team-publisher-heading > svg { width: 35px; height: 35px; padding: 7px; }
  .team-publish-fields { grid-template-columns: 1fr; gap: 13px; }
  .team-captain-fields { gap: 10px; }
  .team-captain-fields select { padding-left: 9px; }
  .team-arrange-actions { display: grid; grid-template-columns: 1fr 1fr; }
  .team-arrange-actions > button:first-child { grid-column: 1 / -1; }
  .team-publisher button { font-size: 11px; padding: 12px 9px; }
  .team-lineup-grid { grid-template-columns: 1fr; gap: 13px; }
  .team-member-row { gap: 9px; }
  .team-member-row > button { padding: 5px; font-size: 10px; }
  .team-export-actions { align-items: stretch; flex-direction: column; gap: 13px; }
  .team-export-actions > span { font-size: 10px; }
  .team-poster-backdrop { padding: 0; align-items: end; }
  .team-poster-dialog { max-height: 94dvh; border-radius: 23px 23px 0 0; }
  .team-poster-dialog > header { padding: 20px; }
  .team-poster-dialog h3 { font-size: 20px; }
  .team-poster-dialog > footer { padding: 14px 20px calc(14px + env(safe-area-inset-bottom)); }
  .team-poster-dialog footer button { flex: 1; }
}
</style>
