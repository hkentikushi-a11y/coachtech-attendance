# coachtech 勤怠管理アプリ

## 環境構築

Docker（Docker Desktop）が起動している状態で、以下を順に実行します。Windows の場合は WSL2（Ubuntu）のターミナルで実行してください。

### 1. リポジトリの取得

```bash
git clone <このリポジトリのURL> attendance-app
cd attendance-app
```

### 2. 依存パッケージのインストール（vendor の作成）

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
  laravelsail/php82-composer:latest \
  composer install
```

### 3. 環境変数ファイルの作成

```bash
cp .env.example .env
```

### 4. Docker のビルドと起動

```bash
./vendor/bin/sail up -d
```

### 5. アプリケーションキーの生成、マイグレーション、シーディング

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

### 6. フロントエンド（Vite）

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

> エイリアスを設定すると `sail` だけで実行できます：`alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'`

## 使用技術（実行環境）

- PHP 8.2
- Laravel 10.x
- MySQL 8.4
- Laravel Sail（Docker）
- Laravel Fortify（認証）
- Vite（フロントエンドのビルド）
- Mailpit（開発用メールサーバー）
- phpMyAdmin

## ER図

![ER図](docs/er.png)

## URL

- 開発環境：http://localhost/
- 会員登録：http://localhost/register
- ログイン（一般ユーザー）：http://localhost/login
- ログイン（管理者）：http://localhost/admin/login
- phpMyAdmin：http://localhost:8080/
- Mailpit：http://localhost:8025/

## ログイン情報

シーディング（`migrate --seed`）で以下のユーザーが作成されます。

| 種別 | 名前 | メールアドレス | パスワード |
|---|---|---|---|
| 一般ユーザー | 山田 太郎 | user1@example.com | password |
| 一般ユーザー | 佐藤 花子 | user2@example.com | password |
| 管理者 | 管理者 | user3@example.com | password |

いずれもメール認証済みです。
