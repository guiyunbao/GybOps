# 运营知识库：Docker 初始化与手动拉取

适用于已用 `docker-compose.prod.yml` 部署的线上实例。用于创建桂运宝运营知识分区，并首次（或补跑）从官方信源 / Dify 写入内容。

本地环境的知识库正文**不会**自动同步到线上。每个环境各自初始化、各自调度抓取。

相关编排与通用部署见 [生产 Docker 部署](../deployment/DEPLOYMENT.md)。

## 1. 知识库与更新方式

| 知识库 | 日常更新 | 说明 |
|--------|----------|------|
| 政策合规 | 工作日 07:10（上海时区） | 政府网、发改委、交通部、广西交通厅列表稿 |
| 区域通道 | 工作日 07:10 | 广西政府、南宁市政府要闻 / 发布会 |
| 行情数据 | 每周一 07:40 | 中国物流与采购网运价指数稿 |
| 运营摘要 | 工作日 06:30 | Dify 只读摘要；需配置密钥 |
| 外贸东盟 | 不自动抓取 | 海关 / 东盟官网当前无法稳定入库 |
| 对外科普 | 不靠媒体定时 | 种子正文 + 人工维护 / 修复命令 |
| 内部话术 | 人工 | 公司素材精选后入库 |
| 合规红线 | 静态规则 | 禁止入库字段说明，不抓媒体 |

抓取行为摘要：

- 每个信源单次最多写入约 2 条新稿。
- 已记录过的原文 URL 会跳过（按 `url_hash`）。
- 含手机号、订单号等敏感字段的内容会被拦截，不写入知识库。

## 2. 前置条件

在服务器上进入部署目录（含 `.env.prod` 与 `docker-compose.prod.yml`），设置命令前缀：

```bash
export COMPOSE_PROD='docker compose --env-file .env.prod -f docker-compose.prod.yml'
# 需要 root 时：
# export COMPOSE_PROD='sudo docker compose --env-file .env.prod -f docker-compose.prod.yml'
```

确认常驻服务已启动：

```bash
$COMPOSE_PROD ps
```

至少应包含：

- `app`（php-fpm）
- `scheduler`（`php artisan schedule:work`，负责定时入队）
- `queue`（通用队列，官方信源 / 运营摘要任务会进入此处）
- `knowledge-queue`（知识库切片与向量化）

若缺少 `scheduler` 或队列，初始化后的定时更新不会自动执行。可按部署文档补启：

```bash
$COMPOSE_PROD up -d --remove-orphans app web queue knowledge-queue scheduler
```

## 3. 配置运营摘要（可选，但建议）

仅「运营摘要」依赖 Dify。在 `.env.prod` 中配置：

```env
GEOFLOW_DIFY_BASE_URL=https://agent.guiyunbao.cn
GEOFLOW_DIFY_API_KEY=你的密钥
GEOFLOW_OPERATIONS_REGION=广西
GEOFLOW_OPERATIONS_CARGO_TYPE=大宗货物
```

- `GEOFLOW_DIFY_API_KEY` 留空时，官方信源仍可拉取；运营摘要命令会失败或无法写入。
- 首次成功同步后，密钥会加密写入数据库连接记录。
- 修改 `.env.prod` 后需让 `app`（及会读该环境变量的相关容器）重新加载配置，例如：

```bash
$COMPOSE_PROD up -d --force-recreate app scheduler queue
$COMPOSE_PROD exec app php artisan config:clear
```

具体以你方缓存与蓝绿流程为准；若使用 `config:cache`，改完后需重新生成缓存。

## 4. 初始化：创建八个运营知识分区

幂等命令。首次会创建带系统绑定的知识库并写入种子正文；已存在则跳过创建。

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:repair-training-knowledge
```

成功后，后台「知识库」列表应能看到：对外科普、内部话术、运营摘要、合规红线、政策合规、行情数据、区域通道、外贸东盟。

## 5. 手动拉取（首次验收或补跑）

`--sync` 表示在当前进程直接执行，不依赖队列，适合首次验收。日常仍由 `scheduler` 按计划入队执行。

### 5.1 工作日官方信源（政策合规、区域通道等）

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-official-sources --cadence=weekday --sync
```

成功时输出类似：`官方信源 weekday：写入 N，跳过 M，拦截 K`。部分信源若仅有说明、未接入抓取，会以注释行打出。

### 5.2 每周官方信源（行情数据）

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-official-sources --cadence=weekly --sync
```

### 5.3 运营摘要（需 Dify）

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-operations-brief --sync
```

可选参数：

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-operations-brief \
  --sync \
  --request-id=operations-brief-验收-$(date +%F) \
  --region=广西 \
  --cargo=大宗货物
```

同一 `request-id` 只保留一个队列实例；验收时建议使用带日期的唯一请求号。

### 5.4 不带 `--sync`（交给队列）

容器网络正常且 `queue` 在跑时，可省略 `--sync`，命令只负责入队：

```bash
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-official-sources --cadence=weekday
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-operations-brief
```

随后用队列日志或后台知识库正文确认是否写入。

## 6. 验收清单

1. 后台打开「政策合规」「区域通道」，正文中出现带「信源 / 原文 / 抓取」的段落。
2. 若已跑 weekly，「行情数据」中出现运价指数相关摘录。
3. 若已配置 Dify，「运营摘要」中出现带请求号与时间窗的摘要。
4. 「外贸东盟」可只有分区说明，无强制要求有新稿。
5. 「合规红线」「内部话术」不依赖本次媒体拉取。
6. 保存知识库后若提示切片延迟，确认 `knowledge-queue` 在跑，必要时在知识库列表点「更新切片」。

## 7. 定时任务对照（线上自动跑）

`scheduler` 容器内已注册（上海时区）：

| 命令 | 计划 |
|------|------|
| `geoflow:sync-operations-brief` | 工作日 06:30 |
| `geoflow:sync-official-sources --cadence=weekday` | 工作日 07:10 |
| `geoflow:sync-official-sources --cadence=weekly` | 每周一 07:40 |
| `geoflow:operations-review` | 每周一 08:00 |

初始化并首次手动拉取成功后，无需再从本机拷贝知识库；保持 `scheduler` 与 `queue` 常驻即可。

## 8. 常见问题

| 现象 | 处理 |
|------|------|
| `官方信源` 全部 `failed` / `unavailable` | 检查容器出网、DNS、目标站点是否可访问；部分站点可能临时拦截爬虫 |
| 写入为 0，跳过很多 | 多为 URL 已抓过；属正常。换日或新稿出现后再跑 |
| 运营摘要失败 | 检查 `GEOFLOW_DIFY_*`、Dify 应用是否可对话、密钥是否有效 |
| 后台看不到新分区 | 重新执行第 4 节初始化；确认连的是线上数据库而非本机 |
| 切片长期不同步 | 确认 `knowledge-queue` 运行；查看 knowledge 队列失败日志 |
| 想把本机已抓正文搬到线上 | 无内置一键同步；在线上后台编辑，或按受控运维流程迁移，勿直接覆盖生产库 |

## 9. 推荐执行顺序（复制即用）

```bash
export COMPOSE_PROD='docker compose --env-file .env.prod -f docker-compose.prod.yml'

$COMPOSE_PROD ps

# 已配置 Dify 并改过 .env.prod 时，按需 recreate / 清配置缓存后再继续

$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:repair-training-knowledge
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-official-sources --cadence=weekday --sync
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-official-sources --cadence=weekly --sync
$COMPOSE_PROD run --rm --no-deps app php artisan geoflow:sync-operations-brief --sync
```

完成后在管理后台核对第 6 节清单。
