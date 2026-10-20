# 工作留痕记录工具 — 使用与部署说明

> 原生 PHP + MySQL，无需 Composer、无需框架。手机 / 电脑都能用。

---

## 一、功能清单

| # | 需求 | 实现情况 |
|---|---|---|
| 1 | 登录才能使用 | 账号密码登录（`password_hash` 加密存储），所有页面和接口强制鉴权 |
| 2 | 点按钮添加记录 | 桌面顶部「＋ 新增记录」、手机右下角悬浮 **＋**，点击弹出录入窗口 |
| 3 | 弹窗填写内容/时间/人员 + 上传视频录音文档图片 | 模态框录入；工作时间、标题、内容、**涉及客户（预置下拉多选 + 可手填新客户）**、相关人员、地点；附件一次可多选（图片/视频/音频/PDF/Office/压缩包），单文件最大 512MB |
| 4 | 修改 / 删除 | 列表「编辑」「删除」，服务端校验归属；删除时附件文件同步清理 |
| 5 | 全部清单导出 Excel | `导出 Excel`，支持按筛选条件导出，`.xls` 可用 Excel / WPS 直接打开 |
| 6 | 单条记录导出 PDF | 每条「PDF」按钮，A4 版式，中文字体已内嵌，浏览器内可直接另存 |
| 7 | 分享按钮生成公开链接 | 「分享」生成只读链接 `share.php?t=…`，无需登录即可看；可随时关闭（关闭后链接立即失效） |
| + | **移动端适配** | 移动优先响应式：卡片列表、全屏弹窗、44px 触控区、视频/音频自适应、筛选栏可折叠 |

---

## 二、本机（Wamp）试运行

1. 数据库：MariaDB @3307（本机默认 root 空密码）
2. 建库：`worklog`（`utf8mb4`）
3. 导入 `install.sql`
4. `config.php` 里已配好：`DB_HOST=127.0.0.1` / `DB_PORT=3307` / `DB_NAME=worklog` / `DB_USER=root` / `DB_PASS=`
5. 浏览器打开 `http://localhost/wd/`，用 **admin / admin123** 登录

命令行起临时服务器也可：
```
D:\soft\wamp64\bin\php\php7.4.9\php.exe -S 127.0.0.1:8899 -t D:\soft\wamp64\www
# 然后访问 http://127.0.0.1:8899/wd/
```

---

## 三、宝塔部署（按按钮操作）

### 步骤 1：上传代码
1. 把整个 `wd` 文件夹压缩成 `wd.zip`
2. 宝塔面板 → 左侧「文件」→ 进入网站根目录（如 `/www/wwwroot/你的域名`）
3. 点上方「上传」→ 选择 `wd.zip` → 上传
4. 上传完，右键 `wd.zip` →「解压」→ 解压到当前目录
5. 若希望域名根目录直接访问（`http://域名/`），把 `wd` 里面的**全部文件**剪切到网站根目录，删掉空的 `wd` 文件夹

### 步骤 2：建数据库
1. 左侧「数据库」→「添加数据库」
2. 数据库名：`worklog`（或任意），用户名、密码记下来
3. 字符集选 `utf8mb4`

### 步骤 3：导入表结构
1. 数据库列表里点该库右侧「管理」→ 打开 phpMyAdmin
2. 顶部「导入」→「选择文件」→ 选 `install.sql` → 页面底部「执行」
3. 看到「导入成功」即可（3 张表：`users` / `records` / `attachments`）

> `install.sql` **只建表不建库**，导入到哪个库就建在哪个库，可重复导入。

