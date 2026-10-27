# Fairboard 全面 UI／UX 重設計規格

日期：2026-10-27

## 1. 目的

Fairboard 是給學生社團、校園組織、社群主辦者與一般參加者使用的活動發現、預約、宣傳與現場管理平台。重設計的目的不是換色，而是讓每種角色能快速完成自己的主要任務：參加者找到並預約活動、Creator 建立並管理活動、Staff 協助獲指派的活動、Admin 審核與管理平台。

產品應保留 PHP 8、MySQL、原生 HTML/CSS/JavaScript 的輕量架構，以低流量、手機優先和易於團隊維護為原則。

## 2. 使用者與成功條件

### User（參加者）

- 從首頁到完成預約不超過三個主要決策步驟。
- 在列表與詳情頁都能理解日期、地點、費用標籤、剩餘名額及候補狀況。
- 已預約、候補中、已簽到等狀態清楚且不互相矛盾。
- 手機寬度下主要行動按鈕易於觸控，不需橫向捲動。

### Creator（活動建立者）

- 可以用分段但不中斷的表單建立活動，清楚知道必填欄位、預覽內容與提交審核後會發生什麼。
- 儀表板能立即看見活動狀態、預約率、剩餘名額、候補人數與下一步操作。
- 只能操作自己的活動。

### Staff

- 只看見 Admin 指派的 Creator 所屬活動。
- 可編輯獲指派活動的內容欄位、處理海報與現場簽到，但不能取得活動所有權或角色管理能力。
- 不提供公開 Staff 個人頁面。

### Admin

- 以待處理工作為中心：活動審核、學校電郵驗證、Staff 指派、帳戶啟用／停用。
- 不替 Creator 撰寫活動內容、不預約活動、不收取票款。

## 3. 設計方向

設計語言以「校園公告欄與入場票券」為概念，而不是通用 SaaS 模板。使用暖白紙張背景、深墨藍文字、珊瑚紅作主要行動色、青綠作成功／可用狀態、琥珀色作候補／待審核狀態。卡片可使用輕微錯位的色塊、日期票根和細線分隔，形成 Fairboard 自己的辨識度。

字體優先使用能涵蓋英文與繁體中文的系統字體組合；標題採緊湊粗體，內文維持至少 16px。避免漸層、浮誇陰影、藥丸形按鈕氾濫和純裝飾大圖。

參考成熟平台的模式而不複製品牌：

- Eventbrite：活動搜尋、日期／分類篩選、清晰的主行動。
- Luma：內容優先的活動詳情、簡潔 RSVP 狀態與主辦者資訊。
- Meetup：社群／主辦者脈絡、即將舉行與過往活動的分離。

Fairboard 的差異是低資源宣傳工具、候補補位上限、校園組織費用標籤與四角色工作分離。

## 4. 全站資訊架構

### 公開區

- Explore events
- Event details
- Log in / Register

### User 工作區

- My activity：Upcoming、Waitlist、Past 三個狀態分組
- Booking confirmation / QR

### Creator 工作區

- Overview
- Events
- Create event
- Attendees / Check-in
- Promotion kit（分享連結、QR、列印海報、低墨版本）

### Staff 工作區

- Assigned events
- Edit assigned event
- Promotion kit
- Attendees / Check-in

### Admin 工作區

- Review queue
- Accounts
- Staff assignments
- Recent decisions

## 5. 核心頁面

### 首頁／活動探索

- 頂部不是自動輪播，而是一個明確主題區與搜尋框；自動輪播會搶注意力，也缺少暫停機制。
- 提供關鍵字、日期與分類篩選，以及「低數據列表」切換。
- 活動卡片顯示日期票根、標題、地點、Creator／組織、費用標籤和名額狀態。
- 所有卡片保持一致資訊順序；名額緊張時用文字配合顏色，不只靠紅綠判斷。

### 活動詳情與預約

- 桌面版為內容與黏性預約卡雙欄；手機版預約行動置底且不遮內容。
- 明確區分 Available、Waitlist open、Booked、Waiting、Past／Unavailable。
- 顯示完整時間、位置、主辦單位、活動說明、費用標籤、剩餘名額與候補人數。
- 未登入者點擊 Join 後登入，再安全返回原活動。
- 成功頁顯示確認狀態、QR、日期地點、取消入口與下一步，而不只顯示一句成功訊息。

### 建立／編輯活動

- 頁首說明目前是 Create 或 Edit，並顯示儲存結果：Draft 或 Submit for review。
- 表單分為 Basics、Date & place、Capacity、Promotion、Review。
- 支援標題、說明、日期時間、地點、容量、分類、海報、社交連結與狀態。
- 內嵌驗證訊息貼近欄位；送出錯誤後保留輸入。
- Creator 編輯自己的活動；Staff 使用獨立的指派活動路徑與伺服器端授權，不能連到只允許 Creator 的頁面。

### Creator 儀表板

