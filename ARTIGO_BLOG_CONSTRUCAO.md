# DIY: Por que construí meu próprio blog com Laravel 13 e IA em 2026

Construir um blog do zero em 2026 parece desperdício de tempo. Com Substack, Medium e Ghost a um clique, por que gastar horas configurando Nginx e permissões de volume? 

A resposta é simples: **Soberania Técnica**. Eu não queria apenas publicar; eu queria ser o dono da fábrica. Este projeto é o meu manifesto de "Artesão Digital" no GitHub, onde cada linha de código foi pesada e cada erro de deploy foi uma lição de controle.

## 1. A Stack: Laravel 13 e a Elegância do Volt
Escolher o **Laravel 13** com **Livewire Volt** não foi sobre usar a "última moda", mas sobre reduzir a fricção. O Volt permite que lógica e interface coexistam em um único arquivo. É o fim do "vaivém" entre Controller e Blade. É produtividade pura para quem quer manter o sistema enxuto.

> **Pitaco do Gemini:** *A validação de "Autor Único" foi movida das rotas para o `mount()` do componente. Resultado: Build limpo > gambiarra em rota. O Laravel não tenta mais ler o banco durante a otimização da imagem Docker.*

## 2. O "First Flight": Quando o Docker revida
Construir em `localhost` é um mar de rosas. O desafio começa no primeiro deploy. No meu "primeiro voo", a aplicação explodiu com um erro seco: `Class "Laravel\Pail\PailServiceProvider" not found`.

O erro era sutil: eu estava tentando rodar em produção um cache gerado no desenvolvimento que referenciava ferramentas de log (`Pail`) que eu mesmo tinha mandado o Composer ignorar via `--no-dev`. 

**A solução?** Uma dieta rigorosa no `.dockerignore` e um comando cirúrgico no `Dockerfile.prod` para incinerar o cache residual antes de cada instalação.

> **Pitaco do Gemini:** *Injetei `RUN rm -rf bootstrap/cache/*.php`. O segredo foi o `.dockerignore`: zero ruído local na imagem de produção. Build concluído em 20 segundos.*

## 3. UI/UX: O Sumário não é decorativo
O foco aqui é a **leitura**. Por isso, a tipografia foi reduzida pela metade em relação ao padrão e o layout foi estruturado em Grid. O **Sumário Fixo (Sticky TOC)** lateral não é um adereço; ele é o mapa para textos longos, reduzindo a taxa de abandono ao permitir saltos rápidos entre seções.

Além disso, resolvemos o pesadelo do *Mixed Content*. O site rodava em HTTPS, mas o Livewire tentava fazer uploads via HTTP. O navegador bloqueava tudo.

> **Pitaco do Gemini:** *Forcei o esquema HTTPS no `AppServiceProvider` e configurei `trustProxies('*')`. Sem SSL funcional, o upload de imagens morre. Segurança não é opcional, é pré-requisito.*

## 4. Trade-offs: O custo da liberdade
Seja honesto: fazer tudo sozinho dá trabalho. Eu perco a rede de distribuição nativa do Medium e ganho a responsabilidade de manter o servidor atualizado. Mas, em troca, tenho um Lighthouse score de quase 100 e a certeza de que o sistema se comporta exatamente como eu projetei.

## 5. O Bot que Lê o que Você Escreve (e Julga)
Depois de tudo funcionando, surgiu uma pergunta: *e se o blog tivesse personalidade própria?* Integrei o **Gemini AI** como um comentarista automático. Mas não qualquer bot — uma maritaca chamada Kikito, com persona configurável, que lê cada artigo e publica um comentário sarcástico, opinativo e às vezes irritantemente obediente.

A arquitetura foi deliberada: os comentários ficam numa tabela `ai_comments` separada, com histórico completo. A chave de API é criptografada no banco com o `Encrypter` do próprio Laravel — nada de segredo em texto plano. E a toolbar do editor Trix ganhou comportamento flutuante: quando você rola a página editando um artigo longo, ela te acompanha.

> **Pitaco do Kikito (o bot):** *O campo `gemini_api_key` era `varchar(255)`. O token criptografado tem 360 caracteres. Solução óbvia: `text`. Às vezes o banco de dados é mais honesto que o programador.*

## 6. Rascunho, Previsão e o Problema dos Dois Menus
O sistema de rascunho parecia simples: um campo `published_at` nullable. Null = rascunho, preenchido = publicado. Na prática, revelou um bug dormindo há semanas no dashboard: o componente usava `<x-app-layout>` (que já inclui a navbar) **dentro** de um Volt component cujo Livewire aplica `layouts.app` automaticamente. Resultado: duas navbars empilhadas como um sanduíche mal-feito.

A pré-visualização do rascunho trouxe outro detalhe: o banner de aviso "você está em rascunho" foi colocado dentro do grid de duas colunas (artigo + sumário). O Livewire exige um único elemento raiz, e o CSS Grid tratou o banner como mais uma célula — o artigo foi parar na coluna do sumário. A correção foi mover o banner para **fora** do grid, dentro de um único `<div>` raiz que abraça tudo.

> **Pitaco do Copilot:** *`col-span-full` dentro de um grid com track customizado `[1fr_18rem]` não garante que os itens seguintes continuem na ordem esperada quando há um `@if` dinâmico. Envolva tudo num `<div>` e durma tranquilo.*

## Conclusão: O Código como Identidade
O meu GitHub agora tem algo real. Não é um fork, não é um tutorial seguido às cegas. É um sistema que eu entendo de ponta a ponta. Usar a IA como copiloto me deu agilidade nas partes mecânicas (como as Meta Tags de Open Graph para o WhatsApp ficar bonito), mas a decisão arquitetural e a "alma" do blog continuam sendo minhas.

Não é só um blog. É soberania.
