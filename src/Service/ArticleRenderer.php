<?php

namespace App\Service;

class ArticleRenderer
{
    /**
     * Renders article content into HTML.
     */
    public function renderBlocks(?array $content): string
    {
        if (!$content) {
            return '';
        }

        if (isset($content['markdown']) && is_string($content['markdown'])) {
            return $this->renderMarkdown($content['markdown']);
        }

        if (!isset($content['blocks']) || !is_array($content['blocks'])) {
            return '';
        }

        $html = '';
        foreach ($content['blocks'] as $block) {
            $html .= $this->renderBlock($block);
        }

        return $html;
    }

    /**
     * Extracts plain text from article content for search indexing.
     */
    public function extractText(?array $content): string
    {
        if (!$content) {
            return '';
        }

        if (isset($content['markdown']) && is_string($content['markdown'])) {
            return $this->extractTextFromMarkdown($content['markdown']);
        }

        if (!isset($content['blocks']) || !is_array($content['blocks'])) {
            return '';
        }

        $texts = [];
        foreach ($content['blocks'] as $block) {
            $text = $this->extractTextFromBlock($block);
            if ($text) {
                $texts[] = $text;
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Extracts headings from article content for TOC generation.
     *
     * @return array<array{id: string, text: string, level: int}>
     */
    public function extractHeadings(?array $content): array
    {
        if (!$content) {
            return [];
        }

        if (isset($content['markdown']) && is_string($content['markdown'])) {
            return $this->extractHeadingsFromMarkdown($content['markdown']);
        }

        if (!isset($content['blocks']) || !is_array($content['blocks'])) {
            return [];
        }

        $headings = [];
        foreach ($content['blocks'] as $block) {
            if ($block['type'] === 'header') {
                $text = strip_tags($block['data']['text'] ?? '');
                $id = $this->slugify($text);
                $headings[] = [
                    'id' => $id,
                    'text' => $text,
                    'level' => $block['data']['level'] ?? 2,
                ];
            }
        }

        return $headings;
    }

    private function renderMarkdown(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($markdown));
        if (!$lines) {
            return '';
        }

        $html = '';
        $paragraph = [];
        $lineCount = count($lines);
        $i = 0;

        while ($i < $lineCount) {
            $line = rtrim($lines[$i]);
            $trimmed = trim($line);

            if ($trimmed === '') {
                $html .= $this->flushParagraph($paragraph);
                $i++;
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $html .= $this->flushParagraph($paragraph);
                $level = strlen($matches[1]);
                $text = $this->renderInline(trim($matches[2]));
                $id = $this->slugify(strip_tags(trim($matches[2])));
                $html .= "<h{$level} id=\"{$id}\">{$text}</h{$level}>";
                $i++;
                continue;
            }

            if (preg_match('/^(-{3,}|_{3,}|\*{3,})$/', $trimmed)) {
                $html .= $this->flushParagraph($paragraph);
                $html .= '<hr class="my-8 border-border">';
                $i++;
                continue;
            }

            if (preg_match('/^```/', $trimmed)) {
                $html .= $this->flushParagraph($paragraph);
                $i++;
                $codeLines = [];

                while ($i < $lineCount && !preg_match('/^```/', trim($lines[$i]))) {
                    $codeLines[] = $lines[$i];
                    $i++;
                }

                if ($i < $lineCount) {
                    $i++;
                }

                $code = htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= "<pre><code>{$code}</code></pre>";
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
                $html .= $this->flushParagraph($paragraph);
                $quoteLines = [trim($matches[1])];
                $i++;

                while ($i < $lineCount && preg_match('/^>\s?(.*)$/', trim($lines[$i]), $nextMatches)) {
                    $quoteLines[] = trim($nextMatches[1]);
                    $i++;
                }

                $quoteText = implode("\n", $quoteLines);
                $html .= "<blockquote><p>{$this->renderInline($quoteText)}</p></blockquote>";
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $trimmed, $matches)) {
                $html .= $this->flushParagraph($paragraph);
                $items = [trim($matches[1])];
                $i++;

                while ($i < $lineCount && preg_match('/^\s*[-*+]\s+(.+)$/', trim($lines[$i]), $nextMatches)) {
                    $items[] = trim($nextMatches[1]);
                    $i++;
                }

                $html .= '<ul>';
                foreach ($items as $item) {
                    $html .= '<li>' . $this->renderInline($item) . '</li>';
                }
                $html .= '</ul>';
                continue;
            }

            if (preg_match('/^\s*\d+\.\s+(.+)$/', $trimmed, $matches)) {
                $html .= $this->flushParagraph($paragraph);
                $items = [trim($matches[1])];
                $i++;

                while ($i < $lineCount && preg_match('/^\s*\d+\.\s+(.+)$/', trim($lines[$i]), $nextMatches)) {
                    $items[] = trim($nextMatches[1]);
                    $i++;
                }

                $html .= '<ol>';
                foreach ($items as $item) {
                    $html .= '<li>' . $this->renderInline($item) . '</li>';
                }
                $html .= '</ol>';
                continue;
            }

            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                $html .= $this->flushParagraph($paragraph);
                $tableLines = [$trimmed];
                $i++;

                while ($i < $lineCount) {
                    $next = trim($lines[$i]);
                    if (!str_starts_with($next, '|') || !str_ends_with($next, '|')) {
                        break;
                    }
                    $tableLines[] = $next;
                    $i++;
                }

                $html .= $this->renderMarkdownTable($tableLines);
                continue;
            }

            $paragraph[] = $trimmed;
            $i++;
        }

        $html .= $this->flushParagraph($paragraph);

        return $html;
    }

