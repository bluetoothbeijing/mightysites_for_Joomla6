# MightySites for Joomla 5/6（v4.0.0）

 MightySites 多站点组件 + 系统插件从 Joomla 3 移植到 **Joomla 5 / Joomla 6**（要求 PHP 8.1+）。
原始 J3 代码未做任何改动，全部新代码位于本 `joomla6/` 目录。

## 包内容

| 文件 | 说明 |
|---|---|
| `com_mightysites.zip` | 后台多站点管理组件（安装包） |
| `plg_mightysites.zip` | System - MightySites 系统插件（安装包） |

## 主要改造点（相对 J3 原版）

- **全面命名空间化**：组件使用 `AlterBrains\Component\MightySites`（`services/provider.php` + MVC 工厂），插件使用 `AlterBrains\Plugin\System\MightySites\Extension\MightySites`（实现 `SubscriberInterface`）。不再依赖 J 前缀旧类，**无需**开启"Behaviour - Backward Compatibility"兼容插件。
- **数据库驱动层适配 Joomla 4+**：`JDatabaseDriver` → `Joomla\Database\Mysqli\MysqliDriver` / `Mysql\MysqlDriver`（`pdomysql` 自动映射为 `mysql`）；`JDatabaseMightysites` 通过 DI 容器替换 `DatabaseInterface` 实现，配置对象从 `$options['mighty']` 显式传入，**避免** bootstrap 期间 `Factory::getConfig()` 循环依赖。
- **事件系统适配 Joomla 5/6**（重点，均按官方 5.x 源码逐一核对）：
  - `onUserLogin` → 接收 `LoginEvent`，用 `$event->getArgument('subject')` 取认证响应（注意：J5 起 legacy 解包后参数顺序是 `options` 在前，与 J3 相反，不能用旧式签名）；
  - `onUserLogout` → 接收 `LogoutEvent`；
  - `onAfterCleanModuleList` → 接收 `AfterCleanModuleListEvent`，通过 `$event->updateModules()` 写回过滤后的模块列表（Joomla 的 ModuleHelper 会读回该参数）；
  - `onApplicationBeforeSave` / `onApplicationAfterSave` → 对应 com_config 的 `BeforeSaveConfigurationEvent` / `AfterSaveConfigurationEvent`；
  - `registerEvent` 注册的 `onContentPrepare` 闭包改为接收 Event 对象（用 `getArgument('context')` / `getArgument('subject')`）。
- **单点登录重新设计**：Joomla 5 的认证响应**不再包含明文密码**，原"传密码"方案已不可行。新版改为 **HMAC-SHA256 签名的可信登录令牌**（不含密码），详见下文。
- FTP 层移除（改 chmod）；`$sql{$l}` → `$sql[$l]`（PHP 8）；`Factory::getCache()` 调用加 `method_exists` 守卫。

## 全新安装

1. 备份全站（文件 + 数据库）。
2. 后台"系统 → 安装 → 扩展"依次安装 `com_mightysites.zip` 与 `plg_mightysites.zip`。
3. 启用 System - MightySites 插件（安装脚本会自动把插件 ordering 调到 `-99980`，即第一个加载，请勿改动）。
4. 组件首次安装时会在站点根目录创建 `configuration/` 目录并 patch `configuration.php`（改为 3 行 bootstrap 引导）。此后进入"组件 → MightySites → 站点"添加站点即可，各域名的独立配置保存在站点根目录的 **`configuration_<域名>.php`** 文件中。
5. 如需按域名分目录，可使用"虚拟目录"（symlink）方式，服务器需支持 symlink。

## 从 Joomla 3 MightySites 迁移

> 前提：站点已升级到 Joomla 5/6，旧的 J3 版 MightySites 已停止工作。

1. **备份**：完整备份站点；单独保留 `configuration.php` 与根目录所有 `configuration_*.php`（各站点配置文件）。
2. **卸载 J3 旧版**组件与插件。J3 版卸载会把 `configuration.php` 还原为原始文件——请确认还原后的文件可直接在 Joomla 6 下运行（即 Joomla 升级时生成的最新 configuration.php）。
3. 依次安装本目录的 `com_mightysites.zip`、`plg_mightysites.zip` 并启用插件。
4. **迁移旧站点配置**：根目录旧的 `configuration_<域名>.php` 文件格式基本兼容（`class JConfig extends JConfig<id>` + `mighty_*` 属性）。注意事项：
   - 每个文件的类声明是 `class JConfig<编号> ...`，编号对应新版组件的站点记录 ID，建议在新版后台重新保存一遍各站点，让组件自动吸收/规范化（保存时会把 configuration.php 的当前值合并进来）；
   - `mighty_hideplugins`、`mighty_sdomains` 等属性原样支持；
   - 如旧文件中写死了 `$dbtype = 'pdomysql'`，新版运行时会自动映射，无需手工修改。
