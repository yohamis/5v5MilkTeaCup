export const LANES = ['对抗', '打野', '法师', '射手', '辅助']

const round = (value, digits = 1) => Number(Number(value || 0).toFixed(digits))
const ratio = (count, total) => (total ? count / total : 0)

export function flattenMatches(matches) {
  return matches.flatMap((match) =>
    ['blue', 'red'].flatMap((side) =>
      (match.teams?.[side] || []).map((player) => ({
        ...player,
        matchId: match.id,
        date: match.date,
        round: match.round,
        side,
        win: match.winner === side,
      })),
    ),
  )
}

export function validateData(data) {
  const warnings = []
  const ids = new Set()
  for (const match of data.matches || []) {
    if (ids.has(match.id)) warnings.push(`${match.id}：比赛 ID 重复`)
    ids.add(match.id)
    const blue = match.teams?.blue?.length || 0
    const red = match.teams?.red?.length || 0
    if (blue !== 5 || red !== 5) warnings.push(`${match.id}：蓝队 ${blue} 人 / 红队 ${red} 人`)
    if (!['blue', 'red'].includes(match.winner)) warnings.push(`${match.id}：缺少合法胜方`)
  }
  return warnings
}

function createBucket(name) {
  return {
    name,
    matches: 0,
    wins: 0,
    ratingSum: 0,
    kills: 0,
    deaths: 0,
    assists: 0,
    mvp: 0,
    fmvp: 0,
    tea: 0,
    treat: 0,
    lanes: new Map(),
  }
}

function accumulate(bucket, row, includeLane = true) {
  bucket.matches += 1
  bucket.wins += Number(row.win)
  bucket.ratingSum += Number(row.rating) || 0
  bucket.kills += Number(row.kills) || 0
  bucket.deaths += Number(row.deaths) || 0
  bucket.assists += Number(row.assists) || 0
  bucket.mvp += Number(Boolean(row.mvp))
  bucket.fmvp += Number(Boolean(row.fmvp))
  bucket.tea += Number(Boolean(row.tea))
  bucket.treat += Number(Boolean(row.treat))
  if (includeLane) {
    if (!bucket.lanes.has(row.lane)) bucket.lanes.set(row.lane, createBucket(row.lane))
    accumulate(bucket.lanes.get(row.lane), row, false)
  }
}

function finalize(bucket) {
  const winRate = ratio(bucket.wins, bucket.matches)
  const avgRating = ratio(bucket.ratingSum, bucket.matches)
  const mvpRate = ratio(bucket.mvp, bucket.matches)
  const fmvpRate = ratio(bucket.fmvp, bucket.matches)
  // 公开、稳定的 100 分制：局内评分 55%，胜率 20%，MVP 率 15%，FMVP 率 10%。
  const powerScore = Math.min(
    100,
    (avgRating / 16) * 55 + winRate * 20 + mvpRate * 15 + fmvpRate * 10,
  )
  return {
    ...bucket,
    winRate,
    avgRating: round(avgRating),
    avgKda: round((bucket.kills + bucket.assists) / Math.max(1, bucket.deaths), 2),
    powerScore: round(powerScore, 2),
  }
}

export function calculateStats(data) {
  const matches = [...(data.matches || [])].sort((a, b) =>
    `${b.date}-${b.round}`.localeCompare(`${a.date}-${a.round}`, 'zh-CN', { numeric: true }),
  )
  const rows = flattenMatches(matches)
  const playerMap = new Map()

  for (const row of rows) {
    if (!playerMap.has(row.name)) playerMap.set(row.name, createBucket(row.name))
    accumulate(playerMap.get(row.name), row)
  }

  const players = [...playerMap.values()]
    .map((bucket) => {
      const player = finalize(bucket)
      player.laneStats = [...bucket.lanes.values()]
        .map(finalize)
        .sort((a, b) => b.avgRating - a.avgRating || b.matches - a.matches)
      return player
    })
    .sort((a, b) => b.powerScore - a.powerScore || b.avgRating - a.avgRating)
    .map((player, index) => ({ ...player, rank: index + 1 }))

  const laneLeaders = Object.fromEntries(
    LANES.map((lane) => [
      lane,
      players
        .map((player) => {
          const laneStat = player.laneStats.find((item) => item.name === lane)
          return laneStat ? { ...laneStat, player: player.name } : null
        })
        .filter(Boolean)
        .sort((a, b) => b.avgRating - a.avgRating || b.matches - a.matches)
        .slice(0, 3)
        .map((item, index) => ({ ...item, rank: index + 1 })),
    ]),
  )

  const leaderboard = (field) =>
    [...players]
      .sort((a, b) => b[field] - a[field] || b.avgRating - a.avgRating)
      .slice(0, 10)
      .map((player, index) => ({ ...player, boardRank: index + 1 }))

  const honors = [...players]
    .map((player) => ({ ...player, honorTotal: player.mvp + player.fmvp }))
    .sort((a, b) => b.honorTotal - a.honorTotal || b.fmvp - a.fmvp || b.mvp - a.mvp)
    .slice(0, 10)
    .map((player, index) => ({ ...player, boardRank: index + 1 }))

  const latestMatch = matches[0]
  let captainSelection = null
  if (latestMatch) {
    const sourceDate = latestMatch.date
    const dateRows = rows.filter((row) => row.date === sourceDate)
    const datePlayerMap = new Map()
    for (const row of dateRows) {
      if (!datePlayerMap.has(row.name)) datePlayerMap.set(row.name, createBucket(row.name))
      accumulate(datePlayerMap.get(row.name), row, false)
    }
    const datePlayers = [...datePlayerMap.values()].map(finalize)

    // 队长统一规则：当日 MVP+FMVP 总次数、MVP 次数、当日平均评分。
    const winnerCandidates = [...datePlayers].sort(
      (a, b) =>
        b.mvp + b.fmvp - (a.mvp + a.fmvp) || b.mvp - a.mvp || b.avgRating - a.avgRating,
    )
    const loserPool = datePlayers.filter((player) => player.name !== winnerCandidates[0]?.name)
    const loserCandidates = [...loserPool].sort(
      (a, b) =>
        b.mvp + b.fmvp - (a.mvp + a.fmvp) || b.mvp - a.mvp || b.avgRating - a.avgRating,
    )

    captainSelection = {
      sourceDate,
      sourceMatchCount: matches.filter((match) => match.date === sourceDate).length,
      winnerCaptain: winnerCandidates[0],
      loserCaptain: loserCandidates[0],
      winnerCandidates,
      loserCandidates,
    }
  }

  return {
    matches,
    rows,
    players,
    laneLeaders,
    honors,
    mvpLeaders: leaderboard('mvp'),
    fmvpLeaders: leaderboard('fmvp'),
    teaLeaders: leaderboard('tea'),
    treatLeaders: leaderboard('treat'),
    captainSelection,
    overview: {
      matchCount: matches.length,
      playerCount: players.length,
      recordCount: rows.length,
      latestDate: matches[0]?.date || '—',
    },
  }
}

export const formatPercent = (value) => `${round(value * 100, 1)}%`
export const formatDate = (date) =>
  new Intl.DateTimeFormat('zh-CN', { month: 'short', day: 'numeric', weekday: 'short' }).format(
    new Date(`${date}T00:00:00`),
  )
