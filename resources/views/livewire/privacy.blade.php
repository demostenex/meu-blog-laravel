<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blog')] class extends Component {};
?>

@push('seo_head')
    <title>Privacidade e cookies - {{ config('app.name') }}</title>
    <meta name="description" content="Conheça os dados de navegação coletados pelo blog e o uso de cookies e serviços da Cloudflare.">
    <link rel="canonical" href="{{ route('privacy') }}">
@endpush

<article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-gray-700 dark:text-gray-300 space-y-6 leading-relaxed">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Privacidade e cookies</h1>
    <p>Este blog coleta dados de navegação para medir a audiência, entender quais conteúdos são acessados e manter o site funcionando com segurança.</p>

    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Estatísticas do próprio blog</h2>
    <p>Registramos páginas visitadas, datas e horários, origem do acesso e parâmetros de campanha, tipo de dispositivo, navegador, sistema operacional e país, quando disponível. Nos artigos, também medimos tempo de leitura e rolagem da página. O endereço IP é transformado em um hash para as estatísticas internas; isso não significa que todos os dados sejam anônimos.</p>
    <p>Os cookies <code>analytics_visitor_id</code> e <code>analytics_session_id</code> identificam visitas e sessões no mesmo navegador e têm validade de até um ano, renovada nas visitas. Uma nova sessão é criada após 30 minutos de inatividade. Essas estatísticas são coletadas automaticamente durante a navegação.</p>

    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Cloudflare</h2>
    <p>Usamos a Cloudflare na infraestrutura do blog, incluindo a entrega de arquivos. Ao acessar o site ou carregar esses arquivos, a Cloudflare pode processar endereço IP, informações do navegador e dados das requisições para entregar conteúdo, proteger o serviço e identificar tráfego malicioso. Os cookies utilizados dependem dos recursos de segurança habilitados.</p>
    <p>Consulte a <a href="https://www.cloudflare.com/privacypolicy/" class="underline">política de privacidade da Cloudflare</a>.</p>

    @if (\App\Models\User::first()?->google_analytics_id)
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Google Analytics opcional</h2>
        <p>Quando você aceita o Google Analytics, carregamos a tag do Google para medir páginas visitadas, interações, origem do tráfego e informações do dispositivo. O Google pode utilizar cookies, como os da família <code>_ga</code>, e processar esses dados fora do Brasil. A tag permanece bloqueada até sua autorização.</p>
        <p>Você pode aceitar, rejeitar ou revogar sua autorização em “Preferências de privacidade”, disponível no fim das páginas públicas. A escolha fica salva neste navegador por até 180 dias. As estatísticas próprias descritas acima continuam ativas independentemente dessa escolha.</p>
        <p>Consulte <a href="https://policies.google.com/technologies/partner-sites?hl=pt-BR" class="underline">como o Google usa dados de sites parceiros</a>.</p>
    @endif

    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Cookies e serviços externos</h2>
    <p>O blog também usa cookies para sessão e segurança, e armazena a preferência de tema no navegador. Fontes, bibliotecas de código e outros recursos externos podem receber seu endereço IP e informações da requisição quando são carregados. Os provedores podem processar dados fora do Brasil, conforme suas políticas.</p>
    <p>Você pode bloquear ou apagar cookies nas configurações do navegador. Isso pode afetar recursos como o login e não impede, por si só, todo processamento de dados das requisições.</p>

    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Seus direitos e contato</h2>
    <p>Você pode solicitar informações sobre o tratamento de seus dados e exercer os direitos previstos na LGPD, incluindo acesso, correção e exclusão quando aplicáveis. Para falar com o responsável pelo blog, utilize os canais públicos disponíveis na página do autor.</p>
    @if ($author = \App\Models\User::first())
        <p><a href="{{ route('author.show', $author) }}" class="underline">Ver perfil e canais do autor</a></p>
    @endif
    <p class="text-sm">Este aviso informa sobre o uso de dados. A navegação ou a leitura desta página não representam consentimento para cookies opcionais.</p>
</article>
