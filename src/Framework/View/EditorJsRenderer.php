<?php

namespace Echo\Framework\View;

class EditorJsRenderer
{
    /**
     * Convert Editor.js JSON (or legacy plain text) into HTML.
     */
    public static function render(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['blocks'])) {
            // Legacy plain text — wrap paragraphs
            return '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $html = '';
        foreach ($data['blocks'] as $block) {
            $html .= self::renderBlock($block);
        }

        return $html;
    }

    private static function renderBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        $data = $block['data'] ?? [];

        return match ($type) {
            'paragraph' => self::paragraph($data),
            'header' => self::header($data),
            'list' => self::list($data),
            'code' => self::code($data),
            'image' => self::image($data),
            'quote' => self::quote($data),
            'delimiter' => '<hr>',
            'raw' => $data['html'] ?? '',
            'embed' => self::embed($data),
            default => '',
        };
    }

    private static function paragraph(array $data): string
    {
        return '<p>' . ($data['text'] ?? '') . '</p>';
    }

    private static function header(array $data): string
    {
        $level = max(1, min(6, (int) ($data['level'] ?? 2)));
        $text = $data['text'] ?? '';
        return "<h{$level}>{$text}</h{$level}>";
    }

    private static function list(array $data): string
    {
        $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
        $items = $data['items'] ?? [];
        $html = "<{$tag}>";
        foreach ($items as $item) {
            $html .= '<li>' . (is_string($item) ? $item : ($item['content'] ?? '')) . '</li>';
        }
        $html .= "</{$tag}>";
        return $html;
    }

    private static function code(array $data): string
    {
        $code = htmlspecialchars($data['code'] ?? '', ENT_QUOTES, 'UTF-8');
        return '<pre><code>' . $code . '</code></pre>';
    }

    private static function image(array $data): string
    {
        $url = $data['file']['url'] ?? '';
        $caption = $data['caption'] ?? '';
        $html = '<figure class="editorjs-image">';
        $html .= '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
        $html .= ' alt="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '">';
        if ($caption) {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }

    private static function quote(array $data): string
    {
        $text = $data['text'] ?? '';
        $caption = $data['caption'] ?? '';
        $html = '<blockquote><p>' . $text . '</p>';
        if ($caption) {
            $html .= '<cite>' . $caption . '</cite>';
        }
        $html .= '</blockquote>';
        return $html;
    }

    private static function embed(array $data): string
    {
        $src = $data['embed'] ?? '';
        $width = $data['width'] ?? 600;
        $height = $data['height'] ?? 300;
        $caption = $data['caption'] ?? '';
        $html = '<div class="editorjs-embed">';
        $html .= '<iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
        $html .= ' width="' . (int) $width . '" height="' . (int) $height . '"';
        $html .= ' frameborder="0" allowfullscreen></iframe>';
        if ($caption) {
            $html .= '<p>' . $caption . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
