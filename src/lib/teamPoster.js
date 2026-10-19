// 直接绘制 PNG，不依赖截图服务、外部字体或上传接口。
export async function createTeamPoster({ title, date, note, mode, blue, red }) {
  await document.fonts.ready
  const canvas = document.createElement('canvas')
  canvas.width = 1080
  canvas.height = 1440
  const ctx = canvas.getContext('2d')
  const font = '"PingFang SC", "Microsoft YaHei", sans-serif'
  const box = (x, y, w, h, radius, color) => {
    ctx.fillStyle = color
    ctx.beginPath()
    ctx.roundRect(x, y, w, h, radius)
    ctx.fill()
  }
  const text = (value, x, y, size, color, weight = 400) => {
    ctx.font = `${weight} ${size}px ${font}`
    ctx.fillStyle = color
    ctx.fillText(String(value), x, y)
  }
  const wrap = (value, width, size, limit = 3) => {
    ctx.font = `600 ${size}px ${font}`
    const segments = typeof Intl.Segmenter === 'function'
      ? [...new Intl.Segmenter('zh', { granularity: 'grapheme' }).segment(value)].map((item) => item.segment)
      : Array.from(value)
    const lines = ['']
    for (const char of segments) {
      if (ctx.measureText(lines.at(-1) + char).width > width) {
        if (lines.length === limit) {
          let tail = lines.at(-1)
          while (ctx.measureText(tail + '…').width > width) tail = Array.from(tail).slice(0, -1).join('')
          lines[lines.length - 1] = tail + '…'
          break
        }
        lines.push(char)
      } else lines[lines.length - 1] += char
    }
    return lines
  }
  box(0, 0, 1080, 1440, 0, '#f1f4fc')
  box(48, 48, 984, 240, 34, '#293759')
  text('MILK TEA CUP  /  5V5', 86, 104, 21, '#bcc7e8', 600)
  let titleSize = 43
  let titleLines = wrap(title || '奶茶杯 · 今日对阵', 870, titleSize, 2)
  while (titleLines.at(-1).endsWith('…') && titleSize > 27) titleLines = wrap(title, 870, titleSize -= 2, 2)
  titleLines.forEach((line, i) => text(line, 86, 173 + i * 54, titleSize, '#ffffff', 700))
  text(`${date}   ·   BO5 好友赛`, 86, 262, 22, '#d9e0f4')
  const drawTeam = (players, x, blueSide) => {
    const color = blueSide ? '#5263e8' : '#c27f61'
    const pale = blueSide ? '#edf0ff' : '#fff1e7'
    box(x, 320, 476, 872, 28, '#ffffff')
    box(x + 20, 340, 436, 82, 18, pale)
    text(blueSide ? '蓝方战队' : '红方战队', x + 42, 392, 29, color, 700)
    text('5 / 5', x + 350, 390, 22, color, 600)
    players.forEach((player, i) => {
      const y = 445 + i * 143
      if (player.captain) box(x + 19, y - 3, 438, 133, 17, pale)
      text(String(i + 1).padStart(2, '0'), x + 35, y + 31, 18, '#9da8be', 600)
      if (player.captain) {
        box(x + 323, y + 9, 103, 30, 8, color)
        text('队长', x + 353, y + 31, 17, '#ffffff', 600)
      }
      let nameSize = 28
      let lines = wrap(player.name, 352, nameSize, 3)
      while (lines.at(-1).endsWith('…') && nameSize > 20) lines = wrap(player.name, 352, nameSize -= 2, 3)
      lines.forEach((line, lineIndex) => text(line, x + 70, y + 62 + lineIndex * 31, nameSize, '#202943', 600))
    })
  }
  drawTeam(blue, 48, true)
  drawTeam(red, 556, false)
  text(mode, 62, 1245, 21, '#66769a', 600)
  wrap(note || '请双方选手准时到场，开赛前确认阵容与比赛规则。', 930, 22, 3).forEach((line, i) => text(line, 62, 1295 + i * 34, 22, '#7b89a6'))
  text('胜负有时，快乐常在。', 62, 1392, 21, '#9ca8c0')
  text('奶茶杯 · 组队发布', 805, 1392, 19, '#9ca8c0')
  return new Promise((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('图片生成失败，请重试')), 'image/png'))
}