    private function extractTextFromMarkdown(string $markdown): string
    {
        $text = preg_replace('/```[\s\S]*?```/', ' ', $markdown);
        $text = preg_replace('/!\[[^\]]*]\([^)]+\)/', ' ', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/[#>*`\-\|\[\]_]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text ?? ''));

        return strip_tags($text ?? '');
    }

    /**
     * @return array<array{id: string, text: string, level: int}>
     */
    private function extractHeadingsFromMarkdown(string $markdown): array
    {
        $headings = [];
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                continue;
            }

            $level = strlen($matches[1]);
            $text = trim(strip_tags($matches[2]));
            if ($text === '') {
                continue;
            }

            $headings[] = [
                'id' => $this->slugify($text),
                'text' => $text,
                'level' => $level,
            ];
        }

        return $headings;
    }

    private function flushParagraph(array &$paragraphLines): string
    {
        if ($paragraphLines === []) {
            return '';
        }

        $text = implode(' ', $paragraphLines);
        $paragraphLines = [];

        return '<p>' . $this->renderInline($text) . '</p>';
    }

    private function renderInline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/!\[([^\]]*)]\(([^)]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $escaped);
        $escaped = preg_replace('/\[([^\]]+)]\(([^)]+)\)/', '<a href="$2">$1</a>', $escaped);
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);

        return $escaped ?? '';
    }

    private function renderMarkdownTable(array $lines): string
    {
        if (count($lines) < 2) {
            return '<p>' . $this->renderInline(implode(' ', $lines)) . '</p>';
        }

        $rows = array_map(function (string $line): array {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            return array_values(array_filter($cells, static fn ($cell) => $cell !== ''));
        }, $lines);

        if (count($rows) < 2) {
            return '';
        }

        $header = $rows[0];
        $body = array_slice($rows, 2);

        $html = '<div class="overflow-x-auto my-4"><table><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . $this->renderInline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($body as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $this->renderInline($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function extractTextFromBlock(array $block): string
    {
        $data = $block['data'] ?? [];

        return match ($block['type'] ?? '') {
            'header' => strip_tags($data['text'] ?? ''),
            'paragraph' => strip_tags($data['text'] ?? ''),
            'list' => implode(' ', array_map(
                static fn ($item) => strip_tags(is_array($item) ? ($item['content'] ?? '') : (string) $item),
                $data['items'] ?? []
            )),
            'image' => strip_tags($data['caption'] ?? ''),
            'table' => implode(' ', array_map(
                static fn ($row) => is_array($row) ? implode(' ', array_map('strip_tags', $row)) : '',
                $data['content'] ?? []
            )),
            'code' => $data['code'] ?? '',
            'quote' => trim(strip_tags(($data['text'] ?? '') . ' ' . ($data['caption'] ?? ''))),
            'warning', 'alert' => trim(strip_tags(($data['title'] ?? '') . ' ' . ($data['message'] ?? '') . ' ' . ($data['text'] ?? ''))),
            'raw' => strip_tags($data['html'] ?? ''),
            'checklist' => implode(' ', array_map(
                static fn ($item) => strip_tags($item['text'] ?? ''),
                $data['items'] ?? []
            )),
            default => '',
        };
    }

    private function renderBlock(array $block): string
    {
        return match ($block['type']) {
            'header' => $this->renderHeader($block['data']),
            'paragraph' => $this->renderParagraph($block['data']),
            'list' => $this->renderList($block['data']),
            'image' => $this->renderImage($block['data']),
            'table' => $this->renderTable($block['data']),
            'code' => $this->renderCode($block['data']),
            'quote' => $this->renderQuote($block['data']),
            'delimiter' => '<hr class="my-8 border-border">',
            'warning' => $this->renderCallout($block['data'], 'warning'),
            'alert' => $this->renderCallout($block['data'], $block['data']['type'] ?? 'info'),
            'raw' => $block['data']['html'] ?? '',
            'checklist' => $this->renderChecklist($block['data']),
            default => '',
        };
    }

    private function renderHeader(array $data): string
    {
        $level = $data['level'] ?? 2;
        $text = $data['text'] ?? '';
        $id = $this->slugify(strip_tags($text));

        return "<h{$level} id=\"{$id}\">{$text}</h{$level}>";
    }

    private function renderParagraph(array $data): string
    {
        $text = $data['text'] ?? '';
        return "<p>{$text}</p>";
    }

    private function renderList(array $data): string
    {
        $style = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
        $items = $data['items'] ?? [];

        $html = "<{$style}>";
        foreach ($items as $item) {
            $content = is_array($item) ? ($item['content'] ?? '') : $item;
            $html .= "<li>{$content}</li>";
        }
        $html .= "</{$style}>";

        return $html;
    }

    private function renderImage(array $data): string
    {
        $url = $data['file']['url'] ?? ($data['url'] ?? '');
        $caption = $data['caption'] ?? '';
        $stretched = !empty($data['stretched']) ? ' w-full' : '';
        $bordered = !empty($data['withBorder']) ? ' border border-border' : '';

        $html = "<figure class=\"my-4{$stretched}\">";
        $html .= "<img src=\"{$url}\" alt=\"" . htmlspecialchars(strip_tags($caption)) . "\" class=\"rounded-xl shadow-sm max-w-full{$bordered}\" loading=\"lazy\">";
        if ($caption) {
            $html .= "<figcaption class=\"text-sm text-muted-foreground mt-2 text-center\">{$caption}</figcaption>";
        }
        $html .= '</figure>';

        return $html;
    }

    private function renderTable(array $data): string
    {
        $content = $data['content'] ?? [];
        $withHeadings = $data['withHeadings'] ?? false;

        if (empty($content)) {
            return '';
        }

        $html = '<div class="overflow-x-auto my-4"><table>';

        foreach ($content as $i => $row) {
            if ($i === 0 && $withHeadings) {
                $html .= '<thead><tr>';
                foreach ($row as $cell) {
                    $html .= "<th>{$cell}</th>";
                }
                $html .= '</tr></thead><tbody>';
            } else {
                if ($i === 1 && $withHeadings) {
                    // tbody already opened
                } elseif ($i === 0) {
                    $html .= '<tbody>';
                }
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= "<td>{$cell}</td>";
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function renderCode(array $data): string
    {
        $code = htmlspecialchars($data['code'] ?? '');
        return "<pre><code>{$code}</code></pre>";
    }

    private function renderQuote(array $data): string
    {
        $text = $data['text'] ?? '';
        $caption = $data['caption'] ?? '';

        $html = "<blockquote><p>{$text}</p>";
        if ($caption) {
            $html .= "<cite class=\"text-sm text-muted-foreground\">— {$caption}</cite>";
        }
        $html .= '</blockquote>';

        return $html;
    }

    private function renderCallout(array $data, string $type): string
    {
        $title = $data['title'] ?? $data['message'] ?? '';
        $message = $data['message'] ?? $data['text'] ?? '';

        $typeClass = match ($type) {
            'warning' => 'callout-warning',
            'success' => 'callout-success',
            default => 'callout-info',
        };

        $icons = [
            'info' => '<svg class="w-5 h-5 text-blue-brand flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
            'warning' => '<svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
            'success' => '<svg class="w-5 h-5 text-accent-foreground flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        ];

        $icon = $icons[$type] ?? $icons['info'];

        return "<div class=\"callout {$typeClass}\">{$icon}<div>{$message}</div></div>";
    }

    private function renderChecklist(array $data): string
    {
        $items = $data['items'] ?? [];
        $html = '<ul class="mb-4 space-y-2">';

        foreach ($items as $item) {
            $checked = !empty($item['checked']);
            $text = $item['text'] ?? '';
            $icon = $checked
                ? '<svg class="w-5 h-5 text-accent-foreground flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
                : '<svg class="w-5 h-5 text-muted-foreground flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>';
            $strikethrough = $checked ? ' line-through text-muted-foreground' : '';
            $html .= "<li class=\"flex items-start gap-2{$strikethrough}\">{$icon}<span>{$text}</span></li>";
        }

        $html .= '</ul>';
        return $html;
    }

    private function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
