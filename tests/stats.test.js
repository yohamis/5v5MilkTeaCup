import assert from 'node:assert/strict'
import test from 'node:test'
import { calculateStats, selectCaptainsByRank } from '../src/lib/stats.js'

const player = (name, extra = {}) => ({ name, lane: '辅助', rating: 8, kills: 2, deaths: 2, assists: 4, mvp: false, fmvp: false, tea: false, treat: false, ...extra })
const match = (date, round, blue = [], red = []) => ({ id: `${date}-${round}`, date, round, winner: 'blue', teams: { blue, red } })
const selection = (blue, red) => calculateStats({ matches: [match('2026-10-17', 'A1', blue, red)] }).captainSelection

test('captains use latest match date only and prefer total MVP plus FMVP', () => {
  const result = calculateStats({ matches: [
    match('2026-10-16', 'A1', [player('历史冠军', { tea: true, mvp: true })], [player('历史对手', { treat: true })]),
    match('2026-10-17', 'A1', [player('甲', { tea: true, mvp: true }), player('乙', { tea: true })], [player('丙', { treat: true })]),
    match('2026-10-17', 'A2', [player('甲'), player('乙', { fmvp: true })], [player('丙')]),
    match('2026-10-17', 'A3', [player('甲'), player('乙', { mvp: true })], [player('丙')]),
  ] }).captainSelection
  assert.equal(result.sourceDate, '2026-10-17')
  assert.equal(result.sourceMatchCount, 3)
  assert.equal(result.winnerCaptain.name, '乙')
  assert.equal(result.winnerCandidates.some((item) => item.name === '历史冠军'), false)
})

test('equal total honors compare average rating directly, not MVP count', () => {
  const result = selection([
    player('高均分', { tea: true, fmvp: true, rating: 14 }),
    player('高MVP', { tea: true, mvp: true, rating: 7 }),
  ], [player('对手', { treat: true })])
  assert.equal(result.winnerCaptain.name, '高均分')
})

test('equal honors and MVP use actual unrounded daily rating average', () => {
  const result = selection([
    player('阿甲', { tea: true, rating: 8.01 }),
    player('阿乙', { tea: true, rating: 8.04 }),
  ], [player('对手', { treat: true })])
  assert.equal(result.winnerCaptain.name, '阿乙')
})

test('manual succession uses original teams and does not require matching registration names', () => {
  const result = selection([
    player('蓝首选', { tea: true, mvp: true }),
    player('蓝替补', { tea: true, rating: 7 }),
  ], [player('红首选', { treat: true, fmvp: true }), player('红替补', { treat: true, rating: 7 })])
  const available = selectCaptainsByRank(result, { winner: 1, loser: 1 })
  assert.equal(available.winnerCaptain.name, '蓝替补')
  assert.equal(available.winnerCaptain.successionRank, 2)
  assert.equal(available.loserCaptain.name, '红替补')
  assert.equal(available.loserCaptain.successionRank, 2)
  const oneSideOnly = selectCaptainsByRank(result, { winner: 1 })
  assert.equal(oneSideOnly.loserCaptain.name, '红首选')
  assert.equal(selectCaptainsByRank(result).winnerCaptain.name, '蓝首选')
  assert.equal(selectCaptainsByRank(result, { winner: 99 }).winnerCaptain.name, '蓝替补')
  assert.equal(selectCaptainsByRank(result, { winner: -1 }).winnerCaptain.name, '蓝首选')
})

test('overlapping tea/treat flags cannot assign a player to both candidate teams', () => {
  const result = selection([player('重复标记', { tea: true, treat: true })], [player('红方', { treat: true })])
  assert.equal(result.loserCandidates.some((item) => item.name === '重复标记'), false)
  assert.notEqual(result.winnerCaptain.name, result.loserCaptain.name)
})

test('latest-day highlights use daily power score and total deaths with all ties included', () => {
  const result = calculateStats({ matches: [
    match('2026-10-16', 'A1', [player('历史', { rating: 0, deaths: 99 })], []),
    match('2026-10-17', 'A1', [player('当日低分甲', { rating: 1, deaths: 5 })], [player('当日低分乙', { rating: 1, deaths: 5 })]),
    match('2026-10-17', 'A2', [player('当日低分乙', { rating: 1, deaths: 5 })], [player('当日低分甲', { rating: 1, deaths: 5 })]),
  ] })
  assert.equal(result.latestRoundHighlights.sourceMatchCount, 2)
  assert.deepEqual(result.latestRoundHighlights.growthStars.map((item) => item.name).sort(), ['当日低分乙', '当日低分甲'].sort())
  assert.equal(result.latestRoundHighlights.mostDeaths, 10)
  assert.equal(result.latestRoundHighlights.teamfightExplorers.length, 2)
  assert.equal(result.latestRoundHighlights.growthStars.some((item) => item.name === '历史'), false)
  assert.equal(result.captainSelection, null)
})

test('highlights are empty with no matches and zero-death day has no death label winner', () => {
  assert.equal(calculateStats({ matches: [] }).latestRoundHighlights, null)
  assert.equal(selectCaptainsByRank(null), null)
  const result = calculateStats({ matches: [match('2026-10-17', 'A1', [player('零阵亡', { deaths: 0 })], [])] })
  assert.deepEqual(result.latestRoundHighlights.teamfightExplorers, [])
  assert.equal(calculateStats({ matches: [match('2026-10-17', 'A1')] }).latestRoundHighlights, null)
})

test('complete sorting ties keep the same captain order when input order changes', () => {
  const candidates = [player('乙', { tea: true }), player('甲', { tea: true })]
  const otherTeam = [player('对手', { treat: true })]
  const first = selection(candidates, otherTeam).winnerCandidates.map((item) => item.name)
  const reversed = selection([...candidates].reverse(), otherTeam).winnerCandidates.map((item) => item.name)
  assert.deepEqual(first, reversed)
})
