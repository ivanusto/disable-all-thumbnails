=== Disable All Thumbnails ===
Contributors: ivanlin
Tags: images, thumbnails, media, optimization, performance
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

本外掛是 Omni Webmaster & SEO Suite（同作者整合最佳化多個獨立外掛的一站式站長工具套件）的起源專案之一：https://github.com/ivanusto/omni-webmaster-seo-suite

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

= 1.3.0 =
* 修正：WordPress 7.1 仍會重新產生已停用的尺寸。有兩條路徑是向 `wp_get_missing_image_subsizes()` 詢問還缺哪些尺寸，完全略過 `intermediate_image_sizes_advanced` 濾鏡：一是行之有年的上傳後補產機制，二是 7.1 的用戶端媒體處理——瀏覽器從 REST 回應讀取缺少清單，在本機產生後再回傳。現在外掛也會過濾這份清單，兩條路徑都不會讓停用的尺寸復活。
* 修正：批次刪除現在會保留 WordPress 7.1 存放在主圖旁的伴隨檔案——`original_image`（縮放前的原始上傳檔）、`source_image`（例如 JPEG 的來源 HEIC）以及 `animated_video` / `animated_video_poster`（動態 GIF 轉出的影片與首格）。7.1 可能把同一個實體檔案登記在多個尺寸名稱下，因此某個尺寸項目可能直接指向這些檔案，刪掉就會讓附件指向不存在的檔案。現在尺寸項目照樣移除，但共用的檔案會留下。
* 修正：設定頁對內建的 1536x1536 與 2048x2048 尺寸完全不顯示尺寸數值。WordPress 是用 `add_image_size()` 註冊它們，從不建立設定頁所讀取的 `*_size_w` / `*_size_h` 選項。尺寸數值改由 `wp_get_registered_image_subsizes()` 取得，並保留核心命名所依據的固定尺寸作為後備，以因應停用後它們被移出註冊表的情況。
* 修正：沒有限制的那一軸現在顯示「auto」而非「0px」，所以 Medium Large 會顯示為 `768px × auto`——它是寬 768px、高度依比例自適應。乘號也不再顯示成 `&times;` 字面文字。
* 已針對 WordPress 7.1 測試。

= 1.2.0 =
* 修正：站點圖示（網站圖示）現在受到保護。原本「刪除所有縮圖」會走過每一個圖片附件並直接清空整個 `sizes` 中繼資料，連帶刪掉支撐 favicon、Apple touch icon 與 Windows 磚的 `site_icon-32`、`site_icon-180`、`site_icon-192`、`site_icon-270` 檔案。只要執行過一次，全站 favicon 就會失效，而且因為中繼資料已被清空，重新產生縮圖也救不回來。現在會完整跳過「目前設定為站點圖示」的那張附件；曾經當過站點圖示但已被替換的舊圖仍會照常清理，因為已經沒有任何地方引用它們。
* 修正：刪除改為逐一移除各尺寸，不再整個清空 `sizes` 陣列，受保護的尺寸得以保留，沒有可刪內容的附件也不會被無謂改寫。
* 修正：站點圖示子尺寸不再出現在設定頁、也無法被關閉；先前若關閉它，之後設定的站點圖示都會壞掉。舊版寫入的殘留設定會被忽略。
* 新增：`disable_all_thumbnails_is_protected_size` 與 `disable_all_thumbnails_skip_attachment` 兩個濾鏡，可保護其他尺寸或附件。

= 1.1.1 =
* 修正：移除對核心媒體選項（thumbnail_size_w/h）的持久性寫入。內建尺寸的停用改由 intermediate_image_sizes_advanced 濾鏡於執行期處理，停用功能後不再影響 WordPress 媒體設定。

= 1.1.0 =
* 新增：分頁 AJAX 批次刪除現有縮圖功能（避免大媒體庫逾時）
* 新增：WebP 與 AVIF 格式縮圖清理支援
* 新增：設定頁面原生進度條 UI
* 優化：移除 register_uninstall_hook 改用標準 uninstall.php 進行清理
* 優化：將 inline JS 抽離並透過 standard wp_enqueue_script 及 wp_localize_script 進行加載
* 優化：英文與繁體中文語系代碼重構

= 1.0.0 =
* 首次發布
