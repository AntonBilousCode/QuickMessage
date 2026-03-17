<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'min:50', 'max:2048'],
            // Nullable: uploaded only when the PBKDF2-derived AES key is available in sessionStorage.
            // Without it the private key can't be encrypted, so we store only the public key.
            'encrypted_private_key' => ['nullable', 'string', 'min:200', 'max:8192'],
        ];
    }
}
