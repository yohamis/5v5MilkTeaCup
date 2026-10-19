export function registeredPlayers(event) {
  const seen = new Set()
  return (event.registrations || []).filter((item) => item.status === 'registered').flatMap((item) => {
    const rawId = item.player_id ?? item.player?.id
    if (rawId == null || !item.player?.name) return []
    const id = String(rawId)
    if (seen.has(id)) return []
    seen.add(id)
    return [{ id, name: item.player.name }]
  })
}

export function randomTeams(players, captains = {}, random = Math.random) {
  const ids = players.map((player) => player.id)
  if (ids.length !== 10 || new Set(ids).size !== 10) throw new Error('请选择 10 位不同的正式报名选手')
  const blueId = captains.blue || ''
  const redId = captains.red || ''
  if (blueId && redId && blueId === redId) throw new Error('两队队长不能是同一人')
  if ([blueId, redId].some((id) => id && !ids.includes(id))) throw new Error('队长必须在本次选定的 10 人名单中')
  const pool = ids.filter((id) => id !== blueId && id !== redId)
  for (let i = pool.length - 1; i > 0; i--) {
    const j = Math.floor(random() * (i + 1))
    ;[pool[i], pool[j]] = [pool[j], pool[i]]
  }
  const blueCaptain = blueId || pool.shift()
  const redCaptain = redId || pool.shift()
  return { slots: [blueCaptain, ...pool.slice(0, 4), redCaptain, ...pool.slice(4)], captains: { blue: blueCaptain, red: redCaptain } }
}

export function swapTeamSlot(slots, index, id) {
  if (index < 0 || index >= slots.length || !slots.includes(id)) return [...slots]
  const next = [...slots]
  const other = next.indexOf(id)
  ;[next[index], next[other]] = [next[other], next[index]]
  return next
}

export function orderedTeams(players, captains = {}) {
  const ids = players.map((player) => player.id)
  const blue = captains.blue || ids.find((id) => id !== captains.red)
  const red = captains.red || [ids[5], ...ids].find((id) => id && id !== blue)
  return randomTeams(players, { blue, red }, () => 0.999999)
}

export function validateTeamPlan(players, slots, captains) {
  const allowed = new Set(players.map((player) => player.id))
  if (slots.length !== 10 || new Set(slots).size !== 10 || slots.some((id) => !allowed.has(id))) return '请先配置两队各 5 人，选手不能重复'
  if (!slots.slice(0, 5).includes(captains.blue) || !slots.slice(5).includes(captains.red)) return '请为两队各选择一名本队队长'
  return ''
}
