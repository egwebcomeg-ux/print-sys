<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Record / remove customer payments (deposits and settlements).
 */
class PaymentController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount_egp' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs', 'id')->where('customer_id', $customer->id)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['job_id.exists' => 'الشغلانة دي مش تبع العميل ده']);

        $payment = $customer->payments()->create($data + ['user_id' => $request->user()->id]);

        $amount = number_format((float) $payment->amount_egp, 2);
        ActivityLog::record('customer', $customer->id, 'payment', "دفعة {$amount} ج ({$payment->method->label()})");
        if ($payment->job_id) {
            ActivityLog::record('job', $payment->job_id, 'payment', "دفعة {$amount} ج ({$payment->method->label()})");
        }

        $this->toast("تم تسجيل دفعة {$amount} ج");

        return back();
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        ActivityLog::record('customer', $payment->customer_id, 'payment_deleted', 'مسح دفعة '.number_format((float) $payment->amount_egp, 2).' ج');
        $payment->delete();
        $this->toast('تم مسح الدفعة');

        return back();
    }
}
