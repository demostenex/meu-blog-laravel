# Atualização automática dos blogs

Execute em cada clone de produção que usa `docker-compose.prod.yml`. Requisitos: Bash, Git, flock (util-linux), Docker Compose v2, `.env`, `Dockerfile.prod`, `package-lock.json` versionado e serviços já funcionando. O usuário do cron deve poder acessar Docker e o remoto Git sem senha interativa (por exemplo, chave SSH de deploy). Rode com o mesmo usuário que mantém o clone.

Antes de agendar, publique este script, faça o pull inicial e resolva alterações locais. Arquivos próprios do servidor devem estar ignorados pelo Git. O script nunca faz reset, stash nem descarta arquivos. Ele acompanha o upstream da branch atual, aceita apenas fast-forward e usa uma trava por clone.

Teste uma execução manual (ela realiza o deploy):

```bash
/bin/bash /srv/blog/scripts/autoupdate.sh /srv/blog
```

No `crontab -e`, agende a cada cinco minutos, usando caminhos absolutos:

```cron
*/5 * * * * /bin/bash /srv/blog/scripts/autoupdate.sh /srv/blog >> /srv/blog/storage/logs/autoupdate.log 2>&1
```

Repita a linha para cada blog, mudando o caminho. Em servidores com vários clones, cada Compose precisa ter nomes de containers, imagem e portas próprios: o arquivo atual usa nomes fixos `meublog_*` e imagem `meublog-app:latest`, portanto não isola múltiplos blogs no mesmo Docker host apenas com `-p`. Distribua os horários para evitar builds simultâneos consumindo os recursos do servidor. Configure rotação do log no servidor.

A primeira execução faz deploy mesmo sem commits novos para registrar uma versão concluída. As seguintes consultam o remoto e só fazem deploy quando necessário. O processo ativa manutenção, atualiza o código, reconstrói a imagem, sincroniza os volumes de dependências, compila o frontend, limpa caches, executa migrações, recria app/worker e reinicia nginx antes de liberar o site.

Falhas deixam o site em manutenção e não registram sucesso. O próximo cron tenta novamente, mesmo se o Git já estiver no commit novo. Consulte `storage/logs/autoupdate.log` e corrija a causa. Não há rollback automático de código ou migrações; mantenha backups do banco. Para liberar manualmente após verificar a aplicação:

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan up
```

Alterações no próprio Compose que afetem banco, Redis ou nginx, e mudanças na configuração local do Dockerfile, exigem revisão manual; o script recria apenas app/worker e reinicia nginx. Não execute este cron em um clone usado para desenvolvimento.
