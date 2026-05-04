<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use App\Support\Aadhaar;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('candidates.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'aadhaar' => ['required', 'regex:/^\d{12}$/'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'dob' => ['required', 'date', 'before:today'],
            'category' => ['required', 'in:gen,obc,sc,st,pwd,minority,ews'],
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'email' => ['nullable', 'email'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'regex:/^\d{6}$/'],
            'education_level' => ['required', 'in:below_8,8_pass,10_pass,12_pass,iti,diploma,graduate,pg'],
            'registered_via' => ['nullable', 'in:self,vendor,bulk_import'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $aadhaar = $this->input('aadhaar');
            if ($aadhaar && Aadhaar::isValid($aadhaar)) {
                $token = Aadhaar::tokenize($aadhaar);
                $exists = Candidate::where('aadhaar_token', $token)->exists();
                if ($exists) {
                    $v->errors()->add('aadhaar', 'A candidate with this Aadhaar is already registered.');
                }
            }
        });
    }
}
