# Simple Booking: Free vs Pro Feature Comparison

Comprehensive feature breakdown for marketing and documentation.

---

## Quick Comparison

| Feature | Free | Pro |
|---------|------|-----|
| **Booking Forms** | ✅ Unlimited | ✅ Unlimited |
| **Services** | ✅ Unlimited | ✅ Unlimited |
| **Email Notifications** | ✅ Basic | ✅ Enhanced |
| **Payment Processing** | ❌ | ✅ Stripe |
| **Calendar Integration** | ❌ | ✅ Google/Outlook |
| **Staff Management** | ❌ | ✅ Unlimited Staff |
| **Refund Management** | ❌ | ✅ Automatic |
| **Reschedule/Cancel Links** | ❌ | ✅ Tokenized |
| **Auto Meeting Links** | ❌ | ✅ Google Meet |
| **Advanced Scheduling** | ❌ | ✅ Per-Service |
| **Webhook Integration** | ❌ | ✅ booking.created |
| **Support** | Community | Priority |
| **Sites** | Unlimited | 1, 5, or Unlimited |
| **Price** | $0 | $79+/year |

---

## Detailed Feature Breakdown

### 1. Core Booking System

#### Booking Forms
| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Shortcode `[simple_booking_form]` | ✅ | ✅ | |
| Service selection dropdown | ✅ | ✅ | |
| Date picker | ✅ | ✅ | |
| Time slot selection | ✅ | ✅ | |
| Customer information fields | ✅ | ✅ | Name, email, phone |
| Custom form styling | ✅ | ✅ | CSS customizable |
| Service-specific forms | ✅ | ✅ | `service_id` attribute |
| Responsive design | ✅ | ✅ | Mobile-friendly |
| **AJAX slot loading** | ✅ | ✅ | No page reload |
| **Timezone detection** | ❌ | ✅ | Browser timezone |
| **Staff selection** | ❌ | ✅ | Customer chooses staff |

#### Service Management
| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Custom post type | ✅ | ✅ | |
| Unlimited services | ✅ | ✅ | |
| Service duration | ✅ | ✅ | Minutes |
| Service active/inactive | ✅ | ✅ | Toggle |
| Static meeting link | ✅ | ✅ | Zoom, Teams, etc |
| Service description | ✅ | ✅ | |
| **Stripe Price ID** | ❌ | ✅ | For paid bookings |
| **Staff assignment** | ❌ | ✅ | Multiple staff per service |
| **Custom schedules** | ❌ | ✅ | Per-service hours |
| **Buffer time** | ❌ | ✅ | Between bookings |
| **Auto Google Meet** | ❌ | ✅ | Generated links |

#### Booking Management
| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Custom post type | ✅ | ✅ | |
| Admin dashboard | ✅ | ✅ | |
| Booking list view | ✅ | ✅ | |
| Filter by service | ✅ | ✅ | |
| Filter by date | ✅ | ✅ | |
| Search bookings | ✅ | ✅ | |
| Booking details | ✅ | ✅ | |
| Manual booking creation | ✅ | ✅ | |
| **Payment status** | ❌ | ✅ | Paid/Free |
| **Refund status** | ❌ | ✅ | Completed/Failed |
| **Google event ID** | ❌ | ✅ | Calendar link |
| **Calendar event ID** | ❌ | ✅ | Provider-specific |
| **Assigned staff** | ❌ | ✅ | Who took booking |

---

### 2. Payment Processing

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Free bookings | ✅ | ✅ | No payment required |
| **Stripe Checkout** | ❌ | ✅ | Secure payment |
| **One-time payments** | ❌ | ✅ | Per booking |
| **Webhook processing** | ❌ | ✅ | Automatic booking creation |
| **Payment confirmation** | ❌ | ✅ | Email after payment |
| **Test mode** | ❌ | ✅ | Stripe test keys |
| **Multiple currencies** | ❌ | ✅ | Via Stripe |
| **Payment metadata** | ❌ | ✅ | Customer info preserved |
| **Success redirect** | ✅ | ✅ | Custom page |
| **Cancel redirect** | ✅ | ✅ | Custom page |

---

