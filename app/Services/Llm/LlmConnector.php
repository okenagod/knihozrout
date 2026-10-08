<?php

namespace App\Services\Llm;

use App\DTO\LlmResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Obecný klient pro lokální LLM běžící na Ollamě (https://github.com/ollama/ollama/blob/main/docs/api.md).
 *
 * Není vázaný na knihy – používej ho pro jakoukoli komunikaci s LLM:
 *  - chat()     – nízkoúrovňové volání /api/chat s vlastními zprávami,
 *  - complete() – jeden prompt → text,
 *  - json()     – jeden prompt → pole podle JSON schématu (structured output),
 *  - obrázky se předávají jako binární obsah (string), connector je sám zakóduje do base64.
 */
class LlmConnector
{
    public function __construct(
        private string $baseUrl,
        private string $defaultModel,
        private int $timeout = 300,
        private ?string $keepAlive = null,
    ) {}

    /**
     * Sestaví zprávu pro chat(). $images = pole binárních obsahů obrázků (jen pro vision modely).
     */
    public static function message(string $role, string $content, array $images = []): array
    {
        $message = ['role' => $role, 'content' => $content];

        if ($images)
        {
            $message['images'] = array_map(fn (string $image) => base64_encode($image), $images);
        }

        return $message;
    }

    /**
     * Nízkoúrovňové volání /api/chat.
     *
     * @param array             $messages Zprávy ve formátu Ollamy, viz message()
     * @param string|null       $model    Model, výchozí z konfigurace (services.llm.model)
     * @param array|string|null $format   'json' nebo JSON schéma pro structured output
     * @param array             $options  Parametry modelu (temperature, num_ctx, seed…)
     */
    public function chat(array $messages, ?string $model = null, array|string|null $format = null, array $options = []): LlmResponse
    {
        $payload = [
            'model' => $model ?? $this->defaultModel,
            'messages' => $messages,
            'stream' => false,
        ];

        if ($format !== null)
        {
            $payload['format'] = $format;
        }

        if ($options)
        {
            $payload['options'] = $options;
        }

        if ($this->keepAlive !== null)
        {
            $payload['keep_alive'] = $this->keepAlive;
        }

        try
        {
            $response = $this->request()->post('/api/chat', $payload);
        }
        catch (ConnectionException $e)
        {
            throw new LlmException('LLM server není dostupný (' . $this->baseUrl . '): ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful())
        {
            $error = $response->json('error') ?? $response->body();

            throw new LlmException("LLM vrátilo chybu {$response->status()}: {$error}");
        }

        $data = $response->json();

        if (!isset($data['message']['content']))
        {
            throw new LlmException('Neočekávaná odpověď LLM: ' . mb_substr($response->body(), 0, 500));
        }

        return new LlmResponse(
            content: $data['message']['content'],
            model: $data['model'] ?? $payload['model'],
            durationMs: isset($data['total_duration']) ? intdiv($data['total_duration'], 1_000_000) : null,
            promptTokens: $data['prompt_eval_count'] ?? null,
            completionTokens: $data['eval_count'] ?? null,
            doneReason: $data['done_reason'] ?? null,
        );
    }

    /**
     * Jeden prompt → textová odpověď.
     */
    public function complete(string $prompt, ?string $system = null, array $images = [], ?string $model = null, array $options = []): string
    {
        return $this->chat($this->buildMessages($prompt, $system, $images), $model, null, $options)->content;
    }

    /**
     * Jeden prompt → strukturovaná data podle JSON schématu.
     *
     * @param  array $schema JSON schéma odpovědi (Ollama structured outputs)
     * @return array Dekódovaný JSON
     *
     * @throws LlmException Pokud model nevrátí validní JSON
     */
    public function json(string $prompt, array $schema, ?string $system = null, array $images = [], ?string $model = null, array $options = []): array
    {
        $response = $this->chat($this->buildMessages($prompt, $system, $images), $model, $schema, $options + ['temperature' => 0]);

        $decoded = json_decode($response->content, true);

        if (!is_array($decoded))
        {
            throw new LlmException('LLM nevrátilo validní JSON: ' . mb_substr($response->content, 0, 500));
        }

        return $decoded;
    }

    /**
     * Seznam modelů dostupných na serveru.
     *
     * @return string[]
     */
    public function models(): array
    {
        try
        {
            $response = $this->request()->timeout(10)->get('/api/tags');
        }
        catch (ConnectionException $e)
        {
            throw new LlmException('LLM server není dostupný (' . $this->baseUrl . '): ' . $e->getMessage(), 0, $e);
        }

        return collect($response->json('models', []))->pluck('name')->all();
    }

    public function isAvailable(): bool
    {
        try
        {
            return $this->request()->timeout(5)->get('/api/version')->successful();
        }
        catch (ConnectionException)
        {
            return false;
        }
    }

    private function buildMessages(string $prompt, ?string $system, array $images): array
    {
        $messages = [];

        if ($system !== null)
        {
            $messages[] = self::message('system', $system);
        }

        $messages[] = self::message('user', $prompt, $images);

        return $messages;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout($this->timeout);
    }
}
