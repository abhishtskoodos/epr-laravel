<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vendors.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'pan' => ['required', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/', 'unique:vendors,pan'],
            'gst' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', 'unique:vendors,gst'],
            'cin' => ['nullable', 'string', 'max:21'],
            'email' => ['nullable', 'email'],
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'regex:/^\d{6}$/'],
            'country' => ['nullable', 'size:2'],
            'entity_type' => ['required', 'in:private_ltd,public_ltd,partnership,llp,proprietorship,society,trust,section_8'],
        ];
    }
}
