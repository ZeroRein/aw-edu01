# aw-edu01 EC2 デプロイ手順書

## 1. Docker / Docker Compose インストール
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

## 2. ソースコード取得
```bash
sudo yum install git -y
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/ZeroRein/aw-edu01.git
cd aw-edu01
```


## 3. ビルド & 起動
```bash
docker compose build
docker compose up -d
docker ps
```

アクセス:
- アプリ: http://<EC2_PUBLIC_IP>/bbs.php



---
