# EventMangement 防御範囲と導入手順

このリリースはアプリの認証・認可・入力・リソース制御を強化するものです。ネットワーク攻撃、感染済み端末、未知の脆弱性まで完全に防止したという意味ではありません。以下の「外部」は設定例・運用手順を提供する範囲で、ユーザーのVMやCloudflareへ適用済みではありません。

## 実装された機能

- 全POSTのCSRF、PDOのネイティブプリペアドステートメント、HTMLエスケープ、CSP、所有権・roleの検証を維持。
- 一般ユーザーの予約・待機登録とcreatorのイベント保存はメール確認が必須。adminが発行したstaffはメール確認に依存せず、割当て済みイベントだけ編集可能。DB内でも予約の有効ユーザーを再確認。
- メール確認は32-byte乱数トークンを使用し、DBにはSHA-256 digestのみ保存。確認リンクは10分、パスワードresetリンクは30分で失効する。確認リンクのGETはdigestをセッションに結び付けた短命challengeへ移し、303でbearer tokenをURLから除去する。CSRF付きPOSTだけがトークンを一度だけ消費し、メールスキャナーのGETアクセスでは認証状態を変更しない。
- admin/staffはMFA必須。通常ユーザー・creatorは任意。TOTPはSHA-1・6桁・30秒、時刻差±1周期。DBロックでコード再使用と回復コードの二重使用を拒否。
- MFA秘密鍵はSodium secretboxで暗号化。`app_key`はバックアップし、漏洩時を除き安易に変更しない。回復コードは64ビット乱数を10個、DBにハッシュのみ保存し、画面には発行時だけ表示。
- Google AuthenticatorとMicrosoft Authenticatorの「その他のアカウント」で扱える`otpauth://` QRをサーバーで生成。外部QRサービスへ秘密鍵を送らない。RFCテストとブラウザー表示の検証と、実機アプリでの確認は区別する。
- adminだけがstaffのログイン用メールIDとアカウントを直接作成。初期パスワードは96ビット乱数を含み、DBにはbcryptハッシュのみ保存、成功したPOST画面で一度だけ表示。初回は本人によるパスワード変更→MFA登録を必須とし、完了までstaff機能を使えない。
- staff用メールIDはアプリ内の識別子であり、既存メールボックスは不要。メール招待・承認は行わず、パスワード回復はadminが初期パスワードを再発行する。全セッションを失効させ、既存MFAは維持。staffのメールreset/verifyリンクは発行・受理しない。セキュリティ通知はアプリ内に表示。
- 管理操作は直近10分の本人確認。SQL DEVは開発環境・許可admin ID・再認証限定、名前やメールと業務データを閲覧可能。秘密値は非表示、任意SQLは実行不可。
- 自己削除は「Delete」完全一致、パスワード、設定済みMFAを要求。プロフィールと認証情報を消去、全セッション無効化、未来の予約を取消して待機者を繰り上げる。過去の参加・イベント・請求は削除済みアカウントの参照として残す。creatorは開催予定を先に取消、adminは手動の引継ぎが必要。
- 連続操作の永続的な制限、取消通知の重複防止、画像保存の全体512 MiB／creator 50 MiBの上限、置換画像と失敗時ファイルの清掃。複数サーバー構成では共有ファイルシステムのロックか専用ストレージ制御が必要。
- 認証失敗・CSRF拒否・権限拒否・制限超過・重要操作を`FairboardSecurity` JSONとしてPHPログへ出力。生パスワード、トークン、OTP、メールアドレス、URLは記録しない。
- GitHub Actionsに構文・依存関係監査・単体・移行・HTTPテスト。checkoutは確認したコミットSHA固定。Dependabot設定を追加。

## staffアカウント作成・再発行

