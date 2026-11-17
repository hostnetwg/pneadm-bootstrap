<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Services\GrowthOS\AI\Support\GrowthAiPromptLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PromptController extends Controller
{
    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(GrowthAiPromptLibrary::has($key), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.GrowthAiPromptLibrary::MAX_CHARS, function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || trim($value) === '') {
                    $fail('Wpisz treść promptu.');
                }
            }],
        ], [
            'body.required' => 'Wpisz treść promptu.',
            'body.max' => 'Prompt jest zbyt długi.',
        ]);

        GrowthAiPromptLibrary::save($key, $data['body'], $request->user()?->id);

        return $this->backToPrompt($key, 'Zapisano prompt. Następne wywołanie API użyje tej treści we wszystkich projektach.');
    }

    public function reset(string $key): RedirectResponse
    {
        abort_unless(GrowthAiPromptLibrary::has($key), 404);

        GrowthAiPromptLibrary::reset($key);

        return $this->backToPrompt($key, 'Przywrócono prompt z kodu.');
    }

    private function backToPrompt(string $key, string $message): RedirectResponse
    {
        return redirect()
            ->back()
            ->with('success', $message)
            ->with('prompt_key', $key)
            ->withFragment('prompt-'.$key);
    }
}
