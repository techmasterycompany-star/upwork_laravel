<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    /**
     * Issue #43: conversational assistant restricted to job-search/career topics.
     * Stateless - the client sends the conversation history each time.
     * Does NOT query any platform data (jobs, applications, users, etc.) -
     * it only ever talks to the Gemini API with the text the client sends.
     */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|in:user,model',
            'history.*.text' => 'required_with:history|string|max:2000',
        ]);

        $systemInstruction = <<<PROMPT
        You are a career and job-search assistant embedded in a job board platform.

        You may ONLY help with topics such as: resume/CV advice, interview preparation,
        career planning, job-search strategy, workplace skills, salary negotiation basics,
        and general career-related questions.

        You must politely decline anything outside that scope (e.g. coding help unrelated to
        careers, general trivia, personal advice unrelated to work, medical/legal/financial
        advice, or any other off-topic request). When declining, briefly say this assistant
        only handles career and job-search topics, and invite the user to ask something in
        that scope.

        You do NOT have access to this platform's live data (no specific job listings,
        no user accounts, no application statuses). If asked about anything requiring
        live platform data, say you don't have access to that and suggest they check the
        relevant section of the site instead.

        Keep responses concise and practical.
        PROMPT;

        $history = collect($data['history'] ?? [])
            ->map(fn (array $turn) => ['role' => $turn['role'], 'text' => $turn['text']])
            ->values()
            ->all();

        $history[] = ['role' => 'user', 'text' => $data['message']];

        $reply = GeminiService::chat($systemInstruction, $history);

        if (! $reply) {
            return response()->json([
                'success' => false,
                'message' => 'The assistant is unavailable right now. Please try again shortly.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'reply' => $reply,
        ]);
    }
}