<?php

namespace App\Services\Llm;

/**
 * Chyba komunikace s LLM – nedostupný server, chybová odpověď nebo nevalidní výstup modelu.
 */
class LlmException extends \RuntimeException {}
