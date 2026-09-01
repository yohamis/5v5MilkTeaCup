<script setup>
import { computed, onMounted, ref } from 'vue'
import initialData from './data/tournament.json'
import { calculateStats, formatDate, formatPercent, LANES, validateData } from './lib/stats'

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
const signupMessage = ref('')
const signupLoading = ref(false)
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
  if (playerSession.value?.token) headers.Authorization = `Bearer ${playerSession.value.token}`
  const response = await fetch(`${apiBase}${path}`, { ...options, headers })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(body.message || '请求失败')
  return body
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
    playerSession.value = session
    localStorage.setItem('milkTeaPlayerSession', JSON.stringify(session))
    signupMessage.value = `欢迎，${session.player.name}`
  } catch (error) { signupMessage.value = error.message } finally { signupLoading.value = false }
}

async function registerEvent(event) {
  signupLoading.value = true
  try {
    const result = await api(`/api/events/${event.id}/register`, { method: 'POST', body: '{}' })
    signupMessage.value = result.registration.status === 'registered' ? '报名成功' : '正赛已满，已进入候补'
    await loadBackend()
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

async function logoutPlayer() {
  try { await api('/api/auth/logout', { method: 'POST' }) } catch { /* 本地仍然退出 */ }
  playerSession.value = null
  localStorage.removeItem('milkTeaPlayerSession')
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

onMounted(loadBackend)

function teamKills(team) {
  return team.reduce((sum, player) => sum + Number(player.kills || 0), 0)
}

function openPlayer(name) {
  selectedPlayerName.value = name
  activeView.value = 'players'
  window.scrollTo({ top: 0, behavior: 'smooth' })
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
        <span class="brand-mark"><i></i><i></i><i></i></span>
        <span><b>奶茶杯</b><small>KING'S BATTLE ARENA</small></span>
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

      <label class="upload-button">
        <input type="file" accept="application/json,.json" @change="loadJson" />
        <span>载入新赛季 JSON</span>
      </label>
    </header>

    <main>
      <div v-if="uploadError" class="notice notice-error">{{ uploadError }}</div>
      <div v-if="warnings.length" class="notice">
        数据校验提示：{{ warnings.join('；') }}
      </div>

      <section v-if="activeView === 'overview'" class="view view-overview">
        <div class="hero">
          <div class="hero-copy">
            <p class="eyebrow">{{ tournament.competition.season }} · 荣耀战场</p>
            <h1>峡谷争锋，<br /><em>王者登榜。</em></h1>
            <p class="hero-intro">
              每一次出征都会写进战绩。分路榜、荣誉榜、选手实力与下场队长，全部由真实对局自动推演。最新战报截止
              {{ stats.overview.latestDate }}。
            </p>
            <div class="hero-actions">
              <button class="primary-button" type="button" @click="activeView = 'matches'">查看最近对战</button>
              <button class="text-button" type="button" @click="activeView = 'players'">打开选手评分 →</button>
            </div>
          </div>

          <div class="cup-scoreboard" aria-label="赛事数据摘要">
            <div class="cup-lid"></div>
            <div class="cup-body">
              <div class="cup-label">
                <small>荣耀赛季</small>
                <strong>{{ stats.overview.matchCount }}</strong>
                <span>场峡谷征战</span>
              </div>
              <div class="pearls" aria-hidden="true">
                <i v-for="n in 10" :key="n"></i>
              </div>
            </div>
            <div class="cup-meta">
              <span><b>{{ stats.overview.playerCount }}</b> 位选手</span>
              <span><b>{{ stats.overview.recordCount }}</b> 条评分</span>
            </div>
          </div>
        </div>

        <section v-if="stats.captainSelection" class="captain-section">
          <div class="captain-heading">
            <div>
              <p class="eyebrow">NEXT BATTLE · CAPTAIN DRAFT</p>
              <h2>下场双队长</h2>
            </div>
            <p>
              只统计最近比赛日 {{ stats.captainSelection.sourceDate }} 的全部
              {{ stats.captainSelection.sourceMatchCount }} 场对局，历史日期不参与。
            </p>
          </div>

          <div class="captain-arena">
            <article class="captain-card winner-captain" data-side="blue">
              <div class="captain-crown">♛</div>
              <div class="captain-copy">
                <small>胜方队长 · 当日荣誉次数优先</small>
                <h3>{{ stats.captainSelection.winnerCaptain.name }}</h3>
                <p>当日 MVP+FMVP {{ stats.captainSelection.winnerCaptain.mvp + stats.captainSelection.winnerCaptain.fmvp }} 次，MVP {{ stats.captainSelection.winnerCaptain.mvp }} 次</p>
              </div>
              <div class="captain-stats">
                <span><small>荣誉次数</small><b>{{ stats.captainSelection.winnerCaptain.mvp + stats.captainSelection.winnerCaptain.fmvp }}</b></span>
                <span><small>当日 MVP</small><b>{{ stats.captainSelection.winnerCaptain.mvp }}</b></span>
                <span><small>当日均分</small><b>{{ stats.captainSelection.winnerCaptain.avgRating.toFixed(1) }}</b></span>
              </div>
              <button type="button" @click="openPlayer(stats.captainSelection.winnerCaptain.name)">查看队长战绩 →</button>
            </article>

            <div class="captain-versus" aria-hidden="true"><span>荣</span><b>VS</b><span>耀</span></div>

            <article class="captain-card loser-captain" data-side="red">
              <div class="captain-crown">♜</div>
              <div class="captain-copy">
                <small>败方队长 · 当日荣誉次数优先</small>
                <h3>{{ stats.captainSelection.loserCaptain.name }}</h3>
                <p>当日 MVP+FMVP {{ stats.captainSelection.loserCaptain.mvp + stats.captainSelection.loserCaptain.fmvp }} 次，MVP {{ stats.captainSelection.loserCaptain.mvp }} 次</p>
              </div>
              <div class="captain-stats">
                <span><small>荣誉次数</small><b>{{ stats.captainSelection.loserCaptain.mvp + stats.captainSelection.loserCaptain.fmvp }}</b></span>
                <span><small>当日 MVP</small><b>{{ stats.captainSelection.loserCaptain.mvp }}</b></span>
                <span><small>当日均分</small><b>{{ stats.captainSelection.loserCaptain.avgRating.toFixed(1) }}</b></span>
              </div>
              <button type="button" @click="openPlayer(stats.captainSelection.loserCaptain.name)">查看队长战绩 →</button>
            </article>
          </div>

          <div class="captain-rules">
            <span><b>胜方规则</b> 当日 MVP+FMVP 次数 → MVP 次数 → 当日平均评分</span>
            <span><b>败方规则</b> 同一排序规则，且必须来自最近一局的另一支队伍</span>
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
              <span class="lane-symbol">{{ { 对抗: '⚔', 打野: '◆', 法师: '✦', 射手: '⌁', 辅助: '✚' }[lane] }}</span>
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
            <button class="text-button" type="button" @click="activeView = 'matches'">全部 {{ stats.overview.matchCount }} 场 →</button>
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
            <header><div><small>竞技荣誉 · TOP 10</small><h2>高光时刻</h2></div><span class="medal">✦</span></header>
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
            <header><div><small>奶茶江湖 · TOP 10</small><h2>{{ teaBoardType === 'tea' ? '品茶大师' : '大善人' }}</h2></div><span class="medal tea">●</span></header>
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
              @click="selectedPlayerName = player.name"
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
        <div class="page-title">
          <div><p class="eyebrow">DAILY MATCH REGISTRATION</p><h1>每日比赛报名</h1></div>
          <p>每天自动开放当天场次；已有选手直接登录，新玩家创建档案后即可报名，无需等待管理员建场。</p>
        </div>

        <div v-if="signupMessage" class="notice">{{ signupMessage }}</div>
        <div v-if="!apiBase" class="signup-empty">
          <b>报名服务尚未部署</b><p>当前统计看板仍可正常使用。部署 Laravel 后端并配置 VITE_API_BASE_URL 后，这里会自动启用。</p>
        </div>
        <template v-else>
          <article v-if="!playerSession" class="signup-login">
            <header><small>PLAYER ACCESS</small><h2>{{ loginForm.new_player ? '创建新玩家' : '玩家登录' }}</h2></header>
            <label>玩家昵称<input v-model.trim="loginForm.name" maxlength="50" placeholder="例如：Jack" /></label>
            <label>报名 PIN<input v-model="loginForm.pin" inputmode="numeric" maxlength="12" type="password" placeholder="4—12 位数字" /></label>
            <label class="signup-check"><input v-model="loginForm.new_player" type="checkbox" /> 我是第一次参加，创建新玩家档案</label>
            <button type="button" :disabled="signupLoading" @click="loginPlayer">{{ signupLoading ? '处理中…' : loginForm.new_player ? '创建并登录' : '登录' }}</button>
          </article>
          <div v-else class="signup-session"><span>当前玩家：<b>{{ playerSession.player.name }}</b></span><button type="button" @click="logoutPlayer">退出</button></div>

          <div class="event-grid">
            <article v-for="event in signupEvents" :key="event.id" class="event-card">
              <header><small>{{ event.status === 'open' ? '报名中' : '报名已关闭' }}</small><h2>{{ event.title }}</h2><time>{{ event.event_date }}</time></header>
              <div class="event-capacity"><span><b>{{ event.registrations.filter(item => item.status === 'registered').length }}</b> / {{ event.capacity }} 人</span><small>候补 {{ event.registrations.filter(item => item.status === 'waitlist').length }} 人</small></div>
              <div class="event-roster"><span v-for="item in event.registrations" :key="item.id" :class="item.status">{{ item.player.name }}</span></div>
              <div v-if="playerSession" class="event-actions">
                <button v-if="!event.registrations.some(item => item.player_id === playerSession.player.id)" type="button" :disabled="signupLoading || event.status !== 'open'" @click="registerEvent(event)">立即报名</button>
                <button v-else class="cancel" type="button" :disabled="signupLoading" @click="cancelRegistration(event)">取消报名</button>
              </div>
            </article>
            <div v-if="!signupEvents.length" class="signup-empty"><b>今日场次正在准备</b><p>请稍后刷新报名页面。</p></div>
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

    <footer><span>奶茶杯赛事数据台</span><p>比赛数据由 Laravel API 自动同步；未配置后端时使用内置 JSON。</p></footer>

    <nav class="mobile-nav" aria-label="移动端主导航">
      <button v-for="view in views" :key="view.id" :class="{ active: activeView === view.id }" type="button" @click="activeView = view.id"><span>{{ { overview: '⌂', matches: '▤', honors: '✦', players: '♙', signup: '✓', admin: '⚙' }[view.id] }}</span>{{ view.short }}</button>
    </nav>
  </div>
</template>