1. adminがMFA付きでログインし、Admin → **Create staff account** に名前とメール形式のログインIDを入力する。例：`staff001@eventlink.invalid`。既存アカウントと同じIDは使えない。
2. 作成直後の画面で初期パスワードを控え、本人へ安全な別経路で渡す。この画面は保存・キャッシュせず、戻った後はパスワードを再表示しない。控え忘れた場合は **Reset staff password** で再発行する。
3. staffが初期パスワードでログインし、別の自分用パスワードに変更。次に認証アプリを登録し、回復コードを安全に保管する。
4. adminが **Assign event staff** でcreatorを割り当てる。staffには他のstaffを作成・再発行する権限はない。
5. 再発行でも既存の認証アプリ／回復コードは必要。MFAも全て紛失した場合は、下記の本人確認を伴う保守手順を使う。

この機能が作るのはFairboard用のログインIDとアカウントです。Google Workspaceなどの送受信用メールボックスの新設は行いません。一般ユーザー・creatorのメール確認とメールによるパスワード回復は継続します。SQL DEVではテーブル内容を表示し、初期パスワードを含む認証秘密情報は表示しません。

## 39項目の対応

| # | 攻撃・脅威 | アプリ／導入する対策 | 外部で必要な作業・残る限界 |
|---|---|---|---|
| 1 | ネットワークスキャン | 公開面をWebに限定 | UFW、ネットワーク分割、不要サービス停止。検出自体の完全阻止は不可 |
| 2 | ポートスキャン | DB・開発ファイルを非公開 | DBはlocalhost、SSHは管理元/VPN限定。Tunnelなら受信公開ポート不要 |
| 3 | バッファオーバーフロー | 入力長・画像寸法・本体サイズ制限 | PHP/GD/OSの更新。ネイティブ拡張の脆弱性は別途修正 |
| 4 | ブルートフォース | アカウント＋IP制限、MFA | WAF、認証失敗の監視 |
| 5 | 辞書攻撃 | 長いパスワード、bcrypt、MFA | 漏洩パスワード照合は将来課題 |
| 6 | パスワードリスト | MFA、試行制限 | 使い回し禁止、通常ユーザーのMFA利用促進 |
| 7 | マルウェア | アップロードの画像限定・再エンコード、実行不可 | OS更新、EDR、最小権限。端末感染防止はアプリ外 |
| 8 | ランサムウェア | Webプロセスの書込先限定 | 暗号化オフライン/不変バックアップと復元訓練 |
| 9 | フィッシング | MFA、短命のメール確認リンク | TOTPは中継型フィッシングに弱い。将来WebAuthn/passkey、利用者教育 |
| 10 | 標的型攻撃 | 最小権限、再認証、監査 | EDR、SOC/アラート、対応手順 |
| 11 | IPスプーフィング | クライアントの転送ヘッダーをPHPで信用しない | 信頼proxyのみWebサーバー側で復元、上流の送信元検証 |
| 12 | 踏み台 | Shell起動をHTTP処理に追加しない、権限制限 | 不要な外向き通信制限、更新、侵害検出 |
| 13 | ARPスプーフィング | HTTPS | 管理スイッチのDHCP Snooping/DAI、LAN分離 |
| 14 | セッションハイジャック | Secure/HttpOnly/SameSite、期限、MFA、重要操作再認証 | HTTPS必須。感染端末や盗難セッションを完全には排除できない |
| 15 | リプレイ | TOTP使用済みカウンタ、回復・resetコード一回限り、QR一回受付 | QRの有効時間内のリアルタイム転送は受付本人確認を併用 |
| 16 | MITB | CSP、外部スクリプト抑制、再認証 | ブラウザー拡張・感染端末はEDRと管理端末で対応 |
| 17 | スニファ | HTTPS、必要時DB TLS | 平文HTTP公開禁止、管理通信SSH/VPN |
| 18 | 電波傍受 | HTTPS | WPA2-AES/WPA3、企業Wi-Fi設定。電波自体の受信は阻止不可 |
| 19 | キーロギング | MFAでパスワード単独漏洩を軽減 | EDR、更新、将来passkey。端末で全要素を盗まれる場合は別問題 |
| 20 | DNSキャッシュ汚染 | HTTPS証明書検証 | 検証型DNS resolver、独自ドメインのDNSSEC |
| 21 | ディレクトリ／パストラバーサル | 固定ファイル名、allowlist、非公開保存領域 | Webサーバーのdeny設定も有効化 |
| 22 | DDoS | 高コスト処理制限、画像クォータ | CDN/WAF・ホスティング上流防御。VMの回線飽和はPHPでは防げない |
| 23 | F5アタック | DB更新回数制限 | Nginx request/connection制限またはWAFルールでGETも制限 |
| 24 | TCP SYNフラッド | アプリ外 | OS更新・SYN cookies・上流DDoS防御 |
| 25 | Ping of Death | アプリ外 | OS更新、上流不正パケット処理 |
| 26 | ランダムサブドメイン | アプリ外 | 権威DNS事業者のDDoS防御、不要なワイルドカードを避ける |
| 27 | ICMPフラッド | アプリ外 | 上流制限。IPv6/PMTUに必要なICMPまで一括遮断しない |
| 28 | Smurf | アプリ外 | directed broadcast無効、broadcast ICMP応答を無効化 |
| 29 | スパムメール | 登録・再送・resetの回数制限 | SMTP送信枠、SPF/DKIM/DMARC、バウンス監視 |
| 30 | XSS | HTML escape、CSP、画像再エンコード | 新画面にも同じ規則を適用。保存値をraw HTMLにしない |
| 31 | SQLインジェクション | PDO bind、動的識別子allowlist | 実行用DB権限はSELECT/INSERT/UPDATE/DELETE、移行用資格情報を分離 |
| 32 | ゼロデイ | 防御の重層化、入力制限 | 更新、WAFの仮想パッチ、監視・隔離。未知の攻撃を全保証不可 |
| 33 | セッションID固定 | strict mode、認証時ID再生成 | Cookie設定を維持 |
| 34 | ルートキット | Web/DB最小権限 | Secure Boot、EDR/FIM、侵害時は信頼できる媒体から再構築 |
| 35 | フォームジャッキング | script-src self、外部JS抑制 | 依存監査・変更監視。カード情報は収集しない |
| 36 | ドメイン乗っ取り | アプリ外 | レジストラMFA、transfer lock、DNS変更通知、更新管理 |
| 37 | OSコマンド注入 | HTTP入力からshellを呼ばない | 将来の外部コマンド追加時も文字列結合しない |
| 38 | CSRF/XSRF | 全POST token、SameSite、GETで状態変更しない | 新エンドポイントも共通bootstrapを使用 |
| 39 | サプライチェーン | composer.lock、audit、SHA固定Actions、Dependabot | main保護・PRレビュー・秘密スキャン、OS/JS依存の継続監視 |

