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

## Conclusão: O Código como Identidade
O meu GitHub agora tem algo real. Não é um fork, não é um tutorial seguido às cegas. É um sistema que eu entendo de ponta a ponta. Usar a IA como copiloto me deu agilidade nas partes mecânicas (como as Meta Tags de Open Graph para o WhatsApp ficar bonito), mas a decisão arquitetural e a "alma" do blog continuam sendo minhas.

Não é só um blog. É soberania.
