<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DataCompletionFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Formularz publiczny
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'birth_date' => [
                'required',
                'string',
                'regex:/^\d{2}-\d{2}-\d{4}$/', // Format DD-MM-RRRR
            ],
            'birth_place' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'birth_date.required' => 'Data urodzenia jest wymagana.',
            'birth_date.regex' => 'Data urodzenia musi być w formacie DD-MM-RRRR (np. 15-03-1985).',
            'birth_place.required' => 'Miejsce urodzenia jest wymagane.',
            'birth_place.max' => 'Miejsce urodzenia nie może przekraczać 255 znaków.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Usuń spacje z daty
        if ($this->has('birth_date')) {
            $this->merge([
                'birth_date' => str_replace(' ', '', $this->input('birth_date')),
            ]);
        }

        // Trim miejsca urodzenia
        if ($this->has('birth_place')) {
            $this->merge([
                'birth_place' => trim($this->input('birth_place')),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $birthDate = $this->input('birth_date');
            
            if ($birthDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $birthDate)) {
                $parts = explode('-', $birthDate);
                $day = (int)$parts[0];
                $month = (int)$parts[1];
                $year = (int)$parts[2];

                // Walidacja sensowności daty
                if ($year < 1900 || $year > now()->year) {
                    $validator->errors()->add('birth_date', 'Rok urodzenia musi być między 1900 a ' . now()->year);
                }

                if ($month < 1 || $month > 12) {
                    $validator->errors()->add('birth_date', 'Miesiąc musi być między 01 a 12');
                }

                if ($day < 1 || $day > 31) {
                    $validator->errors()->add('birth_date', 'Dzień musi być między 01 a 31');
                }

                // Sprawdź czy data jest poprawna (np. nie 31 lutego)
                if (!checkdate($month, $day, $year)) {
                    $validator->errors()->add('birth_date', 'Nieprawidłowa data urodzenia.');
                }

                // Sprawdź czy data nie jest w przyszłości
                $date = \Carbon\Carbon::createFromFormat('d-m-Y', $birthDate);
                if ($date->isFuture()) {
                    $validator->errors()->add('birth_date', 'Data urodzenia nie może być w przyszłości.');
                }
            }
        });
    }
}