## Ubuntu `/var/www/html/EventLink` への更新

1. サイトを一時停止してDB、`config.php`、`storage/`、`uploads/`をWeb公開外へバックアップ。特に`app_key`はMFA復号に必要。復元テストを別のDBで行う。
2. PRを確認・マージ後、`git pull --ff-only`。未コミットの変更がある場合は先に内容を確認し、resetで消さない。
3. `sudo apt install php-mbstring php-mysql php-gd php-xml php-curl`。`php -m`で`sodium`も確認。CLIとApache/FPMの両方を確認。
4. `composer install --no-dev --prefer-dist --no-interaction --no-plugins`。`composer audit --locked`を確認。
5. **既存DBにdatabase.sqlを再投入しない。** 移行用資格情報で`php tools/migrate.php /private/path/migration-config.php`を実行する。新設テーブルと`deleted_at`・`must_change_password`を追加する。繰返し実行可能。旧版のstaff招待がある場合は保留リンクとstaff用メールトークンを無効化し、既存staffのパスワードとMFAは保持する。
6. 実行用configではHTTPSの`base_url`（末尾の空白も確認）、`session_secure=true`、`app_env=production`、実際に配送できる`mail_transport=mail`を設定。SMTP/MTAとSPF/DKIM/DMARCを設定し、テストアドレスへ送信確認。
7. `docs/apache-hardening.conf`と`docs/php-hardening.ini`を実環境に合わせて適用。Apacheは`apache2ctl configtest`後にreload。Nginxの場合は別の`nginx.example.conf`を利用し`nginx -t`。設定例を両方同時に適用しない。
8. コードはデプロイユーザー所有でWebユーザーは読取のみ、`storage/`だけ必要な書込権限を付与。configはWebユーザーから読める0600/0640、秘密鍵・DBパスワードはGitに入れない。
9. `php tools/maintenance.php`をWebユーザーまたは同じ保存領域へアクセスできる保守ユーザーで毎日実行。PHPログをlogrotate/中央ログで保存し、認証失敗集中・429増加・5xx・保存容量を通知するルールを設定する。
10. Ubuntu側は`timedatectl status`で時刻同期を確認。admin/staffの初回ログインでMFA登録、回復コードをオフライン保管。実機のGoogle/Microsoft Authenticatorで登録→新しいコードで再ログインを確認。

