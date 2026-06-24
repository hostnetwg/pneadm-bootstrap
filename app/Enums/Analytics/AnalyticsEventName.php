<?php

namespace App\Enums\Analytics;

enum AnalyticsEventName: string
{
    case CampaignShortLinkVisit = 'campaign_short_link_visit';
    case CampaignRedirectResolved = 'campaign_redirect_resolved';
    case UtmCaptured = 'utm_captured';
    case CourseDescriptionViewed = 'course_description_viewed';
    case OrderFormViewed = 'order_form_viewed';
    case OrderFormSubmitAttempted = 'order_form_submit_attempted';
    case OrderFormValidationFailed = 'order_form_validation_failed';
    case FormOrderCreated = 'form_order_created';
    case OnlinePaymentSelected = 'online_payment_selected';
    case DeferredInvoiceSelected = 'deferred_invoice_selected';
    case PaymentOrderCreated = 'payment_order_created';
    case PaymentStatusChanged = 'payment_status_changed';
    case InvoiceCreated = 'invoice_created';

    public function category(): AnalyticsCategory
    {
        return match ($this) {
            self::CampaignShortLinkVisit,
            self::CampaignRedirectResolved,
            self::UtmCaptured => AnalyticsCategory::Campaign,
            self::CourseDescriptionViewed => AnalyticsCategory::Landing,
            self::OrderFormViewed,
            self::OrderFormSubmitAttempted => AnalyticsCategory::OrderForm,
            self::OrderFormValidationFailed => AnalyticsCategory::Validation,
            self::FormOrderCreated => AnalyticsCategory::Conversion,
            self::OnlinePaymentSelected,
            self::PaymentOrderCreated,
            self::PaymentStatusChanged => AnalyticsCategory::Payment,
            self::DeferredInvoiceSelected,
            self::InvoiceCreated => AnalyticsCategory::Invoice,
        };
    }
}
