<script setup>
import { computed, ref } from 'vue'
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
]

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
            <span><b>败方规则</b> 当日 MVP+FMVP 次数 → MVP 次数 → 当日平均评分</span>
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

      <section v-else class="view">
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
    </main>

    <footer><span>奶茶杯赛事数据台</span><p>替换 <code>src/data/tournament.json</code> 后，全部榜单会在构建时自动更新。</p></footer>

    <nav class="mobile-nav" aria-label="移动端主导航">
      <button v-for="view in views" :key="view.id" :class="{ active: activeView === view.id }" type="button" @click="activeView = view.id"><span>{{ { overview: '⌂', matches: '▤', honors: '✦', players: '♙' }[view.id] }}</span>{{ view.short }}</button>
    </nav>
  </div>
</template>
