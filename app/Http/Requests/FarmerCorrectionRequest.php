<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a farmer may change when their application has been returned for
 * correction (revision step 6).
 *
 * WHY THIS IS NOT FarmerRegistrationRequest
 *
 * Three of that request's rules cannot apply here and one of them would
 * lock the farmer out. 'username' => unique:users,username and the same for
 * email would fail against the farmer's OWN account, because by now the row
 * exists; the farmer would be told their username is taken by themselves.
 * The password rules do not belong either: a correction is not a password
 * change, and sending one back through the registration request would
 * require the farmer to retype their password to fix a misspelled barangay.
 * And the barangay certificate is 'required_if:ownership_type,land_owner'
 * there, which is right at registration and wrong here, where the farmer
 * already uploaded one and is not necessarily being asked to replace it.
 *
 * So credentials are deliberately absent: this form cannot change the
 * username, the email or the password. Those are the account, not the
 * application, and the farmer has Settings for them once approved.
 *
 * The association is absent for a different reason. A farmer who picked the
 * wrong association cannot quietly move themselves to another one while
 * that association is reviewing their application, because the association
 * reading it would change underneath the officer. Moving an applicant
 * between associations is an office job.
 *
 * Everything else is identical to the registration rules, on purpose: a
 * correction that passed a looser test than the original form would let a
 * farmer fix one field and break another.
 */
class FarmerCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller does the real check: this has to be the signed-in
        // farmer's own application, and it has to actually be returned for
        // correction. Neither is knowable from the request body.
        return true;
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
            // Same digits:11 as registration: it pins the length and rejects
            // anything that is not a digit, so a number the SMS provider
            // cannot dial never reaches the database.
            'phone_number'  => ['required', 'digits:11'],
            'address'       => ['required', 'string', 'max:1000'],
            'barangay_id'   => ['required', 'exists:barangays,id'],

            // --- Farm ownership ---
            'ownership_type' => ['required', 'in:land_owner,tenant'],

            'landowner_name'     => ['required_if:ownership_type,tenant', 'nullable', 'string', 'max:255'],
            'landowner_contact'  => ['nullable', 'string', 'max:20'],
            'landowner_location' => ['required_if:ownership_type,tenant', 'nullable', 'string', 'max:255'],

            /* Optional here, unlike registration. A land owner who already
               uploaded a certificate and is only fixing their address should
               not have to find the file again; the controller keeps the one
               on record when nothing new is sent. The controller is also
               what requires a certificate from a farmer who has switched to
               land owner and has none on file, which is a rule this request
               cannot express: it depends on what is already stored. */
            'barangay_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            // --- Farm information ---
            'farm_size_hectares' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            // --- Main crops (at least one) ---
            'crops'                => ['required', 'array', 'min:1'],
            'crops.*.crop_id'      => ['required', Rule::exists('crops', 'id')->whereNull('archived_at')],
            'crops.*.crop_specify' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'landowner_name.required_if'     => 'Please enter the land owner\'s full name.',
            'landowner_location.required_if' => 'Please enter the land owner\'s location.',
            'crops.required'                 => 'Please keep at least one main crop.',
            'phone_number.digits'            => 'Please enter an 11-digit mobile number, for example 09171234567.',
            'phone_number.required'          => 'Please enter your mobile number.',
            'barangay_certificate.mimes'     => 'The certificate has to be a JPG, PNG or PDF file.',
            'barangay_certificate.max'       => 'The certificate has to be smaller than 5MB.',
        ];
    }
}