### 步骤 4：改配置
1. 「文件」→ 找到 `config.php` → 右键「编辑」
2. 修改这 4 行：
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'worklog');      // 改成你建的库名
   define('DB_USER', 'worklog');      // 改成宝塔给的用户名
   define('DB_PASS', '你的密码');      // 改成宝塔给的密码
   ```
3. 保存

### 步骤 5：放开上传体积（重要，否则视频传不上去）

> **为什么必须手动改**：`upload_max_filesize` 和 `post_max_size` 属于 PHP 的
> **PHP_INI_PERDIR** 级别，程序里用 `ini_set()` 改**无效**（只能在 php.ini /
> `.user.ini` / `.htaccess` 里设，然后重启）。所以这一步不做，手机照片（3~8MB）
> 和视频都会传不上去，而记录本身还能保存——表现为"保存成功但附件没了"。
>
> 改完可以去 **设置页** 看「服务器上传限制」那张卡片，确认「实际可用」已经变大。

**① PHP 设置**
「软件商店」→ 找到 PHP（建议 7.4 或 8.x）→「设置」→「配置修改」：
- `upload_max_filesize` = `512M`
- `post_max_size` = `1024M`
- `max_execution_time` = `600`
- `max_input_time` = `600`
保存 → 「服务」→「重启」

**② Nginx 请求体上限**
「网站」→ 你的站点 →「设置」→「配置文件」，在最外层 `server {` 里面加一行：
```nginx
client_max_body_size 1024m;
```
保存。

### 步骤 6：禁止上传目录执行脚本（安全）

「网站」→ 站点 →「设置」→「配置文件」，在 `server { }` 内加：
```nginx
# 上传目录禁止执行任何脚本
location ~* ^/uploads/.*\.(php|php5|phtml|phar)$ {
    deny all;
}
```
保存后点「重载配置」。

> 程序本身已经做了扩展名白名单 + 随机文件名，这层是双保险。

### 步骤 7：开启 HTTPS（建议）
「网站」→ 站点 →「SSL」→「Let's Encrypt」→ 申请 → 开启「强制 HTTPS」

### 步骤 8：首次登录
浏览器打开 `http://你的域名/`，账号 **admin** / 密码 **admin123**
登录后立刻去右上角「设置」→「修改密码」。

---

## 四、日常使用

- **新增**：桌面点顶部「＋ 新增记录」；手机点右下角圆形 **＋**
- **快捷入口**：`index.php?new=1` 打开页面即弹新增框；`index.php?edit=5` 直接编辑第 5 条
- **搜索**：顶部搜索框支持内容、相关人员、地点模糊搜索
- **看详情**：列表默认只显示 3 行摘要，**点记录标题**（标题右侧有 › 提示）即可进入该条记录的详情页，
  完整内容、全部附件（图片大图 / 视频播放 / 录音 / 文档下载）都在详情页里；点缩略图效果相同
- **筛选**：点「筛选」按日期区间过滤
- **附件**：手机上录完视频/录音，点附件框选「相册/文件」即可上传
- **涉及客户**：点输入框弹出预设客户列表（创维、长虹、茂佳、BOE、TPV、惠科…），点一下即选中，可连续多选；
  要填列表里没有的客户，直接在框里打字，回车即添加（下次它会自动出现在候选里）。
  已选客户显示为蓝色标签，点标签上的 × 可移除。一条记录最多选 12 个。
- **分享**：列表里点「分享」→ 复制链接发给别人 → 对方无需登录即可只读查看（图片视频可直接看，文档可下载）
- **关闭分享**：分享弹窗里点「关闭分享」，或重新点「分享」→「关闭分享」

---

## 四·五、从旧版升级（v1.0 → v1.1，新增「涉及客户」字段）

如果之前已经部署过（库里有旧表），**重新导入 install.sql 是没用的**（`CREATE TABLE IF NOT EXISTS`
对已存在的表不生效），必须补字段。两种方式任选：

**方式一（推荐，最省事）**
1. 上传 `upgrade.php` 到网站根目录
2. 浏览器访问 `http://你的域名/upgrade.php`
3. 看到「✅ 已新增字段 records.customer」即成功
4. **立刻删除 upgrade.php**

**方式二（phpMyAdmin）**
宝塔「数据库」→ 该库「管理」→ 顶部「SQL」→ 粘贴执行：
```sql
ALTER TABLE `records`
  ADD COLUMN `customer` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '涉及客户'
  AFTER `related_people`;
```

> 忘记这一步的症状：新增/编辑记录时提示「保存失败」，或页面报
> `Unknown column 'customer' in 'field list'`。

## 五、备份

需要备份两样东西：

1. **数据库**：宝塔「数据库」→ 该库 →「备份」
2. **附件目录**：`uploads/` 整个文件夹（按 `年/月/` 分目录，文件名随机）

> 只备份数据库不备份 uploads，恢复后附件会丢；只备份 uploads 不备份数据库，记录会丢。

---

## 六、常见问题

**Q：上传视频提示失败 / 一直卡住？**
A：按「步骤 5」调大 `upload_max_filesize`、`post_max_size`、`client_max_body_size`，并重启 PHP。

**Q：登录后马上又跳回登录页？**
A：服务器时间不对或 `session` 目录不可写。宝塔「软件商店」→ PHP → 设置 → 配置修改，检查 `session.save_path` 是否为可写目录（一般用默认即可），并确认 PHP 已重启。

**Q：PDF 打开是空白 / 中文乱码？**
A：`lib/tcpdf/fonts/stsongstdlight.php` 被删了。重新上传整个 `lib/tcpdf/fonts` 目录。中文字体是 CID 字体，**不需要**在服务器装任何字体。

**Q：导出 Excel 打开乱码？**
A：本程序导出的是 Excel 2003 XML（`.xls`），Excel、WPS 都能直接打开。若被 Excel 提示"格式与扩展名不一致"，点「是」即可正常显示。

**Q：想改默认账号名？**
A：phpMyAdmin 打开 `users` 表，把 `username` 改成你要的；密码请在页面「设置」里改（不要直接改数据库里的哈希）。

**Q：客户下拉里的选项怎么增删？**
A：打开 `config.php`，改这个数组即可（改完刷新页面生效）：
```php
$GLOBALS['CUSTOMER_PRESET'] = array(
    '创维', '长虹', '茂佳', 'BOE', 'TPV', '惠科',
    'TCL', '海信', '康佳', '小米', '三星', 'LG',
    '索尼', '飞利浦', '冠捷',
);
```
另外，你在页面里**手工输入过的客户名会自动进候选**（从历史记录里读），不用改代码。
想限制一条记录最多选几个，改 `define('CUSTOMER_MAX_PER_RECORD', 12);`。

**Q：怎么改成多人各自独立？**
A：本版本按需求做的是**单账号**。表结构已预留 `records.user_id`，如需扩展为多用户，可在 `users` 表加账号，列表页加 `WHERE user_id = 当前用户` 即可（`can_edit_record()` 已按归属校验）。

---

## 七、目录结构

```
config.php            ← 数据库配置、上传限制（部署只需改这里）
install.sql           ← 建表脚本（只建表，不建库）
upgrade.php           ← 旧库升级（用完即删）
login.php  logout.php
index.php             ← 列表 + 筛选 + 新增/编辑弹窗 + 分享弹窗
view.php              ← 登录态单条详情
share.php             ← 公开分享页（免登录只读）
settings.php          ← 账号设置 / 改密码 / 数据概览
file.php              ← 登录态附件输出（支持 Range，视频可拖进度条）
share_file.php        ← 分享态附件输出
export_excel.php      ← 导出 .xls
export_pdf.php        ← 导出单条 PDF（TCPDF）
api/record_save.php   ← 新增 / 编辑（含上传）
api/record_get.php    ← 读取单条（编辑回填）
api/record_delete.php ← 删除（含磁盘清理）
api/share_toggle.php  ← 开启 / 关闭分享
lib/db.php  func.php  auth.php  layout.php
lib/upload.php        ← 上传校验与落盘
lib/serve.php         ← 附件输出（Range / 缩略图）
lib/render_record.php ← 详情渲染（view 与 share 共用）
lib/tcpdf/            ← TCPDF 6.7.5（精简版，含中文 CID 字体）
assets/style.css      ← 移动优先样式
assets/app.js         ← 弹窗、AJAX、Toast、复制
uploads/              ← 附件（年/月 分目录）+ .htaccess 禁执行
```

---

版本 1.0 · 单账号版
