# Disable All Thumbnails (停用縮圖)

停用 WordPress 所有縮圖格式生成功能，優化網站空間使用並提升效能。

## 螢幕截圖

![Disable All Thumbnails 介面預覽](https://yblog.org/wp-content/uploads/2026/07/wordpress_disable_thumbnails01.webp)

| 選擇停用縮圖尺寸 | 分批刪除舊縮圖 |
| :---: | :---: |
| ![停用縮圖尺寸設定頁面](https://yblog.org/wp-content/uploads/2026/07/wordpress_disable_thumbnails.webp) | ![批次刪除現有縮圖介面](https://yblog.org/wp-content/uploads/2026/07/wordpress_disable_thumbnails02.webp) |
| 自由勾選欲停用的縮圖格式 | 分頁 AJAX 清理技術與原生進度條 |

*圖片引用自文章：[解決 WordPress 媒體庫空間膨脹：使用 Disable All Thumbnails 批次清理數十萬冗餘縮圖](https://yblog.org/2026/07/21/solving-wordpress-media/)*


## 說明

**Disable All Thumbnails** 是一款輕量且穩定的 WordPress 外掛，讓您能夠自由選擇要停用的圖片尺寸格式（包含 WordPress 原生內建尺寸，以及由其他外掛或主題所註冊的自訂尺寸）。這能有效節省主機硬碟空間、加快上傳圖片的處理速度，並縮小備份檔案。外掛亦內建安全的分頁分批（Paginated Batching）刪除引擎與進度條介面，讓您能在不停機、不逾時的情況下，一鍵清除舊有的縮圖檔案。

### 主要功能
- **自訂停用縮圖尺寸：** 提供清晰易懂的表格，讓您自由勾選想要禁用的縮圖格式。
- **支援所有尺寸格式：** 完美支援 WordPress 內建尺寸（縮圖、中尺寸、大尺寸等）以及其他外掛/主題所註冊的自訂縮圖。
- **分批刪除引擎：** 採用分頁式 AJAX 批次處理技術，搭配原生進度條 UI，輕鬆刪除幾千張圖片的縮圖而不會造成伺服器逾時（Timeout）。
- **多格式清理支援：** 自動偵測並刪除縮圖對應的 WebP (`.webp`) 與 AVIF (`.avif`) 格式檔案（由優化外掛生成）。
- **乾淨的資料庫足跡：** 完美遵循 WordPress 開發規範，並在解除安裝（Uninstall）外掛時徹底清除資料庫中的設定選項。

## 安裝步驟

1. 將 `disable-all-thumbnails` 資料夾上傳至網站的 `/wp-content/plugins/` 目錄，或直接透過 WordPress 後台的「外掛 > 安裝外掛」功能進行上傳安裝。
2. 在 WordPress 的「外掛」頁面中啟用此外掛。
3. 前往 **[設定] > [停用縮圖]** 進行配置，勾選想要停用的圖片尺寸。
4. （選用）在 **[刪除現有縮圖]** 區塊中，點擊「刪除所有縮圖」以清理之前已產生的多餘縮圖檔案。

## 常見問題

### 停用縮圖會影響網站前台的排版嗎？
如果您的佈景主題有特定區塊（例如精選圖片或網格列表）強制調用已停用的縮圖尺寸，則該區塊可能會載入原始圖片（造成載入變慢）或排版異常。建議僅勾選您確定網站主題不曾使用到的尺寸（例如未使用的原生尺寸，或已經停用之舊外掛產生的尺寸）。

### 如果我改變心意，可以重新生成縮圖嗎？
可以。只要停用此外掛，WordPress 就會恢復正常的縮圖生成功能。若您之前已刪除了縮圖檔案，可以使用如 "Regenerate Thumbnails" 等外掛重新為媒體庫圖片生成所需的縮圖。

## 相關專案

本外掛是 [Omni Webmaster & SEO Suite](https://github.com/ivanusto/omni-webmaster-seo-suite) 的六個起源專案之一——該套件整合並最佳化作者的多個獨立外掛，並額外加入 Meta Pixel 追蹤與文章數據匯出功能。

## 授權條款

本專案採用 Apache License 2.0 授權。詳情請參閱 [LICENSE](LICENSE) 檔案。
