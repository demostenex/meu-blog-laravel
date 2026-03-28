# DIY Parte 2: Agora que sabemos o porquê, vamos dar uma olhadinha no como

Na parte 1 eu justifiquei a loucura de fazer um blog do zero em 2026. Agora vem a parte que a maioria dos tutoriais pula: **o que de fato acontece quando você para de planejar e começa a codar**.

Spoiler: vai quebrar. Muito. Mas cada quebra ensina algo que nenhum tutorial de 10 minutos no YouTube vai te dar.

---

## 1. O Editor: Por que o Trix e não o ChatGPT me recomendar outro

Quando você decide fazer um blog, a primeira pergunta técnica não é o banco de dados. É: *como eu vou escrever os textos?*

Testei o Quill. Bonito, flexível, cheio de plugins. E cheio de `<span style="font-size: 11pt; font-family: Arial">` quando você cola qualquer coisa do Word ou do Notion. O HTML que ele gera parece o código-fonte de um e-mail corporativo de 2008.

O **Trix**, editor do Basecamp, é opinionado demais pra ser popular — e exatamente por isso eu escolhi. Ele normaliza tudo que você cola. Não aceita HTML arbitrário. Ele transforma o que você colou em estrutura semântica limpa. Pra um blog técnico onde vou colar código, threads e trechos de outros lugares, isso é ouro.

O trade-off? **Upload de imagens não vem de graça.** O Trix dispara um evento `trix-attachment-add` quando você arrasta uma foto pro editor, mas o resto é por sua conta.

A solução foi usar o `WithFileUploads` do próprio Livewire — que já tínhamos para o upload da capa. O JS captura o arquivo, manda pro servidor, o Laravel salva e devolve a URL pro Trix posicionar a imagem no cursor. Simples na teoria. Na prática levei dois erros bons antes de chegar lá.

> **Pitaco do Copilot:** *O `document.querySelector('[wire:id]')` pega o **primeiro** componente Livewire do DOM — que era a navegação, sem `WithFileUploads`. A solução foi subir pelo DOM a partir do próprio `<trix-editor>` com `.closest('[wire:id]')`. Detalhe idiota, meia hora de debug.*

---

## 2. O Sumário: JavaScript Raiz, Sem Biblioteca

O Sumário lateral (o "Nesta Página" que você vê aqui do lado) é 100% JavaScript puro. Sem dependência, sem pacote npm, sem nada.

A lógica é simples: varrer o conteúdo em busca de `h1, h2, h3`, gerar IDs únicos para cada um, montar uma lista de links.

O problema é que **conteúdo colado do Trix frequentemente não usa headings**. Quando você cola um texto, o editor não sabe que aquele parágrafo em negrito era um título de seção. Então adicionei um fallback: se um `<strong>` é o único conteúdo do seu parágrafo pai, ele entra no sumário como se fosse um heading.

Funciona na maioria dos casos. Não é perfeito. Mas é melhor que nada e melhor que instalar uma biblioteca de 50kb pra fazer isso.

O detalhe mais recente: o último item do sumário é sempre o comentário do Kikito — com o avatar dele, o nome configurado e a cor que eu escolhi no painel. Um link que diz implicitamente *"tem uma opinião não solicitada te esperando lá embaixo"*.

---

## 3. Docker: O Ambiente que Te Ensina Respeito

Esse tópico eu já toquei na Parte 1, mas vale aprofundar porque aprendi mais coisas.

O Dockerfile de produção tem uma linha que parece boba mas salvou minha sanidade:

```dockerfile
RUN rm -rf bootstrap/cache/*.php
RUN composer install --no-dev --optimize-autoloader
```

A ordem importa. Se você rodar o `composer install` antes de limpar o cache, o Laravel vai tentar usar o cache do seu ambiente de desenvolvimento — que referencia pacotes que você mandou ignorar com `--no-dev`. Resultado: erro de classe não encontrada no primeiro request. Em produção. Com usuário tentando acessar.

