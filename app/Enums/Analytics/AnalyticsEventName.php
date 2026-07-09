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
    case OrderFormStarted = 'order_form_started';
    case OrderFormSectionInteracted = 'order_form_section_interacted';
    case OrderFormCtaClicked = 'order_form_cta_clicked';
    case OrderFormSubmitClicked = 'order_form_submit_clicked';
    case FormVisible = 'form_visible';
    case FormFirstInteraction = 'form_first_interaction';
    case FormSectionViewed = 'form_section_viewed';
    case FormSectionStarted = 'form_section_started';
    case FormSectionCompleted = 'form_section_completed';
    case FormFieldChanged = 'form_field_changed';
    case FormSubmitClicked = 'form_submit_clicked';
    case ClientValidationFailed = 'client_validation_failed';
    case ServerSubmitAttempted = 'server_submit_attempted';
    case ServerValidationFailed = 'server_validation_failed';
    case OrderCreateFailed = 'order_create_failed';
    case OrderCreated = 'order_created';
    case FormLastActivity = 'form_last_activity';
    case GusLookupClicked = 'gus_lookup_clicked';
    case GusLookupStarted = 'gus_lookup_started';
    case GusLookupSuccess = 'gus_lookup_success';
    case GusLookupError = 'gus_lookup_error';
    case GusDataApplied = 'gus_data_applied';
    case GusManualFallbackStarted = 'gus_manual_fallback_started';
    case FormFieldEditedAfterGus = 'form_field_edited_after_gus';
    case InternalOfferImpression = 'internal_offer_impression';
    case InternalOfferClicked = 'internal_offer_clicked';

    public function category(): AnalyticsCategory
    {
        return match ($this) {
            self::CampaignShortLinkVisit,
            self::CampaignRedirectResolved,
            self::UtmCaptured => AnalyticsCategory::Campaign,
            self::CourseDescriptionViewed => AnalyticsCategory::Landing,
            self::OrderFormViewed,
            self::OrderFormSubmitAttempted,
            self::OrderFormStarted,
            self::OrderFormSectionInteracted,
            self::OrderFormCtaClicked,
            self::OrderFormSubmitClicked,
            self::FormVisible,
            self::FormFirstInteraction,
            self::FormSectionViewed,
            self::FormSectionStarted,
            self::FormSectionCompleted,
            self::FormFieldChanged,
            self::FormSubmitClicked,
            self::ServerSubmitAttempted,
            self::FormLastActivity,
            self::GusLookupClicked,
            self::GusLookupStarted,
            self::GusLookupSuccess,
            self::GusDataApplied,
            self::GusManualFallbackStarted,
            self::FormFieldEditedAfterGus,
            self::InternalOfferImpression,
            self::InternalOfferClicked => AnalyticsCategory::OrderForm,
            self::OrderFormValidationFailed,
            self::ClientValidationFailed,
            self::ServerValidationFailed,
            self::OrderCreateFailed,
            self::GusLookupError => AnalyticsCategory::Validation,
            self::FormOrderCreated,
            self::OrderCreated => AnalyticsCategory::Conversion,
            self::OnlinePaymentSelected,
            self::PaymentOrderCreated,
            self::PaymentStatusChanged => AnalyticsCategory::Payment,
            self::DeferredInvoiceSelected,
            self::InvoiceCreated => AnalyticsCategory::Invoice,
        };
    }
}
