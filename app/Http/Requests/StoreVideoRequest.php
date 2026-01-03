<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['movie', 'series', 'anime'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'thumbnail' => ['nullable', 'url', 'max:500'],
            'banner' => ['nullable', 'url', 'max:500'],
            'cloudflare_video_uid' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 5)],
            'age_rating' => ['nullable', 'string', Rule::in(['G', 'PG', 'PG-13', 'R', 'NC-17', 'TV-Y', 'TV-Y7', 'TV-G', 'TV-PG', 'TV-14', 'TV-MA'])],
            'imdb_rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_trending' => ['boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Video type is required',
            'type.in' => 'Video type must be movie, series, or anime',
            'title.required' => 'Title is required',
            'title.max' => 'Title cannot exceed 255 characters',
            'description.required' => 'Description is required',
            'release_year.min' => 'Release year must be 1900 or later',
            'release_year.max' => 'Release year cannot be more than 5 years in the future',
            'imdb_rating.min' => 'IMDB rating must be between 0 and 10',
            'imdb_rating.max' => 'IMDB rating must be between 0 and 10',
            'categories.*.exists' => 'One or more categories do not exist',
            'genres.*.exists' => 'One or more genres do not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'cloudflare_video_uid' => 'Cloudflare video ID',
            'imdb_rating' => 'IMDB rating',
        ];
    }
}
