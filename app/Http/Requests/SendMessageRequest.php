<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $recipient */
        $recipient = $this->route('user');

        return $this->user() !== null
            && $recipient !== null
            && $this->user()->id !== $recipient->id;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:30000'],
        ];
    }
}
