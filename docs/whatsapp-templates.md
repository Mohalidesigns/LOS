# WhatsApp templates for approval

WhatsApp only allows business-initiated messages that use **pre-approved
templates**. Submit the templates below to HollaTags (or your BSP) for
approval under the bank's WhatsApp business number. The approved number then
goes in `WHATSAPP_FROM`.

- **Category:** Utility (transactional), not Marketing.
- **Language:** English (`en`).
- **Variables:**
  - Fundly fills `{{ name }}` placeholders by name.
  - Most BSPs want positional variables `{{1}}`, `{{2}}`…; the mapping is
    given for each template.
  - The approved wording must match the text Fundly sends exactly, apart
    from the variables.
- **Audience:** sent only to customers who consented to `whatsapp_messaging`
  (Party → Consents).
- **Customising:** a bank can change any text through a `notification.template`
  version (channel `whatsapp`, fields `template` and `body`). The new text
  must be approved first.

| Template name | Event | Body (Fundly placeholders) | Positional mapping |
|---|---|---|---|
| `fundly_application_received` | Application submitted | Hello {{ applicant_name }}, {{ bank_name }} has received your loan application {{ reference }} for {{ amount }}. We will contact you if we need anything else. | 1 = applicant_name, 2 = bank_name, 3 = reference, 4 = amount |
| `fundly_offer_issued` | Offer issued | Hello {{ applicant_name }}, your loan offer {{ reference }} for {{ amount }} over {{ tenor_months }} months is ready and valid until {{ valid_until }}. Your relationship manager will share the offer letter. | 1 = applicant_name, 2 = reference, 3 = amount, 4 = tenor_months, 5 = valid_until |
| `fundly_offer_expiring` | Offer expires within the reminder window | Hello {{ applicant_name }}, your loan offer {{ reference }} expires on {{ valid_until }}. Contact your relationship manager if you would like to accept it. | 1 = applicant_name, 2 = reference, 3 = valid_until |
| `fundly_facility_booked` | Loan disbursed and booked | Hello {{ applicant_name }}, {{ net_amount }} has been disbursed for your loan {{ reference }} on {{ value_date }}. Your advice and repayment schedule have been sent to your email. | 1 = applicant_name, 2 = net_amount, 3 = reference, 4 = value_date |

**Sample values for the approval form:**
- Amaka Nwosu
- Fundly Demo Bank
- DEMO-2026-000006
- NGN 3,000,000.00
- 12
- 2026-11-13
- NGN 2,955,000.00
