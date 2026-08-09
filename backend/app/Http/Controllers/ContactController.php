<?php

namespace App\Http\Controllers;

use App\Events\UserNotificationEvent;
use App\Helpers\ProfanityFilter;
use App\Models\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class ContactController extends Controller
{
    public function SubmitContactEmail(Request $request)
    {
        // Rate limit: tối đa 3 lần đăng ký / giờ / IP
        $key = 'newsletter:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 3)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'status' => 'error',
                'message' => "Bạn đăng ký quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ], 429)->header('Retry-After', $seconds);
        }
        RateLimiter::hit($key, 3600);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        Contact::create([
            'name' => 'Newsletter Subscriber',
            'email' => $request->email,
            'subject' => 'Đăng ký nhận bản tin',
            'message' => 'Khách hàng đăng ký nhận tin từ footer.',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký nhận tin thành công!',
        ], 201);
    }

    private function verifyTurnstile(?string $token): bool
    {
        if (! $token) {
            return false;
        }
        $secretKey = config('services.turnstile.secret_key');
        if (! $secretKey) {
            return false;
        }
        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return $response->json('success', false);
        } catch (\Exception $e) {
            Log::error('Turnstile verification failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * User gửi form liên hệ (Public)
     */
    public function SubmitContact(Request $request)
    {
        // Rate limit theo IP: tối đa 5 lần gửi / giờ
        $ipKey = 'contact:ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return response()->json([
                'status' => 'error',
                'message' => "Bạn gửi yêu cầu quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ], 429)->header('Retry-After', $seconds);
        }

        $turnstileToken = $request->input('turnstile_token');
        if (! $this->verifyTurnstile($turnstileToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'subject.required' => 'Vui lòng chọn chủ đề.',
            'message.required' => 'Vui lòng nhập nội dung.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (ProfanityFilter::hasProfanity($request->name) ||
                ProfanityFilter::hasProfanity($request->subject) ||
                ProfanityFilter::hasProfanity($request->message)) {
                $validator->errors()->add('message', 'Nội dung chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa lại.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rate limit theo email: tối đa 3 lần / giờ (chặn spam qua nhiều IP)
        $emailKey = 'contact:email:'.md5(strtolower($request->email));
        if (RateLimiter::tooManyAttempts($emailKey, maxAttempts: 3)) {
            $seconds = RateLimiter::availableIn($emailKey);

            return response()->json([
                'status' => 'error',
                'message' => "Email này đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau {$seconds} giây.",
            ], 429)->header('Retry-After', $seconds);
        }

        // Ghi hit sau khi vượt qua cả 2 check
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($emailKey, 3600);

        $filteredMessage = ProfanityFilter::filter($request->message);

        Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $filteredMessage,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Yêu cầu hỗ trợ đã được gửi thành công! Chúng tôi sẽ phản hồi trong 24 giờ.',
        ], 201);
    }

    /**
     * Admin xem danh sách contacts
     */
    public function index(Request $request)
    {
        $query = Contact::query()->orderBy('created_at', 'desc');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Filter status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $contacts = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $contacts->items(),
            'total' => $contacts->total(),
            'current_page' => $contacts->currentPage(),
            'last_page' => $contacts->lastPage(),
        ]);
    }

    /**
     * Admin trả lời liên hệ qua email
     */
    public function reply(Request $request, $id)
    {
        $contact = Contact::find($id);

        if (! $contact) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy liên hệ.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reply' => 'required|string|max:10000',
        ], [
            'reply.required' => 'Vui lòng nhập nội dung phản hồi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Gửi email phản hồi
        try {
            $emailUser = config('mail.mailers.smtp.username');
            $emailPass = config('mail.mailers.smtp.password');

            if (! $emailUser || ! $emailPass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chưa cấu hình email. Vui lòng kiểm tra .env.',
                ], 500);
            }

            $replyContent = $request->reply;
            $subject = "Re: {$contact->subject} — Ocean Store";

            $htmlBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">
                <div style="background:#E63B6F;padding:24px;border-radius:12px 12px 0 0;text-align:center">
                    <h2 style="color:#fff;margin:0;font-size:20px">Ocean Store — Phản hồi hỗ trợ</h2>
                </div>
                <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px">
                    <p style="color:#4b5563;margin:0 0 8px"><strong>Xin chào '.htmlspecialchars($contact->name).',</strong></p>
                    <p style="color:#6b7280;font-size:14px;margin:0 0 16px">Cảm ơn bạn đã liên hệ với chúng tôi về: <em>"'.htmlspecialchars($contact->subject).'"</em></p>
                    <div style="background:#FFF0F3;border-left:4px solid #E63B6F;padding:16px;border-radius:8px;margin:0 0 16px">
                        <p style="color:#1a1a2e;margin:0;white-space:pre-wrap">'.htmlspecialchars($replyContent).'</p>
                    </div>
                    <p style="color:#6b7280;font-size:13px;margin:0">Nếu cần thêm hỗ trợ, hãy trả lời email này hoặc liên hệ Hotline 1900-OCEAN.</p>
                </div>
                <p style="text-align:center;color:#9ca3af;font-size:12px;margin-top:16px">© 2026 Ocean Store. All rights reserved.</p>
            </div>';

            $transport = new EsmtpTransport(
                'smtp.gmail.com', 587, false
            );
            $transport->setUsername($emailUser);
            $transport->setPassword($emailPass);

            $mailer = new Mailer($transport);

            $email = (new Email)
                ->from($emailUser)
                ->to($contact->email)
                ->subject($subject)
                ->html($htmlBody);

            $mailer->send($email);

            // --- Ghi notification cho khách hàng (nếu khách là User hệ thống) ---
            try {
                $user = User::where('email', $contact->email)->first();
                if ($user) {
                    $notificationData = [
                        'title' => 'Phản hồi hỗ trợ',
                        'message' => 'Admin đã trả lời yêu cầu hỗ trợ của bạn: "'.$contact->subject.'".',
                        'type' => 'contact_reply',
                    ];

                    DB::table('notifications')->insert([
                        'id' => Str::uuid(),
                        'type' => 'App\Notifications\ContactReplyNotification',
                        'notifiable_type' => User::class,
                        'notifiable_id' => $user->user_id,
                        'data' => json_encode($notificationData),
                        'read_at' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    // Broadcast realtime event
                    event(new UserNotificationEvent($user->user_id, $notificationData));
                }
            } catch (\Exception $ex) {
                Log::error('Save contact reply notification failed: '.$ex->getMessage());
            }

        } catch (\Exception $e) {
            Log::error('Contact reply email error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gửi email thất bại, vui lòng thử lại sau.',
            ], 500);
        }

        // Cập nhật contact
        $contact->update([
            'admin_reply' => $request->reply,
            'status' => 'replied',
            'replied_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi phản hồi thành công!',
        ]);
    }

    /**
     * Admin xóa liên hệ
     */
    public function destroy($id)
    {
        $contact = Contact::find($id);

        if (! $contact) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy liên hệ.',
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa liên hệ thành công!',
        ]);
    }
}