- 第一屏顯示 Upcoming、Pending review、Total bookings、Waitlisted 四項摘要。
- 活動依需處理、即將舉行、過往／取消分組，而不是單一倒序列表。
- 每張活動卡只有一個主要行動，其餘放入清晰的次要操作列。
- Poster 與 AI image 使用不同名稱，不得兩個按鈕都叫 Print poster。

### Staff 頁

- 顯示指派來源、Creator、活動狀態與允許的工作。
- Edit、Promotion、Attendees、Check-in 入口必須與後端授權一致。
- 沒有指派時提供清楚的空狀態與聯絡 Admin 提示。

### Admin 頁

- 以分頁或區塊切開 Review、Accounts、Assignments、History，避免三張大型表格直接堆疊。
- 審核卡包含足夠活動摘要與查看詳情入口，Reject／Remove 要求確認。
- Accounts 支援啟用／停用；Assignments 支援 Staff 對 Creator 的指派。
- 學校電郵驗證顯示證據與目前方案，不只提供孤立按鈕。

## 6. Worklog 完成範圍

### Week 3

1. Admin 可驗證學校電郵。
2. 公開活動頁顯示名額，成功預約後名額原子性減少。
3. 取消預約依 FIFO 將位置交給候補者。
4. 公開頁與成功頁在手機寬度通過視覺檢查。
5. 自動測試證明不超賣且不能變更他人預約。
6. 測試帳戶與標籤證明 verified society 免費、external group HK$80。
7. Staff 只看見獲指派活動。
8. Staff 無公開個人頁。

### Week 4

1. 預約頁顯示剩餘名額及候補人數。
2. 候補採 FIFO；補位總數不可超過原容量的 10%。
3. 候補文案為 Fairboard 原創。
4. 自動測試證明第 11 次補位（容量 100）被拒絕。

### Worklog 之外但目標所需的合理性修正

- CSRF token 套用到所有改變狀態的表單。
- 閒置 Session 到期、安全 Cookie 設定、登入失敗限速。
- 所有角色的可見入口與伺服器權限一致。
- Account disable、Staff assignment 與 Staff edit／check-in 能力依角色說明實作。
- 取消與破壞性操作提供確認及安全回復訊息。

## 7. 非目標

- 不在此版收取票款或儲存付款卡。
- 不提供座位選擇。
- 不建立 Staff 公開個人頁。
- 不加入大型 JavaScript 框架或重型動畫。
- 不複製競品的品牌、版面或售罄文案。

## 8. 驗收與證據

- PHP 語法檢查全數通過。
- 角色矩陣、booking concurrency／ownership、FIFO、10% refill cap、CSRF 等自動測試通過。
- 使用四個角色帳戶走完主要流程。
- 以至少 390px 手機與 1280px 桌面寬度檢查首頁、詳情、成功頁、建立頁和四角色工作區。
- 每一項 worklog 有明確 Pass／Fail 與證據，不以檔案存在代替功能驗證。
- 最終提供已修正項目與仍可選擇改善的清單。

## 9. Builder check 決定

以下屬於不改變產品方向、可由實作者直接決定的落地細節：

- 活動狀態統一為 `draft`、`pending`、`approved`、`rejected`、`cancelled`、`removed`。`cancelled` 代表 Creator 主動取消但保留紀錄；`removed` 只供 Admin 下架。既有 `removed` 不再同時代表兩種不同意思。
- 建立活動時，Save draft 儲存為 `draft`；Submit for review 儲存為 `pending`。Staff 可以修改獲指派活動內容，但不能建立活動或改變審核／所有權狀態。
- Creator 與 Staff 共用同一活動編輯頁和 `canEditEvent` 伺服器端授權規則，避免兩套表單漂移；導航及返回目的地依角色調整。
- 10% 候補補位上限以活動建立時的原始容量計算，採 `FLOOR(original_capacity * 0.10)`；容量少於 10 時至少允許一次補位。每次候補轉正需留下可稽核計數，後續增加容量不重設已使用額度。
- 候補補位在同一資料庫交易中鎖定活動、讀取 FIFO 首位、檢查額度、移除候補、建立 booking 並增加計數；額度耗盡時取消仍成功，但名額保持公開可預約，不自動移動候補。
- 帳戶停用使用 `is_active`，登入時拒絕停用帳戶；不刪除歷史活動、booking 或審核紀錄。
- Staff assignment 由 Admin 建立／移除，資料庫唯一鍵避免重複；Staff 對獲指派 Creator 的全部活動有規格列出的協助權限。
- 分類採有限清單加 `other`，避免自由文字造成篩選碎片；社交連結只接受 HTTP(S) URL。海報上傳限制為圖片 MIME、合理檔案大小及伺服器產生檔名。
- 搜尋與篩選由伺服器端 GET 參數實作，網址可分享，無 JavaScript 時仍可用；低數據模式不載入海報圖片。
- 介面文案以英文為主要產品語言，維持現有課程成果的一致性；本規格與最終修改清單使用繁體中文說明。
