<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import AppIcon from './components/AppIcon.vue'
import initialData from './data/tournament.json'
import { calculateStats, formatDate, formatPercent, LANES, selectCaptainsByRank, validateData } from './lib/stats'

const activeView = ref('overview')
const tournament = ref(initialData)
const uploadError = ref('')
const matchPlayerFilter = ref('全部')
const matchDateFilter = ref('全部')
const boardType = ref('honor')
const teaBoardType = ref('tea')
const selectedPlayerName = ref('')
const apiBase = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')
const signupEvents = ref([])
const captainRanks = ref({ winner: 0, loser: 0 })
const signupMessage = ref('')
const signupLoading = ref(false)
const rulesDialogOpen = ref(false)
const rulesDialogEvent = ref(null)
const deciderVoteChoice = ref('')
const deciderVoteMessage = ref('')
const deciderVoteLoading = ref(false)
const storedPlayerSession = localStorage.getItem('milkTeaPlayerSession')
let initialPlayerSession = null
try { initialPlayerSession = storedPlayerSession ? JSON.parse(storedPlayerSession) : null } catch { localStorage.removeItem('milkTeaPlayerSession') }
const playerSession = ref(initialPlayerSession)
const loginForm = ref({ name: '', pin: '', new_player: false })
const adminKey = ref(localStorage.getItem('milkTeaAdminKey') || '')
const adminMessage = ref('')
const adminLoading = ref(false)
const adminMatchId = ref('')
const adminMatchJson = ref('')
const eventForm = ref({ event_date: '', title: '奶茶杯日常赛', capacity: 10, waitlist_capacity: 5, status: 'open' })
let rulesPreviousFocus = null
let rulesPreviousOverflow = ''

watch(activeView, () => window.scrollTo({ top: 0, behavior: 'instant' }))
watch(rulesDialogOpen, async (open) => {
  if (open) {
    rulesPreviousFocus = document.activeElement
    rulesPreviousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    document.querySelector('.rules-dialog-header > button')?.focus({ preventScroll: true })
  } else {
    document.body.style.overflow = rulesPreviousOverflow
    rulesPreviousFocus?.focus({ preventScroll: true })
  }
})
onUnmounted(() => { document.body.style.overflow = rulesPreviousOverflow })

function handleRulesKeydown(event) {
  if (event.key === 'Escape') closeRulesDialog()
  if (event.key !== 'Tab') return
  const buttons = [...event.currentTarget.querySelectorAll('button:not(:disabled), input:not(:disabled)')]
  const first = buttons[0]
  const last = buttons[buttons.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
  if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}

function clearPlayerSession() {
  playerSession.value = null
  localStorage.removeItem('milkTeaPlayerSession')
}

function savePlayerSession(session) {
  playerSession.value = session
  localStorage.setItem('milkTeaPlayerSession', JSON.stringify(session))
}

const stats = computed(() => calculateStats(tournament.value))
const warnings = computed(() => [
  ...(tournament.value.source?.warnings || []),
  ...validateData(tournament.value),
])
const playerNames = computed(() => stats.value.players.map((player) => player.name))
const dates = computed(() => [...new Set(stats.value.matches.map((match) => match.date))])
const selectedPlayer = computed(() =>
  stats.value.players.find((player) => player.name === selectedPlayerName.value) || stats.value.players[0],
)
const filteredMatches = computed(() =>
  stats.value.matches.filter((match) => {
    const matchesDate = matchDateFilter.value === '全部' || match.date === matchDateFilter.value
    const allPlayers = [...match.teams.blue, ...match.teams.red]
    const matchesPlayer =
      matchPlayerFilter.value === '全部' || allPlayers.some((player) => player.name === matchPlayerFilter.value)
    return matchesDate && matchesPlayer
  }),
)
const honorBoard = computed(() => {
  if (boardType.value === 'mvp') return stats.value.mvpLeaders
  if (boardType.value === 'fmvp') return stats.value.fmvpLeaders
  return stats.value.honors
})
const teaBoard = computed(() =>
  teaBoardType.value === 'tea' ? stats.value.teaLeaders : stats.value.treatLeaders,
)
const primarySignupEvent = computed(() => signupEvents.value[0] || null)
const captainTeams = [
  { key: 'winner', title: '胜方', candidates: 'winnerCandidates', captain: 'winnerCaptain', description: '上一轮品茶队伍' },
  { key: 'loser', title: '败方', candidates: 'loserCandidates', captain: 'loserCaptain', description: '上一轮请客队伍' },
]
const currentCaptains = computed(() => selectCaptainsByRank(stats.value.captainSelection, captainRanks.value))
const dialogCaptains = computed(() => currentCaptains.value)
watch(() => stats.value.captainSelection?.referenceMatchId, () => {
  captainRanks.value = { winner: 0, loser: 0 }
})

function eventRegistrations(event, status) {
  return event.registrations.filter((item) => item.status === status)
}

function playerRegistration(event) {
  return event.registrations.find((item) => isCurrentPlayer(item)) || null
}

function isCurrentPlayer(item) {
  return item.player_id === playerSession.value?.player.id
}

function eventVoteSummary(event) {
  return event.decider_vote_summary || {
    registered_count: eventRegistrations(event, 'registered').length,
    required_votes: event.capacity,
    voted_count: 0,
    remaining_votes: event.capacity,
    matchmaking_count: 0,
    ranked_count: 0,
    status: 'waiting_roster',
    result: null,
    tie_break_applied: false,
  }
}

function deciderModeLabel(mode) {
  return mode === 'matchmaking' ? '匹配模式' : mode === 'ranked' ? '排位模式' : '等待结果'
}

const views = [
  { id: 'overview', label: '赛场总览', short: '总览' },
  { id: 'matches', label: '对战记录', short: '对战' },
  { id: 'honors', label: '荣誉榜单', short: '榜单' },
  { id: 'players', label: '选手评分', short: '选手' },
  { id: 'signup', label: '比赛报名', short: '报名' },
  { id: 'admin', label: '赛事管理', short: '管理' },
]

async function api(path, options = {}) {
  if (!apiBase) throw new Error('报名后端尚未配置')
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) }
  const playerToken = playerSession.value?.token
  if (playerToken) headers.Authorization = `Bearer ${playerToken}`
  const response = await fetch(`${apiBase}${path}`, { ...options, headers })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    const error = new Error(body.message || '请求失败')
    error.status = response.status
    if (response.status === 401 && playerToken) {
      clearPlayerSession()
      error.message = '登录状态已失效，请重新登录'
    }
    throw error
  }
  return body
}

