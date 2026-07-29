<?php

namespace App\Http\Requests\CourtBooking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourtBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,court_id',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'payment_method' => 'required|in:cash,vnpay,momo,bank_transfer',
            'lock_token' => 'required|string|max:64',
            'services' => 'nullable|array',
            'services.*.service_id' => 'required_with:services|integer|exists:court_services,service_id',
            'services.*.quantity' => 'required_with:services|integer|min:1|max:99',
        ];
    }
}
