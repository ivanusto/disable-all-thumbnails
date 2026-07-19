=== Disable All Thumbnails ===
Contributors: ivanlin
Tags: images, thumbnails, media, optimization, performance
Requires at least: 5.3
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.1.0
License: Apache-2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0
Donate link: https://yblog.org

停用 WordPress 所有縮圖格式生成功能，優化網站空間使用並提升效能。

== Description ==

Disable All Thumbnails 是一個簡單但功能強大的 WordPress 外掛，讓你可以：

* 選擇性停用任何 WordPress 縮圖尺寸
* 分批安全地刪除現有的縮圖文件，避免伺服器逾時
* 支援清理 WebP 與 AVIF 縮圖副檔名檔案
* 節省伺服器儲存空間
* 加快圖片上傳速度
* 減少伺服器負載

主要功能：

1. 可自由選擇要停用的縮圖尺寸
2. 支援所有 WordPress 預設縮圖尺寸
3. 支援其他外掛或主題添加的自訂縮圖尺寸
4. 提供分頁 AJAX 批次刪除現有縮圖功能與進度條介面
5. 遵循 WordPress 標準，排除 inline scripts 改為 enqueue script 方式加載

== Installation ==

1. 在 WordPress 後台上傳並啟用外掛
2. 前往 [設定] > [停用縮圖] 頁面
3. 勾選想要停用的縮圖尺寸
4. 點擊 [儲存變更]

== Frequently Asked Questions ==

= 停用縮圖會影響我的網站外觀嗎？ =

如果你的主題需要特定尺寸的縮圖，停用該尺寸可能會導致載入原始圖片（影響效能）或前台排版問題，建議保留該尺寸的生成功能。

= 刪除縮圖後可以還原嗎？ =

不可以。刪除縮圖是永久性的操作。不過你可以透過停用外掛並使用 "Regenerate Thumbnails" 等外掛重新為媒體庫圖片生成縮圖。

= 這個外掛會影響網站效能嗎？ =

這個外掛會提升網站效能，因為：
* 減少圖片上傳時的處理時間
* 減少伺服器儲存空間使用
* 減少備份大小

== Changelog ==

= 1.1.0 =
* 新增：分頁 AJAX 批次刪除現有縮圖功能（避免大媒體庫逾時）
* 新增：WebP 與 AVIF 格式縮圖清理支援
* 新增：設定頁面原生進度條 UI
* 優化：移除 register_uninstall_hook 改用標準 uninstall.php 進行清理
* 優化：將 inline JS 抽離並透過 standard wp_enqueue_script 及 wp_localize_script 進行加載
* 優化：英文與繁體中文語系代碼重構

= 1.0.0 =
* 首次發布
