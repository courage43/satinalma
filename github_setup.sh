#!/bin/bash

echo "🚀 GitHub Repository Setup Script"
echo "=================================="

# Repository bilgileri
REPO_NAME="satinalma-talep-sistemi"
DESCRIPTION="Satın Alma Talep Sistemi - Kütahya TSO tarafından geliştirilmiş modern satın alma yönetim sistemi"

echo "📋 Repository Bilgileri:"
echo "   • İsim: $REPO_NAME"
echo "   • Açıklama: $DESCRIPTION"
echo ""

echo "📝 Manuel GitHub Repository Kurulum Adımları:"
echo ""
echo "1. GitHub'a gidin: https://github.com/new"
echo "2. Repository name: $REPO_NAME"
echo "3. Description: $DESCRIPTION"
echo "4. Public repository seçin"
echo "5. README file oluşturmayın (zaten var)"
echo "6. .gitignore oluşturmayın"
echo "7. License oluşturmayın"
echo ""

echo "8. Repository oluşturduktan sonra aşağıdaki komutları çalıştırın:"
echo ""
echo "cd \"$(pwd)\""
echo "git remote add origin https://github.com/[USERNAME]/$REPO_NAME.git"
echo "git branch -M main"
echo "git push -u origin main"
echo ""

echo "🎯 Hızlı GitHub Kurulum:"
echo "Aşağıdaki URL'yi tarayıcınızda açın ve repository'yi oluşturun:"
echo "https://github.com/new?name=$REPO_NAME&description=$DESCRIPTION&visibility=public"
echo ""

echo "✨ Repository oluşturduktan sonra GitHub URL'iniz:"
echo "https://github.com/[USERNAME]/$REPO_NAME"
echo ""

echo "📁 Klasör İçeriği:"
ls -la

echo ""
echo "🎉 Setup tamamlandı! GitHub'da repository oluşturun ve push komutlarını çalıştırın."