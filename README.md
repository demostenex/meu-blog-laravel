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

Você tem duas opções para hospedar este blog na internet: a forma **Pura** (instalando tudo diretamente na sua VPS/Servidor) ou via **Docker** (usando os contêineres otimizados que criamos).

---

### Opção 1: Instalação Pura (Direto no Servidor)

Esta é a opção tradicional, ideal para servidores compartilhados ou se você usa o Laravel Forge.

**1. Requisitos:** PHP 8.2+, Servidor Web (Nginx/Apache), Composer, Node.js e um Banco de Dados (PostgreSQL/MySQL).

**2. Instalação:**
```bash
git clone https://github.com/SEU_USUARIO/meu-blog-laravel.git
cd meu-blog-laravel
cp .env.example .env

# Instale as dependências e compile o CSS
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Gere a chave e crie o banco (Configure o .env antes com as senhas do banco!)
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Otimize o cache para produção
php artisan optimize
```

---

### Opção 2: Instalação via Docker (Recomendada para VPS limpa)

Esta opção usa a nossa infraestrutura Docker super otimizada (`Dockerfile.prod` e `docker-compose.prod.yml`). Ideal se você comprou uma VPS limpa (DigitalOcean, AWS, etc) e só quer instalar o Docker.

**1. Requisitos:** Apenas Docker e Docker Compose (v2) instalados na VPS.

**2. Instalação:**
```bash
git clone https://github.com/SEU_USUARIO/meu-blog-laravel.git
cd meu-blog-laravel
cp .env.example .env
```

**3. Configure o `.env`:**
Edite o arquivo (ex: `nano .env`) com estas configurações obrigatórias para o Docker:
```env
APP_NAME="Seu Nome"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seublog.com.br

DB_CONNECTION=pgsql
DB_HOST=db # IMPORTANTE: O host no Docker se chama 'db'
DB_PORT=5432
DB_DATABASE=blog
DB_USERNAME=blog_user
DB_PASSWORD=sua_senha_secreta_aqui
```

**4. Suba o Site:**
Mande o Docker construir o site (ele vai instalar o PHP, Nginx, compilar o Tailwind, etc, tudo sozinho):
```bash
docker compose -f docker-compose.prod.yml up -d --build
```

**5. Segurança Final:**
Entre no contêiner do Laravel e rode os comandos finais:
```bash
docker compose -f docker-compose.prod.yml exec app sh

# Dentro do contêiner:
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
exit
```

---

### 🔒 Criando o seu Usuário (Primeiro Acesso)
O nosso blog tem uma **Trava de Segurança (Autor Único)**. A página de registro só existe se o banco estiver 100% vazio.
1. Acesse imediatamente `http://seu-dominio/register`.
2. Crie a sua conta de administrador.
3. *Magia:* No momento em que a conta for criada, a página de registro deixará de existir para o público, impedindo invasores!
4. Vá em "Painel > Perfil", preencha sua Bio, suba sua foto de perfil e seu Favicon.

---

## 🔧 Manutenção Diária

Se você alterar o código futuramente e enviá-lo para o GitHub, atualize seu servidor assim:

**Se usou a Instalação Pura:**
```bash
git pull origin master
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan optimize
```

**Se usou a Instalação via Docker:**
```bash
git pull origin master
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan optimize
```