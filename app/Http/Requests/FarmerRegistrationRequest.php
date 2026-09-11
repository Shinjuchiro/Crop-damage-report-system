<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * All farmer registration validation lives here, kept out of the controller.
 * Note the conditional rules: tenant fields are required ONLY for tenants,
 * and the barangay certificate is required ONLY for land owners.
 */
class FarmerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public registration page
    }

    public function rules(): array
    {
        return [
            // --- Personal information ---
            'first_name'    => ['required', 'string', 'max:100'],
            'middle_name'   => ['nullable', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'sex'           => ['required', 'in:male,female'],
            'phone_number'  => ['required', 'string', 'max:20'],
            'address'       => ['required', 'string', 'max:1000'],
            'barangay_id'   => ['required', 'exists:barangays,id'],
            'association_id'=> ['required', Rule::exists('associations', 'id')->whereNull('archived_at')],

            // --- Account information ---
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // --- Farm ownership ---
            'ownership_type' => ['required', 'in:land_owner,tenant'],

            // Tenant-only (required when tenant)
            'landowner_name'     => ['required_if:ownership_type,tenant', 'nullable', 'string', 'max:255'],
            'landowner_contact'  => ['nullable', 'string', 'max:20'],
            'landowner_location' => ['required_if:ownership_type,tenant', 'nullable', 'string', 'max:255'],

            // Land owner-only: the Barangay Certificate proving the farmer owns the land.
            // Image or PDF, since these are usually scanned or photographed.
            'barangay_certificate' => [
                'required_if:ownership_type,land_owner',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],

            // --- Farm information ---
            'farm_size_hectares' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            // --- Main crops (at least one required) ---
            'crops'                 => ['required', 'array', 'min:1'],
            'crops.*.crop_id'       => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'landowner_name.required_if'       => 'Please enter the land owner\'s full name.',
            'landowner_location.required_if'   => 'Please enter the land owner\'s location.',
            'barangay_certificate.required_if' => 'Land owners must upload a Barangay Certificate proving they own the land.',
            'crops.required'                   => 'Please add at least one main crop.',
        ];
    }
}
