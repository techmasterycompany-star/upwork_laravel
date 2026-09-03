<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Send a prompt to Gemini and expect a JSON object back.
     * Returns the decoded array, or null on failure.
     */
    public static function generateJson(string $prompt): ?array
    {
        $apiKey = config('services.gemini.key');
        $model  = config('services.gemini.model', 'gemini-2.0-flash');

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.7,
                ],
            ]
        );

        if ($response->failed()) {
            Log::error('Gemini API request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! $text) {
            Log::warning('Gemini API returned no text content', ['response' => $response->json()]);
            return null;
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Gemini API returned invalid JSON', ['text' => $text]);
            return null;
        }

        return $decoded;
    }

    /**
     * Send a system instruction + conversation history to Gemini and get a plain text reply.
     * Used for the career chatbot (Issue #43) where we want free-form text, not JSON.
     *
     * @param string $systemInstruction
     * @param array<int, array{role: string, text: string}> $history  role is 'user' or 'model'
     */
    public static function chat(string $systemInstruction, array $history): ?string
    {
        $apiKey = config('services.gemini.key');
        $model  = config('services.gemini.model', 'gemini-2.0-flash');

        $contents = array_map(
            fn (array $turn) => [
                'role'  => $turn['role'],
                'parts' => [['text' => $turn['text']]],
            ],
            $history
        );

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'system_instruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.6,
                ],
            ]
        );

        if ($response->failed()) {
            Log::error('Gemini chat API request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! $text) {
            Log::warning('Gemini chat API returned no text content', ['response' => $response->json()]);
            return null;
        }

        return trim($text);
    }
}