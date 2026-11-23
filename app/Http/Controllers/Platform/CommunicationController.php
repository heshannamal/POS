<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\SendCommunicationDelivery;
use App\Models\AuditLog;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\PlatformSetting;
use App\Services\CommunicationTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function templates(): View
    {
        $templates = CommunicationTemplate::with([])->orderBy('name')->get();
        $rules = CommunicationRule::all()->keyBy('event_key');
        return view('platform.communications.templates', compact('templates','rules'));
    }

    public function edit(CommunicationTemplate $template): View
    {
        abort_unless(isset(config('communications.events')[$template->event_key]), 404);
        $rule = CommunicationRule::where('event_key',$template->event_key)->firstOrFail();
        return view('platform.communications.edit', compact('template','rule'));
    }

    public function update(Request $request, CommunicationTemplate $template, CommunicationTemplateRenderer $renderer): RedirectResponse
    {
        $event = $template->event_key;
        abort_unless(isset(config('communications.events')[$event]), 404);
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'subject' => ['required','string','max:255'],
            'body_markdown' => ['required','string','max:30000'],
            'is_active' => ['required','boolean'],
        ]);

        $invalid = $renderer->validateVariables($data['subject']."\n".$data['body_markdown'], $event);
        if ($invalid) {
            throw ValidationException::withMessages([
                'body_markdown' => 'Unknown variables: '.implode(', ', $invalid),
            ]);
        }

        DB::transaction(function () use ($request,$template,$data): void {
            $template->update([
                ...$data,
                'allowed_variables' => (config('communications.events', [])[$template->event_key]['variables'] ?? []),
                'updated_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'communication.template.updated',
                'subject_type' => CommunicationTemplate::class,
                'subject_id' => $template->id,
                'metadata' => ['event' => $template->event_key, 'is_active' => (bool) $data['is_active']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status','Email template saved. Existing queued messages retain their snapshot.');
    }

    public function preview(Request $request, CommunicationTemplateRenderer $renderer): View
    {
        $data = $request->validate([
            'event_key' => ['required', Rule::in(array_keys(config('communications.events', [])))],
            'subject' => ['required','string','max:255'],
            'body_markdown' => ['required','string','max:30000'],
        ]);
        $invalid = $renderer->validateVariables($data['subject']."\n".$data['body_markdown'],$data['event_key']);
        if ($invalid) {
            throw ValidationException::withMessages(['body_markdown'=>'Unknown variables: '.implode(',',$invalid)]);
        }
        $template = new CommunicationTemplate($data);
        $subject = $renderer->subject($template, [], true);
        $html = $renderer->html($template, [], true);
        return view('platform.communications.preview',compact('subject','html'));
    }

    public function rules(): View
    {
        $rules = CommunicationRule::orderBy('event_key')->get();
        return view('platform.communications.rules', compact('rules'));
    }

    public function updateRule(Request $request, CommunicationRule $rule): RedirectResponse
    {
        abort_unless(isset(config('communications.events')[$rule->event_key]),404);
        $data = $request->validate([
            'is_enabled'=>['required','boolean'],
            'send_email'=>['required','boolean'],
            'send_in_app'=>['required','boolean'],
            'to_owner'=>['required','boolean'],
            'to_actor'=>['required','boolean'],
            'to_platform'=>['required','boolean'],
            'extra_emails'=>['nullable','string','max:2000'],
        ]);
        $addresses = array_values(array_unique(array_filter(array_map(
            fn ($email)=>mb_strtolower(trim($email)),
            preg_split('/[\s,;]+/', $data['extra_emails'] ?? '')
        ))));
        if (count($addresses) > 10) {
            throw ValidationException::withMessages(['extra_emails'=>'Maximum 10 additional recipients.']);
        }
        foreach ($addresses as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
                throw ValidationException::withMessages(['extra_emails'=>'Enter valid email addresses, separated by commas.']);
            }
        }

        DB::transaction(function () use ($rule,$data,$addresses,$request): void {
            $rule->update([...collect($data)->except('extra_emails')->toArray(),'extra_emails'=>$addresses]);
            AuditLog::create([
                'actor_id'=>$request->user()->id,
                'action'=>'communication.recipients.updated',
                'subject_type'=>CommunicationRule::class,'subject_id'=>$rule->id,
                'metadata'=>['event'=>$rule->event_key,'email_enabled'=>(bool)$data['send_email'],
                'in_app_enabled'=>(bool)$data['send_in_app'],'extra_emails_count'=>count($addresses)],
                'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Recipient rules updated.');
    }

    public function settings(): View
    {
        $settings = [
            'email_enabled' => PlatformSetting::valueFor('communications.email_enabled','0') === '1',
            'test_mode' => PlatformSetting::valueFor('communications.test_mode','1') === '1',
            'test_recipients' => PlatformSetting::valueFor('communications.test_recipients',''),
            'mail_transport' => config('mail.default','log'),
            'smtp_host_configured' => (bool) config('mail.mailers.smtp.host'),
            'smtp_username_configured' => (bool) config('mail.mailers.smtp.username'),
            'sender_address' => config('mail.from.address',''),
            'sender_name' => config('mail.from.name',''),
        ];
        return view('platform.communications.settings',compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_enabled'=>['required','boolean'],
            'test_mode'=>['required','boolean'],
            'test_recipients'=>['nullable','string','max:2000'],
        ]);
        $emails = array_values(array_unique(array_filter(array_map(
            'trim', preg_split('/[\s,;]+/', $data['test_recipients'] ?? '')
        ))));
        if (count($emails)>5) throw ValidationException::withMessages([
            'test_recipients'=>'Maximum five test addresses.',
        ]);
        foreach ($emails as $email) {
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>191) {
                throw ValidationException::withMessages(['test_recipients'=>'Invalid test recipient email.']);
            }
        }
        if ($data['email_enabled'] && $data['test_mode'] && !$emails) {
            throw ValidationException::withMessages([
                'test_recipients'=>'Add at least one test recipient before enabling test mode email delivery.',
            ]);
        }
        if ($data['email_enabled'] && !$data['test_mode'] && (
            in_array(config('mail.default'),['log','array','failover'],true)
            || (config('mail.default') === 'smtp'
                && (!config('mail.mailers.smtp.username') || !config('mail.mailers.smtp.host')))
        )) {
            throw ValidationException::withMessages([
                'email_enabled'=>'Use an explicitly configured SMTP/transactional transport before enabling real-recipient delivery.',
            ]);
        }

        DB::transaction(function () use ($request,$data,$emails): void {
            foreach ([
                'communications.email_enabled'=>(string)(int)$data['email_enabled'],
                'communications.test_mode'=>(string)(int)$data['test_mode'],
                'communications.test_recipients'=>implode(',',$emails),
            ] as $key=>$value) {
                PlatformSetting::updateOrCreate(['key'=>$key],['value'=>$value]);
            }
            AuditLog::create([
                'actor_id'=>$request->user()->id, 'action'=>'communication.settings.updated',
                'metadata'=>['enabled'=>(bool)$data['email_enabled'],
                    'test_mode'=>(bool)$data['test_mode'],'test_recipient_count'=>count($emails)],
                'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Email controls saved. SMTP credentials remain in your private .env.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient'=>['required','email','max:191']]);
        $allowed = array_filter(explode(',',PlatformSetting::valueFor('communications.test_recipients','')));
        if (! in_array($data['recipient'],$allowed,true)) {
            throw ValidationException::withMessages(['recipient'=>'Recipient must be on the test-recipient allowlist.']);
        }
        try {
            Mail::raw('This is a POS Cloud SMTP diagnostic message. No customer notification was sent.',
                fn ($mail) => $mail->to($data['recipient'])
                    ->subject('[TEST] POS Cloud mail transport'));
            AuditLog::create(['actor_id'=>$request->user()->id,'action'=>'communication.smtp.tested',
                'metadata'=>['transport'=>config('mail.default'),'recipient'=>$data['recipient']],
                'ip_address'=>$request->ip()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['recipient'=>'Test mail transport failed. Check Laravel logs and your .env configuration.']);
        }
        return back()->with('status','The test message was accepted by the configured mail transport (or written to the log mailer). Inbox delivery is not guaranteed.');
    }

    public function deliveries(Request $request): View
    {
        $data = $request->validate([
            'status'=>['nullable',Rule::in(['queued','retrying','sending','accepted','logged','failed','skipped'])],
            'event_key'=>['nullable',Rule::in(array_keys(config('communications.events',[])))],
            'search'=>['nullable','string','max:120'],
        ]);
        $query=CommunicationDelivery::query()->latest('id')
            ->when($data['status']??null,fn($q,$s)=>$q->where('status',$s))
            ->when($data['event_key']??null,fn($q,$e)=>$q->where('event_key',$e))
            ->when($data['search']??null,fn($q,$s)=>$q->where('recipient_email','like','%'.$s.'%'));
        $deliveries=$query->paginate(20)->withQueryString();
        $stats=[
            'queued'=>CommunicationDelivery::whereIn('status',['queued','retrying','sending'])->count(),
            'accepted'=>CommunicationDelivery::whereIn('status',['accepted','logged'])->count(),
            'failed'=>CommunicationDelivery::where('status','failed')->count(),
        ];
        return view('platform.communications.deliveries',compact('deliveries','stats'));
    }

    public function retry(Request $request, CommunicationDelivery $delivery): RedirectResponse
    {
        if (!in_array($delivery->status,['failed','skipped'],true)) {
            throw ValidationException::withMessages(['delivery'=>'Only failed/skipped messages may be retried.']);
        }
        if (PlatformSetting::valueFor('communications.email_enabled','0')!=='1') {
            throw ValidationException::withMessages(['delivery'=>'Enable email sending first.']);
        }

        if (PlatformSetting::valueFor('communications.test_mode','1')==='1') {
            $allowlist = explode(',', PlatformSetting::valueFor('communications.test_recipients',''));
            if (! in_array($delivery->recipient_email,$allowlist,true)) {
                throw ValidationException::withMessages(['delivery'=>'This recipient is not on the current test allowlist.']);
            }
        }
        DB::transaction(function () use ($request,$delivery): void {
            $delivery->update(['status'=>'queued','attempts'=>0,'last_error'=>null,'queued_at'=>now()]);
            SendCommunicationDelivery::dispatch($delivery->id)->afterCommit();
            AuditLog::create([
                'actor_id'=>$request->user()->id,'action'=>'communication.delivery.retried',
                'subject_type'=>CommunicationDelivery::class,'subject_id'=>$delivery->id,
                'metadata'=>['event'=>$delivery->event_key],
                'ip_address'=>$request->ip(),
            ]);
        });

        return back()->with('status','Delivery placed back on the communications queue.');
    }
}
