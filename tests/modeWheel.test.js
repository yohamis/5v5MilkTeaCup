import assert from 'node:assert/strict'
import test from 'node:test'
import { createModeSpin, TEAM_MODES } from '../src/lib/modeWheel.js'

test('the wheel has exactly the three requested modes', () => {
  assert.deepEqual(TEAM_MODES.map((mode) => mode.id), ['honor', 'underdog', 'random'])
})

test('equal intervals select each of the three modes, including interval boundaries', () => {
  for (const [value, expected] of [[0, 0], [1 / 3 - 1e-8, 0], [1 / 3, 1], [2 / 3 - 1e-8, 1], [2 / 3, 2], [1 - 1e-8, 2]]) {
    assert.equal(createModeSpin(0, () => value).index, expected)
  }
})

test('the pointer always lands safely inside the selected sector after repeated spins', () => {
  let rotation = 0
  for (let run = 0; run < 200; run++) {
    for (let index = 0; index < 3; index++) {
      for (const jitter of [0, .5, .999999]) {
        const values = [(index + .5) / 3, jitter]
        const plan = createModeSpin(rotation, () => values.shift())
        const pointerAngle = ((-plan.rotation % 360) + 360) % 360
        assert.equal(Math.floor(((pointerAngle + 60) % 360) / 120), plan.index)
        assert.ok(plan.rotation - rotation >= 1800)
        assert.ok(plan.rotation - rotation < 2160)
        rotation = plan.rotation
      }
    }
  }
})

test('invalid rotation and random samples are rejected', () => {
  assert.throws(() => createModeSpin(Infinity), TypeError)
  for (const value of [-.1, 1, NaN, Infinity]) assert.throws(() => createModeSpin(0, () => value), RangeError)
})
