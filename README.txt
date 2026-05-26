=== CSC Member Directory ===
Contributors: abubakar
Tags: members, directory, registration, login
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.2.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Custom plugin for the Celtic Sea Cluster (CSC) Members Portal.

== Description ==

Provides the complete Members Portal for the Celtic Sea Cluster website, including:

* Branded login page with optional two-factor authentication (email OTP)
* Multi-step member registration / join flow
* Organisation management (custom post type)
* Admin panel for approving / rejecting member applications
* Member Dashboard with portal access guard
* Member Directory — Companies & Representatives with filters and pagination
* Forum with threaded replies and reply notifications
* Newsletters & Resources section with batched email delivery
* Account Settings — profile, company info, login & security, notifications

== Required Pages ==

The plugin needs the following WordPress pages to exist. Each page must use
the exact slug listed and contain only the corresponding shortcode as its content.
The page template should be set to "Portal Template" (page-csc-portal.php).

The plugin attempts to sync shortcode content and the portal template automatically
on each version update (via the sync_page_content routine on init). However, the
pages themselves must be created manually in WP Admin first.

  Slug                      Shortcode                 Purpose
  ------------------------  ------------------------  --------------------------------
  members-login             [csc_login]               Login page
  join-csc                  [csc_join]                Member registration / join flow
  member-dashboard          [csc_dashboard]           Portal dashboard (access guard)
  member-directory          [csc_directory]           Companies & representatives list
  update-account            [csc_update_account]      Profile & company info editor
  member-settings           [csc_settings]            Account settings & preferences
  terms-of-use              [csc_terms]               Terms of Use
  member-forum              [csc_forum]               Member forum
  member-newsletters        [csc_newsletters]         Newsletters & Resources
  members-forgot-password   [csc_forgot_password]     Custom forgot password page
  members-set-password      [csc_set_password]        Set / reset password page

== Shortcodes ==

[csc_login]
  Renders the CSC-branded login form.
  - Supports optional two-factor authentication (email OTP) per user setting.
  - Pending users see "Application Under Review" message after login.
  - Approved users are redirected to /member-dashboard/ on login.

[csc_join]
  Renders the multi-step Join / Registration form.
  Step 1: Select existing organisation (typeahead) + enter personal data.
  Step 2 (new org flow): Register organisation, then enter personal data.
  On submit: creates a WordPress user with status=pending and notifies admin.

[csc_dashboard]
  Renders the portal dashboard landing page.
  Redirects unauthenticated or non-approved users to the login page.

[csc_directory]
  Renders the Member Directory with two tabs:
  - Companies: filterable, paginated list of member organisations.
  - Representatives: filterable, paginated list of individual members.
  Pagination uses custom query vars (dir_page, reps_page) to avoid conflicts
  with WordPress reserved vars.

[csc_update_account]
  Renders the Update Account / Profile editor with tabs:
  - Personal Information
  - Company Information (if the user is linked to an organisation)

[csc_settings]
  Renders the Account Settings page with tabs:
  - Notifications: newsletter emails, forum reply notifications.
  - Login & Security: two-factor auth toggle, login alert emails,
    sign out of all other devices.

[csc_terms]
  Renders the Terms of Use page content (managed via plugin settings or
  static output).

[csc_forum]
  Renders the member forum. Members can create topics and post replies.
  Topic authors receive an email notification when someone replies (if
  forum notifications are enabled in their settings).

[csc_newsletters]
  Renders the Newsletters & Resources section.
  - Newsletters: lists published csc_newsletter posts, newest first.
  - Resources: lists published csc_resource posts ordered by the "Order"
    field set on each resource. The section heading is configurable in
    WP Admin > Newsletters (plugin settings page).
  Newsletter emails are sent to subscribed members via a batched cron job
  when a newsletter is first published (20 emails per batch, 120 s apart).

[csc_forgot_password]
  Renders the custom Forgot Password page.
  Sends a password-reset link to the user's email address.
  Returns a generic success message regardless of whether the email exists
  (prevents email enumeration).

[csc_set_password]
  Renders the Set / Reset Password page.
  Used for both the Forgot Password flow and for new account approvals
  (the approval email links here instead of the default wp-login.php page).
  Validates the reset key on load; shows an error card if the link has
  expired or is invalid. On success, logs the user in and redirects to
  the dashboard.

== Admin Panel ==

Navigate to CSC Members in the WordPress admin sidebar.
- Pending tab: Review new applications. Click Approve or Reject.
  - Approve sends the member an email with a link to /members-set-password/
    where they can set their password and gain access to the portal.
  - Reject sets their status to rejected (can be reversed later).
- Approved tab: View active members. Revoke Access if needed.
- Rejected tab: View rejected applications. Can be re-approved.

A "CSC Status" column is also added to the standard Users list.

Navigate to Newsletters in the WordPress admin sidebar to manage:
- Newsletter posts (csc_newsletter CPT)
- Resource posts (csc_resource CPT) — each resource has a label and URL
- The Resources section heading shown on the Newsletters & Resources page

== Custom Post Types ==

csc_organisation — Member organisations
  - _csc_org_location, _csc_org_sector, _csc_org_industry
  - _csc_org_igp_category, _csc_org_country, _csc_org_county, _csc_org_postcode
  - _csc_org_company_type, _csc_org_website, _csc_org_description
  - _csc_org_logo_id (attachment ID)

csc_newsletter — Newsletter posts
  - Standard post content used as the newsletter body / excerpt.
  - On first publish, a batched cron job sends an email to all members
    who have newsletter notifications enabled.

csc_resource — Resource links (shown in Newsletters & Resources sidebar)
  - _csc_resource_url : the destination URL for the resource link
  - menu_order        : controls display order (set via the "Order" field
    on the edit screen, lower numbers appear first)

== User Meta Keys ==

  Key                    Values / Notes
  ---------------------  -------------------------------------------------------
  _csc_status            pending | approved | rejected
  _csc_job_title         entered at registration
  _csc_organisation_id   post ID of linked csc_organisation
  _csc_2fa_enabled       1 = enabled, 0 = disabled (default: disabled)
  _csc_login_alerts      '' or '1' = on, '0' = off (default: on)
  _csc_notif_newsletter  '' or '1' = on, '0' = off (default: on)
  _csc_notif_forum       '' or '1' = on, '0' = off (default: on)
  _csc_user_photo_id     attachment ID of the user's profile photo
  _csc_skills            comma-separated list of skills/keywords

== Colors ==

Blue  : #1F2D57
Green : #44BD70

== Changelog ==

= 1.2.0 =
* Custom Forgot Password page ([csc_forgot_password]) replacing wp-login.php flow
* Custom Set Password page ([csc_set_password]) for both forgot password and
  new account approval flows
* Two-factor authentication (email OTP) in Login & Security settings
* Login alert emails when account is accessed from a new session
* Sign out of all other devices action in settings
* Newsletter batched email delivery via WP-Cron (20 per batch, 120 s intervals)
* Forum reply notifications (email to topic author on new reply)
* Newsletters sidebar renamed to Newsletters & Resources
* Multiple resources via csc_resource CPT (label + URL, orderable)
* Configurable Resources section heading in plugin settings
* Directory pagination fixed (custom query vars: dir_page, reps_page)

= 1.1.0 =
* Member Directory with Companies and Representatives tabs
* Filter panel with country, county, industry, IGP category, company type, postcode
* Forum with topics, replies, and threaded display
* Newsletters section
* Account Settings with Notifications and Login & Security tabs
* Update Account / Profile page with photo and company logo upload
* Password strength meter and show/hide toggle

= 1.0.0 =
* Initial release — Login & Registration