MFA全紛失時は、管理者が本人確認した後、保守時間中に対象ユーザーの`user_mfa`を削除して`auth_version`を増加させる。WebにMFAを無条件で解除するボタンは設けない。操作を記録し、本人へ別経路で通知する。admin/staffは次回ログインで再登録が必須になる。

## 外部の必須設定と残る検証

- 今のNAT + Quick Tunnelはデモ向け。本番は固定ドメインのCloudflare Tunnel/ホスティングを用意し、WAF・rate limitの利用可能な機能と制限を契約プランで確認する。Cloudflareの画面をここから設定済みとはしない。
- Tunnel専用originはlocalhostにbindし、MariaDBは3306を公開しない。SSHの許可元を先に登録してからUFWのデフォルト拒否を有効にする。SSH接続中の無条件なUFW投入は避ける。
- Cloudflare経由で全員のIPが127.0.0.1になる場合、**originに直接アクセスできないことを確認してから**Apache RemoteIP/Nginx real_ipで信頼するproxyだけを指定する。PHPが任意の`X-Forwarded-For`を読む実装にはしない。
- GitHubのSettings → RulesでmainへのPR必須・レビュー・Security checks成功必須・force push禁止を設定。現時点のコード追加だけではリポジトリ設定は変更されない。
- ログ収集先で`auth.login_failed`, `auth.mfa_failed`, `request.csrf_rejected`, `request.rejected_429`などを集計し通知。ログにあるIPはHMAC値なので、その値を実IPとしてfirewallへ渡さない。WAF側の実IPログと併用する。
- メール送信が同期処理のため、resetの応答時間によるアカウント存在推測は残る。高い秘匿性が必要な公開サービスでは非同期キューと存在しない宛先の同じ応答経路を追加・測定する。
- 大規模DDoS、感染済み端末、侵害後の復旧、実機認証アプリ、実際のUbuntu/Cloudflare構成はこのリポジトリのローカルテストだけでは検証できない。

## 参照した一次資料

- [RFC 6238 TOTP](https://www.rfc-editor.org/rfc/rfc6238)
- [Google Authenticator](https://support.google.com/accounts/answer/1066447)
- [Microsoft Authenticator のアカウント追加](https://support.microsoft.com/en-us/authenticator/how-to-add-your-accounts-to-microsoft-authenticator)
- [OWASP MFA](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html)、[Forgot Password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
- [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/)
- [Apache RequestReadTimeout](https://httpd.apache.org/docs/2.4/en/mod/mod_reqtimeout.html)
- [Nginx request limits](https://nginx.org/en/docs/http/ngx_http_limit_req_module.html)、[connection limits](https://nginx.org/en/docs/http/ngx_http_limit_conn_module.html)
