import json
import sys
from collections import defaultdict
from datetime import datetime
from pathlib import Path

import pandas as pd


CHECKED = "☑"
SIDE_MAP = {"蓝队": "blue", "红队": "red"}


def as_number(value, integer=False):
    if pd.isna(value):
        return 0
    number = float(str(value).strip())
    return int(number) if integer else round(number, 2)


def checked(value):
    return str(value).strip() == CHECKED


def main():
    if len(sys.argv) != 3:
        raise SystemExit("用法: python convert_excel.py <输入.xlsx> <输出.json>")

    source = Path(sys.argv[1])
    target = Path(sys.argv[2])
    frame = pd.read_excel(source, sheet_name="比赛数据")
    frame = frame[frame["日期"].notna() & frame["玩家"].notna()].copy()
    corrections = []

    # 源表中该行夹在 2026-08-24 A1 的红队数据内；修正后两场均恢复为 10 人。
    anomaly = (
        (frame["日期"] == pd.Timestamp("2026-08-21"))
        & (frame["场次"] == "A1")
        & (frame["玩家"] == "Jack")
        & (frame["队伍"] == "红队")
        & (frame["分路"] == "打野")
        & (pd.to_numeric(frame["游戏内评分"], errors="coerce") == 8.1)
    )
    if anomaly.sum() == 1:
        frame.loc[anomaly, "日期"] = pd.Timestamp("2026-08-24")
        corrections.append(
            {
                "type": "source-date-correction",
                "player": "Jack",
                "from": "2026-08-21/A1",
                "to": "2026-08-24/A1",
                "reason": "该行位于 8 月 24 日 A1 红队阵容中；修正后两场人数均为 10 人",
            }
        )

    grouped = defaultdict(list)
    for _, row in frame.iterrows():
        date = pd.Timestamp(row["日期"]).strftime("%Y-%m-%d")
        grouped[(date, str(row["场次"]).strip())].append(row)

    matches = []
    warnings = []
    for (date, round_name), rows in sorted(grouped.items()):
        teams = {"blue": [], "red": []}
        winners = set()
        for row in rows:
            side = SIDE_MAP[str(row["队伍"]).strip()]
            if str(row["胜负"]).strip() == "胜利":
                winners.add(side)
            teams[side].append(
                {
                    "name": str(row["玩家"]).strip(),
                    "lane": str(row["分路"]).strip(),
                    "kills": as_number(row["击杀"], True),
                    "deaths": as_number(row["阵亡"], True),
                    "assists": as_number(row["助攻"], True),
                    "rating": as_number(row["游戏内评分"]),
                    "mvp": checked(row["MVP"]),
                    "fmvp": checked(row["FMVP"]),
                    "tea": checked(row["品茶"]),
                    "treat": checked(row["善人"]),
                }
            )

        match_id = f"{date}-{round_name.lower()}"
        if len(rows) != 10:
            warnings.append(f"{match_id} 共有 {len(rows)} 名选手，应为 10 名")
        if len(teams["blue"]) != 5 or len(teams["red"]) != 5:
            warnings.append(
                f"{match_id} 队伍人数异常：蓝队 {len(teams['blue'])}，红队 {len(teams['red'])}"
            )
        if len(winners) != 1:
            warnings.append(f"{match_id} 胜方数据不唯一：{sorted(winners)}")

        matches.append(
            {
                "id": match_id,
                "date": date,
                "round": round_name,
                "winner": next(iter(winners), None),
                "teams": teams,
            }
        )

    payload = {
        "schemaVersion": 1,
        "competition": {
            "name": "王者荣耀 5V5 奶茶杯",
            "shortName": "奶茶杯",
            "season": "2026 夏季赛",
        },
        "source": {
            "file": source.name,
            "generatedAt": datetime.now().astimezone().isoformat(timespec="seconds"),
            "corrections": corrections,
            "warnings": warnings,
        },
        "matches": matches,
    }
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"已生成 {target}：{len(matches)} 场，{len(frame)} 条选手记录，{len(warnings)} 条警告")


if __name__ == "__main__":
    main()