### 3. Calendar Integration

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| **Google Calendar OAuth** | ❌ | ✅ | Secure connection |
| **Microsoft Outlook OAuth** | ❌ | ✅ | Graph API integration |
| **ICS Feed fallback** | ❌ | ✅ | No OAuth required |
| **Automatic event creation** | ❌ | ✅ | On booking |
| **Auto Google Meet links** | ❌ | ✅ | Per service setting |
| **Event deletion** | ❌ | ✅ | On cancel/reschedule |
| **Event updates** | ❌ | ✅ | Time changes |
| **Availability checking** | ❌ | ✅ | Prevent double-booking |
| **Multi-calendar support** | ❌ | ✅ | Different calendars per staff |
| **Token refresh** | ❌ | ✅ | Automatic OAuth refresh |
| **Calendar disconnect** | ❌ | ✅ | One-click disconnect |
| **Provider selection** | ❌ | ✅ | ICS/Google/Outlook |

---

### 4. Staff Management

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| **Staff custom post type** | ❌ | ✅ | |
| **Unlimited staff** | ❌ | ✅ | |
| **Staff profiles** | ❌ | ✅ | Name, email, calendar |
| **Active/inactive toggle** | ❌ | ✅ | Availability control |
| **Per-staff calendar** | ❌ | ✅ | Override default |
| **Staff assignment to services** | ❌ | ✅ | Multiple staff per service |
| **Availability routing** | ❌ | ✅ | Find available staff |
| **Staff selection (customer)** | ❌ | ✅ | Choose preferred staff |

---

### 5. Customer Experience

#### Email Notifications
| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Booking confirmation | ✅ | ✅ | |
| Customer name personalization | ✅ | ✅ | |
| Service details | ✅ | ✅ | |
| Date/time | ✅ | ✅ | |
| Meeting link | ✅ | ✅ | |
| Custom subject line | ✅ | ✅ | |
| Custom email body | ✅ | ✅ | |
| **Reschedule link** | ❌ | ✅ | Tokenized |
| **Cancel link** | ❌ | ✅ | Tokenized |
| **Payment receipt** | ❌ | ✅ | Stripe amount |
| **Calendar attachment** | ❌ | ✅ | .ics file |

#### Booking Management (Customer)
| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| **Reschedule booking** | ❌ | ✅ | Via email link |
| **Cancel booking** | ❌ | ✅ | Via email link |
| **Free reschedule (paid bookings)** | ❌ | ✅ | No re-payment |
| **Automatic refunds** | ❌ | ✅ | On cancellation |
| **Token-based security** | ❌ | ✅ | 48-char secure token |
| **Token expiry** | ❌ | ✅ | Never expires (can be changed) |

---

### 6. Scheduling & Availability

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Global working schedule | ✅ | ✅ | Per-day hours |
| Working days selection | ✅ | ✅ | Mon-Sun toggles |
| Time slot intervals | ✅ | ✅ | 15, 30, 60 min |
| **Per-service schedules** | ❌ | ✅ | Override global |
| **Buffer time** | ❌ | ✅ | Minutes between bookings |
| **Calendar availability check** | ❌ | ✅ | Check for conflicts |
| **Multi-staff availability** | ❌ | ✅ | Find any available staff |
| **Graceful fallback** | ❌ | ✅ | Allow booking if check fails |

---

### 7. Refund Management

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| **Automatic refunds** | ❌ | ✅ | On cancellation |
| **Configurable percentage** | ❌ | ✅ | 0-100% |
| **Stripe refund API** | ❌ | ✅ | Direct refund |
| **Refund tracking** | ❌ | ✅ | Status, ID, errors |
| **Refund policy text** | ❌ | ✅ | Admin setting |
| **Partial refunds** | ❌ | ✅ | Via percentage |

---

### 8. Integration & Automation

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| **Webhook notifications** | ❌ | ✅ | booking.created event |
| **External URL endpoint** | ❌ | ✅ | POST JSON payload |
| **Zapier compatible** | ❌ | ✅ | Via webhooks |
| **Make.com compatible** | ❌ | ✅ | Via webhooks |
| **CRM integration** | ❌ | ✅ | Via webhooks |
| **Debug mode** | ✅ | ✅ | Error logging |

---

