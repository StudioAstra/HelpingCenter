<?php

namespace App\Service;

class ArticleRenderer
{
    /**
     * Renders Editor.js JSON blocks into HTML.
     */
    public function renderBlocks(?array $blocks): string
    {
        if (!$blocks || !isset($blocks['blocks'])) {
            return '';
        }

        $html = '';
        foreach ($blocks['blocks'] as $block) {
            $html .= $this->renderBlock($block);
        }

        return $html;
    }

    /**
     * Extracts plain text from Editor.js blocks for search indexing.
     */
    public function extractText(?array $blocks): string
    {
        if (!$blocks || !isset($blocks['blocks'])) {
            return '';
        }

        $texts = [];
        foreach ($blocks['blocks'] as $block) {
            $text = $this->extractBlockText($block);
            if ($text) {
                $texts[] = $text;
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Extracts headings from Editor.js blocks for TOC generation.
     *
     * @return array<array{id: string, text: string, level: int}>
     */
    public function extractHeadings(?array $blocks): array
    {
        if (!$blocks || !isset($blocks['blocks'])) {
            return [];
        }

        $headings = [];
        foreach ($blocks['blocks'] as $block) {
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
