# Projeto: Blog demostenesalbert.com.br

## Objetivos e Tecnologias
- **Framework:** Laravel
- **Frontend:** Livewire + Blade (estilo simples)
- **Banco de Dados:** PostgreSQL
- **Infraestrutura:** Docker e Docker Compose (Laravel Sail)
- **Autenticação:** Sistema de usuário único (apenas o administrador)

## Funcionalidades Principais
1. **Gestão de Artigos (Blog):**
   - Editor de texto integrado (WYSIWYG) que permita colar artigos do Twitter e Instagram com facilidade (sugestão: Trix ou Quill, devido à integração simples com Livewire e suporte a rich text).
2. **Sistema de Comentários e Respostas:**
   - Comentários em artigos com suporte a respostas (replies) utilizando um sistema de hierarquia simples (`parent_id`). Isso será implementado usando Livewire para atualizações em tempo real sem recarregar a página.

## Próximos Passos (Plano de Ação)
1. **Configuração Inicial:** Inicializar o projeto Laravel com Sail (Docker + PostgreSQL) no diretório atual.
2. **Banco e Autenticação:** Configurar o Laravel Breeze (ou estrutura simples) para o painel de administrador único.
3. **Painel e Editor:** Instalar e configurar Livewire. Integrar um editor de texto rico no painel. Criar o CRUD de artigos.
4. **Sistema de Comentários:** Desenvolver os modelos, migrations e interface de comentários nos artigos da área pública.
5. **Estilização:** Refinar o visual da área pública (simples e focado na leitura).