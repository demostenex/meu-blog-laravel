<?php

namespace App\Console\Commands;

use App\Models\Post;
use DOMDocument;
use DOMNode;
use DOMText;
use Illuminate\Console\Command;

class MigratePostHeadings extends Command
{
    protected $signature = 'posts:migrate-headings
                            {--dry-run : Mostra o que seria alterado sem salvar no banco}';

    protected $description = 'Converte H1 (botão TT) e <strong> isolado em H2/H3 nos artigos existentes';

    public function handle(): int
    {
        $posts = Post::whereNotNull('content')->get();

        $this->info("Analisando {$posts->count()} artigos...");
        $this->newLine();

        $changed = 0;

        foreach ($posts as $post) {
            $converted = $this->convertHeadings($post->content);

            if ($converted === $post->content) {
                continue;
            }

            $this->line("<fg=yellow>Artigo:</> {$post->title}");
            $this->showDiff($post->content, $converted);
            $this->newLine();

            if (! $this->option('dry-run')) {
                $post->content = $converted;
                $post->saveQuietly();
            }

            $changed++;
        }

        if ($changed === 0) {
            $this->info('Nenhum artigo precisava de conversão.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry-run: {$changed} artigo(s) seriam alterados. Rode sem --dry-run para aplicar.");
        } else {
            $this->info("✓ {$changed} artigo(s) convertidos com sucesso.");
        }

        return self::SUCCESS;
    }

    private function convertHeadings(string $html): string
    {
        // Carrega o HTML sem adicionar doctype/html/body
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);

        $replacements = [];

        foreach ($root->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($node->nodeName);

            // TT do Trix gera <h1> — converte para <h2>
            if ($tag === 'h1') {
                $replacements[] = [$node, 'h2'];
                continue;
            }

            // <strong> sozinho dentro de <div> ou <p> com texto > 10 chars → <h3>
            if (in_array($tag, ['div', 'p'])) {
                $text = trim($node->textContent);
                if (strlen($text) > 10 && $this->isSoleStrong($node)) {
                    $replacements[] = [$node, 'h3'];
                }
            }
        }

        foreach ($replacements as [$node, $newTag]) {
            $newNode = $dom->createElement($newTag);
            $newNode->textContent = trim($node->textContent);
            $node->parentNode->replaceChild($newNode, $node);
        }

        // Extrai apenas o conteúdo interno do <div> que usamos como wrapper
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    /** Retorna true se o único conteúdo não-vazio do nó é um <strong> ou <b> */
    private function isSoleStrong(DOMNode $node): bool
    {
        $meaningful = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }
            $meaningful[] = $child;
        }

        if (count($meaningful) !== 1) {
            return false;
        }

        return in_array(strtolower($meaningful[0]->nodeName), ['strong', 'b']);
    }

    private function showDiff(string $before, string $after): void
    {
        $linesBefore = explode("\n", $before);
        $linesAfter  = explode("\n", $after);

        foreach ($linesBefore as $i => $line) {
            $newLine = $linesAfter[$i] ?? '';
            if ($line !== $newLine) {
                $this->line("  <fg=red>- " . mb_strimwidth(strip_tags($line), 0, 80, '…') . "</>");
                $this->line("  <fg=green>+ " . mb_strimwidth(strip_tags($newLine), 0, 80, '…') . "</>");
            }
        }
    }
}
