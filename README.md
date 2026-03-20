# Blog Pessoal - demostenesalbert.com.br

Este é um blog pessoal completo e moderno, construído com **Laravel 11**, **Livewire (Volt)** e **Tailwind CSS**. Ele foi desenhado para ser leve, rápido, e com foco total na legibilidade (tipografia, suporte a Dark Mode e leitura sem distrações).

## Funcionalidades Principais
- 🚀 **Painel Administrativo:** Área restrita para criar, editar e excluir artigos.
- 📝 **Editor Trix:** Editor de texto rico (WYSIWYG) ideal para copiar e colar formatações complexas (Twitter, Word).
- 🧑‍💻 **Highlight.js:** Realce automático de sintaxe para blocos de código (perfeito para blogs de tecnologia).
- 🎨 **Design Moderno:** Listagem limpa agrupada por mês/ano, sumário dinâmico ("Nesta Página") nos artigos longos e barra de busca global.
- 🌓 **Modo Escuro:** Suporte nativo e fluido a Light/Dark mode.
- 💬 **Comentários:** Integração ponta a ponta com **Disqus**.

---

## 🛠️ Como colocar em Produção (Deploy)

Para hospedar este blog na internet (VPS, Forge, Vapor, etc.), siga o passo a passo seguro abaixo.

### 1. Requisitos do Servidor
- PHP 8.2 ou superior.
- Banco de Dados (PostgreSQL recomendado, mas MySQL/SQLite funcionam).
- Servidor Web (Nginx/Apache).
- Composer e Node.js/NPM instalados.

### 2. Clonando o Repositório
No servidor, clone este repositório do GitHub:
```bash
git clone https://github.com/SEU_USUARIO/meu-blog-laravel.git
cd meu-blog-laravel
```

### 3. Instalando as Dependências
Instale as bibliotecas do PHP e os pacotes front-end (ignorando os pacotes de desenvolvimento local):
```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 4. Configurando o Arquivo `.env`
O arquivo com as senhas e configurações NUNCA é enviado pelo Git. Crie o seu arquivo de produção a partir do modelo:
```bash
cp .env.example .env
```
Edite o arquivo `.env` (usando `nano .env` ou `vim .env`) e configure as variáveis principais:
```env
APP_NAME="Demostenes Albert"
APP_ENV=production
APP_KEY= # (Será preenchido no próximo passo)
APP_DEBUG=false
APP_URL=https://seublog.com.br # URL oficial do seu site

# Configurações do Banco de Dados de Produção
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nome_do_seu_banco
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha_segura
```

### 5. Preparando o Banco de Dados e Segurança
Gere a chave criptográfica única para o seu site e prepare as tabelas do banco:
```bash
php artisan key:generate
php artisan migrate --force
```

### 6. Configurando as Imagens (Storage)
Para que as fotos de capa e perfil carreguem, crie o link simbólico da pasta pública:
```bash
php artisan storage:link
```
*Lembre-se de configurar as permissões corretas da pasta `storage` e `bootstrap/cache` para que o servidor web (ex: www-data) possa escrever nelas.*

### 7. Otimizando para Velocidade
Em produção, você deve dizer ao Laravel para fazer "cache" (salvar na memória) as configurações, rotas e views, deixando o site incrivelmente rápido:
```bash
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

### 8. Criando o seu Usuário (Primeiro Acesso)
Como o sistema não deve permitir que outras pessoas se registrem como administradores, crie a sua conta e **depois desative o registro** (removendo a rota de registro no código ou usando um bloqueio no servidor):
1. Acesse `https://seublog.com.br/register`.
2. Crie a sua conta de administrador.
3. Vá em "Painel > Perfil", preencha sua Bio, suba sua foto de perfil e seu Favicon.

---

## 🔧 Manutenção Diária

Se você fizer alterações no código futuramente e enviá-las para o GitHub, para atualizar o servidor de produção, basta rodar:
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan optimize
```