async function restorePlayerSession() {
  if (!playerSession.value?.token) {
    if (playerSession.value) clearPlayerSession()
    return
  }

  try {
    const result = await api('/api/auth/me')
    savePlayerSession({ token: playerSession.value.token, player: result.player })
  } catch (error) {
    if (error.status === 401) signupMessage.value = error.message
  }
}

async function loadBackend() {
  if (!apiBase) return
  try {
    const [data, events] = await Promise.all([api('/api/tournament'), api('/api/events')])
    tournament.value = data
    signupEvents.value = events.events || []
    if (signupMessage.value.startsWith('后端连接失败')) signupMessage.value = ''
  } catch (error) {
    signupMessage.value = `后端连接失败，当前继续显示静态数据：${error.message}`
  }
}

async function loginPlayer() {
  signupLoading.value = true
  signupMessage.value = ''
  try {
    const session = await api('/api/auth/player', { method: 'POST', body: JSON.stringify(loginForm.value) })
    savePlayerSession(session)
    signupMessage.value = `欢迎，${session.player.name}`
  } catch (error) { signupMessage.value = error.message } finally { signupLoading.value = false }
}

async function registerEvent(event) {
  signupLoading.value = true
  try {
    const result = await api(`/api/events/${event.id}/register`, { method: 'POST', body: '{}' })
    signupMessage.value = result.registration.status === 'registered' ? '报名成功' : '正赛已满，已进入候补'
    await loadBackend()
    const refreshedEvent = signupEvents.value.find((item) => item.id === event.id) || event
    await openRulesDialog(refreshedEvent)
  } catch (error) { signupMessage.value = error.message } finally { signupLoading.value = false }
}

async function cancelRegistration(event) {
  signupLoading.value = true
  try {
    await api(`/api/events/${event.id}/register`, { method: 'DELETE' })
    signupMessage.value = '已取消报名'
    await loadBackend()
  } catch (error) { signupMessage.value = error.message } finally { signupLoading.value = false }
}

async function openRulesDialog(event) {
  rulesDialogEvent.value = event
  rulesDialogOpen.value = true
  deciderVoteMessage.value = ''
  deciderVoteChoice.value = ''

  if (playerRegistration(event)?.status !== 'registered') return

  try {
    const result = await api(`/api/events/${event.id}/decider-vote`)
    deciderVoteChoice.value = result.vote || ''
    rulesDialogEvent.value.decider_vote_summary = result.summary
  } catch (error) {
    deciderVoteMessage.value = error.message
  }
}

function closeRulesDialog() {
  rulesDialogOpen.value = false
  deciderVoteMessage.value = ''
}

async function submitDeciderVote() {
  if (!rulesDialogEvent.value || !deciderVoteChoice.value) return
  deciderVoteLoading.value = true
  deciderVoteMessage.value = ''

  try {
    const result = await api(`/api/events/${rulesDialogEvent.value.id}/decider-vote`, {
      method: 'PUT',
      body: JSON.stringify({ mode: deciderVoteChoice.value }),
    })
    rulesDialogEvent.value.decider_vote_summary = result.summary
    const registration = playerRegistration(rulesDialogEvent.value)
    if (registration) registration.has_decider_vote = true
    deciderVoteMessage.value = `${result.message}：${deciderModeLabel(result.vote)}`
  } catch (error) {
    deciderVoteMessage.value = error.message
  } finally {
    deciderVoteLoading.value = false
  }
}

async function logoutPlayer() {
  try { await api('/api/auth/logout', { method: 'POST' }) } catch { /* 本地仍然退出 */ }
  clearPlayerSession()
  closeRulesDialog()
}

function saveAdminKey() {
  localStorage.setItem('milkTeaAdminKey', adminKey.value)
  adminMessage.value = '管理员密钥已保存在当前浏览器'
}

async function adminApi(path, options = {}) {
  return api(path, { ...options, headers: { 'X-Admin-Key': adminKey.value, ...(options.headers || {}) } })
}

function selectAdminMatch() {
  const match = tournament.value.matches.find((item) => item.id === adminMatchId.value)
  adminMatchJson.value = match ? JSON.stringify(match, null, 2) : ''
}

