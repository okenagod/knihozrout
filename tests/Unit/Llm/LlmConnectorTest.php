<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmConnector;
use App\Services\Llm\LlmException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmConnectorTest extends TestCase
{
    private function connector(): LlmConnector
    {
        return new LlmConnector('http://llm.test:11434', 'default-model', 30, '5m');
    }

    public function test_json_sends_schema_and_images_and_decodes_response()
    {
        Http::fake([
            'llm.test:11434/api/chat' => Http::response([
                'model' => 'vision-model',
                'message' => ['role' => 'assistant', 'content' => '{"title": "Dračí sklizeň"}'],
                'done_reason' => 'stop',
            ]),
        ]);

        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];
        $result = $this->connector()->json('Přečti tiráž', $schema, images: ['binary-image'], model: 'vision-model');

        $this->assertSame(['title' => 'Dračí sklizeň'], $result);

        Http::assertSent(function (Request $request) use ($schema) {
            return $request->url() === 'http://llm.test:11434/api/chat'
                && $request['model'] === 'vision-model'
                && $request['stream'] === false
                && $request['format'] === $schema
                && $request['options']['temperature'] === 0
                && $request['keep_alive'] === '5m'
                && $request['messages'][0]['images'] === [base64_encode('binary-image')];
        });
    }

    public function test_complete_uses_default_model_and_system_prompt()
    {
        Http::fake([
            '*' => Http::response([
                'model' => 'default-model',
                'message' => ['role' => 'assistant', 'content' => 'Ahoj'],
                'total_duration' => 2_500_000_000,
            ]),
        ]);

        $this->assertSame('Ahoj', $this->connector()->complete('Pozdrav', system: 'Odpovídej česky'));

        Http::assertSent(fn (Request $request) => $request['model'] === 'default-model'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'Odpovídej česky']
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Pozdrav']);
    }

    public function test_chat_returns_metadata()
    {
        Http::fake([
            '*' => Http::response([
                'model' => 'm',
                'message' => ['content' => 'x'],
                'total_duration' => 2_500_000_000,
                'prompt_eval_count' => 10,
                'eval_count' => 3,
                'done_reason' => 'stop',
            ]),
        ]);

        $response = $this->connector()->chat([LlmConnector::message('user', 'x')]);

        $this->assertSame(2500, $response->durationMs);
        $this->assertSame(10, $response->promptTokens);
        $this->assertSame(3, $response->completionTokens);
        $this->assertSame('stop', $response->doneReason);
    }

    public function test_error_response_throws_exception()
    {
        Http::fake(['*' => Http::response(['error' => 'model "x" not found'], 404)]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('model "x" not found');

        $this->connector()->complete('x', model: 'x');
    }

    public function test_invalid_json_throws_exception()
    {
        Http::fake(['*' => Http::response(['model' => 'm', 'message' => ['content' => 'tohle není JSON']])]);

        $this->expectException(LlmException::class);

        $this->connector()->json('x', ['type' => 'object']);
    }

    public function test_models_lists_names()
    {
        Http::fake(['*/api/tags' => Http::response(['models' => [['name' => 'qwen2.5vl:7b'], ['name' => 'qwen3-coder:30b']]])]);

        $this->assertSame(['qwen2.5vl:7b', 'qwen3-coder:30b'], $this->connector()->models());
    }
}
