<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use Illuminate\Support\Str;

class CommunicationTemplateRenderer
{
    public function subject(CommunicationTemplate $template, array $variables, bool $preview = false): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(
            $this->replace($template->subject, $variables, $preview, false)
        ))), 255, '');
    }

    public function html(CommunicationTemplate $template, array $variables, bool $preview = false): string
    {
        // Markdown strips inline HTML and unsafe URLs before substitution.
        // Variables are replaced *after* markdown parsing with HTML entities.
        $source = $this->replace($template->body_markdown, $variables, $preview, true);
        return Str::markdown($source, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function replace(string $text, array $variables, bool $preview, bool $escape): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/D', (string) $key)) continue;
            $replace['{{'.$key.'}}'] = $escape
                ? e((string) $value)
                : (string) $value;
        }

        return strtr($text, $replace);
    }

    public function validateVariables(string $content, string $event): array
    {
        $allowed = config('communications.events.'.$event.'.variables', []);
        preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/', $content, $matches);
        return array_values(array_diff(array_unique($matches[1]), $allowed));
    }
}