async function saveAdminMatch() {
  adminLoading.value = true
  try {
    const match = JSON.parse(adminMatchJson.value)
    const id = match.id || adminMatchId.value
    if (!id) throw new Error('请选择比赛或填写比赛 id')
    await adminApi(`/api/admin/matches/${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify(match) })
    adminMessage.value = `比赛 ${id} 已保存`
    await loadBackend()
  } catch (error) { adminMessage.value = error.message } finally { adminLoading.value = false }
}

async function importTournament(event) {
  const [file] = event.target.files
  if (!file) return
  adminLoading.value = true
  try {
    const payload = JSON.parse(await file.text())
    const result = await adminApi('/api/admin/tournament/import?replace_all=1', { method: 'POST', body: JSON.stringify(payload) })
    adminMessage.value = `导入成功：${result.summary.matches} 场，${result.summary.records} 条记录`
    await loadBackend()
  } catch (error) { adminMessage.value = error.message } finally {
    adminLoading.value = false
    event.target.value = ''
  }
}

async function createEvent() {
  adminLoading.value = true
  try {
    await adminApi('/api/admin/events', { method: 'POST', body: JSON.stringify(eventForm.value) })
    adminMessage.value = `${eventForm.value.event_date} 的报名场次已创建`
    await loadBackend()
  } catch (error) { adminMessage.value = error.message } finally { adminLoading.value = false }
}

onMounted(async () => {
  await restorePlayerSession()
  await loadBackend()
})

function teamKills(team) {
  return team.reduce((sum, player) => sum + Number(player.kills || 0), 0)
}

function openPlayer(name) {
  selectedPlayerName.value = name
  activeView.value = 'players'
  scrollToPlayer()
}

function selectPlayer(name) {
  selectedPlayerName.value = name
  if (window.matchMedia('(max-width: 700px)').matches) scrollToPlayer()
}

function scrollToPlayer() {
  window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' })
}

async function loadJson(event) {
  const [file] = event.target.files
  if (!file) return
  uploadError.value = ''
  try {
    const next = JSON.parse(await file.text())
    if (!Array.isArray(next.matches)) throw new Error('JSON 中缺少 matches 数组')
    tournament.value = next
    selectedPlayerName.value = ''
  } catch (error) {
    uploadError.value = `读取失败：${error.message}`
  } finally {
    event.target.value = ''
  }
}
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <button class="brand" type="button" @click="activeView = 'overview'" aria-label="回到赛场总览">
        <span class="brand-mark"><AppIcon name="cup" /></span>
        <span><b>奶茶杯<span class="brand-dot">.</span></b><small>一起开黑，一起喝茶</small></span>
      </button>

      <nav class="desktop-nav" aria-label="主导航">
        <button
          v-for="view in views"
          :key="view.id"
          :class="{ active: activeView === view.id }"
          type="button"
          @click="activeView = view.id"
        >
          {{ view.label }}
        </button>
      </nav>

      <button class="topbar-manage" type="button" aria-label="赛事管理" @click="activeView = 'admin'"><AppIcon name="admin" /><span>赛事管理</span></button>
    </header>

    <main>
      <div v-if="uploadError" class="notice notice-error">{{ uploadError }}</div>
      <div v-if="warnings.length" class="notice">
        数据校验提示：{{ warnings.join('；') }}
      </div>

      <section v-if="activeView === 'overview'" class="view view-overview">
        <div class="hero">
          <div class="hero-copy">
            <p class="eyebrow"><span class="live-dot"></span> {{ tournament.competition.season }} · 5V5 好友赛</p>
            <h1>今天的奶茶，<br /><em>峡谷里见。</em></h1>
            <p class="hero-intro">
              十个朋友，两支队伍。把每一场高光，留在奶茶杯。
            </p>
            <div class="hero-actions">
              <button class="primary-button" type="button" @click="activeView = 'signup'">报名下一场 <AppIcon name="arrow" /></button>
              <button class="text-button" type="button" @click="activeView = 'matches'">看看最近战绩 <AppIcon name="arrow-up-right" /></button>
            </div>
          </div>

          <div class="match-ticket" aria-label="奶茶杯五对五好友赛">
            <div class="ticket-head"><span>MILK TEA CUP</span><span>好友局 / BO5</span></div>
            <div class="ticket-lineup blue"><i v-for="n in 5" :key="n"></i></div>
            <div class="ticket-versus"><span>5</span><AppIcon name="cup" /><span>5</span></div>
            <div class="ticket-lineup orange"><i v-for="n in 5" :key="n"></i></div>
            <div class="ticket-bottom"><span>胜负有时 · 快乐常在</span><b>LET’S PLAY <AppIcon name="arrow-up-right" /></b></div>
          </div>
        </div>

        <div class="season-summary"><div><b>{{ stats.overview.matchCount }}</b><span>场精彩对局</span></div><div><b>{{ stats.overview.playerCount }}</b><span>位参赛好友</span></div><div><b>{{ stats.overview.recordCount }}</b><span>条高光记录</span></div><small>战报更新至 {{ stats.overview.latestDate }}</small></div>
        <div class="quick-links"><button v-for="view in views.filter(item => ['matches', 'honors', 'players', 'signup'].includes(item.id))" :key="view.id" type="button" @click="activeView = view.id"><span><AppIcon :name="view.id" /></span><b>{{ view.label }}</b><small>{{ {matches: '每场都值得回味', honors: '看看谁是 MVP', players: '发现你的拿手分路', signup: '下一场等你来'}[view.id] }}</small></button></div>

        <section v-if="stats.captainSelection" class="captain-section">
          <div class="captain-heading">
            <div>
              <p class="eyebrow">NEXT BATTLE · CAPTAIN DRAFT</p>
              <h2>下一场，谁带队？</h2>
            </div>
            <p>
              只统计最近比赛日 {{ stats.captainSelection.sourceDate }} 的全部
              {{ stats.captainSelection.sourceMatchCount }} 场对局，历史日期不参与。
            </p>
          </div>

          <div class="captain-controls">
            <span>按上一轮原队伍顺位递补</span>
            <button type="button" @click="captainRanks = { winner: 0, loser: 0 }">恢复双方首选</button>
          </div>
          <p class="captain-roster-hint">不关联报名昵称。首选无法参赛时，可手动查看本队下一顺位；切换仅供本机预览，不代表全站已确定队长。</p>

          <div class="captain-arena captain-succession-arena" aria-live="polite">
            <article v-for="team in captainTeams" :key="team.key" :class="['captain-card', `${team.key}-captain` ]">
              <div class="captain-copy">
                <small>{{ team.title }}队长 · {{ team.description }}</small>
                <template v-if="currentCaptains?.[team.captain]">
                  <h3>{{ currentCaptains[team.captain].name }}<span class="captain-rank-chip">{{ currentCaptains[team.captain].successionRank === 1 ? '首选' : `第 ${currentCaptains[team.captain].successionRank} 顺位 · 预览` }}</span></h3>
                  <p>上轮首选：{{ stats.captainSelection[team.candidates][0].name }}</p>
                  <div class="captain-stats">
                    <span><small>MVP＋FMVP</small><b>{{ currentCaptains[team.captain].mvp + currentCaptains[team.captain].fmvp }}</b></span>
                    <span><small>MVP 次数</small><b>{{ currentCaptains[team.captain].mvp }}</b></span>
                    <span><small>上轮均分</small><b>{{ currentCaptains[team.captain].avgRating.toFixed(1) }}</b></span>
                  </div>
                  <button class="captain-profile-link" type="button" @click="openPlayer(currentCaptains[team.captain].name)">查看选手战绩 <AppIcon name="arrow-up-right" /></button>
                  <div class="captain-rank-picker">
                    <label :for="`captain-rank-${team.key}`">替补顺位</label>
                    <select :id="`captain-rank-${team.key}`" v-model.number="captainRanks[team.key]">
                      <option v-for="(candidate, index) in stats.captainSelection[team.candidates]" :key="candidate.name" :value="index">{{ index === 0 ? '首选' : `第 ${index + 1} 顺位` }} · {{ candidate.name }}</option>
                    </select>
                    <button type="button" :disabled="captainRanks[team.key] >= stats.captainSelection[team.candidates].length - 1" @click="captainRanks[team.key]++">下一顺位 <AppIcon name="arrow" /></button>
                  </div>
                </template>
                <div v-else class="captain-pending"><h3>暂无候选人</h3><p>本队暂无可用比赛数据。</p></div>
              </div>
              <details class="captain-reserves">
                <summary><span>替补队长 · 查看完整顺位</span><AppIcon name="chevron-down" class="reserve-chevron" /></summary>
                <ol>
                  <li v-for="(candidate, index) in stats.captainSelection[team.candidates]" :key="candidate.name" :class="{ recommended: currentCaptains?.[team.captain]?.name === candidate.name }">
                    <span class="reserve-order">{{ String(index + 1).padStart(2, '0') }}</span>
                    <button type="button" @click="openPlayer(candidate.name)"><b>{{ candidate.name }}</b><small>荣誉 {{ candidate.mvp + candidate.fmvp }} · MVP {{ candidate.mvp }} · 均分 {{ candidate.avgRating.toFixed(1) }}</small></button>
                    <span class="reserve-status">{{ index === 0 ? '首选' : `替补 ${index}` }}</span>
                  </li>
                </ol>
                <p>MVP＋FMVP 总次数优先，相同再比上轮均分；全部相同则按昵称稳定排列。</p>
              </details>
            </article>
          </div>

          <div class="captain-rules">
            <span><b>胜方规则</b> 最后比赛日品茶选手 → MVP+FMVP 次数 → 当日平均评分</span>
            <span><b>败方规则</b> 最后比赛日请客选手 → MVP+FMVP 次数 → 当日平均评分</span>
          </div>
        </section>

        <section v-if="stats.latestRoundHighlights" class="round-stories">
          <div class="section-heading"><div><p class="eyebrow">LAST ROUND · FRIENDS MOMENTS</p><h2>上一轮的小彩蛋</h2></div><p>{{ stats.latestRoundHighlights.sourceDate }} · 只看当日 {{ stats.latestRoundHighlights.sourceMatchCount }} 场，下一轮重新出发。</p></div>
          <div class="round-story-grid">
            <article class="round-story growth-story">
              <span class="story-icon"><AppIcon name="cup" /></span>
              <div><small>再攒一点能量，下场就发光</small><h3>蓄力之星</h3></div>
              <div class="story-players"><button v-for="player in stats.latestRoundHighlights.growthStars" :key="player.name" type="button" @click="openPlayer(player.name)">{{ player.name }} <AppIcon name="arrow-up-right" /></button></div>
              <p>上轮综合分最低 · <b>{{ stats.latestRoundHighlights.lowestPowerScore.toFixed(2) }}</b> / 100</p>
              <small class="story-footnote">按当日均分、胜率及 MVP／FMVP 率计算；仅是这一轮的状态。</small>
            </article>
            <article class="round-story teamfight-story">
              <span class="story-icon"><AppIcon name="players" /></span>
              <div><small>这轮先体验，下轮找回节奏</small><h3>团战体验官</h3></div>
              <div class="story-players"><button v-for="player in stats.latestRoundHighlights.teamfightExplorers" :key="player.name" type="button" @click="openPlayer(player.name)">{{ player.name }} <AppIcon name="arrow-up-right" /></button><span v-if="!stats.latestRoundHighlights.mostDeaths">全员零阵亡，这轮空缺</span></div>
              <p>上轮累计阵亡最多 · <b>{{ stats.latestRoundHighlights.mostDeaths }}</b> 次</p>
              <small class="story-footnote">按当日总阵亡次数统计，并列一起上榜；只是朋友间的小彩蛋。</small>
            </article>
          </div>
        </section>

        <div class="section-heading">
          <div>
            <p class="eyebrow">FIVE LANES · TOP 3</p>
            <h2>五路领跑者</h2>
          </div>
          <p>按该分路全部参赛局的平均游戏内评分排序。</p>
        </div>

        <div class="lane-grid">
          <article v-for="lane in LANES" :key="lane" class="lane-card" :data-lane="lane">
            <header>
              <span class="lane-symbol"><AppIcon :name="{ 对抗: 'swords', 打野: 'jungle', 法师: 'magic', 射手: 'target', 辅助: 'support' }[lane]" /></span>
              <div><small>LANE RANK</small><h3>{{ lane }}</h3></div>
            </header>
            <button
              v-for="player in stats.laneLeaders[lane]"
              :key="player.player"
              class="lane-rank-row"
              type="button"
              @click="openPlayer(player.player)"
            >
              <span class="rank-number">{{ String(player.rank).padStart(2, '0') }}</span>
              <span class="rank-name"><b>{{ player.player }}</b><small>{{ player.matches }} 场 · 胜率 {{ formatPercent(player.winRate) }}</small></span>
              <strong>{{ player.avgRating.toFixed(1) }}</strong>
            </button>
          </article>
        </div>

        <div class="recent-strip">
          <div class="section-heading compact">
            <div><p class="eyebrow">LAST MATCHES</p><h2>最近赛况</h2></div>
            <button class="text-button" type="button" @click="activeView = 'matches'">全部 {{ stats.overview.matchCount }} 场 <AppIcon name="arrow" /></button>
          </div>
          <div class="recent-list">
            <article v-for="match in stats.matches.slice(0, 4)" :key="match.id" class="recent-match">
              <span>{{ formatDate(match.date) }} · {{ match.round }}</span>
              <div>
                <b :class="{ winner: match.winner === 'blue' }">蓝队 {{ teamKills(match.teams.blue) }}</b>
                <i>:</i>
                <b :class="{ winner: match.winner === 'red' }">{{ teamKills(match.teams.red) }} 红队</b>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-else-if="activeView === 'matches'" class="view">
        <div class="page-title">
          <div><p class="eyebrow">MATCH ARCHIVE</p><h1>对战记录</h1></div>
          <p>每一场的阵容、分路、KDA、游戏内评分和当局荣誉都保留在这里。</p>
        </div>

        <div class="filter-bar">
          <label>比赛日期<select v-model="matchDateFilter"><option>全部</option><option v-for="date in dates" :key="date" :value="date">{{ date }}</option></select></label>
          <label>参赛选手<select v-model="matchPlayerFilter"><option>全部</option><option v-for="name in playerNames" :key="name" :value="name">{{ name }}</option></select></label>
          <span>找到 {{ filteredMatches.length }} 场</span>
        </div>

        <div class="match-list">
          <details v-for="(match, index) in filteredMatches" :key="match.id" class="match-card" :open="index === 0">
            <summary>
              <div class="match-date"><small>{{ formatDate(match.date) }}</small><b>{{ match.round }}</b></div>
              <div class="team-score blue" :class="{ winner: match.winner === 'blue' }"><span>蓝队</span><strong>{{ teamKills(match.teams.blue) }}</strong><small>{{ match.winner === 'blue' ? '胜利' : '败北' }}</small></div>
              <span class="versus">VS</span>
              <div class="team-score red" :class="{ winner: match.winner === 'red' }"><strong>{{ teamKills(match.teams.red) }}</strong><span>红队</span><small>{{ match.winner === 'red' ? '胜利' : '败北' }}</small></div>
              <span class="expand-hint">展开阵容</span>
            </summary>
            <div class="lineups">
              <div class="lineup-header"><span>蓝队阵容</span><span>分路</span><span>K / D / A</span><span>评分</span><span>荣誉</span></div>
              <button v-for="player in match.teams.blue" :key="`blue-${player.name}`" class="player-match-row" type="button" @click="openPlayer(player.name)">
                <b>{{ player.name }}</b><span>{{ player.lane }}</span><span>{{ player.kills }} / {{ player.deaths }} / {{ player.assists }}</span><strong>{{ player.rating.toFixed(1) }}</strong><span class="honor-tags"><i v-if="player.mvp">MVP</i><i v-if="player.fmvp">FMVP</i><i v-if="player.tea">品茶</i><i v-if="player.treat">善人</i></span>
              </button>
              <div class="lineup-divider">红队阵容</div>
              <button v-for="player in match.teams.red" :key="`red-${player.name}`" class="player-match-row" type="button" @click="openPlayer(player.name)">
                <b>{{ player.name }}</b><span>{{ player.lane }}</span><span>{{ player.kills }} / {{ player.deaths }} / {{ player.assists }}</span><strong>{{ player.rating.toFixed(1) }}</strong><span class="honor-tags"><i v-if="player.mvp">MVP</i><i v-if="player.fmvp">FMVP</i><i v-if="player.tea">品茶</i><i v-if="player.treat">善人</i></span>
              </button>
            </div>
          </details>
        </div>
      </section>

      <section v-else-if="activeView === 'honors'" class="view">
        <div class="page-title">
          <div><p class="eyebrow">HALL OF FAME</p><h1>本季荣誉榜</h1></div>
          <p>MVP、FMVP、喝奶茶与请客次数，全部由原始比赛记录自动累加。</p>
        </div>

        <div class="boards-grid">
          <article class="leaderboard honor-board">
            <header><div><small>竞技荣誉 · TOP 10</small><h2>高光时刻</h2></div><span class="medal"><AppIcon name="honors" /></span></header>
            <div class="segmented">
              <button :class="{ active: boardType === 'honor' }" @click="boardType = 'honor'">总荣誉</button>
              <button :class="{ active: boardType === 'mvp' }" @click="boardType = 'mvp'">MVP</button>
              <button :class="{ active: boardType === 'fmvp' }" @click="boardType = 'fmvp'">FMVP</button>
            </div>
            <button v-for="player in honorBoard" :key="player.name" class="board-row" type="button" @click="openPlayer(player.name)">
              <span class="board-rank">{{ player.boardRank }}</span><span class="avatar">{{ player.name.slice(0, 1) }}</span><span class="board-name"><b>{{ player.name }}</b><small>MVP {{ player.mvp }} · FMVP {{ player.fmvp }}</small></span><strong>{{ boardType === 'mvp' ? player.mvp : boardType === 'fmvp' ? player.fmvp : player.honorTotal }}<small> 次</small></strong>
            </button>
          </article>

          <article class="leaderboard tea-board">
            <header><div><small>奶茶江湖 · TOP 10</small><h2>{{ teaBoardType === 'tea' ? '品茶大师' : '大善人' }}</h2></div><span class="medal tea"><AppIcon name="cup" /></span></header>
            <div class="segmented">
              <button :class="{ active: teaBoardType === 'tea' }" @click="teaBoardType = 'tea'">喝奶茶</button>
              <button :class="{ active: teaBoardType === 'treat' }" @click="teaBoardType = 'treat'">请客</button>
            </div>
            <button v-for="player in teaBoard" :key="player.name" class="board-row" type="button" @click="openPlayer(player.name)">
              <span class="board-rank">{{ player.boardRank }}</span><span class="avatar tea-avatar">{{ player.name.slice(0, 1) }}</span><span class="board-name"><b>{{ player.name }}</b><small>{{ player.matches }} 场 · 胜率 {{ formatPercent(player.winRate) }}</small></span><strong>{{ teaBoardType === 'tea' ? player.tea : player.treat }}<small> 杯</small></strong>
            </button>
          </article>
        </div>
      </section>

      <section v-else-if="activeView === 'players'" class="view">
        <div class="page-title player-title">
          <div><p class="eyebrow">PLAYER POWER RANKING</p><h1>选手实力评分</h1></div>
          <p>100 分制：局内均分 55% + 胜率 20% + MVP 率 15% + FMVP 率 10%。</p>
        </div>

        <div class="player-layout">
          <article class="player-ranking">
            <div class="player-table-head"><span>排名 / 选手</span><span>场次</span><span>胜率</span><span>MVP</span><span>FMVP</span><span>实力分</span></div>
            <button
              v-for="player in stats.players"
              :key="player.name"
              :class="['player-table-row', { selected: selectedPlayer.name === player.name }]"
              type="button"
              @click="selectPlayer(player.name)"
            >
              <span class="player-cell"><i>{{ player.rank }}</i><b>{{ player.name }}</b></span><span>{{ player.matches }}</span><span>{{ formatPercent(player.winRate) }}</span><span>{{ player.mvp }}</span><span>{{ player.fmvp }}</span><strong>{{ player.powerScore.toFixed(2) }}</strong>
            </button>
          </article>

          <aside class="player-profile">
            <div class="profile-top"><span class="profile-rank">RANK <b>#{{ selectedPlayer.rank }}</b></span><div class="profile-avatar">{{ selectedPlayer.name.slice(0, 1) }}</div><h2>{{ selectedPlayer.name }}</h2><p>{{ selectedPlayer.matches }} 次出战 · {{ selectedPlayer.wins }} 场胜利</p></div>
            <div class="power-score"><span>实力评分</span><strong>{{ selectedPlayer.powerScore.toFixed(2) }}</strong><div class="score-track"><i :style="{ width: `${selectedPlayer.powerScore}%` }"></i></div></div>
            <div class="profile-metrics"><div><small>游戏均分</small><b>{{ selectedPlayer.avgRating.toFixed(1) }}</b></div><div><small>胜率</small><b>{{ formatPercent(selectedPlayer.winRate) }}</b></div><div><small>平均 KDA</small><b>{{ selectedPlayer.avgKda.toFixed(2) }}</b></div><div><small>MVP / FMVP</small><b>{{ selectedPlayer.mvp }} / {{ selectedPlayer.fmvp }}</b></div></div>
            <div class="best-lanes"><header><span>最强三路</span><small>按分路平均评分</small></header><div v-for="(lane, index) in selectedPlayer.laneStats.slice(0, 3)" :key="lane.name" class="best-lane-row"><span>{{ index + 1 }}</span><b>{{ lane.name }}</b><div class="mini-track"><i :style="{ width: `${Math.min(100, lane.avgRating / 14 * 100)}%` }"></i></div><strong>{{ lane.avgRating.toFixed(1) }}</strong><small>{{ lane.matches }} 场</small></div></div>
          </aside>
        </div>
      </section>

      <section v-else-if="activeView === 'signup'" class="view signup-view">
        <div class="signup-command-hero">
          <div class="signup-command-copy">
            <div class="signup-live"><i></i><span>DAILY ASSEMBLY · 每日自动开放</span></div>
            <p class="eyebrow">MILK TEA CUP · 5V5</p>
            <h1>今晚开黑，<em>就差你了。</em></h1>
            <p>每天自动开放报名，满 10 人就能组局。带上拿手英雄，和朋友一起争夺下一杯奶茶。</p>
            <div class="signup-hero-meta">
              <span><small>今日场次</small><b>{{ primarySignupEvent ? '已开放' : '准备中' }}</b></span>
              <span><small>集结人数</small><b>{{ primarySignupEvent ? eventRegistrations(primarySignupEvent, 'registered').length : 0 }} / {{ primarySignupEvent?.capacity || 10 }}</b></span>
              <span><small>候补队列</small><b>{{ primarySignupEvent ? eventRegistrations(primarySignupEvent, 'waitlist').length : 0 }} 人</b></span>
            </div>
          </div>
          <div class="signup-war-order" aria-hidden="true">
            <div class="war-order-ring"><AppIcon name="cup" /><b>好朋友 · 好对局</b></div>
            <i class="war-order-wing left"></i><i class="war-order-wing right"></i>
            <p>荣耀峡谷 · 奶茶杯</p>
          </div>
        </div>

        <div v-if="signupMessage" class="notice">{{ signupMessage }}</div>
        <div v-if="!apiBase" class="signup-empty">
          <b>报名服务尚未部署</b><p>当前统计看板仍可正常使用。部署 Laravel 后端并配置 VITE_API_BASE_URL 后，这里会自动启用。</p>
        </div>
        <template v-else>
          <section v-if="!playerSession" class="signup-access">
            <div class="signup-access-brief">
              <small>STEP 01 · 身份确认</small>
              <h2>先亮出你的<br />选手名牌</h2>
              <p>老选手使用昵称和 PIN 登录；首次参赛勾选“新玩家”，系统会同时建立选手档案。</p>
              <div><span>01</span> 登录选手身份</div>
              <div><span>02</span> 选择今日席位</div>
              <div><span>03</span> 等待十人集结</div>
            </div>
            <form class="signup-login" @submit.prevent="loginPlayer">
              <header><small>PLAYER ACCESS</small><h2>{{ loginForm.new_player ? '创建新玩家' : '选手登录' }}</h2><p>{{ loginForm.new_player ? '第一次参赛？现在建立你的选手档案。' : '欢迎归队，登录后即可占领今日席位。' }}</p></header>
              <label>玩家昵称<input v-model.trim="loginForm.name" autocomplete="username" maxlength="50" placeholder="输入比赛常用昵称" /></label>
              <label>报名 PIN<input v-model="loginForm.pin" :autocomplete="loginForm.new_player ? 'new-password' : 'current-password'" inputmode="numeric" maxlength="12" type="password" placeholder="4—12 位数字" /></label>
              <label class="signup-check"><input v-model="loginForm.new_player" type="checkbox" /><span><b>我是第一次参加</b><small>创建新玩家档案并继续报名</small></span></label>
              <button type="submit" :disabled="signupLoading"><span>{{ signupLoading ? '身份确认中…' : loginForm.new_player ? '创建档案并进入' : '确认身份，进入战场' }}</span><AppIcon name="arrow" /></button>
            </form>
          </section>
          <div v-else class="signup-session">
            <div class="session-avatar">{{ playerSession.player.name.slice(0, 1) }}</div>
            <span><small>当前选手 · ONLINE</small><b>{{ playerSession.player.name }}</b></span>
            <em>身份已确认，可以报名</em>
            <button type="button" @click="logoutPlayer">退出登录</button>
          </div>

          <div class="event-grid">
            <article v-for="event in signupEvents" :key="event.id" class="event-card" :class="{ full: eventRegistrations(event, 'registered').length >= event.capacity }">
              <header class="event-command-header">
                <div><span class="event-state"><i></i>{{ event.status === 'open' ? '报名开放中' : '报名已关闭' }}</span><small>DAILY MATCH ORDER</small><h2>{{ event.title }}</h2></div>
                <time><small>MATCH DAY</small><b>{{ event.event_date.slice(5).replace('-', ' / ') }}</b><span>{{ event.event_date.slice(0, 4) }}</span></time>
              </header>

              <div class="event-readiness">
                <div class="readiness-copy"><span>战队集结进度</span><b>{{ eventRegistrations(event, 'registered').length }}<small> / {{ event.capacity }} 人</small></b></div>
                <div class="readiness-track" role="progressbar" :aria-valuenow="eventRegistrations(event, 'registered').length" aria-valuemin="0" :aria-valuemax="event.capacity">
                  <i v-for="slot in event.capacity" :key="slot" :class="{ filled: slot <= eventRegistrations(event, 'registered').length }"></i>
                </div>
                <p>{{ eventRegistrations(event, 'registered').length >= event.capacity ? '正式席位已满，新报名将进入候补队列。' : `还差 ${event.capacity - eventRegistrations(event, 'registered').length} 人即可满员开战。` }}</p>
              </div>

              <div class="event-roster-board">
                <div class="roster-heading"><span><AppIcon name="players" />正式席位</span><small>{{ eventRegistrations(event, 'registered').length }} / {{ event.capacity }}</small></div>
                <div class="roster-grid">
                  <div v-for="(item, index) in eventRegistrations(event, 'registered')" :key="item.id" class="roster-slot occupied" :class="{ mine: isCurrentPlayer(item) }">
                    <span>{{ item.player.name.slice(0, 1) }}</span><b>{{ item.player.name }}</b><small>#{{ String(index + 1).padStart(2, '0') }}</small>
                  </div>
                  <div v-for="slot in Math.max(0, event.capacity - eventRegistrations(event, 'registered').length)" :key="`empty-${slot}`" class="roster-slot empty">
                    <span><AppIcon name="plus" /></span><b>等待选手</b><small>OPEN</small>
                  </div>
                </div>

                <div class="waitlist-row">
                  <div class="roster-heading"><span><AppIcon name="signup" />候补队列</span><small>{{ eventRegistrations(event, 'waitlist').length }} / {{ event.waitlist_capacity }}</small></div>
                  <div class="waitlist-players">
                    <span v-for="item in eventRegistrations(event, 'waitlist')" :key="item.id" :class="{ mine: isCurrentPlayer(item) }">{{ item.player.name }}</span>
                    <em v-if="!eventRegistrations(event, 'waitlist').length">当前无人候补</em>
                  </div>
                </div>
              </div>

              <div class="event-vote-brief">
                <div>
                  <small>GAME 5 · DECIDER PROTOCOL</small>
                  <b>决胜局模式票决</b>
                  <p>全员投票后显示结果，报名关闭后锁定。</p>
                </div>
                <div class="event-vote-progress">
                  <span><b>{{ eventVoteSummary(event).voted_count }}</b> / {{ eventVoteSummary(event).required_votes }} 已投</span>
                  <div><i :style="{ width: `${Math.min(100, eventVoteSummary(event).voted_count / Math.max(1, eventVoteSummary(event).required_votes) * 100)}%` }"></i></div>
                  <strong v-if="eventVoteSummary(event).status === 'decided'">
                    {{ deciderModeLabel(eventVoteSummary(event).result) }}
                    <small v-if="eventVoteSummary(event).tie_break_applied">5:5 平票保底</small>
                  </strong>
                  <em v-else>还需 {{ eventVoteSummary(event).remaining_votes }} 票</em>
                </div>
              </div>

              <div class="event-actions">
                <div><small>{{ playerSession ? 'YOUR STATUS' : 'PLAYER ACCESS REQUIRED' }}</small><b v-if="playerSession">{{ event.registrations.some(item => isCurrentPlayer(item)) ? '你已在今日名单中' : '今日尚未报名' }}</b><b v-else>登录后即可占领席位</b></div>
                <div class="event-action-buttons">
                  <button v-if="playerSession && !event.registrations.some(item => isCurrentPlayer(item))" type="button" :disabled="signupLoading || event.status !== 'open'" @click="registerEvent(event)"><span>{{ eventRegistrations(event, 'registered').length >= event.capacity ? '加入候补队列' : '立即报名，占领席位' }}</span><AppIcon name="arrow" /></button>
                  <template v-else-if="playerSession">
                    <button class="rules" type="button" @click="openRulesDialog(event)">{{ playerRegistration(event)?.status === 'registered' && !playerRegistration(event)?.has_decider_vote ? '阅读规则并投票' : '查看赛制与投票' }}</button>
                    <button class="cancel" type="button" :disabled="signupLoading" @click="cancelRegistration(event)">取消今日报名</button>
                  </template>
                  <template v-else>
                    <button class="rules" type="button" @click="openRulesDialog(event)">查看今日赛制</button>
                    <span class="login-hint">请先完成上方身份确认</span>
                  </template>
                </div>
              </div>
            </article>
            <div v-if="!signupEvents.length" class="signup-empty"><span><AppIcon name="signup" /></span><b>今日战场正在开启</b><p>场次准备完成后会在这里显示，请稍后刷新页面。</p></div>
          </div>
        </template>
      </section>

      <section v-else class="view admin-view">
        <div class="page-title">
          <div><p class="eyebrow">TOURNAMENT CONTROL CENTER</p><h1>赛事管理</h1></div>
          <p>日常报名会每天自动创建；这里可以补建特殊日期场次、导入完整比赛 JSON，或修改单场比赛。</p>
        </div>

        <div v-if="adminMessage" class="notice">{{ adminMessage }}</div>
        <div v-if="!apiBase" class="signup-empty"><b>管理服务尚未部署</b><p>配置 VITE_API_BASE_URL 后即可使用。</p></div>
        <template v-else>
          <article class="admin-key-card">
            <label>管理员密钥<input v-model="adminKey" type="password" autocomplete="current-password" placeholder="后端 TOURNAMENT_ADMIN_KEY" /></label>
            <button type="button" @click="saveAdminKey">保存到本机</button>
          </article>

          <div class="admin-grid">
            <article class="admin-panel">
              <header><small>REGISTRATION EVENT</small><h2>补建特殊日期报名</h2></header>
              <label>比赛日期<input v-model="eventForm.event_date" type="date" /></label>
              <label>场次名称<input v-model.trim="eventForm.title" maxlength="100" /></label>
              <div class="admin-fields">
                <label>正赛人数<input v-model.number="eventForm.capacity" type="number" min="2" max="100" /></label>
                <label>候补人数<input v-model.number="eventForm.waitlist_capacity" type="number" min="0" max="100" /></label>
              </div>
              <button type="button" :disabled="adminLoading || !adminKey || !eventForm.event_date" @click="createEvent">创建报名场次</button>
            </article>

            <article class="admin-panel">
              <header><small>FULL DATA IMPORT</small><h2>导入比赛 JSON</h2></header>
              <p>会用上传文件替换数据库内的全部比赛记录。玩家报名账号与报名记录不会被删除。</p>
              <label class="admin-upload"><input type="file" accept="application/json,.json" :disabled="adminLoading || !adminKey" @change="importTournament" /><span>选择 tournament.json 并导入</span></label>
            </article>
          </div>

          <article class="admin-panel match-editor">
            <header><small>SINGLE MATCH EDITOR</small><h2>修改单场比赛</h2></header>
            <label>选择比赛<select v-model="adminMatchId" @change="selectAdminMatch"><option value="">请选择</option><option v-for="match in stats.matches" :key="match.id" :value="match.id">{{ match.date }} · {{ match.round }} · {{ match.id }}</option></select></label>
            <textarea v-model="adminMatchJson" spellcheck="false" placeholder="选择比赛后，这里会显示该场 JSON"></textarea>
            <button type="button" :disabled="adminLoading || !adminKey || !adminMatchJson" @click="saveAdminMatch">校验并保存本场数据</button>
          </article>
        </template>
      </section>
    </main>

    <Teleport to="body">
      <div v-if="rulesDialogOpen && rulesDialogEvent" class="rules-dialog-backdrop" @click.self="closeRulesDialog" @keydown="handleRulesKeydown">
        <section class="rules-dialog" role="dialog" aria-modal="true" aria-labelledby="rules-dialog-title">
          <header class="rules-dialog-header">
            <div>
              <p class="eyebrow">MATCH ORDER · BO5</p>
              <h2 id="rules-dialog-title">今日比赛规则确认</h2>
              <p>{{ rulesDialogEvent.event_date }} · {{ rulesDialogEvent.title }}</p>
            </div>
            <button type="button" aria-label="关闭比赛规则" @click="closeRulesDialog"><AppIcon name="close" /></button>
          </header>

          <div class="rules-dialog-body">
            <section class="captain-order">
              <div class="rules-section-title">
                <span>01</span>
                <div><small>CAPTAIN CONTINUATION</small><h3>延续上轮时的双方队长</h3></div>
              </div>
              <p v-if="stats.captainSelection" class="rule-explainer">
                如果今天沿用最近比赛日 {{ stats.captainSelection.sourceDate }} 的分队结果，上轮败方在第一局进入蓝色方并先手选英雄。
              </p>
              <p v-else class="rule-explainer">当前没有可引用的上一轮比赛结果，双方队长与第一局蓝色方需在开赛前现场确认。</p>
              <p v-if="captainRanks.winner || captainRanks.loser" class="rule-explainer">以下为本机手动切换的替补顺位预览，实际队长请在开赛前确认。</p>
              <div v-if="stats.captainSelection" class="captain-order-grid">
                <article>
                  <small>上轮胜方队长</small>
                  <b>{{ dialogCaptains?.winnerCaptain?.name || '暂无候选人' }}</b>
                  <span>守擂方</span>
                </article>
                <i>VS</i>
                <article class="blue-first">
                  <small>上轮败方队长</small>
                  <b>{{ dialogCaptains?.loserCaptain?.name || '暂无候选人' }}</b>
                  <span>首局蓝方 · 先手</span>
                </article>
              </div>
            </section>

            <section class="bo5-order">
              <div class="rules-section-title">
                <span>02</span>
                <div><small>SERIES FORMAT</small><h3>BO5 对局流程</h3></div>
              </div>
              <p class="rule-explainer">先赢 3 局的一方获胜。第一至第四局执行常规排位征召；每局结束后，败方在下一局进入蓝色方并获得先手选英雄权。</p>
              <div class="bo5-track" aria-label="BO5 对局流程">
                <div v-for="game in 4" :key="game"><small>GAME {{ game }}</small><b>排位 BP</b><span>败方下局蓝方</span></div>
                <div class="decider"><small>GAME 5</small><b>全员票决</b><span>仅 2:2 时启用</span></div>
              </div>
            </section>

            <section class="decider-order">
              <div class="rules-section-title">
                <span>03</span>
                <div><small>DECIDER VOTE</small><h3>第五局模式投票</h3></div>
              </div>
              <p class="rule-explainer">只有正式席位的 10 名选手参与投票。报名开放期间可以修改选择；报名关闭后票数锁定。</p>

              <div class="decider-options">
                <label :class="{ selected: deciderVoteChoice === 'matchmaking' }">
                  <input v-model="deciderVoteChoice" type="radio" value="matchmaking" :disabled="playerRegistration(rulesDialogEvent)?.status !== 'registered' || rulesDialogEvent.status !== 'open'" />
                  <span>方案 A</span>
                  <b>匹配模式</b>
                  <p>不进行 Ban/Pick，不限制英雄选择；双方直接自由选择英雄进入决胜局。</p>
                </label>
                <label :class="{ selected: deciderVoteChoice === 'ranked' }">
                  <input v-model="deciderVoteChoice" type="radio" value="ranked" :disabled="playerRegistration(rulesDialogEvent)?.status !== 'registered' || rulesDialogEvent.status !== 'open'" />
                  <span>方案 B · 平票默认</span>
                  <b>排位模式</b>
                  <p>第四局败方进入蓝色方并先手；Ban 位全部留空，不增加额外 Pick 限制，双方自由选择英雄。</p>
                </label>
              </div>

              <div class="tie-rule"><b>5:5 如何处理？</b><p>平票时直接采用排位模式。队长已经包含在 10 名投票选手中，不设置额外队长票，确保每人一票。</p></div>

              <div class="vote-board">
                <header><span>投票进度</span><b>{{ eventVoteSummary(rulesDialogEvent).voted_count }} / {{ eventVoteSummary(rulesDialogEvent).required_votes }}</b></header>
                <div class="vote-bars">
                  <div><span>匹配模式</span><i><b :style="{ width: `${eventVoteSummary(rulesDialogEvent).matchmaking_count / Math.max(1, eventVoteSummary(rulesDialogEvent).required_votes) * 100}%` }"></b></i><strong>{{ eventVoteSummary(rulesDialogEvent).matchmaking_count }}</strong></div>
                  <div><span>排位模式</span><i><b :style="{ width: `${eventVoteSummary(rulesDialogEvent).ranked_count / Math.max(1, eventVoteSummary(rulesDialogEvent).required_votes) * 100}%` }"></b></i><strong>{{ eventVoteSummary(rulesDialogEvent).ranked_count }}</strong></div>
                </div>
                <p v-if="eventVoteSummary(rulesDialogEvent).status === 'decided'" class="vote-decision">
                  决胜局采用：<b>{{ deciderModeLabel(eventVoteSummary(rulesDialogEvent).result) }}</b>
                  <span v-if="eventVoteSummary(rulesDialogEvent).tie_break_applied">（5:5 平票规则生效）</span>
                </p>
                <p v-else class="vote-pending">还需 {{ eventVoteSummary(rulesDialogEvent).remaining_votes }} 名正式席位选手完成投票。</p>
              </div>

              <p v-if="deciderVoteMessage" class="decider-vote-message">{{ deciderVoteMessage }}</p>
              <p v-else-if="playerRegistration(rulesDialogEvent)?.status === 'waitlist'" class="decider-vote-message">你当前在候补队列，晋级正式席位后即可投票。</p>
              <p v-else-if="!playerSession" class="decider-vote-message">登录并报名正式席位后即可参与投票。</p>
            </section>
          </div>

          <footer class="rules-dialog-actions">
            <button class="secondary" type="button" @click="closeRulesDialog">稍后再看</button>
            <button
              type="button"
              :disabled="deciderVoteLoading || !deciderVoteChoice || playerRegistration(rulesDialogEvent)?.status !== 'registered' || rulesDialogEvent.status !== 'open'"
              @click="submitDeciderVote"
            >
              {{ deciderVoteLoading ? '正在记录…' : playerRegistration(rulesDialogEvent)?.has_decider_vote ? '更新我的投票' : '确认规则并投票' }}
            </button>
          </footer>
        </section>
      </div>
    </Teleport>

    <footer class="site-footer"><span>奶茶杯 · 和朋友认真玩一场</span><p>胜负有时，快乐常在。</p><label class="upload-button"><input type="file" accept="application/json,.json" @change="loadJson" /><span>预览 JSON</span></label></footer>

    <nav class="mobile-nav" aria-label="移动端主导航">
      <button v-for="view in views.filter(item => item.id !== 'admin')" :key="view.id" :class="{ active: activeView === view.id }" type="button" @click="activeView = view.id"><AppIcon :name="view.id" /><span>{{ view.short }}</span></button>
    </nav>
  </div>
</template>
