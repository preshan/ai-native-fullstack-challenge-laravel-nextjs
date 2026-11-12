<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassifyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'requestId' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messageText(): string
    {
        return trim($this->string('message')->toString());
    }

    public function requestId(): ?string
    {
        $requestId = $this->input('requestId');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }
}