O segundo aprendizado foi o `nginx.conf`. Por padrão, o Nginx rejeita uploads acima de 1MB. Silenciosamente. Você fica olhando pro formulário sem entender por que a foto de capa não sobe. Um `client_max_body_size 100M` resolve — mas só depois que você perder tempo debugando o lado errado do sistema.

> **Pitaco do Copilot:** *O Nginx rejeita em silêncio. O PHP diz que recebeu nada. O Livewire diz que o campo é nulo. Você olha pro código PHP por 40 minutos sem achar o problema. O problema é uma linha no Nginx.*

---

## 4. RSS: Porque Sim

Adicionei um feed RSS. Em 2026. Sem ninguém pedir.

O RSS é um contrato direto com o leitor: *"se você me seguir por aqui, eu te aviso quando tiver coisa nova"*. Sem algoritmo decidindo pra quem mostrar, sem engajamento forçado, sem plataforma intermediária.

Leitores técnicos ainda usam RSS. Muito. Feedly, newsboat, leitores de terminal. E bots de indexação adoram. É um sinal de que o site é sério.

A implementação foi zero dependências: um controller simples, uma rota pública `/feed.rss` e uma view Blade que gera o XML na mão. Cinquenta linhas. Funciona. Vai durar décadas sem precisar de atualização.

```
GET /feed.rss → últimos 20 artigos publicados, RSS 2.0
```

Pronto. Próximo.

---

## 5. O Kikito Ganhou Personalidade Configurável

Na Parte 1 eu apresentei o Kikito, a maritaca IA que comenta meus artigos. O que não contei é que ele evoluiu.

Agora qualquer pessoa que usar esse blog pode configurar o próprio bot: nome, foto, modelo do Gemini (do econômico ao mais inteligente) e a persona completa em texto livre. É como contratar um funcionário imaginário — você escreve a descrição da vaga e o modelo interpreta o papel.

Mais recente ainda: o bloco de comentário da IA ganhou **cor configurável**. O painel de perfil tem um color picker. Você escolhe a cor, o bloco no artigo usa aquela cor com opacidade suave — fundo e borda — via CSS inline calculado no servidor. Sem Tailwind hardcoded, sem classe arbitrária. A cor sai do banco, vira `rgba()` e vai pro `style=""`.

E a cor aparece também no sumário, no link do Kikito, junto com o avatar dele. Consistência visual sem esforço do autor.

> **Pitaco do Copilot:** *Converter hex para rgba no PHP é três linhas: `ltrim`, `str_split(hex, 2)`, `array_map('hexdec', ...)`. Não precisa de pacote. Não precisa de helper. Só precisa de vontade.*

---

## 6. O que Ainda Está Faltando (e eu sei disso)

Ser honesto é parte do contrato aqui.

O sumário com bold como heading funciona, mas mal. Se você escrever um parágrafo onde a primeira frase é em negrito, ele vai parar no sumário. Não é o comportamento esperado. Preciso de um critério melhor — provavelmente tamanho mínimo de texto e posição no parágrafo.

O sistema de comentários é o Disqus. Funciona, é grátis, mas carrega um iframe pesado e rastreia o leitor. Num blog sobre soberania técnica, terceirizar os comentários para uma plataforma de publicidade é uma ironia que não me escapa. Pode virar uma Parte 3.

O deploy ainda é manual: `git pull`, rebuild da imagem Docker, migrate. Funciona, mas um webhook do GitHub que dispara o deploy automaticamente no push seria o próximo passo natural.

---

## Conclusão: O Sistema que Você Entende

A Parte 1 foi sobre identidade. A Parte 2 é sobre **consequência**.

Cada decisão aqui tem um motivo. Trix porque normaliza. Grid porque escala. Docker porque isola. RSS porque dura. Kikito porque por que não ter uma maritaca que te julga em público.

Não é o sistema mais sofisticado do mercado. É o sistema que eu consigo depurar às 11 da noite sem Stack Overflow — porque eu mesmo construí, erro por erro, linha por linha.

O código está no GitHub, aberto, livre pra quem quiser usar ou melhorar.

Parte 3? Se tiver, vai falar sobre CDN, cache de borda e a pergunta que todo dev adia até não poder mais: *quanto isso custa pra rodar de verdade?*
