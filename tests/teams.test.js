import assert from 'node:assert/strict'
import test from 'node:test'
import { orderedTeams, registeredPlayers, randomTeams, swapTeamSlot, validateTeamPlan } from '../src/lib/teams.js'

const players = Array.from({ length: 10 }, (_, i) => ({ id: String(i + 1), name: `报名昵称${i + 1}` }))

test('only official registrations are used, IDs distinguish duplicate names', () => {
  const result = registeredPlayers({ registrations: [
    { player_id: 1, status: 'registered', player: { name: '同名' } },
    { player_id: 2, status: 'registered', player: { name: '同名' } },
    { player_id: 1, status: 'registered', player: { name: '重复记录' } },
    { player_id: 3, status: 'waitlist', player: { name: '候补' } },
    { player_id: 4, status: 'cancelled', player: { name: '取消' } },
  ] })
  assert.deepEqual(result, [{ id: '1', name: '同名' }, { id: '2', name: '同名' }])
})

test('random split gives five unique players each and honors pinned captains', () => {
  for (let i = 0; i < 30; i++) {
    const result = randomTeams(players, { blue: '2', red: '9' })
    assert.equal(result.slots.length, 10)
    assert.equal(new Set(result.slots).size, 10)
    assert.deepEqual([...result.slots].sort(), players.map((player) => player.id).sort())
    assert.equal(result.slots[0], '2')
    assert.equal(result.slots[5], '9')
    assert.equal(validateTeamPlan(players, result.slots, result.captains), '')
  }
})

test('random captain selection and one pinned captain always produce valid lineups', () => {
  for (const captains of [{}, { blue: '3' }, { red: '6' }]) {
    const result = randomTeams(players, captains, () => 0)
    assert.equal(validateTeamPlan(players, result.slots, result.captains), '')
    assert.notEqual(result.captains.blue, result.captains.red)
    if (captains.blue) assert.equal(result.captains.blue, captains.blue)
    if (captains.red) assert.equal(result.captains.red, captains.red)
  }
})

test('invalid roster sizes, duplicate IDs, and invalid captains are rejected', () => {
  assert.throws(() => randomTeams(players.slice(0, 9)))
  assert.throws(() => randomTeams([...players, { id: '11', name: '多余' }]))
  assert.throws(() => randomTeams([...players.slice(0, 9), players[0]]))
  assert.throws(() => randomTeams(players, { blue: '1', red: '1' }))
  assert.throws(() => randomTeams(players, { blue: 'unknown' }))
})

test('manual order splits first five and last five and preserves assigned captains', () => {
  const result = orderedTeams(players)
  assert.deepEqual(result.slots, players.map((player) => player.id))
  assert.deepEqual(result.captains, { blue: '1', red: '6' })
  const pinned = orderedTeams(players, { blue: '7', red: '2' })
  assert.equal(pinned.slots[0], '7')
  assert.equal(pinned.slots[5], '2')
  assert.equal(validateTeamPlan(players, pinned.slots, pinned.captains), '')
})

test('manual moves swap existing seats, preserve uniqueness and do not mutate input', () => {
  const slots = players.map((player) => player.id)
  const swapped = swapTeamSlot(slots, 0, '9')
  assert.equal(swapped[0], '9')
  assert.equal(swapped[8], '1')
  assert.equal(new Set(swapped).size, 10)
  assert.equal(slots[0], '1')
  assert.ok(validateTeamPlan(players, swapped, { blue: '1', red: '6' }))
  assert.equal(validateTeamPlan(players, swapped, { blue: '9', red: '6' }), '')
  assert.ok(validateTeamPlan(players.slice(0, 9), slots, { blue: '1', red: '6' }))
})
