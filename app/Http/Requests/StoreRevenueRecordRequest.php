<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\RevenueRecord;

class StoreRevenueRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Wszyscy zalogowani użytkownicy mogą wprowadzać dane
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
            ],
            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'source' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Sprawdź unikalność year + month
            $year = $this->input('year');
            $month = $this->input('month');

            if ($year && $month) {
                $exists = RevenueRecord::where('year', $year)
                    ->where('month', $month)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'month',
                        'Dla wybranego roku i miesiąca już istnieje rekord przychodu.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'year.required' => 'Rok jest wymagany.',
            'year.integer' => 'Rok musi być liczbą całkowitą.',
            'year.min' => 'Rok musi być większy lub równy 2000.',
            'year.max' => 'Rok musi być mniejszy lub równy 2100.',
            'month.required' => 'Miesiąc jest wymagany.',
            'month.integer' => 'Miesiąc musi być liczbą całkowitą.',
            'month.min' => 'Miesiąc musi być między 1 a 12.',
            'month.max' => 'Miesiąc musi być między 1 a 12.',
            'amount.required' => 'Kwota jest wymagana.',
            'amount.numeric' => 'Kwota musi być liczbą.',
            'amount.min' => 'Kwota nie może być ujemna.',
            'amount.max' => 'Kwota jest zbyt duża.',
            'notes.max' => 'Notatki nie mogą przekraczać 1000 znaków.',
            'source.max' => 'Źródło nie może przekraczać 50 znaków.',
        ];
    }
}
