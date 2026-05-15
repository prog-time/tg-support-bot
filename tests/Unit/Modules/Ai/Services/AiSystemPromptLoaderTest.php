<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ai\Services;

use App\Modules\Ai\Services\AiSystemPromptLoader;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

class AiSystemPromptLoaderTest extends TestCase
{
    private string $templatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = sys_get_temp_dir() . '/ai-system-prompt-' . uniqid() . '.blade.php';
        file_put_contents(
            $this->templatePath,
            'Bot: {{ $botName }}, Platform: {{ $platform }}, Today: {{ $today }}',
        );

        Config::set('ai.system_prompt_path', $this->templatePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->templatePath);
        Mockery::close();

        parent::tearDown();
    }

    public function test_render_substitutes_variables(): void
    {
        $rendered = (new AiSystemPromptLoader())->render([
            'botName' => 'Support Bot',
            'platform' => 'telegram',
            'today' => '2026-05-16',
        ]);

        $this->assertSame('Bot: Support Bot, Platform: telegram, Today: 2026-05-16', $rendered);
    }

    public function test_render_memoizes_for_identical_vars(): void
    {
        $calls = 0;
        $view = Mockery::mock(ViewContract::class);
        $view->shouldReceive('render')->andReturn('Cached output');

        View::shouldReceive('file')->andReturnUsing(function (string $path, array $vars) use (&$calls, $view) {
            $calls++;

            return $view;
        });

        $loader = new AiSystemPromptLoader();

        $first = $loader->render(['botName' => 'Cached', 'platform' => 'telegram', 'today' => '2026-05-16']);
        $second = $loader->render(['botName' => 'Cached', 'platform' => 'telegram', 'today' => '2026-05-16']);

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls, 'View::file should be called only once for identical $vars');
    }

    public function test_render_re_renders_when_vars_change(): void
    {
        $calls = 0;

        View::shouldReceive('file')->andReturnUsing(function (string $path, array $vars) use (&$calls) {
            $calls++;
            $view = Mockery::mock(ViewContract::class);
            $view->shouldReceive('render')->andReturn('Bot: ' . $vars['botName']);

            return $view;
        });

        $loader = new AiSystemPromptLoader();

        $a = $loader->render(['botName' => 'A', 'platform' => 'telegram', 'today' => '2026-05-16']);
        $b = $loader->render(['botName' => 'B', 'platform' => 'telegram', 'today' => '2026-05-16']);

        $this->assertSame('Bot: A', $a);
        $this->assertSame('Bot: B', $b);
        $this->assertSame(2, $calls, 'View::file should be called once per distinct $vars set');
    }
}