5. **重新生成前缀替换规则**：在每个站点上点"表替换"重新保存一次，新版内置了全部核心表 + 120 余个第三方扩展的前缀规则（含 K2、Fabrik、HikaShop、JoomShopping、VirtueMart 等）。
6. 在 Global Configuration 里保存一次全局配置，触发 `onApplicationAfterSave` 自愈（新版会在每次保存全局配置后自动把配置"吸收"回 `configuration_*.php`，防止 com_config 覆盖 `configuration.php`）。

## 站点配置文件缺失（J3 迁移最常见的遗留问题）

Joomla 3 版把每个站点的配置保存在 `configuration_<域名>.php`（根目录），迁移后这些文件经常没被一起拷过来，症状是：

- 站点列表整页刷出多条 `Configuration file ... doesn't exist!`；
- 该域名前台实际落到了 `default.php`，也就是**加载主站配置**（显示的是主站内容）。

处理顺序（v4.0.1 起）：

1. **优先找回旧文件**（凭据完整、最省事）。在服务器上搜索：
   ```bash
   find /var/www -maxdepth 4 -name "configuration_*.php"
   ```
   找到的 `configuration_xxx_com.php` 复制到 `components/com_mightysites/configuration/`（或站点根目录），属主 `www-data:www-data`、权限 `0644`。
2. **找不到时用工具栏按钮**：站点列表 → 工具栏 **"Rebuild missing configuration files"**。它以「当前站点的实时配置」为模板，套用**站点记录里保存的数据库名 / 表前缀**（`#__mightysites.db` / `.dbprefix`）为每个缺失站点生成配置文件。
   - 生成后请逐个打开站点核对 `db` / `dbprefix`；
   - 若某站点用**独立数据库账号**，在该站点编辑页改成它自己的账号再保存一次即可。
3. **单个站点**也可以直接打开编辑页保存：配置缺失时表单会用实时配置预填账号，`db`/`dbprefix` 取自站点记录，保存即生成文件。

> 自动重建只对**当前访问的域名**生效（其它域名的数据库凭据无法凭空知道）。所以后台提示里若是 `domain_mismatch`（域名不匹配），说明不是权限问题——用上面的按钮或编辑页处理。

## 单点登录（新版机制）

- **签名方式**：`HMAC-SHA256(payload, secret + User-Agent)`，payload 为 Base64(JSON)，只包含 `username`、`remember`、`time`，**不再传输密码**。
- **有效性**：60 秒内有效，且绑定发起端浏览器 User-Agent。
- **secret 约定**：发起站在站点配置的"单点登录域名"里为每个目标域名填写的 secret，应填写**目标站点 `configuration.php` 中的 `secret` 值**（即目标站的 Joomla 全局密钥）。这样接收站用自己的 secret 即可验签。
- **接收端**：验签通过后由插件直接建立会话（按用户名加载未封禁用户、写入 session 与 `#__session` 表）。这属于"可信登录"，请务必保证各站点 `secret` 不泄露、站点间通信走 HTTPS。
- **退出同步**：任一站点退出，其它站点通过 `mighty_logout` 触发销毁会话。
- 与 J3 不同，此机制**不经过认证插件**（J5 已无法从插件内拿到明文密码）。如站点启用了 MFA，单点登录将跳过 MFA 校验，请自行评估安全策略。

## 已知限制与注意事项

- 需要 PHP 8.1+；Joomla 5.0+（已在 Joomla 6.2 API 上核对过事件/数据库/会话层）。
- 插件必须保持 **ordering = -99980**（第一个 system 插件加载），否则"隐藏插件/隐藏菜单项"等功能时序失效。
- 菜单项隐藏依赖反射访问 `SiteMenu::_items/_default/_active`，模板切换反射 `SiteApplication::$template`——Joomla 大版本升级后如失败会静默降级（不报错但功能失效）。
- 内容分类守卫、YooTheme 样式切换等功能依赖对应组件的事件参数结构（`ContentPrepareEvent` 等），第三方组件自定义的 view 不受保护。
- 站点根目录的 `configuration_*.php` 包含敏感信息，务必确认服务器配置已阻止 web 直接访问（如 Apache `<FilesMatch>` 或 nginx location 规则）。
- 数据库"创建/检查/复制"工具在共享主机上可能因权限不足失败，属正常降级。

## 后续功能升级建议

1. **自动同步**：利用 Joomla 6 调度任务（Scheduled Tasks）定期同步各站点配置/用户/内容（替代 J3 时代的 cron 方案）。
2. **更新服务器**：为组件/插件配置 Joomla 更新服务器，实现一键升级。
3. **Cookie 安全**：单点登录相关 Cookie（`mightylogout` 等）增加 `Secure`、`SameSite=Lax` 属性。
4. **后台单点登录**：管理员后台跨站跳转目前用 tmp 文件令牌，可改为与前台一致的 HMAC 令牌（免落盘）。
5. **数据库驱动扩展**：支持 PostgreSQL / MariaDB 专属特性（当前以 MySQL 为主）。
6. **站点健康检查**：后台仪表盘显示各域名配置文件可写性、patch 状态、secret 一致性检查。
7. **API 化**：提供 JSON API 供外部系统查询站点列表与健康状态。
8. **中文语言包**：当前仅英文语言文件，可补充 zh-CN。
