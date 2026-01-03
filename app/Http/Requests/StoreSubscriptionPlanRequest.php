<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'max:50', 'unique:subscription_plans,plan_id', 'regex:/^[a-z0-9_-]+$/'],
            'tier' => ['required', Rule::in(['basic', 'standard', 'premium'])],
            'name' => ['required', 'string', 'max:100'],
            'duration' => ['required', Rule::in(['monthly', 'annual'])],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['required', 'string', 'max:255'],
            'is_popular' => ['boolean'],
            'savings_text' => ['nullable', 'string', 'max:50'],
            'discount_text' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Plan ID is required',
            'plan_id.unique' => 'This plan ID already exists',
            'plan_id.regex' => 'Plan ID must contain only lowercase letters, numbers, hyphens, and underscores',
            'tier.in' => 'Tier must be basic, standard, or premium',
            'duration.in' => 'Duration must be monthly or annual',
            'price.min' => 'Price must be a positive number',
            'currency.size' => 'Currency must be a 3-letter code (e.g., MVR, USD)',
            'currency.regex' => 'Currency must be uppercase letters only',
            'features.required' => 'At least one feature is required',
            'features.*.required' => 'Feature cannot be empty',
        ];
    }
}
