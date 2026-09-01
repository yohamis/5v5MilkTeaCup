# 王者荣耀 5V5 奶茶杯

Vue 赛事数据台 + Laravel/SQLite 后端。支持历史数据、自动排行榜、比赛数据导入修改，以及玩家每日自助报名。

首页还会根据最近一个比赛日期的全部对局，自动推选下一场两名队长。更早日期的历史数据不会参与：

- 两位队长统一按：当日 MVP+FMVP 总次数、MVP 次数、当日平均评分排序。
- 两名队长以最近一局阵容为准，必须来自不同队伍。

## 使用

```powershell
npm install
npm run dev
```

后端首次启动：

```powershell
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan tournament:import ..\src\data\tournament.json
php artisan serve
```

前端复制根目录 `.env.example` 为 `.env.local`，设置 `VITE_API_BASE_URL`。未设置时自动继续读取静态 JSON。

玩家通过“昵称 + 4—12 位数字 PIN”登录。历史玩家首次登录会设置自己的 PIN；新玩家勾选“第一次参加”即可创建档案并报名。

网页的“赛事管理”页面可以保存管理员密钥、创建报名场次、上传完整 JSON，以及选择单场比赛编辑并保存。密钥仅保存在当前浏览器，后端仍会用 `.env` 中的 `TOURNAMENT_ADMIN_KEY` 校验每次管理请求。

对应管理员接口：

- `POST /api/admin/tournament/import`：导入完整 JSON
- `PUT /api/admin/matches/{比赛ID}`：修改单场比赛
- `DELETE /api/admin/matches/{比赛ID}`：删除比赛
- `POST /api/admin/events`：创建每日报名场次
- `PATCH /api/admin/events/{id}`：修改报名场次
- `PATCH /api/admin/players/{id}`：修改玩家

生产构建：

```powershell
npm run build
```

GitHub Pages 只能运行 Vue 静态页面，Laravel 后端需要部署到支持 PHP 8.3 且有持久磁盘的服务器。后端上线后，在 GitHub 仓库 `Settings → Secrets and variables → Actions → Variables` 新增 `VITE_API_BASE_URL`，值填后端地址（例如 `https://api.example.com`）；下一次 Actions 构建会自动接入报名和管理功能。

## 后续更新数据

启用 Laravel 后端后，通过管理 API 上传或修改比赛即可，排行榜会自动更新。`src/data/tournament.json` 继续作为初始数据和静态站点降级备份；页面顶部也可以临时载入 JSON 预览。

每场比赛的推荐格式：

```json
{
  "id": "2026-08-25-a1",
  "date": "2026-08-25",
  "round": "A1",
  "winner": "blue",
  "teams": {
    "blue": [
      {
        "name": "选手名",
        "lane": "辅助",
        "kills": 1,
        "deaths": 2,
        "assists": 10,
        "rating": 8.6,
        "mvp": false,
        "fmvp": false,
        "tea": false,
        "treat": false
      }
    ],
    "red": []
  }
}
```

- `winner` 只能是 `blue` 或 `red`。
- 每队必须 5 人，分路使用：`对抗`、`打野`、`法师`、`射手`、`辅助`。
- KDA、胜率、平均分、排行榜和实力分不写入 JSON，避免重复数据不一致。
- `tea` 表示本场喝奶茶，`treat` 表示本场请客。

## 从 Excel 转换

当前表格可用转换脚本重新生成 JSON：

```powershell
python scripts/convert_excel.py "C:\Users\zdong\Desktop\1-奶茶杯.xlsx" "src\data\tournament.json"
```

转换时会过滤 Excel 底部预留空行，并校验每场是否为蓝红双方各 5 人、胜方是否唯一。

## 实力评分

实力评分为公开的 100 分制：

- 游戏内平均评分（按 16 分满分归一化）：55%
- 胜率：20%
- MVP 率：15%
- FMVP 率：10%

公式位于 `src/lib/stats.js`，后续可以按赛事规则调整。

## 部署到 GitHub Pages

项目已经包含 `.github/workflows/deploy-pages.yml`。推送到 GitHub 的 `main` 分支后，会自动安装依赖、执行生产构建并发布 `dist`。

首次部署：

1. 在 GitHub 创建一个空仓库，例如 `milk-tea-cup`。
2. 在本项目目录执行 Git 初始化并推送到仓库。
3. 打开 GitHub 仓库的 `Settings → Pages`。
4. 在 `Build and deployment → Source` 中选择 `GitHub Actions`。
5. 打开仓库的 `Actions` 页面，等待 `Deploy to GitHub Pages` 完成。

默认访问地址通常为：

```text
https://你的GitHub用户名.github.io/仓库名/
```

以后只要更新 `src/data/tournament.json` 并推送到 `main`，网站就会自动重新部署。

## 中国大陆 CDN

如果需要中国大陆 CDN 节点，必须准备自有域名，并完成 ICP 备案。推荐架构：

```text
GitHub 仓库
  ├─ GitHub Pages：免费公开站和备用访问
  └─ GitHub Actions 将 dist 同步至 COS/OSS
        └─ 腾讯云 CDN / 阿里云 CDN：中国大陆访问域名
```

CDN 建议：

- 业务类型选择“网页小文件”或“图片小文件”。
- HTML 缓存时间设置为 5 分钟左右。
- 带哈希的 `/assets/*` 设置为 30 天或更久。
- 开启 HTTPS、HTTP/2、Gzip/Brotli。
- 每次更新后刷新 `index.html`，哈希静态资源无需强制刷新。

如果域名尚未备案，可以先使用 GitHub Pages；海外 CDN 节点不等于中国大陆 CDN，无法获得稳定的大陆节点加速。
