<?php

namespace App\Http\Requests\Import;

use App\Enums\ChessComTimeClass;
use App\Enums\GameColorFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportChessComGamesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'from_date' => ['required', 'date', 'before_or_equal:to_date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date', 'before_or_equal:today'],
            'time_classes' => ['required', 'array', 'min:1'],
            'time_classes.*' => ['required', 'string', Rule::enum(ChessComTimeClass::class)],
            'color' => ['required', 'string', Rule::enum(GameColorFilter::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain letters, numbers, underscores, and hyphens.',
            'time_classes.min' => 'Select at least one time control.',
        ];
    }

    public function username(): string
    {
        return strtolower($this->string('username')->toString());
    }

    /**
     * @return list<ChessComTimeClass>
     */
    public function timeClasses(): array
    {
        return collect($this->validated('time_classes'))
            ->map(fn (string $timeClass) => ChessComTimeClass::from($timeClass))
            ->all();
    }

    public function colorFilter(): GameColorFilter
    {
        return GameColorFilter::from($this->string('color')->toString());
    }
}