### 9. Admin Settings

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Settings page | ✅ | ✅ | |
| Email settings | ✅ | ✅ | |
| Schedule settings | ✅ | ✅ | |
| Debug mode | ✅ | ✅ | |
| **Stripe settings** | ❌ | ✅ | Keys, webhook secret |
| **Calendar settings** | ❌ | ✅ | Google/Outlook OAuth |
| **Refund settings** | ❌ | ✅ | Percentage, policy |
| **Webhook settings** | ❌ | ✅ | External URL |
| **License management** | ✅ | ✅ | Activate/deactivate |

---

### 10. Developer Features

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Open source code | ✅ | ✅ | GPL license |
| Custom CSS | ✅ | ✅ | |
| WordPress hooks | ✅ | ✅ | Actions, filters |
| Shortcode attributes | ✅ | ✅ | service_id |
| REST API endpoints | ✅ | ✅ | AJAX handlers |
| **Pro feature detection** | ✅ | ✅ | `is_pro_active()` |
| **License API** | ❌ | ✅ | Activation check |
| **Webhook payload customization** | ❌ | ✅ | Filter hooks |

---

## Support & Updates

| Feature | Free | Pro |
|---------|------|-----|
| **Support Channel** | Community forums | Email support |
| **Response Time** | Best effort | <24 hours |
| **Updates** | WordPress.org | Automatic |
| **Documentation** | Public docs | Premium docs |
| **Priority Feature Requests** | ❌ | ✅ |
| **Beta Access** | ❌ | ✅ |
| **Video Tutorials** | Basic | Advanced |

---

## Pricing Plans

### Free
- **Price:** $0
- **Sites:** Unlimited
- **Features:** Basic booking system
- **Support:** Community forums
- **Updates:** WordPress.org automatic

### Pro Personal
- **Price:** $79/year
- **Sites:** 1
- **Features:** All Pro features
- **Support:** Email (<24hr response)
- **Updates:** Automatic
- **Best for:** Freelancers, coaches, consultants

### Pro Business
- **Price:** $149/year
- **Sites:** 5
- **Features:** All Pro features
- **Support:** Email (<24hr response)
- **Updates:** Automatic
- **Best for:** Small agencies, multi-sites

### Pro Agency
- **Price:** $299/year
- **Sites:** Unlimited
- **Features:** All Pro features
- **Support:** Priority email (<12hr response)
- **Updates:** Automatic
- **Best for:** Large agencies, white-label

---

## Upgrade Path

### Free → Pro
1. Purchase Pro license
2. Enter license key in Settings
3. Pro features unlock immediately
4. All existing bookings preserved
5. No data loss

### Pro Personal → Pro Business
1. Click "Upgrade" in account portal
2. Pay difference ($70 prorated)
3. License upgraded instantly
4. Activate on additional sites

### Pro Business → Pro Agency
1. Click "Upgrade" in account portal
2. Pay difference ($150 prorated)
3. Unlimited site activations enabled

---

## Migration Examples

### Scenario 1: Free User with 50 Bookings
**Before:** Free version, basic bookings, no payments
**After Pro:** 
- All 50 bookings preserved
- Can now add Stripe pricing to services
- Future bookings can be paid
- Old free bookings remain free

### Scenario 2: Pro User Downgrade
**Before:** Pro with Google Calendar, Stripe, 100 bookings
**After Free:**
- All 100 bookings preserved (read-only)
- Cannot create new paid bookings
- Google sync stops (events remain in calendar)
- Can reactivate Pro anytime

---

## Common Questions

**Q: Can I try Pro before buying?**  
A: Yes! 30-day money-back guarantee on all plans.

**Q: What happens if my license expires?**  
A: 30-day grace period, then Pro features disable. Data preserved.

**Q: Can I upgrade/downgrade mid-year?**  
A: Yes, prorated pricing applies.

**Q: Do I need coding skills?**  
A: No, both versions are no-code setup.

**Q: Is my data safe during upgrades?**  
A: Yes, zero data loss guaranteed.

**Q: Can I use Pro features in free version?**  
A: No, licensing required for Pro features.

---

**For marketing materials, use this comparison table on:**
- Pro upgrade page
- WordPress.org readme
- Email campaigns
- Social media posts
- Sales presentations
