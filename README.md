# aw-edu01 EC2 デプロイ手順書

## 1. EC2 インスタンス準備
- AMI: Amazon Linux 2023
- インスタンスタイプ: t3.small 以上推奨
- セキュリティグループ
  - 22/tcp (SSH)
  - 80/tcp (HTTP)
  - 443/tcp (HTTPS)

SSH 接続:
```bash
ssh -i your-key.pem ec2-user@<EC2_PUBLIC_IP>
```

## 2. Docker / Docker Compose インストール
```bash

sudo dnf update -y
sudo dnf install -y docker
sudo systemctl enable docker
sudo systemctl start docker
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
sudo usermod -aG docker ec2-user
```

再ログイン後に確認:
```bash
docker --version
docker compose version
```

## 3. ソースコード取得
```bash
sudo yum install git -y
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/ZeroRein/aw-edu01.git
cd aw-edu01
```
## 5. データベーステーブル作成
### (1) SQL
```bash
docker compose exec mysql mysql example_db
```
```sql
CREATE TABLE `bbs_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `body` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `bbs_entries` ADD COLUMN image_filename TEXT DEFAULT NULL
);
```

### (2) phpMyAdmin を利用する場合
compose.yml に phpMyAdmin を追加し、GUIで作成可能

## 6. ビルド & 起動
```bash
docker compose build
docker compose up -d
docker ps
```

アクセス:
- アプリ: http://<EC2_PUBLIC_IP>/
- phpMyAdmin: http://<EC2_PUBLIC_IP>:8080/ (追加した場合)

## 7. 運用補足
- 更新手順:
```bash
git pull
docker compose build
docker compose up -d
```
- DB 永続化: volume 利用
- HTTPS 化: Nginx + Let’s Encrypt または ALB+ACM

---
