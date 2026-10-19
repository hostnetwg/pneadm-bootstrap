<?php

namespace App\Http\Controllers;

use App\Models\DebtPaymentReminderCampaign;
use App\Services\Debt\DebtPaymentReminderCampaignService;
use App\Services\Debt\DebtPaymentReminderEligibilityService;
use App\Services\Debt\DebtPaymentReminderGateService;
use App\Services\DebtReminderTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DebtPaymentReminderCampaignController extends Controller
{
    public function index(
        Request $request,
        DebtPaymentReminderGateService $gate,
        DebtPaymentReminderEligibilityService $eligibility,
        DebtReminderTemplateService $templates,
    ) {
        $gateState = $gate->evaluate();
        if (! $gateState['allowed']) {
            return redirect()
                ->route('accounting.bank-imports.index')
                ->with('warning', $gateState['block_reason'] ?? 'Kampania niedostępna.');
        }

        $audience = $request->string('audience')->toString();
        if (! in_array($audience, [
            DebtPaymentReminderCampaign::AUDIENCE_STANDARD,
            DebtPaymentReminderCampaign::AUDIENCE_VIP,
        ], true)) {
            $audience = DebtPaymentReminderCampaign::AUDIENCE_STANDARD;
        }

        $defaults = $this->defaultOptions($audience);
        $sendEmail = $request->has('send_email')
            ? $request->boolean('send_email')
            : $defaults['send_email'];
        $sendSms = $request->has('send_sms')
            ? $request->boolean('send_sms')
            : $defaults['send_sms'];

        $lists = $eligibility->buildLists($audience, $sendEmail, $sendSms);

        return view('accounting.bank-imports.payment-reminders', [
            'gate' => $gateState,
            'audience' => $audience,
            'candidates' => $lists['candidates'],
            'excluded' => $lists['excluded'],
            'counts' => $lists['counts'],
            'defaults' => $defaults,
            'options' => [
                'send_email' => $sendEmail,
                'send_sms' => $sendSms,
                'email_template' => $request->input('email_template', $defaults['email_template']),
                'sms_template' => $request->input('sms_template', $defaults['sms_template']),
                'check_ifirma' => $request->has('check_ifirma')
                    ? $request->boolean('check_ifirma')
                    : $defaults['check_ifirma'],
                'attach_ifirma_pdf' => $request->has('attach_ifirma_pdf')
                    ? $request->boolean('attach_ifirma_pdf')
                    : $defaults['attach_ifirma_pdf'],
                'strip_sms_diacritics' => $request->has('strip_sms_diacritics')
                    ? $request->boolean('strip_sms_diacritics')
                    : $defaults['strip_sms_diacritics'],
                'sms_companion_email' => $request->has('sms_companion_email')
                    ? $request->boolean('sms_companion_email')
                    : $defaults['sms_companion_email'],
            ],
            'templateLabels' => [
                DebtReminderTemplateService::TEMPLATE_REMINDER => $templates->templateLabels()[DebtReminderTemplateService::TEMPLATE_REMINDER],
                DebtReminderTemplateService::TEMPLATE_VIP => $templates->templateLabels()[DebtReminderTemplateService::TEMPLATE_VIP],
            ],
        ]);
    }

    public function store(
        Request $request,
        DebtPaymentReminderCampaignService $campaigns,
    ) {
        $validated = $request->validate([
            'audience' => ['required', 'in:standard,vip'],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:debt_cases,id'],
            'send_email' => ['nullable', 'boolean'],
            'send_sms' => ['nullable', 'boolean'],
            'email_template' => ['required', 'in:reminder,vip_soft'],
            'sms_template' => ['required', 'in:reminder,vip_soft'],
            'check_ifirma' => ['nullable', 'boolean'],
            'attach_ifirma_pdf' => ['nullable', 'boolean'],
            'strip_sms_diacritics' => ['nullable', 'boolean'],
            'sms_companion_email' => ['nullable', 'boolean'],
        ]);

        try {
            $campaign = $campaigns->start(
                $validated['audience'],
                $validated['selected_ids'],
                [
                    'send_email' => $request->boolean('send_email'),
                    'send_sms' => $request->boolean('send_sms'),
                    'email_template' => $validated['email_template'],
                    'sms_template' => $validated['sms_template'],
                    'check_ifirma' => $request->boolean('check_ifirma'),
                    'attach_ifirma_pdf' => $request->boolean('attach_ifirma_pdf'),
                    'strip_sms_diacritics' => $request->boolean('strip_sms_diacritics'),
                    'sms_companion_email' => $request->boolean('sms_companion_email'),
                ],
                Auth::user(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return redirect()
                ->route('accounting.bank-imports.index')
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('accounting.bank-imports.payment-reminders.show', $campaign)
            ->with('success', 'Zlecono wysyłkę '.$campaign->selected_count.' spraw. Przetwarzanie w kolejce.');
    }

    public function show(
        DebtPaymentReminderCampaign $campaign,
        DebtPaymentReminderCampaignService $campaigns,
        DebtPaymentReminderEligibilityService $eligibility,
    ) {
        $campaign->load(['starter', 'items.debtCase.formOrder']);
        $counts = $campaigns->statusCounts($campaign);

        return view('accounting.bank-imports.payment-reminders-report', [
            'campaign' => $campaign,
            'counts' => $counts,
            'exclusionLabels' => $eligibility->exclusionLabels(),
        ]);
    }

    public function cancel(
        DebtPaymentReminderCampaign $campaign,
        DebtPaymentReminderCampaignService $campaigns,
    ) {
        $campaigns->cancel($campaign);

        return redirect()
            ->route('accounting.bank-imports.payment-reminders.show', $campaign)
            ->with('success', 'Anulowano oczekujące zadania kampanii.');
    }

    /**
     * @return array{
     *     send_email: bool,
     *     send_sms: bool,
     *     email_template: string,
     *     sms_template: string,
     *     check_ifirma: bool,
     *     attach_ifirma_pdf: bool,
     *     strip_sms_diacritics: bool,
     *     sms_companion_email: bool
     * }
     */
    private function defaultOptions(string $audience): array
    {
        $isVip = $audience === DebtPaymentReminderCampaign::AUDIENCE_VIP;

        return [
            'send_email' => true,
            'send_sms' => false,
            'email_template' => $isVip
                ? DebtReminderTemplateService::TEMPLATE_VIP
                : DebtReminderTemplateService::TEMPLATE_REMINDER,
            'sms_template' => $isVip
                ? DebtReminderTemplateService::TEMPLATE_VIP
                : DebtReminderTemplateService::TEMPLATE_REMINDER,
            'check_ifirma' => true,
            'attach_ifirma_pdf' => true,
            'strip_sms_diacritics' => true,
            'sms_companion_email' => true,
        ];
    }
}
