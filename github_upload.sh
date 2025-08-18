#!/bin/bash

# GitHub Upload Shortcut Script
# Satın Alma Talep Sistemi için dosya yükleme kısayolu

PROJECT_DIR="/Users/kutahyatso/Desktop/adsız klasör"
REPO_NAME="satinalma-talep-sistemi"

echo "🚀 GitHub Upload Kısayolu"
echo "========================"

# Proje dizinine git
cd "$PROJECT_DIR" || {
    echo "❌ Proje dizini bulunamadı: $PROJECT_DIR"
    exit 1
}

echo "📁 Çalışma dizini: $(pwd)"

# Git durumunu kontrol et
if [ ! -d ".git" ]; then
    echo "❌ Bu dizin bir Git repository değil!"
    echo "   Önce 'git init' komutunu çalıştırın."
    exit 1
fi

# Değişiklikleri kontrol et
echo "📊 Git durumu kontrol ediliyor..."
git status --porcelain > /tmp/git_changes.txt

if [ ! -s /tmp/git_changes.txt ]; then
    echo "✅ Herhangi bir değişiklik yok. Repository güncel."
    exit 0
fi

echo "📝 Bulunan değişiklikler:"
git status --short

# Remote repository kontrolü
REMOTE_URL=$(git remote get-url origin 2>/dev/null)
if [ -z "$REMOTE_URL" ]; then
    echo "⚠️  Remote repository ayarlanmamış!"
    echo "   GitHub'da repository oluşturduktan sonra şu komutu çalıştırın:"
    echo "   git remote add origin https://github.com/[USERNAME]/$REPO_NAME.git"
    echo ""
fi

# Commit mesajı al
echo ""
read -p "📝 Commit mesajı girin (Enter=otomatik): " COMMIT_MESSAGE

if [ -z "$COMMIT_MESSAGE" ]; then
    COMMIT_MESSAGE="Update: $(date '+%d/%m/%Y %H:%M')"
fi

# Dosyaları stage'e al
echo "📤 Dosyalar hazırlanıyor..."
git add .

# Commit yap
echo "💾 Commit yapılıyor..."
git commit -m "$COMMIT_MESSAGE

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>"

# Push yap
if [ ! -z "$REMOTE_URL" ]; then
    echo "🚀 GitHub'a yükleniyor..."
    
    # Branch adını al
    CURRENT_BRANCH=$(git branch --show-current)
    
    # Push yap
    if git push origin "$CURRENT_BRANCH"; then
        echo "✅ Başarıyla GitHub'a yüklendi!"
        echo "🌐 Repository URL: $REMOTE_URL"
    else
        echo "❌ GitHub'a yüklenirken hata oluştu!"
        echo "   Manuel olarak şu komutu çalıştırın:"
        echo "   git push origin $CURRENT_BRANCH"
    fi
else
    echo "⚠️  Remote repository ayarlanmamış, sadece local commit yapıldı."
    echo "   GitHub setup için ./github_setup.sh scriptini çalıştırın."
fi

echo ""
echo "🎉 İşlem tamamlandı!"

# Temizlik
rm -f /tmp/git_changes.txt