export const TEAM_MODES = Object.freeze([
  { id: 'honor', name: '荣耀传承', icon: 'crown', tag: '高手带队', color: '#626ee7', summary: '双方各出一位，实力接棒。', rule: '按上一比赛日的双方原队伍，分别选出 MVP＋FMVP 总次数最多的选手；次数相同，再比较当日平均评分。' },
  { id: 'underdog', name: '逆风领航', icon: 'magic', tag: '换你主场', color: '#dd9068', summary: '给上轮蓄力的朋友，一个主场。', rule: '以上一比赛日最后一局的 10 位选手为候选，取当日平均评分最低的两位；相同则阵亡次数多者优先，第一顺位带蓝方，第二顺位带红方。' },
  { id: 'random', name: '随机组队', icon: 'shuffle', tag: '打散重逢', color: '#439b8a', summary: '熟悉的十个人，全新的队友。', rule: '将当日正式报名的 10 位选手随机打乱，分为蓝、红两队，每队 5 人。具体名单可到报名页的组队工具生成。' },
])

// Sector centres are 0, 120 and 240 degrees clockwise from the top pointer.
export function createModeSpin(currentRotation = 0, random = Math.random) {
  if (!Number.isFinite(currentRotation)) throw new TypeError('Invalid rotation')
  const pick = random()
  const offset = random()
  if (![pick, offset].every((value) => Number.isFinite(value) && value >= 0 && value < 1)) {
    throw new RangeError('Random values must be in [0, 1)')
  }
  const index = Math.floor(pick * TEAM_MODES.length)
  const jitter = (offset - 0.5) * 64
  const target = ((-index * 120 - jitter) % 360 + 360) % 360
  const normalized = ((currentRotation % 360) + 360) % 360
  return { index, rotation: currentRotation + 1800 + ((target - normalized + 360) % 360) }
}
