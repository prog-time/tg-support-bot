<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use Illuminate\Support\Facades\View;

class AiSystemPromptLoader
{
    private ?string $cached = null;

    private ?array $cachedVars = null;

    /**
     * Render the system prompt Blade template with the given variables.
     *
     * Result is memoized in the object's lifetime. Repeated calls with
     * identical $vars return the cached string without re-reading the
     * Blade file.
     *
     * @param array<string, mixed> $vars Variables exposed to the Blade template
     *
     * @return string Rendered prompt
     */
    public function render(array $vars = []): string
    {
        if ($this->cached !== null && $this->cachedVars === $vars) {
            return $this->cached;
        }

        $path = (string) config('ai.system_prompt_path');

        $this->cached = View::file($path, $vars)->render();
        $this->cachedVars = $vars;

        return $this->cached;
    }
}
